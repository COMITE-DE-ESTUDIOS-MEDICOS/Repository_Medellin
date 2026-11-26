<?php

namespace App\Casts;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * DOC-27 (plan D3): hora de pared de Colombia, sin importar app.timezone ni la
 * zona del servidor. Guarda 'Y-m-d H:i:00' (segundos siempre en 00, al minuto)
 * y devuelve un Carbon ya en Colombia. Va en columnas datetime, no timestamp.
 *
 * @implements CastsAttributes<Carbon, CarbonInterface|string|null>
 */
class HoraColombia implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::createFromFormat('Y-m-d H:i:s', $value, config('repositorio.horario.zona'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $zona = config('repositorio.horario.zona');
        $hora = $value instanceof CarbonInterface ? Carbon::instance($value) : Carbon::parse($value, $zona);

        return $hora->setTimezone($zona)->format('Y-m-d H:i:00');
    }
}
