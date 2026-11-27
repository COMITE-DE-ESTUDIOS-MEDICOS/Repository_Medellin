<?php

namespace App\Support;

use App\Models\CasoCctv;
use App\Models\Derivacion;
use Illuminate\Support\Collection;

/**
 * Las cifras del reporte de Seguridad CCTV (DOC-33), calculadas en PHP sobre los
 * casos ya traídos (plan D8): la mediana no existe en MySQL ni SQLite y así no
 * hay GROUP BY. La pantalla y el Excel leen de aquí.
 */
class ResumenCctv
{
    /**
     * Los tiempos cuentan solo los casos ya atendidos (el abierto aún corre).
     *
     * @param  Collection<int, CasoCctv>  $casos  sin anulados
     * @return array{total: int, estados: array<string, int>, entidades: array<string, int>, comunas: array<string, int>, tiempos: ?array<string, int|string>}
     */
    public static function de(Collection $casos): array
    {
        $minutos = $casos->pluck('tiempo_respuesta_min')->filter(fn (?int $m): bool => $m !== null)->sort()->values();

        return [
            'total' => $casos->count(),
            'estados' => [
                'Abiertos' => $casos->where('estado', CasoCctv::ABIERTO)->count(),
                'Atendidos' => $casos->where('estado', CasoCctv::ATENDIDO)->count(),
                'Cerrados' => $casos->where('estado', CasoCctv::CERRADO)->count(),
            ],
            'entidades' => $casos->countBy(fn (CasoCctv $c): string => CasoCctv::ENTIDADES[$c->entidad] ?? $c->entidad)->sortDesc()->all(),
            'comunas' => $casos->sortBy('comuna')->countBy(fn (CasoCctv $c): string => $c->nombreComuna())->all(),
            'tiempos' => $minutos->isEmpty() ? null : [
                'Casos con tiempo' => $minutos->count(),
                'Promedio' => Derivacion::duracion((int) round($minutos->avg())),
                'Mediana' => Derivacion::duracion((int) round($minutos->median())),
                'Mínimo' => Derivacion::duracion($minutos->first()),
                'Máximo' => Derivacion::duracion($minutos->last()),
            ],
        ];
    }
}
