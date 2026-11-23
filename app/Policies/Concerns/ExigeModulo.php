<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * DOC-25 (RN-49): primero el componente, después el rol. Si el componente donde
 * está parado el usuario no tiene el módulo de la Policy (const MODULO), se niega
 * todo, también al superadministrador; si lo tiene, decide la regla del rol.
 * Cubre lo que no pasa por las rutas: acciones de Filament y el menú.
 */
trait ExigeModulo
{
    public function before(User $usuario): ?bool
    {
        return app(ContextoDependencia::class)->tieneModulo(static::MODULO) ? null : false;
    }
}
