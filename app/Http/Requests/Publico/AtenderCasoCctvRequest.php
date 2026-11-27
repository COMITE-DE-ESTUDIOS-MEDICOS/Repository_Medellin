<?php

namespace App\Http\Requests\Publico;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** DOC-33: lo que envía la persona de campo al atender un caso de Seguridad CCTV. */
class AtenderCasoCctvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Vía pública: la cédula y el turno se revalidan en el controlador.
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cedula' => User::normalizarDocumento($this->input('cedula'))]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $imagen = ['image', 'mimetypes:'.implode(',', config('repositorio.formatos.imagenes.mimetypes')), 'max:'.config('repositorio.tamano_maximo_kb')];

        return [
            'cedula' => ['required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/'],
            'numero' => ['required', 'string', 'max:20'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
            'evidencia1' => ['required', ...$imagen],
            'evidencia2' => ['nullable', ...$imagen],
            'evidencia3' => ['nullable', ...$imagen],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cedula.regex' => 'La identificación solo lleva números y letras, sin puntos ni espacios.',
            'evidencia1.required' => 'Falta la evidencia #1.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'cedula' => 'identificación', 'numero' => 'número del caso', 'justificacion' => 'justificación',
            'evidencia1' => 'evidencia #1', 'evidencia2' => 'evidencia #2', 'evidencia3' => 'evidencia #3',
        ];
    }
}
