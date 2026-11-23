<?php

namespace App\Policies;

use App\Models\Supervision;
use App\Models\User;
use App\Policies\Concerns\ExigeModulo;
use App\Services\ContextoDependencia;

/** Las supervisiones las consultan los líderes (Edición) en adelante, como las derivaciones. */
class SupervisionPolicy
{
    use ExigeModulo;

    protected const MODULO = 'supervisiones';

    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function view(User $usuario, Supervision $supervision): bool
    {
        return $usuario->puedeEditarEn($supervision->dependencia_id);
    }
}
