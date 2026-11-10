<?php

namespace App\Policies;

use App\Models\Derivacion;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Las derivaciones las revisan y corrigen los líderes (Edición), como los
 * turnos. Borrar es de Coordinación en adelante.
 */
class DerivacionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function view(User $usuario, Derivacion $derivacion): bool
    {
        return $usuario->puedeEditarEn($derivacion->dependencia_id);
    }

    /** DOC-20: la oficina crea casos (Edición en adelante). */
    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    /**
     * DOC-20 (plan de contingencia): horas, ¿se cumplió?, cierre a mano y la
     * hora de cierre de los registros anteriores. Solo el superadministrador
     * de la plataforma; ningún rol de dependencia.
     */
    public function corregirHoras(User $usuario, Derivacion $derivacion): bool
    {
        return (bool) $usuario->es_superadmin;
    }

    public function update(User $usuario, Derivacion $derivacion): bool
    {
        return $this->view($usuario, $derivacion);
    }

    public function delete(User $usuario, Derivacion $derivacion): bool
    {
        return $usuario->puedeGestionarEn($derivacion->dependencia_id);
    }
}
