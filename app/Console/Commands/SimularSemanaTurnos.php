<?php

namespace App\Console\Commands;

use App\Enums\OrigenColaborador;
use App\Enums\OrigenMarcacion;
use App\Enums\TipoMarcacion;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\TipoTurno;
use App\Services\ContextoDependencia;
use App\Services\Turnos\Jornadas;
use App\Services\Turnos\Marcador;
use App\Services\Turnos\Programacion;
use App\Services\Turnos\RevisorEntradas;
use App\Services\Turnos\TurnoCerrado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prueba de estrés de una semana de turnos (DOC-21), SOLO en desarrollo.
 *
 * En «DEMO · Calle», personas SIM-### con turnos distintos viven una semana:
 * llegan a tiempo o tarde, el viernes unas hacen T4/T4. en vez de N1/N1.,
 * el domingo C13, se quedan de más, olvidan la salida, tocan dos veces, y
 * los conductores (15 h) hacen jornadas largas. El cron corre como en el
 * servidor (revisión cada 15 min, cierre cada hora). Al final compara lo que
 * se simuló con lo que el sistema reconoció.
 */
class SimularSemanaTurnos extends SimularDiaTurnos
{
    protected $signature = 'turnos:simular-semana
        {desde? : Primer día (por defecto, el lunes de la semana pasada)}
        {--dias=7 : Cuántos días}
        {--personas=120 : Cuántas personas simuladas}
        {--semilla=7 : Para repetir exactamente la misma simulación}
        {--sin-cron : No corre el cierre automático cada hora (como un servidor sin cron)}';

    protected $description = 'Simula una semana de turnos variados en DEMO · Calle y verifica el reconocimiento y los cierres (solo desarrollo)';

    protected const CONDUCTOR = 'Conductor camioneta';

