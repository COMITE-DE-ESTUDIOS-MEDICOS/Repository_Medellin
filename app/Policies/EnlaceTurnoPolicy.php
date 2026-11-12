<?php

namespace App\Policies;

use App\Models\EnlaceTurno;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Los enlaces de turno (el QR de cada componente o nodo) los manejan desde
 * Edición: los editores son los líderes que los reparten.
 */
class EnlaceTurnoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function view(User $usuario, EnlaceTurno $enlace): bool
    {
        return $usuario->puedeEditarEn($enlace->dependencia_id);
    }

    public function revocar(User $usuario, EnlaceTurno $enlace): bool
    {
        return $this->view($usuario, $enlace) && $enlace->activo;
    }
}
