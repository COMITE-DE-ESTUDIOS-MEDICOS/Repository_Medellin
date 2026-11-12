<?php

namespace App\Http\Requests;

use App\Models\Componente;
use App\Models\Nodo;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarColaboradorRequest extends FormRequest
{
    /**
     * La cédula se valida ya normalizada: «1.234.567» y «1234567» tienen
     * que chocar contra la misma fila. Y si eligen nodo sin componente, el
     * componente sale del nodo: nadie tiene que elegir las dos cosas.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('documento')) {
            $this->merge(['documento' => User::normalizarDocumento($this->input('documento'))]);
        }

        $this->merge(['dias_descanso' => array_values((array) $this->input('dias_descanso', []))]);

        if ($this->filled('nodo_id') && ! $this->filled('componente_id')) {
            $this->merge(['componente_id' => Nodo::find($this->input('nodo_id'))?->componente_id]);
        }
    }

    /**
     * El permiso va antes que la validación: sin esto, a quien no puede
     * registrar personas le salían errores de campo en vez de un 403.
     */
    public function authorize(): bool
    {
        $colaborador = $this->route('colaborador');

        return $colaborador
            ? $this->user()->can('update', $colaborador)
            : $this->user()->can('create', \App\Models\Colaborador::class);
    }

    public function rules(): array
    {
        $dependenciaId = app(ContextoDependencia::class)->id();

        return [
            'documento' => [
                'required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/',
                Rule::unique('colaboradores', 'documento')
                    ->where('dependencia_id', $dependenciaId)
                    ->ignore($this->route('colaborador')?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'entidad' => ['nullable', 'string', 'max:255'],
            // Si el componente tiene su lista de cargos, solo vale uno de ella.
            // El cargo que la persona ya tenía se acepta aunque no esté: así
            // editar otro dato no obliga a cambiarle el cargo.
            //
            // Componente, nodo y cargo son obligatorios (PLAN.md §8.1): sin
            // ellos no se sabe dónde trabaja ni en qué, y los reportes cojean.
            'cargo' => array_filter([
                'required', 'string', 'max:255',
                ($cargos = $this->cargosPermitidos()) !== null ? Rule::in($cargos) : null,
            ]),
            'componente_id' => [
                'required',
                Rule::exists('componentes', 'id')->where('dependencia_id', $dependenciaId),
            ],
            // El nodo tiene que ser del componente elegido, y por lo tanto de
            // esta dependencia: el id de un nodo ajeno no pasa.
            'nodo_id' => [
                'required',
                Rule::exists('nodos', 'id')->where('componente_id', $this->input('componente_id')),
            ],
            // Lo habitual: con su turno se sabe a qué hora esperarla cada día.
            'tipo_turno_id' => [
                'nullable',
                Rule::exists('tipos_turno', 'id')->where('componente_id', $this->input('componente_id'))->where('es_turno', true),
            ],
            'punto_critico_id' => [
                'nullable',
                Rule::exists('puntos_criticos', 'id')->where('componente_id', $this->input('componente_id')),
            ],
            'dias_descanso' => ['nullable', 'array', 'max:6'],
            'dias_descanso.*' => ['integer', 'between:1,7'],
        ];
    }

    /** @return list<string>|null null cuando el componente no restringe los cargos */
    protected function cargosPermitidos(): ?array
    {
        $cargos = Componente::find($this->input('componente_id'))?->cargos() ?? [];

        if ($cargos === []) {
            return null;
        }

        $actual = $this->route('colaborador')?->cargo;

        return $actual !== null ? [...$cargos, $actual] : $cargos;
    }

    public function attributes(): array
    {
        return [
            'documento' => 'cédula',
            'componente_id' => 'componente',
            'nodo_id' => 'nodo',
            'tipo_turno_id' => 'turno habitual',
            'punto_critico_id' => 'punto crítico habitual',
            'dias_descanso' => 'días de descanso',
        ];
    }

    public function messages(): array
    {
        return [
            'documento.unique' => 'Ya hay una persona registrada con esa cédula.',
            'documento.regex' => 'La cédula solo puede tener números y letras, sin puntos ni espacios.',
            'nodo_id.exists' => 'Ese nodo no pertenece al componente elegido.',
            'cargo.in' => 'Ese cargo no es de los del componente elegido.',
        ];
    }
}
