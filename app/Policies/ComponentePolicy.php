<?php

namespace App\Policies;

use App\Models\Componente;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Cambiar las reglas de un componente cambia cómo trabaja todo un equipo: es
 * de Coordinación en adelante, cada quien en el suyo (ComponenteScope). Sus
 * nodos van con él. Crearlo y cambiar sus módulos: solo el superadministrador.
 */
class ComponentePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeGestionarEn(app(ContextoDependencia::class)->id());
    }

    /** DOC-25 (PA-07, CA-10): crear un componente es solo del superadministrador. */
    public function create(User $usuario): bool
    {
        return (bool) $usuario->es_superadmin;
    }

    public function update(User $usuario, Componente $componente): bool
    {
        return $usuario->puedeGestionarEn($componente->dependencia_id);
    }
}
