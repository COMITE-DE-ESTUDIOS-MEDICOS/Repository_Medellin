<?php

namespace App\Policies;

use App\Models\CasoCctv;
use App\Models\User;
use App\Policies\Concerns\ExigeModulo;
use App\Services\ContextoDependencia;

/**
 * DOC-33. Ver: Edición en adelante. Registrar, compartir el enlace y ver las
 * imágenes: Coordinación, Administración o Edición con la casilla «Responsable
 * de Seguridad CCTV». Anular: solo Coordinación en adelante.
 */
class CasoCctvPolicy
{
    use ExigeModulo;

    protected const MODULO = 'seguridad_cctv';

    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function view(User $usuario, CasoCctv $caso): bool
    {
        return $usuario->puedeEditarEn($caso->dependencia_id);
    }

    public function create(User $usuario): bool
    {
        return $usuario->puedeGestionarCctvEn(app(ContextoDependencia::class)->id());
    }

    public function compartirEnlace(User $usuario): bool
    {
        return $this->create($usuario);
    }

    public function verImagenes(User $usuario, CasoCctv $caso): bool
    {
        return $usuario->puedeGestionarCctvEn($caso->dependencia_id);
    }

    /** DOC-34: quien ve las imágenes completa evidencias vacías de un caso Atendido o Cerrado. */
    public function cargarEvidencia(User $usuario, CasoCctv $caso): bool
    {
        return $this->verImagenes($usuario, $caso)
            && in_array($caso->estado, [CasoCctv::ATENDIDO, CasoCctv::CERRADO], true);
    }

    public function update(User $usuario, CasoCctv $caso): bool
    {
        return $usuario->puedeGestionarCctvEn($caso->dependencia_id)
            && in_array($caso->estado, [CasoCctv::ABIERTO, CasoCctv::ATENDIDO], true);
    }

    public function cerrar(User $usuario, CasoCctv $caso): bool
    {
        return $this->update($usuario, $caso) && $caso->estado === CasoCctv::ATENDIDO;
    }

    public function anular(User $usuario, CasoCctv $caso): bool
    {
        return $usuario->puedeGestionarEn($caso->dependencia_id) && $caso->estado !== CasoCctv::ANULADO;
    }
}
