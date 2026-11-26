<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UsuarioHc;
use App\Policies\Concerns\ExigeModulo;
use App\Services\ContextoDependencia;

/**
 * DOC-26 (plan D3): la ficha se ve en toda la dependencia; lo sensible y la
 * edición piden Edición en adelante y el componente que la conoce, salvo el
 * coordinador líder (Administración), que alcanza a todas.
 */
class UsuarioHcPolicy
{
    use ExigeModulo {
        before as exigeModulo;
    }

    protected const MODULO = 'usuarios_hc';

    public function __construct(private readonly ContextoDependencia $contexto) {}

    /** Sin módulo, todo negado (también al superadministrador); una ficha de otra dependencia, en cada método (CA-31). */
    public function before(User $usuario, string $habilidad, mixed $ficha = null): ?bool
    {
        if ($this->exigeModulo($usuario) === false) {
            return false;
        }

        return $ficha instanceof UsuarioHc && (int) $ficha->dependencia_id !== $this->contexto->id() ? false : null;
    }

    public function viewAny(User $usuario): bool
    {
        return $usuario->rolEn($this->contexto->id()) !== null;
    }

    /** La foto va con view: es identificación. */
    public function view(User $usuario, UsuarioHc $ficha): bool
    {
        return $usuario->rolEn($ficha->dependencia_id) !== null;
    }

    /** El componente del contexto será el de registro. */
    public function create(User $usuario): bool
    {
        return $this->contexto->componenteId() !== null && $usuario->puedeEditarEn($this->contexto->id());
    }

    /** Caracterización e historial. Lectura, nunca (PA-11). */
    public function verCaracterizacion(User $usuario, UsuarioHc $ficha): bool
    {
        return $this->desdeEdicion($usuario, $ficha, $ficha->conocidaPor($this->contexto->componente()));
    }

    /** Identificación, estado vital, foto y caracterización. */
    public function update(User $usuario, UsuarioHc $ficha): bool
    {
        return $this->desdeEdicion($usuario, $ficha, $ficha->editablePor($this->contexto->componente()));
    }

    /** Edición en adelante, y el componente alcanza la ficha o es el coordinador líder. */
    private function desdeEdicion(User $usuario, UsuarioHc $ficha, bool $componenteAlcanza): bool
    {
        return $usuario->puedeEditarEn($ficha->dependencia_id)
            && ($componenteAlcanza || $usuario->puedeAdministrarEn($ficha->dependencia_id));
    }
}
