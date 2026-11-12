<?php

namespace App\Http\Requests;

use App\Models\Carpeta;
use App\Services\ContextoDependencia;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class GuardarEnlaceCargaRequest extends FormRequest
{
    /**
     * El vencimiento se elige por día, no por minuto: quien genera un enlace
     * piensa «vence el 15», no «vence el 15 a las 14:30».
     *
     * El campo manda solo la fecha, que Carbon interpreta como su medianoche.
     * Sin normalizar, elegir hoy daba una fecha ya pasada y la validación lo
     * rechazaba; y un enlace que «vence el 15» moría a las 00:00 del 15, que
     * es justo al revés de lo que espera cualquiera.
     */
    protected function prepareForValidation(): void
    {
        $valor = $this->input('expira_at');

        if (! is_string($valor) || trim($valor) === '') {
            return;
        }

        try {
            $this->merge(['expira_at' => Carbon::parse($valor)->endOfDay()->toDateTimeString()]);
        } catch (Throwable) {
            // Si llega basura se deja tal cual: que la rechace la regla 'date'
            // con un mensaje legible, en vez de reventar con un error 500.
        }
    }

    public function rules(): array
    {
        $dependenciaId = app(ContextoDependencia::class)->id();
        $usuario = $this->user();
        // Edición ya puede subir a cualquier carpeta: delegar hacia ella no le da más.
        $puedeCualquierCarpeta = $usuario->puedeEditarEn($dependenciaId);

        return [
            /*
             * La carpeta es obligatoria para todos, también para
             * administración. Sin bandeja intermedia, lo que sube el remitente
             * entra directo al repositorio: si el enlace no dijera dónde,
             * el archivo no tendría a dónde llegar.
             */
            'carpeta_id' => [
                'required',
                Rule::exists('carpetas', 'id')->where('dependencia_id', $dependenciaId),
                new \App\Rules\CarpetaAccesible,
                function (string $atributo, mixed $valor, Closure $falla) use ($puedeCualquierCarpeta, $usuario) {
                    if ($puedeCualquierCarpeta || $valor === null) {
                        return;
                    }

                    // Un líder solo puede delegar hacia una carpeta que lidera.
                    $carpeta = Carpeta::withoutGlobalScopes()->find($valor);

                    if ($carpeta === null || ! $usuario->lideraCarpeta($carpeta)) {
                        $falla('Solo puedes delegar acceso a una carpeta que lideras.');
                    }
                },
            ],

            // Opcional: con componente, quien envía se identifica solo con su
            // cédula (persona de campo). Sin él, declara nombre, correo y entidad.
            'componente_id' => [
                'nullable',
                Rule::exists('componentes', 'id')->where('dependencia_id', $dependenciaId)->where('activo', true),
            ],

            'proposito' => ['nullable', 'string', 'max:255'],

            // Hoy vale: prepareForValidation() ya lo llevó al final del día.
            'expira_at' => ['nullable', 'date', 'after_or_equal:today'],
            'max_usos' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'carpeta_id' => 'carpeta',
            'expira_at' => 'fecha de vencimiento',
            'max_usos' => 'usos máximos',
        ];
    }
}
