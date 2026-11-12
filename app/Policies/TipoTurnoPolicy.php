<?php

namespace App\Policies;

use App\Models\TipoTurno;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * El catálogo de turnos lo manejan los líderes (Edición en adelante): son
 * quienes arman la malla. Los códigos son los de nómina.
 */
class TipoTurnoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function create(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function update(User $usuario, TipoTurno $tipo): bool
    {
        return $usuario->puedeEditarEn($tipo->componente->dependencia_id);
    }

    public function delete(User $usuario, TipoTurno $tipo): bool
    {
        return $this->update($usuario, $tipo);
    }

    public function deleteAny(User $usuario): bool
    {
        return $this->create($usuario);
    }
}
