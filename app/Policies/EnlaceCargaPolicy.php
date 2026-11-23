<?php

namespace App\Policies;

use App\Models\EnlaceCarga;
use App\Models\User;
use App\Policies\Concerns\ExigeModulo;
use App\Services\ContextoDependencia;

/**
 * Quién crea y revoca enlaces de carga: Coordinación y Administración, todos
 * los de la dependencia; Edición, hacia cualquier carpeta pero solo los
 * enlaces que creó; y un líder, solo hacia las carpetas que lidera y solo
 * los suyos. Ser líder no da visibilidad sobre el resto de la dependencia —
 * por eso esto vive aparte de RolDependencia.
 */
class EnlaceCargaPolicy
{
    use ExigeModulo;

    protected const MODULO = 'documentos';

    public function viewAny(User $usuario): bool
    {
        $dependenciaId = app(ContextoDependencia::class)->id();

        return $usuario->puedeEditarEn($dependenciaId) || $this->lideraAlgunaCarpeta($usuario, $dependenciaId);
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function view(User $usuario, EnlaceCarga $enlace): bool
    {
        if (! $usuario->perteneceA($enlace->dependencia_id)) {
            return false;
        }

        return $usuario->puedeGestionarEn($enlace->dependencia_id) || $enlace->creado_por === $usuario->id;
    }

    /** Coordinación revoca cualquiera de su dependencia; Edición y un líder, solo los suyos. */
    public function revocar(User $usuario, EnlaceCarga $enlace): bool
    {
        if ($usuario->puedeGestionarEn($enlace->dependencia_id)) {
            return true;
        }

        if ($enlace->creado_por !== $usuario->id) {
            return false;
        }

        return $usuario->puedeEditarEn($enlace->dependencia_id)
            || ($enlace->carpeta_id !== null && $usuario->lideraCarpeta($enlace->carpeta));
    }

    /**
     * Quien puede revocar un enlace puede corregir su fecha de entrega: es
     * el mismo círculo de confianza. Va aparte de 'revocar' porque se
     * concede sobre enlaces ya vencidos o revocados —corregir es arreglar
     * el historial, y el historial no caduca.
     */
    public function corregirEnvio(User $usuario, EnlaceCarga $enlace): bool
    {
        return $this->revocar($usuario, $enlace);
    }

    protected function lideraAlgunaCarpeta(User $usuario, ?int $dependenciaId): bool
    {
        if ($dependenciaId === null) {
            return false;
        }

        return $usuario->carpetasLideradas()->where('dependencia_id', $dependenciaId)->exists();
    }
}
