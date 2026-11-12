<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarCarpetaRequest extends FormRequest
{
    public function rules(): array
    {
        $dependenciaId = app(\App\Services\ContextoDependencia::class)->id();

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'carpeta_id' => [
                'nullable',
                Rule::exists('carpetas', 'id')->where('dependencia_id', $dependenciaId),
                new \App\Rules\CarpetaAccesible,
            ],
            // La custodia la asigna Coordinación (el controlador lo comprueba).
            'grupo_custodia_id' => [
                'nullable',
                Rule::exists('grupos_custodia', 'id')->where('dependencia_id', $dependenciaId),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['carpeta_id' => 'carpeta contenedora'];
    }
}
