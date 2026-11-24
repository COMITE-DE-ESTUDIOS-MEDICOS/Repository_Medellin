<?php

namespace App\Http\Requests;

use App\Enums\RolDependencia;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarUsuarioRequest extends FormRequest
{
    /**
     * El documento se guarda y se compara siempre en su forma canónica, para
     * que «1.234.567» y «1234567» no acaben siendo dos cuentas de la misma
     * persona.
     */
    protected function prepareForValidation(): void
    {
        // DOC-22: el de una persona es su cédula (se le quitan puntos y
        // espacios); el del superadministrador («adminDocumenta») va tal cual,
        // solo sin espacios a los lados: con sus mayúsculas y minúsculas.
        if ($this->has('documento')) {
            $this->merge(['documento' => $this->esSuperadmin()
                ? trim((string) $this->input('documento'))
                : User::normalizarDocumento($this->input('documento'))]);
        }

        if ($this->filled('usuario')) {
            $this->merge(['usuario' => User::normalizarUsuario($this->input('usuario'))]);
        }

        // DOC-25 (Edwar 2026-11-24): cada quien edita sus datos en su propia
        // ficha, pero no su acceso: el rol, el componente y el estado se quedan
        // como están (los de Administración los cambia el superadministrador).
        if ($this->esPropia()) {
            $dependencia = $this->dependenciaId();
            $this->merge([
                'rol' => $this->user()->rolEn($dependencia)?->value,
                'componente_id' => $this->user()->componenteEn($dependencia),
                'activo' => true,
            ]);
        }

    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $esEdicion = $usuario !== null;

        // En pantalla se llama «Usuario»: por norma interna, la cédula. Es el
        // username de la cuenta de Authentik (DOC-22).
        $documento = [
            'required', 'string', $this->esSuperadmin() ? 'regex:/^[A-Za-z0-9]{3,30}$/' : 'regex:/^[0-9]{3,20}$/',
            $this->noChocarConOtraColumna('usuario', $usuario?->id),
        ];

        if ($esEdicion) {
            // Al editar, el documento sigue siendo único: nadie puede
            // quedarse con el de otra cuenta y suplantarla al entrar.
            $documento[] = Rule::unique('users', 'documento')->ignore($usuario->id);
        } else {
            // DOC-25: «Dar acceso» a quien ya está aquí le pisaría rol y
            // componente (Coordinación de Calle le quitaría una persona a
            // Básica). Se edita desde la lista.
            $documento[] = function (string $atributo, mixed $valor, \Closure $falla) {
                if ($this->cuentaExistente()?->dependencias()->whereKey($this->dependenciaId())->exists()) {
                    $falla('Esa persona ya tiene acceso aquí; edítala desde la lista.');
                }
            };
        }

        return [
            'name' => ['required', 'string', 'max:255'],

            // Al dar de alta no se exige único a propósito: un documento
            // repetido no es un error, es la señal de que esa persona ya
            // tiene cuenta en otra dependencia y lo que toca es sumarle el
            // acceso a esta. Quien impide de verdad las cuentas duplicadas es
            // el índice único de la tabla, del que se encarga el controlador.
            'documento' => $documento,

            // El correo no interviene en el ingreso: lo que empareja esta
            // cuenta con su identidad en Authentik es el documento. Queda
            // como dato de contacto, y puede faltar —hay funcionarios que
            // sencillamente no tienen uno.
            //
            // Único porque la columna lleva ese índice desde la primera
            // migración: sin esta regla, repetir un correo no sale como un
            // error del campo sino como un error 500. Vacío no choca con
            // vacío, así que los que no tienen correo conviven sin problema.
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($usuario?->id ?? $this->cuentaExistente()?->id),
            ],

            'cargo' => ['nullable', 'string', 'max:150'],

            // No basta con que el rol exista: tiene que estar entre los que
            // quien envía el formulario puede repartir. La lista de la
            // pantalla ya viene filtrada, pero eso solo esconde el botón; lo
            // que impide el ascenso de verdad es esta regla.
            'rol' => ['required', Rule::in($this->esPropia() ? [$this->input('rol')] : $this->rolesQuePuedeAsignar())],

            // DOC-25: obligatorio salvo para Administración, que no tiene
            // componente (el controlador guarda null). Coordinación solo da
            // el suyo; Administración, cualquiera activo de la dependencia.
            'componente_id' => $this->input('rol') === RolDependencia::Administracion->value || $this->esPropia()
                ? ['nullable']
                : ['required', Rule::in($this->user()?->componentesAsignablesEn($this->dependenciaId())->modelKeys() ?? [])],
            'activo' => ['boolean'],

            // El alias corto del formulario de emergencia: solo existe para
            // el superadministrador (PA-01). A los demás ni se les valida ni
            // se les guarda.
            ...($usuario?->es_superadmin ? ['usuario' => [
                'nullable', 'string', 'max:50', 'regex:/^(?=.*[a-z])[a-z0-9._-]+$/',
                Rule::unique('users', 'usuario')->ignore($usuario->id),
                $this->noChocarConOtraColumna('documento', $usuario->id),
            ]] : []),
        ];
    }

    /**
     * Impide que el valor de un campo coincida con el de la otra columna de
     * acceso en otra cuenta.
     *
     * Los dos identifican a la misma persona y el alias es, además, el
     * nombre con el que nace su identidad en Authentik. Si alguien pusiera
     * como alias la cédula de un compañero, ese valor dejaría de resolver a
     * una sola persona: no alcanza para suplantar a nadie, pero sí para
     * dejar a uno de los dos fuera sin que nadie entienda por qué.
     */
    protected function noChocarConOtraColumna(string $columna, ?int $ignorarId): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $falla) use ($columna, $ignorarId) {
            if (! is_string($valor) || $valor === '') {
                return;
            }

            $chocan = User::where($columna, $valor)
                ->when($ignorarId !== null, fn ($q) => $q->whereKeyNot($ignorarId))
                ->exists();

            if ($chocan) {
                $falla($columna === 'usuario'
                    ? 'Ese usuario ya lo usa otra persona como alias de emergencia.'
                    : 'Ese alias coincide con el usuario de otra persona.');
            }
        };
    }

    /** @return list<string> valores de rol que quien envía puede otorgar */
    protected function rolesQuePuedeAsignar(): array
    {
        return array_map(fn (RolDependencia $rol) => $rol->value, $this->user()?->rolesAsignablesEn($this->dependenciaId()) ?? []);
    }

    protected function dependenciaId(): int
    {
        return app(\App\Services\ContextoDependencia::class)->requerida()->id;
    }

    /** La ficha de quien la envía (el superadministrador, de plataforma, no cuenta). */
    protected function esPropia(): bool
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User && $usuario->is($this->user()) && ! $this->user()->es_superadmin;
    }

    /** DOC-22: solo el superadministrador tiene un usuario con letras (p. ej. «adminDocumenta»). */
    protected function esSuperadmin(): bool
    {
        return (bool) $this->route('usuario')?->es_superadmin;
    }

    /** Cuenta que ya existe con el documento enviado, si la hay. */
    protected function cuentaExistente(): ?User
    {
        $documento = $this->input('documento');

        return is_string($documento) && $documento !== ''
            ? User::where('documento', $documento)->first()
            : null;
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'documento' => 'usuario',
            'usuario' => 'alias de emergencia',
            'email' => 'correo',
            'componente_id' => 'componente',
        ];
    }

    public function messages(): array
    {
        return [
            'documento.required' => 'El usuario es obligatorio con acceso web: por norma interna, la cédula de la persona.',
            'documento.unique' => 'Ese usuario ya lo tiene otra persona con acceso web.',
            'documento.regex' => $this->esSuperadmin()
                ? 'El usuario del superadministrador solo admite letras y números, sin espacios.'
                : 'El usuario solo admite números (la cédula), sin puntos ni espacios.',
            'usuario.regex' => 'El alias usa minúsculas, números, puntos, guiones y guiones bajos, '
                .'y debe llevar al menos una letra.',
            'rol.in' => 'No puedes otorgar ese rol: nunca uno por encima del tuyo, y Administración solo la da el superadministrador.',
            'componente_id.required' => 'Elige el componente de la persona.',
            'componente_id.in' => 'Solo puedes asignar un componente activo que tengas a cargo.',
        ];
    }
}