    public function handle(Marcador $marcador, Programacion $programacion, RevisorEntradas $revisor, ContextoDependencia $contexto): int
    {
        if (app()->isProduction()) {
            $this->error('Esto crea personas y marcas inventadas: no se corre en producción.');

            return self::FAILURE;
        }

        mt_srand((int) $this->option('semilla'));
        $desde = Carbon::parse($this->argument('desde') ?? now()->subWeek()->startOfWeek()->toDateString())->startOfDay();
        $dias = max(1, (int) $this->option('dias'));
        $dependencia = Dependencia::where('slug', 'inclusion-social')->firstOrFail();
        $contexto->establecer($dependencia);

        $componente = $this->prepararComponente($dependencia);
        // Las reglas de DOC-21 tal como quedan en producción.
        $componente->update(['config' => [
            'horas_max_turno' => 8, 'tolerancia_min' => 20, 'tolerancia_extra_min' => 60,
            'descanso_min' => 30, 'descanso_parcial_min' => 30, 'jornada_horas' => 7,
            'horas_max_por_cargo' => [self::CONDUCTOR => 15],
            'turnos_por_dia' => 1, 'salida_minima_min' => 30,
        ] + ($componente->config ?? [])]);
        $enlace = EnlaceTurno::withoutGlobalScopes()->firstOrCreate(
            ['componente_id' => $componente->id, 'nombre' => 'SIMULACIÓN (datos inventados)'],
            ['token_hash' => EnlaceTurno::hashDe($t = EnlaceTurno::generarToken()), 'token_cifrado' => $t,
                'dependencia_id' => $dependencia->id, 'tipo' => 'turno'],
        );

        $tipos = TipoTurno::where('componente_id', $componente->id)->get()->keyBy('codigo');
        $personas = $this->personasDeLaSemana($componente, $tipos);
        $plan = $this->planear($personas, $tipos, $programacion, $desde, $dias);

        // La línea de tiempo: las marcas y el cron.
        $eventos = collect();
        foreach ($plan as $i => $p) {
            $eventos->push(['cuando' => $p['entra'], 'plan' => $i, 'que' => 'entrada']);
            if ($p['doble']) {
                $eventos->push(['cuando' => $p['entra']->copy()->addMinutes(2), 'plan' => $i, 'que' => 'doble']);
            }
            if ($p['sale']) {
                $eventos->push(['cuando' => $p['sale'], 'plan' => $i, 'que' => 'salida']);
            }
        }
        $fin = $desde->copy()->addDays($dias)->addHours(16);
        for ($t = $desde->copy(); $t->lt($fin); $t->addMinutes(15)) {
            $eventos->push(['cuando' => $t->copy(), 'que' => 'revisar']);
            if ($t->minute === 0 && ! $this->option('sin-cron')) {
                $eventos->push(['cuando' => $t->copy()->addSecond(), 'que' => 'cerrar']);
            }
        }

        $this->info("Simulando {$dias} días desde el {$desde->format('d/m/Y')}: {$personas->count()} personas, {$plan->count()} turnos…");
        $inicio = microtime(true);
        $r = ['entrada_como_salida' => 0, 'rechazadas' => 0, 'marcas' => 0];
        $barra = $this->output->createProgressBar($eventos->count());

        foreach ($eventos->sortBy(fn ($e) => $e['cuando']->getTimestamp())->values() as $e) {
            Carbon::setTestNow($e['cuando']);
            $barra->advance();

            if ($e['que'] === 'revisar') {
                $revisor->revisar();

                continue;
            }
            if ($e['que'] === 'cerrar') {
                $marcador->cerrarAbiertas();

                continue;
            }

            $p = $plan[$e['plan']];
            try {
                $marca = $marcador->marcar($p['persona'], $enlace, ['lat' => 6.2476, 'lng' => -75.5658, 'precision_m' => 10, 'municipio' => 'Medellín, Antioquia', 'epp' => true]);
                $r['marcas']++;
            } catch (TurnoCerrado) {
                $r['rechazadas']++;

                continue;
            }

            if ($e['que'] === 'entrada') {
                if ($marca->tipo !== TipoMarcacion::Entrada) {
                    $r['entrada_como_salida']++;
                }
                $plan[$e['plan']] = $p + ['entrada_id' => $marca->id];
            }
        }
        Carbon::setTestNow();
        $barra->finish();
        $this->newLine(2);

        $this->informeSemana($componente, $plan, $r, $desde, $dias, microtime(true) - $inicio);

        return self::SUCCESS;
    }

    /** SIM-###: el 10 % conductores; turnos habituales repartidos; uno de descanso por semana. */
    protected function personasDeLaSemana(Componente $componente, Collection $tipos): Collection
    {
        $n = max(1, (int) $this->option('personas'));
        $nodos = $componente->nodos()->pluck('id');

        $previas = Colaborador::withoutGlobalScopes()->where('componente_id', $componente->id)->where('documento', 'like', 'SIM%')->pluck('id');
        DB::table('novedades_turno')->whereIn('colaborador_id', $previas)->delete();
        DB::table('marcaciones')->whereIn('colaborador_id', $previas)->delete();
        DB::table('ausencias')->whereIn('colaborador_id', $previas)->delete();
        Colaborador::withoutGlobalScopes()->whereIn('id', $previas)->update(['activo' => false]);

        return collect(range(1, $n))->map(function (int $i) use ($componente, $tipos, $nodos) {
            $suerte = mt_rand(1, 100);
            $codigo = $suerte <= 35 ? 'N1' : ($suerte <= 70 ? 'N1.' : ($suerte <= 85 ? 'T3' : 'N7'));

            $persona = Colaborador::withoutGlobalScopes()->updateOrCreate(
                ['dependencia_id' => $componente->dependencia_id, 'documento' => sprintf('SIM%03d', $i)],
                [
                    'nombre' => sprintf('Persona simulada %03d', $i),
                    'cargo' => $i % 10 === 0 ? self::CONDUCTOR : 'Operador terapéutico',
                    'componente_id' => $componente->id, 'nodo_id' => $nodos[$i % $nodos->count()],
                    // DOC-21: el habitual casi no se usa; solo la mitad lo tiene.
                    'tipo_turno_id' => $i % 2 ? $tipos[$codigo]->id : null,
                    'dias_descanso' => [($i % 7) + 1],
                    'origen' => OrigenColaborador::Admin, 'verificado_at' => now(), 'activo' => true,
                ],
            );

            return ['persona' => $persona, 'turno' => $codigo];
        });
    }

