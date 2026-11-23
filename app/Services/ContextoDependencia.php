<?php

namespace App\Services;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;

/**
 * Guarda cuál es la dependencia sobre la que está trabajando el usuario
 * durante la petición actual. Es la pieza que hace multitenant al sistema:
 * todas las consultas se filtran por esta dependencia.
 */
class ContextoDependencia
{
    protected ?Dependencia $dependencia = null;

    /** DOC-25: el componente donde está parado (null = sin componente). */
    protected ?Componente $componente = null;

    /** DOC-25: false en consola, colas, vía pública y Livewire::test sin establecer. */
    protected bool $componenteEstablecido = false;

    /** Cambiar de dependencia deja el componente sin establecer: lo vuelve a fijar el middleware. */
    public function establecer(?Dependencia $dependencia): void
    {
        $this->dependencia = $dependencia;
        $this->componente = null;
        $this->componenteEstablecido = false;
    }

    /** null = establecido sin componente: no abre ningún módulo (CA-15). */
    public function establecerComponente(?Componente $componente): void
    {
        $this->componente = $componente;
        $this->componenteEstablecido = true;
    }

    public function componente(): ?Componente
    {
        return $this->componente;
    }

    public function componenteId(): ?int
    {
        return $this->componente?->id;
    }

    public function filtraComponente(): bool
    {
        return $this->componenteEstablecido;
    }

    /** Sin establecer no se restringe nada (consola, vía pública), como DependenciaScope. */
    public function tieneModulo(string $modulo): bool
    {
        return ! $this->componenteEstablecido || ($this->componente?->tieneModulo($modulo) ?? false);
    }

    public function actual(): ?Dependencia
    {
        return $this->dependencia;
    }

    public function id(): ?int
    {
        return $this->dependencia?->id;
    }

    /** Falla en vez de devolver null: úsalo dentro de rutas ya protegidas. */
    public function requerida(): Dependencia
    {
        abort_if($this->dependencia === null, 403, 'No tienes una dependencia asignada.');

        return $this->dependencia;
    }

    public function rol(): ?RolDependencia
    {
        return auth()->user()?->rolEn($this->dependencia);
    }

    public function puedeEditar(): bool
    {
        return $this->rol()?->puedeEditar() ?? false;
    }

    public function puedeGestionar(): bool
    {
        return $this->rol()?->puedeGestionar() ?? false;
    }

    public function puedeAdministrar(): bool
    {
        return $this->rol()?->puedeAdministrar() ?? false;
    }
}
