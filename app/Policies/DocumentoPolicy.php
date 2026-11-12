<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

class DocumentoPolicy
{
    public function view(User $usuario, Documento $documento): bool
    {
        if (! $usuario->perteneceA($documento->dependencia_id)) {
            return false;
        }

        // Un documento inactivo solo lo ve quien administra: sigue existiendo
        // para auditoría, pero desaparece de la vista de los demás.
        if (! $documento->activo && ! $usuario->puedeAdministrarEn($documento->dependencia_id)) {
            return false;
        }

        // Y uno dentro de una carpeta retirada, solo quien ve esa carpeta:
        // si no, su enlace directo seguía abriéndolo.
        return $documento->carpeta === null || $usuario->can('view', $documento->carpeta);
    }

    public function download(User $usuario, Documento $documento): bool
    {
        return $this->view($usuario, $documento);
    }

    /** Editar (y copiar, mover, subir versión) exige primero poder verlo: un documento custodiado por otros no se toca. */
    public function update(User $usuario, Documento $documento): bool
    {
        return $documento->activo && $usuario->puedeEditarEn($documento->dependencia_id) && $this->view($usuario, $documento);
    }

    public function subirVersion(User $usuario, Documento $documento): bool
    {
        return $this->update($usuario, $documento);
    }

    /**
     * Edición inactiva lo que puede ver (la custodia ya aparta lo de otros
     * nodos), siempre con motivo. Nada se borra: queda para auditoría.
     */
    public function inactivar(User $usuario, Documento $documento): bool
    {
        return $documento->activo
            && $usuario->puedeEditarEn($documento->dependencia_id)
            && $this->view($usuario, $documento);
    }

    /** Deshacer es de Administración: es la única que ve lo inactivo. */
    public function reactivar(User $usuario, Documento $documento): bool
    {
        return $usuario->puedeAdministrarEn($documento->dependencia_id);
    }

    /**
     * Los archivos reemplazados. Para quien sube, reemplazar es reemplazar;
     * lo anterior se conserva para auditoría y lo consulta Coordinación.
     */
    public function verVersiones(User $usuario, Documento $documento): bool
    {
        return $this->view($usuario, $documento) && $usuario->puedeGestionarEn($documento->dependencia_id);
    }
}