    /**
     * Qué hace cada persona cada día: qué turno trabaja de verdad, a qué hora
     * entra y sale (o si olvida la salida), y si toca dos veces.
     */
    protected function planear(Collection $personas, Collection $tipos, Programacion $programacion, Carbon $desde, int $dias): Collection
    {
        $plan = collect();

        for ($d = 0; $d < $dias; $d++) {
            $dia = $desde->copy()->addDays($d);

            foreach ($personas as ['persona' => $persona, 'turno' => $habitual]) {
                if (in_array($dia->dayOfWeekIso, array_map('intval', (array) $persona->dias_descanso), true) || mt_rand(1, 100) <= 5) {
                    continue; // Descanso, o no vino.
                }

                $codigo = $habitual;
                if ($dia->isFriday() && in_array($codigo, ['N1', 'N1.'], true) && mt_rand(1, 100) <= 40) {
                    $codigo = $codigo === 'N1' ? 'T4' : 'T4.';
                }
                if ($dia->isSunday()) {
                    if (mt_rand(1, 100) > 50) {
                        continue;
                    }
                    $codigo = 'C13';
                }

                $tipo = $tipos[$codigo];
                if (! $programacion->aplica($tipo, $dia)) {
                    continue;
                }

                $inicioT = $programacion->inicio($tipo, $dia);
                $finT = Carbon::parse($dia->toDateString().' '.$tipo->hora_fin);
                if ($finT->lte($inicioT)) {
                    $finT->addDay();
                }

                $tarde = mt_rand(1, 100) <= 15 ? mt_rand(21, 70) : 0;
                $entra = $inicioT->copy()->addMinutes($tarde ?: mt_rand(-10, 10));
                $sale = match (true) {
                    mt_rand(1, 100) <= 7 => null, // Olvida la salida.
                    $persona->cargo === self::CONDUCTOR && mt_rand(1, 100) <= 30 => $entra->copy()->addMinutes(mt_rand(12 * 60, 14 * 60)),
                    mt_rand(1, 100) <= 10 => $finT->copy()->addMinutes(mt_rand(25, 55)), // Se queda de más (dentro del margen).
                    mt_rand(1, 100) <= 3 => $finT->copy()->addMinutes(mt_rand(70, 120)), // Hora extra larga.
                    default => $finT->copy()->addMinutes(mt_rand(-10, 15)),
                };

                $plan->push([
                    'persona' => $persona, 'codigo' => $codigo, 'entra' => $entra, 'sale' => $sale,
                    'tarde' => $tarde, 'extraLarga' => $sale && $sale->gt($finT->copy()->addMinutes(60)), 'doble' => mt_rand(1, 100) <= 5, 'largo' => $persona->cargo === self::CONDUCTOR && $sale && $entra->diffInHours($sale) >= 12,
                ]);
            }
        }

        return $plan;
    }

