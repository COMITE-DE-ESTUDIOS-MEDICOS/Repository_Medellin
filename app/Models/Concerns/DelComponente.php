<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ComponenteScope;
use App\Services\ContextoDependencia;
use Illuminate\Database\Eloquent\Model;

/**
 * DOC-25: el registro es de un componente. Lee solo los del componente donde
 * está parado el usuario y no deja guardar uno en otro: los formularios validan
 * componente_id contra toda la dependencia, y esta es la defensa en un solo lugar.
 */
trait DelComponente
{
    public static function bootDelComponente(): void
    {
        static::addGlobalScope(new ComponenteScope);

        static::saving(function (Model $registro) {
            $contexto = app(ContextoDependencia::class);
            $id = $contexto->componenteId();

            abort_if(
                $contexto->filtraComponente() && ($id === null || (int) $registro->componente_id !== $id),
                403,
                'Ese registro es de otro componente.',
            );
        });
    }
}
