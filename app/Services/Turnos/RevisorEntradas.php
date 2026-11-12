<?php

namespace App\Services\Turnos;

use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Models\Componente;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\Scopes\DependenciaScope;

/**
 * Cada 15 minutos (turnos:revisar-entradas): quien se esperaba según su turno
 * habitual, ya pasó la hora más el margen del componente y no ha marcado
 * entrada, queda como novedad «No registró entrada» y avisa por la campana.
 *
 * Dos consultas por componente y día (las entradas y los avisos ya dados),
 * no una por persona: con cientos de personas corre en milisegundos.
 */
class RevisorEntradas
{
    public function __construct(
        protected Programacion $programacion,
        protected Novedades $novedades,
    ) {
    }

    /** @return int cuántas novedades nuevas */
    public function revisar(): int
    {
        $ahora = now();
        $nuevas = 0;

        foreach (Componente::withoutGlobalScopes()->where('activo', true)->get() as $componente) {
            $margen = (int) $componente->regla('aviso_sin_entrada_min');
            $antes = (int) $componente->regla('reconocer_antes_min');

            // Ayer también: si el servidor no corrió un rato, lo de anoche no se pierde.
            foreach ([$ahora->copy()->subDay(), $ahora->copy()] as $dia) {
                $pendientes = $this->programacion->esperados($componente, $dia)
                    ->filter(fn ($e) => $ahora->gte($e['inicio']->copy()->addMinutes($margen)) && $e['inicio']->gte($ahora->copy()->subDay()));

                if ($pendientes->isEmpty()) {
                    continue;
                }

                $ids = $pendientes->pluck('colaborador.id');

                $entradas = Marcacion::withoutGlobalScope(DependenciaScope::class)
                    ->vigentes()
                    ->whereIn('colaborador_id', $ids)
                    ->where('tipo', TipoMarcacion::Entrada->value)
                    ->whereBetween('marcada_at', [$pendientes->min('inicio')->copy()->subMinutes($antes), $ahora])
                    ->get(['colaborador_id', 'marcada_at'])
                    ->groupBy('colaborador_id');

                $avisados = NovedadTurno::withoutGlobalScopes()
                    ->whereIn('colaborador_id', $ids)
                    ->whereDate('fecha', $dia)
                    ->where('tipo', TipoNovedad::SinEntrada->value)
                    ->pluck('colaborador_id')
                    ->flip();

                foreach ($pendientes as ['colaborador' => $persona, 'tipo' => $tipo, 'inicio' => $inicio]) {
                    $desde = $inicio->copy()->subMinutes($antes);
                    $marco = ($entradas[$persona->id] ?? collect())->contains(fn ($m) => $m->marcada_at->gte($desde));

                    if (! $marco && ! isset($avisados[$persona->id]) && $this->novedades->sinEntrada($persona, $tipo, $inicio)) {
                        $nuevas++;
                    }
                }
            }
        }

        return $nuevas;
    }
}