    protected function informeSemana(Componente $componente, Collection $plan, array $r, Carbon $desde, int $dias, float $segundos): void
    {
        $turnos = app(Jornadas::class)->entre($desde, $desde->copy()->addDays($dias + 1))
            ->keyBy(fn ($t) => $t['entrada']->id);

        $sinTurno = $plan->filter(fn ($p) => isset($p['entrada_id']) && ! $turnos->has($p['entrada_id']));
        $conSalida = $plan->filter(fn ($p) => $p['sale'] && isset($p['entrada_id']) && $turnos->has($p['entrada_id']));
        $bien = $conSalida->filter(fn ($p) => ($turnos->get($p['entrada_id'])['tipo']->codigo ?? null) === $p['codigo']);
        $errores = $conSalida->reject(fn ($p) => ($turnos->get($p['entrada_id'])['tipo']->codigo ?? null) === $p['codigo'])
            ->groupBy(fn ($p) => $p['codigo'].' → '.($turnos->get($p['entrada_id'])['tipo']->codigo ?? 'sin reconocer'))
            ->map->count()->sortDesc();

        $extraLarga = $conSalida->where('extraLarga', true);
        $cortados = $conSalida->where('extraLarga', false)->filter(fn ($p) => ($turnos->get($p['entrada_id'])['salida']->origen ?? null) === OrigenMarcacion::CierreAutomatico);
        $olvidos = $plan->filter(fn ($p) => ! $p['sale'] && isset($p['entrada_id']));
        $cerrados = $olvidos->filter(fn ($p) => ($turnos->get($p['entrada_id'])['salida']->origen ?? null) === OrigenMarcacion::CierreAutomatico);
        $largos = $plan->where('largo', true)->filter(fn ($p) => isset($p['entrada_id']));
        $largosBien = $largos->filter(fn ($p) => ($turnos->get($p['entrada_id'])['salida']->origen ?? null) !== OrigenMarcacion::CierreAutomatico);

        $tardanzaBien = $conSalida->filter(function ($p) use ($turnos) {
            $anotada = ($turnos->get($p['entrada_id'])['minutos_tarde'] ?? 0) > 20;

            return $anotada === ($p['tarde'] > 20);
        });

        $novedades = NovedadTurno::withoutGlobalScopes()->where('componente_id', $componente->id)
            ->whereIn('colaborador_id', $plan->pluck('persona.id')->unique())
            ->selectRaw('tipo, estado, count(*) as total')->groupBy('tipo', 'estado')->get();

        $this->table(['Qué', 'Resultado'], [
            ['Turnos simulados', $plan->count()],
            ['Marcas registradas', $r['marcas']],
            ['Turno reconocido igual al trabajado (con salida)', $bien->count().' de '.$conSalida->count().' ('.$this->pct($bien->count(), $conSalida->count()).')'],
            ['Tardanza bien juzgada (con la salida)', $tardanzaBien->count().' de '.$conSalida->count().' ('.$this->pct($tardanzaBien->count(), $conSalida->count()).')'],
            ['Entradas tomadas como salida (debe ser 0)', $r['entrada_como_salida']],
            ['Entradas que no aparecen en el reporte (debe ser 0)', $sinTurno->count()],
            ['Turnos cortados mientras trabajaban (debe ser 0)', $cortados->count()],
            ['Se quedaron más de 1 h extra (se cierran a fin + 60)', $extraLarga->count()],
            ['Olvidaron la salida', $olvidos->count()],
            ['  · cerrados solos', $cerrados->count()],
            ['Conductores con jornada larga (12–14 h)', $largos->count()],
            ['  · no se les cortó (15 h)', $largosBien->count()],
            ['Marcas rechazadas (turnos por día)', $r['rechazadas']],
            ['Tiempo', round($segundos, 1).' s'],
        ]);

        if ($errores->isNotEmpty()) {
            $this->warn('Dónde no coincidió el turno (trabajado → reconocido):');
            $this->table(['Turno', 'Veces'], $errores->map(fn ($n, $k) => [$k, $n])->values()->all());
        }

        $this->line('Novedades de la semana:');
        $this->table(['Tipo', 'Estado', 'Cuántas'], $novedades->map(fn ($n) => [$n->tipo instanceof \BackedEnum ? $n->tipo->value : $n->tipo, $n->estado instanceof \BackedEnum ? $n->estado->value : $n->estado, $n->total])->all());
        $this->line('Míralo en Turnos → Reporte (componente DEMO · Calle) y en Novedades.');
    }

    protected function pct(int $a, int $b): string
    {
        return $b ? round(100 * $a / $b, 1).' %' : '—';
    }
}
