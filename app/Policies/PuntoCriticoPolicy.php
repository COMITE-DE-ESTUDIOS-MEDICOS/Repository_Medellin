<?php

namespace App\Policies;

use App\Models\PuntoCritico;
use App\Models\User;
use App\Services\ContextoDependencia;

/** Los puntos críticos los manejan los líderes (Edición en adelante): son quienes reparten a la gente. */
class PuntoCriticoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function create(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function update(User $usuario, PuntoCritico $punto): bool
    {
        return $usuario->puedeEditarEn($punto->componente->dependencia_id);
    }

    public function delete(User $usuario, PuntoCritico $punto): bool
    {
        return $this->update($usuario, $punto);
    }

    public function deleteAny(User $usuario): bool
    {
        return $this->create($usuario);
    }
}
