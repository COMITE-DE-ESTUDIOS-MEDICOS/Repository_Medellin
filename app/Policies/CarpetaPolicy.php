<?php

namespace App\Policies;

use App\Models\Carpeta;
use App\Models\User;

class CarpetaPolicy
{
    public function view(User $usuario, Carpeta $carpeta): bool
    {
        if (! $usuario->perteneceA($carpeta->dependencia_id)) {
            return false;
        }

        if ($usuario->puedeGestionarEn($carpeta->dependencia_id)) {
            return true;
        }

        // Ni retirada ni custodiada por un grupo ajeno.
        return ! $carpeta->estaRetirada()
            && ! in_array($carpeta->id, Carpeta::idsVedadasPara($usuario, $carpeta->dependencia_id), true);
    }

    /**
     * Editar exige primero poder verla: sin esto, el líder del Nodo 2 no veía
     * la carpeta custodiada del Nodo 6, pero con la URL de edición a mano sí
     * podía cambiarla.
     */
    public function update(User $usuario, Carpeta $carpeta): bool
    {
        return $usuario->puedeEditarEn($carpeta->dependencia_id) && $this->view($usuario, $carpeta);
    }

    public function inactivar(User $usuario, Carpeta $carpeta): bool
    {
        if ($usuario->puedeGestionarEn($carpeta->dependencia_id)) {
            return true;
        }

        // Edición retira cualquier carpeta que pueda ver: la custodia ya
        // aparta las de otros nodos. Reactivarla es de Coordinación.
        return $this->view($usuario, $carpeta)
            && $carpeta->activa
            && $usuario->puedeEditarEn($carpeta->dependencia_id);
    }

    public function reactivar(User $usuario, Carpeta $carpeta): bool
    {
        return $usuario->puedeGestionarEn($carpeta->dependencia_id);
    }
}
