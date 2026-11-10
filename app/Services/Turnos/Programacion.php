<?php

namespace App\Services\Turnos;

use App\Models\Ausencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Scopes\DependenciaScope;
use App\Models\TipoTurno;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que se espera de cada quien: qué turno es una entrada (por la hora) y a
 * quién se espera cada día (su turno habitual, salvo descanso o ausencia).
 */
class Programacion
{
    public function esFestivo(CarbonInterface $dia): bool
    {
        return in_array($dia->toDateString(), config('repositorio.festivos', []), true);
    }

    /**
     * ¿Ese turno se trabaja ese día? En festivo solo los marcados «también
     * festivos» o los de todos los días (como el nocturno).
     */
    public function aplica(TipoTurno $tipo, CarbonInterface $dia): bool
    {
        if (! $tipo->es_turno || ! $tipo->activo) {
            return false;
        }

        $dias = array_map('intval', $tipo->dias ?? []);

        if ($this->esFestivo($dia)) {
            return $tipo->festivos || $dias === [];
        }

        return $dias === [] || in_array($dia->dayOfWeekIso, $dias, true);
    }

    /** Cuándo empieza ese turno ese día. */
    public function inicio(TipoTurno $tipo, CarbonInterface $dia): ?Carbon
    {
        return $tipo->hora_inicio ? Carbon::parse($dia->toDateString().' '.$tipo->hora_inicio) : null;
    }

    /**
     * El turno de una entrada (DOC-21). Candidatos: los que aplican ese día
     * con inicio dentro de la ventana del componente. Con la salida, gana el
     * que mejor encaja con las dos horas: |entrada − inicio| + |salida − fin|.
     * Sin ella (al marcar, o en un cierre automático), el de inicio más
     * cercano. El habitual solo desempata; después, el orden de la malla.
     * Null si ninguno encaja.
     *
     * @return array{tipo: TipoTurno, inicio: Carbon, minutos_tarde: int}|null
     */
    public function reconocer(Componente $componente, CarbonInterface $entrada, ?TipoTurno $habitual = null, ?CarbonInterface $salida = null): ?array
    {
        $antes = (int) $componente->regla('reconocer_antes_min');
        $despues = (int) $componente->regla('reconocer_despues_min');

        $elegido = $this->tiposDe($componente)
            ->filter(fn (TipoTurno $t) => $t->hora_inicio && $this->aplica($t, $entrada))
            ->map(function (TipoTurno $t) use ($entrada, $salida, $habitual) {
                $inicio = $this->inicio($t, $entrada);
                // Pasada la medianoche, un turno de noche empezó «ayer».
                if ($inicio->gt($entrada) && $inicio->diffInMinutes($entrada) > 12 * 60) {
                    $inicio->subDay();
                }

                $diferencia = (int) $inicio->diffInMinutes($entrada, false);
                $distancia = abs($diferencia);

                // Un tipo sin hora de fin se mide solo por la entrada.
                if ($salida && $t->hora_fin) {
                    $fin = Carbon::parse($inicio->toDateString().' '.$t->hora_fin);
                    // N7 21:00–4:30: termina al día siguiente.
                    if ($fin->lte($inicio)) {
                        $fin->addDay();
                    }
                    $distancia += abs((int) $fin->diffInMinutes($salida, false));
                }

                return [
                    'tipo' => $t, 'inicio' => $inicio, 'diferencia' => $diferencia,
                    'orden' => [$distancia, $habitual && $t->is($habitual) ? 0 : 1, (int) $t->orden, $t->id],
                ];
            })
            ->filter(fn ($c) => $c['diferencia'] >= -$antes && $c['diferencia'] <= $despues)
            ->sort(fn ($a, $b) => $a['orden'] <=> $b['orden'])
            ->first();

        return $elegido ? [
            'tipo' => $elegido['tipo'],
            'inicio' => $elegido['inicio'],
            'minutos_tarde' => max(0, $elegido['diferencia']),
        ] : null;
    }

    /**
     * A quién se espera ese día y a qué hora: activos con turno habitual que
     * aplica ese día, que no tengan ese día de descanso ni una ausencia.
     *
     * @return Collection<int, array{colaborador: Colaborador, tipo: TipoTurno, inicio: Carbon}>
     */
    public function esperados(Componente $componente, CarbonInterface $dia): Collection
    {
        $ausentes = Ausencia::withoutGlobalScope(DependenciaScope::class)
            ->en($dia)
            ->pluck('colaborador_id')
            ->flip();

        return Colaborador::withoutGlobalScope(DependenciaScope::class)
            ->where('componente_id', $componente->id)
            ->where('activo', true)
            ->whereNotNull('tipo_turno_id')
            ->with('tipoTurno')
            ->get()
            ->reject(fn (Colaborador $c) => isset($ausentes[$c->id])
                || in_array($dia->dayOfWeekIso, array_map('intval', $c->dias_descanso ?? []), true)
                || ! $c->tipoTurno
                || ! $c->tipoTurno->hora_inicio
                || ! $this->aplica($c->tipoTurno, $dia))
            ->map(fn (Colaborador $c) => [
                'colaborador' => $c,
                'tipo' => $c->tipoTurno,
                'inicio' => $this->inicio($c->tipoTurno, $dia),
            ])
            ->values();
    }

    /** @var array<int, Collection<int, TipoTurno>> por componente, para no consultarlos en cada marca */
    protected array $tipos = [];

    /** @return Collection<int, TipoTurno> */
    protected function tiposDe(Componente $componente): Collection
    {
        return $this->tipos[$componente->id] ??= TipoTurno::where('componente_id', $componente->id)->where('activo', true)->get();
    }
}
