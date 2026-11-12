<?php

namespace App\Http\Requests;

use App\Models\EnlaceTurno;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEnlaceTurnoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Sin tipo es de turno, como todos los que había antes.
            'tipo' => ['nullable', Rule::in(array_keys(EnlaceTurno::TIPOS))],
            'componente_id' => [
                'required',
                Rule::exists('componentes', 'id')
                    ->where('dependencia_id', app(ContextoDependencia::class)->id())
                    ->where('activo', true),
            ],
            // Opcional: un QR de un nodo concreto. Tiene que ser de ese componente.
            'nodo_id' => [
                'nullable',
                Rule::exists('nodos', 'id')
                    ->where('componente_id', $this->input('componente_id'))
                    ->where('activo', true),
            ],
            // Opcional: el QR pegado en un punto crítico; quien marca con él, queda ahí.
            'punto_critico_id' => [
                'nullable',
                Rule::exists('puntos_criticos', 'id')
                    ->where('componente_id', $this->input('componente_id'))
                    ->where('activo', true),
            ],
            'nombre' => ['nullable', 'string', 'max:255'],
            // Vence al final de ese día (lo pone el controlador): hoy también vale.
            'expira_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return [
            'componente_id' => 'componente',
            'nodo_id' => 'nodo',
            'punto_critico_id' => 'punto crítico',
            'expira_at' => 'vencimiento',
        ];
    }

    public function messages(): array
    {
        return ['nodo_id.exists' => 'Ese nodo no pertenece al componente elegido.'];
    }
}
