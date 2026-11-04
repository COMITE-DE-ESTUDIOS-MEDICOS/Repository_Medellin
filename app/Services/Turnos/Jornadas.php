<?php

namespace App\Services\Turnos;

use App\Enums\TipoMarcacion;
use App\Models\Marcacion;
use App\Models\TipoTurno;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Arma los turnos a partir de las marcaciones sueltas: cada entrada con la
 * salida que le sigue. Es lo que leen el reporte y las sumas de horas.
 */
class Jornadas
{
    public function __construct(protected Programacion $programacion)
    {
    }

    /**
     * Los turnos que empezaron en el rango, con los filtros que se pasen.
     * «tipo» es el turno de la malla (N1, N7…) que corresponde a la hora de
     * la entrada, reconocido ahora y no el que se guardó al marcar: una hora
     * corregida o una marca manual también lo tienen.
     *
     * @return Collection<int, array{entrada: Marcacion, salida: ?Marcacion, minutos: ?int, tipo: ?TipoTurno}>
     */
    public function entre(CarbonInterface $desde, CarbonInterface $hasta, ?callable $filtrar = null): Collection
    {
        $marcaciones = Marcacion::query()
            ->vigentes()
            ->with(['colaborador.tipoTurno', 'componente', 'nodo'])
            ->when($filtrar, fn (Builder $q) => $filtrar($q))
            // Un día de margen al final: la salida de un turno de noche cae
            // al día siguiente del último día del rango.
            ->whereBetween('marcada_at', [$desde, $hasta->copy()->addDay()])
            ->orderBy('colaborador_id')
            ->orderBy('marcada_at')
            ->get();

        $turnos = collect();

        foreach ($marcaciones->groupBy('colaborador_id') as $dePersona) {
            $abierta = null;

            foreach ($dePersona as $marca) {
                if ($marca->tipo === TipoMarcacion::Entrada) {
                    // Dos entradas seguidas: la primera quedó sin salida.
                    if ($abierta) {
                        $turnos->push($this->turno($abierta, null));
                    }

                    $abierta = $marca->marcada_at->lte($hasta) ? $marca : null;

                    continue;
                }

                if ($abierta) {
                    $turnos->push($this->turno($abierta, $marca));
                    $abierta = null;
                }
            }

            if ($abierta) {
                $turnos->push($this->turno($abierta, null));
            }
        }

        return $turnos->sortBy(fn ($t) => $t['entrada']->marcada_at)->values();
    }

    protected function turno(Marcacion $entrada, ?Marcacion $salida): array
    {
        return [
            'entrada' => $entrada,
            'salida' => $salida,
            'minutos' => $salida ? (int) $entrada->marcada_at->diffInMinutes($salida->marcada_at) : null,
            'tipo' => $this->programacion->reconocer($entrada->componente, $entrada->marcada_at, $entrada->colaborador->tipoTurno)['tipo'] ?? null,
        ];
    }
}
