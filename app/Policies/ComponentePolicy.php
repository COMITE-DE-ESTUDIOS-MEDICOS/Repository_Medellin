<?php

namespace App\Policies;

use App\Models\Componente;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Crear Básica o Estabilización, o cambiar sus reglas, cambia cómo trabaja
 * todo un equipo: es de Coordinación en adelante. Sus nodos van con él.
 */
class ComponentePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeGestionarEn(app(ContextoDependencia::class)->id());
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function update(User $usuario, Componente $componente): bool
    {
        return $usuario->puedeGestionarEn($componente->dependencia_id);
    }
}
