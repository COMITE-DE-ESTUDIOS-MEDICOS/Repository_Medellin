<?php

namespace App\Services;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;
use App\Models\Componente;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría. El requerimiento pide poder inactivar archivos
 * "por motivos de auditoría y seguridad": esto es lo que hace que esa
 * inactivación sea rastreable hasta la persona que la ejecutó.
 */
class Auditor
{
    public function __construct(protected ContextoDependencia $contexto)
    {
    }

    public function registrar(
        AccionAuditoria $accion,
        ?Model $auditable = null,
        ?string $descripcion = null,
        array $datos = [],
    ): Auditoria {
        return Auditoria::create([
            'dependencia_id' => $this->contexto->id(),
            // DOC-25: el del registro (vía pública, cierres automáticos) o, si
            // no tiene, aquel donde está parado el usuario.
            'componente_id' => ($auditable instanceof Componente ? $auditable->getKey() : $auditable?->getAttribute('componente_id'))
                ?? $this->contexto->componenteId(),
            'user_id' => auth()->id(),
            'accion' => $accion->value,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'descripcion' => $descripcion,
            'datos' => $datos ?: null,
            'ip' => request()->ip(),
            'agente' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
