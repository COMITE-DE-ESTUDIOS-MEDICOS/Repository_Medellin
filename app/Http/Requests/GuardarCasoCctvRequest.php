<?php

namespace App\Http\Requests;

use App\Models\CasoCctv;
use App\Models\Colaborador;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /** El id de la persona asignada, resuelto desde su cédula al validar (null: sin asignar). */
    protected ?int $asignadoId = null;

    public function colaboradorAsignadoId(): ?int
    {
        return $this->asignadoId;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cedula_asignado' => User::normalizarDocumento($this->input('cedula_asignado'))]);
    }

    /**
     * La cédula debe ser de una persona de campo activa de este componente; al corregir se
     * conserva a quien ya estaba asignado aunque se haya desactivado.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validador): void {
            $cedula = (string) $this->input('cedula_asignado');
            if ($cedula === '' || $validador->errors()->has('cedula_asignado')) {
                return;
            }

            $actual = $this->route('caso')?->colaborador_asignado_id;
            $this->asignadoId = Colaborador::query()
                ->where('componente_id', app(ContextoDependencia::class)->componenteId())
                ->where('documento', $cedula)
                ->where(fn ($q) => $q->where('activo', true)->when($actual, fn ($q) => $q->orWhere('id', $actual)))
                ->value('id');

            if ($this->asignadoId === null) {
                $validador->errors()->add('cedula_asignado', 'No hay una persona de campo activa de este componente con esa cédula.');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $caso = $this->route('caso');
        $componente = app(ContextoDependencia::class)->componenteId();

        // Al corregir, el nodo ya elegido se conserva aunque se haya desactivado.
        $nodoActual = $caso?->nodo_id;
        $nodo = Rule::exists('nodos', 'id')
            ->where('componente_id', $componente)
            ->where(fn ($q) => $q->where('activo', 1)->when($nodoActual, fn ($q) => $q->orWhere('id', $nodoActual)));

        $reglas = [
            'entidad' => ['required', Rule::in(array_keys(CasoCctv::ENTIDADES))],
            'entidad_otra' => ['nullable', 'required_if:entidad,otras', 'string', 'max:255'],
            'requerido_at' => ['required', 'date', 'before_or_equal:now'],
            'cedula_asignado' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Z]+$/'],
            'nodo_id' => ['nullable', 'integer', $nodo],
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
            'cedula_asignado.regex' => 'La cédula solo lleva números y letras, sin puntos ni espacios.',
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
            'cedula_asignado' => 'cédula de la persona a quien se le pasa la novedad',
            'nodo_id' => 'nodo que atiende',
            'descripcion' => 'descripción del caso',
            'ubicacion' => 'ubicación',
            'comuna' => 'comuna',
            'soporte' => 'soporte del requerimiento',
        ];
    }
}
