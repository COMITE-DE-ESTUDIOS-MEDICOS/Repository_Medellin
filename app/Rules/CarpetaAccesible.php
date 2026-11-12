<?php

namespace App\Rules;

use App\Models\Carpeta;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La carpeta de destino tiene que ser una que esa persona ve y que esté
 * activa: ni de otra dependencia, ni retirada, ni custodiada por un grupo
 * ajeno. Es la misma regla para subir, crear o mover una carpeta, copiar,
 * copiar o mover por check y crear un enlace de carga: ninguno de esos
 * caminos puede meter algo donde no se tiene acceso.
 */
class CarpetaAccesible implements ValidationRule
{
    public function validate(string $atributo, mixed $valor, Closure $falla): void
    {
        if ($valor === null || $valor === '') {
            return;
        }

        $accesible = Carpeta::visiblesPara(auth()->user())->where('activa', true)->whereKey($valor)->exists();

        if (! $accesible) {
            $falla('No tienes acceso a esa carpeta.');
        }
    }
}
