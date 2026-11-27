<?php

namespace App\Http\Requests;

use App\Models\CasoCctv;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** DOC-33: registrar (store) y corregir (update) un caso de Seguridad CCTV. */
class GuardarCasoCctvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $caso = $this->route('caso');

        return $caso instanceof CasoCctv
            ? $this->user()->can('update', $caso)
            : $this->user()->can('create', CasoCctv::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $caso = $this->route('caso');
        $componente = app(ContextoDependencia::class)->componenteId();

        // Al corregir, quien ya está asignado o atiende el nodo se conserva aunque se haya desactivado.
        $delComponente = fn (string $tabla, ?int $actual) => Rule::exists($tabla, 'id')
            ->where('componente_id', $componente)
            ->where(fn ($q) => $q->where('activo', 1)->when($actual, fn ($q) => $q->orWhere('id', $actual)));

        $reglas = [
            'entidad' => ['required', Rule::in(array_keys(CasoCctv::ENTIDADES))],
            'entidad_otra' => ['nullable', 'required_if:entidad,otras', 'string', 'max:255'],
            'requerido_at' => ['required', 'date', 'before_or_equal:now'],
            'colaborador_asignado_id' => ['required', 'integer', $delComponente('colaboradores', $caso?->colaborador_asignado_id)],
            'nodo_id' => ['required', 'integer', $delComponente('nodos', $caso?->nodo_id)],
            'descripcion' => ['required', 'string', 'max:5000'],
            'ubicacion' => ['required', 'string', 'max:500'],
            'comuna' => ['required', 'integer', Rule::in(array_keys(config('repositorio.comunas')))],
        ];

        if ($caso === null) {
            return $reglas + [
                'clave_formulario' => ['required', 'uuid'],
                'soporte' => ['nullable', 'file', 'mimes:'.implode(',', config('repositorio.formatos.imagenes.extensiones')), 'max:'.config('repositorio.tamano_maximo_kb')],
            ];
        }

        // La fecha del requerimiento no puede quedar después de la respuesta (CA-13).
        if ($caso->atendido_at !== null) {
            $reglas['requerido_at'][] = 'before_or_equal:'.$caso->atendido_at->format('Y-m-d H:i:s');
        }

        return $reglas + [
            'motivo' => ['nullable', 'string', 'max:1000'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'entidad_otra.required_if' => 'Escribe cuál es la entidad.',
            'requerido_at.before_or_equal' => 'La fecha y hora del requerimiento no puede ser futura ni posterior a la respuesta.',
            'comuna.in' => 'Elige una comuna o un corregimiento de la lista.',
            'colaborador_asignado_id.exists' => 'Elige una persona de campo activa de la lista.',
            'nodo_id.exists' => 'Elige un nodo activo de la lista.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'entidad' => 'entidad que solicita',
            'entidad_otra' => '¿cuál?',
            'requerido_at' => 'fecha y hora del requerimiento',
            'colaborador_asignado_id' => 'persona a quien se le pasa la novedad',
            'nodo_id' => 'nodo que atiende',
            'descripcion' => 'descripción del caso',
            'ubicacion' => 'ubicación',
            'comuna' => 'comuna',
            'soporte' => 'soporte del requerimiento',
        ];
    }
}
