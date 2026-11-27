<?php

namespace App\Http\Requests;

use App\Models\UsuarioHc;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * DOC-26 (plan D6): crear una ficha de Usuario HC. La corrección (UHC-12) la
 * comparte: si la ruta trae {usuarioHc}, ignora esa ficha en la unicidad y
 * ofrece también el tipo de documento que ya tiene aunque se haya retirado.
 *
 * El documento y la ficha se validan sin quitar tildes ni «ñ» (no con
 * normalizarCodigo): se rechazan, no se convierten en silencio (CA-28, CA-29).
 */
class GuardarUsuarioHcRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ficha = $this->ficha();

        return $ficha ? $this->user()->can('update', $ficha) : $this->user()->can('create', UsuarioHc::class);
    }

    private function ficha(): ?UsuarioHc
    {
        $ficha = $this->route('usuarioHc');

        return $ficha instanceof UsuarioHc ? $ficha : null;
    }

    /** Recorta (también el espacio duro de Excel); vacío queda nulo. */
    private static function recortado(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }
        $valor = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $valor) ?? $valor;

        return $valor === '' ? null : $valor;
    }

    /**
     * El documento se guarda como se escribió, solo recortado («ab0012» queda así, §2 y CA-08);
     * las mayúsculas son para comparar (documento_normalizado). La ficha sí se guarda en
     * mayúsculas (PA-20).
     */
    protected function prepareForValidation(): void
    {
        $ficha = self::recortado($this->input('numero_ficha'));

        $this->merge([
            'numero_documento' => self::recortado($this->input('numero_documento')),
            'numero_ficha' => $ficha === null ? null : strtoupper($ficha),
        ]);
    }

    public function rules(): array
    {
        $ficha = $this->ficha();
        $lista = config('repositorio.usuarios_hc.tipos_documento');
        $tipos = array_keys(UsuarioHc::opciones($lista, $ficha ? [$ficha->tipo_documento => $ficha->tipo_documento_texto] : []));
        $sd = UsuarioHc::SIN_DOCUMENTO;

        return [
            'tipo_documento' => ['required', 'string', Rule::in($tipos)],
            'numero_documento' => [
                'bail', 'nullable', Rule::requiredIf(fn () => $this->input('tipo_documento') !== $sd),
                // Es la regla del plan, /^[0-9A-Z]{1,20}$/ sobre strtoupper(...), escrita sobre el
                // texto sin pasarlo a mayúsculas: strtoupper solo toca a-z (ASCII), así que es lo
                // mismo. Sin la bandera u: tildes y «ñ» se rechazan (PA-19).
                'prohibited_if:tipo_documento,'.$sd, 'regex:/^[0-9A-Za-z]{1,20}$/',
                fn ($atributo, $valor, $fail) => ($m = $this->mensajeRepetido('numero_documento')) ? $fail($m) : null,
            ],
            'numero_ficha' => [
                'bail', 'nullable', 'regex:/^[0-9A-Z-]{1,30}$/',
                // Al menos una letra o un número («---» se rechaza): una regex propia, con su mensaje.
                fn ($atributo, $valor, $fail) => preg_match('/[0-9A-Z]/', $valor) ? null : $fail('La ficha necesita al menos una letra o un número.'),
                fn ($atributo, $valor, $fail) => ($m = $this->mensajeRepetido('numero_ficha')) ? $fail($m) : null,
            ],
            'primer_nombre' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'primer_apellido' => ['nullable', 'string', 'max:100'],
            'segundo_apellido' => ['nullable', 'string', 'max:100'],
            'alias' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_documento.required' => 'Escribe el número de documento, o elige «Sin documento».',
            'numero_documento.prohibited_if' => 'Con «Sin documento» no se escribe el número.',
            'numero_documento.regex' => 'Escribe el número solo con letras y números, sin puntos, guiones ni espacios.',
            'numero_ficha.regex' => 'Escribe la ficha solo con letras, números y guiones, sin puntos ni espacios.',
            'tipo_documento.in' => 'Elige un tipo de documento de la lista.',
            'primer_nombre.required' => 'Escribe el primer nombre (si no se conoce, uno provisional).',
        ];
    }

    /**
     * El nombre y el alias, ya normalizados para buscar, tienen que caber en su columna.
     * Str::ascii alarga algunas letras («ß» → «ss»): un nombre así se rechaza con un mensaje
     * en vez de guardarse con la búsqueda recortada (y el último apellido sin encontrarse).
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            $texto = fn (string $campo) => is_string($this->input($campo)) ? $this->input($campo) : '';
            $nombre = implode(' ', array_map($texto, ['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido']));

            if (strlen(UsuarioHc::normalizarTexto($nombre)) > UsuarioHc::NOMBRE_BUSQUEDA_MAX) {
                $validador->errors()->add('primer_nombre', 'El nombre completo es demasiado largo. Acórtalo.');
            }
            if (strlen(UsuarioHc::normalizarTexto($texto('alias'))) > UsuarioHc::ALIAS_BUSQUEDA_MAX) {
                $validador->errors()->add('alias', 'El alias es demasiado largo. Acórtalo.');
            }
        }];
    }

    /**
     * La ficha que ya tiene ese documento o ese número de ficha en TODA la
     * dependencia (sin importar el componente), como mensaje que solo dice la
     * identificación (CA-21); null si está libre. Lo usa también el controlador
     * cuando el índice único gana una carrera entre dos personas: ahí, si ya no
     * se encuentra, el mensaje es el general.
     */
    public function mensajeRepetido(string $campo): ?string
    {
        $otra = UsuarioHc::query()
            ->where('dependencia_id', app(ContextoDependencia::class)->id())
            ->when($this->ficha(), fn ($q, $propia) => $q->whereKeyNot($propia->getKey()))
            ->when($campo === 'numero_documento',
                fn ($q) => $q->where('tipo_documento', $this->input('tipo_documento'))
                    ->where('documento_normalizado', UsuarioHc::normalizarCodigo($this->input('numero_documento'))),
                fn ($q) => $q->where('ficha_normalizada', UsuarioHc::normalizarCodigo($this->input('numero_ficha'))))
            ->first();

        if ($otra === null) {
            return null;
        }

        return $campo === 'numero_documento'
            ? "Ya existe una ficha con ese documento: {$otra->nombre_completo}".($otra->numero_ficha ? ", ficha {$otra->numero_ficha}" : '')
            : "Ya existe una ficha con ese número de ficha: {$otra->nombre_completo}, {$otra->documentoLegible()}";
    }
}
