<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class GuardarDocumentoRequest extends FormRequest
{
    /** Tope de una sola carga. Cada archivo se convierte en un documento. */
    public const MAXIMO_ARCHIVOS = 50;

    /** @return list<\Illuminate\Http\UploadedFile> */
    public function archivos(): array
    {
        return Arr::wrap($this->file('archivo'));
    }

    public function rules(): array
    {
        $dependenciaId = app(\App\Services\ContextoDependencia::class)->id();
        $cantidad = count($this->archivos());

        return [
            // Al subir, el nombre de cada documento viaja en nombres[], una
            // caja por tarjeta. El campo suelto sigue aceptándose como
            // respaldo para una carga de un solo archivo.
            'nombre' => [$this->routeIs('documentos.store') ? 'nullable' : 'required', 'string', 'max:5000'],

            // Lista paralela a archivo[]: casan por posición. Vacío vale,
            // y entonces el documento toma el nombre de su archivo.
            'nombres' => ['nullable', 'array', 'max:'.self::MAXIMO_ARCHIVOS],
            'nombres.*' => ['nullable', 'string', 'max:5000'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_documento' => ['nullable', 'date', 'before_or_equal:today'],
            'carpeta_id' => [
                'nullable',
                Rule::exists('carpetas', 'id')->where('dependencia_id', $dependenciaId),
                new \App\Rules\CarpetaAccesible,
            ],
            'tipo_documento_id' => [
                'nullable',
                Rule::exists('tipos_documento', 'id')->where(
                    fn ($q) => $q->where('dependencia_id', $dependenciaId)->orWhereNull('dependencia_id')
                ),
            ],
            'etiquetas' => ['nullable', 'string', 'max:500'],

            // El formulario manda archivo[] para poder subir varios de una
            // vez. Un archivo suelto también vale y se valida igual: así una
            // carga de uno y una de veinte recorren el mismo camino.
            'archivo' => [
                $this->routeIs('documentos.store') ? 'required' : 'nullable',
                'array',
                'max:'.self::MAXIMO_ARCHIVOS,
                \App\Services\CargaPorTandas::reglaCompleta(),
            ],
            'archivo.*' => [
                'bail',
                'file',
                'max:'.config('repositorio.tamano_maximo_kb'),
                'mimes:'.implode(',', config('repositorio.formatos.aplicacion.extensiones')),
                'mimetypes:'.implode(',', config('repositorio.formatos.aplicacion.mimetypes')),
            ],

            'comentario_version' => ['nullable', 'string', 'max:255'],

            // Si la carga viene partida en tandas (carga-tandas.js).
            ...\App\Services\CargaPorTandas::reglas(),
        ];
    }

    /**
     * Un archivo suelto llega como tal, no como lista de uno. Se envuelve
     * antes de validar para que las reglas de arriba sirvan en los dos casos.
     */
    protected function prepareForValidation(): void
    {
        $archivo = $this->file('archivo');

        if ($archivo !== null && ! is_array($archivo)) {
            $this->files->set('archivo', [$archivo]);

            // allFiles() guarda en caché lo ya convertido. Sin vaciarla
            // seguiría devolviendo el archivo suelto y la regla 'array'
            // fallaría sin motivo.
            $this->convertedFiles = null;
        }
    }

    public function attributes(): array
    {
        return [
            'carpeta_id' => 'carpeta',
            'tipo_documento_id' => 'tipo de documento',
            'fecha_documento' => 'fecha del documento',
            'comentario_version' => 'comentario de la versión',
            'archivo' => 'archivo',
            'archivo.*' => 'archivo',
            'nombres.*' => 'nombre del documento',
        ];
    }

    public function messages(): array
    {
        $maximoMb = round(config('repositorio.tamano_maximo_kb') / 1024);

        return [
            'archivo.required' => 'Selecciona al menos un archivo.',
            'archivo.max' => 'No puedes subir más de '.self::MAXIMO_ARCHIVOS.' archivos a la vez.',

            // Con varios, el mensaje dice cuál falló: «archivo 3» a secas no
            // le sirve a nadie para saber qué quitar.
            'archivo.*.max' => 'El archivo :position supera el tamaño máximo permitido ('.$maximoMb.' MB).',
            'archivo.*.mimes' => 'El archivo :position no es un PDF, ni una imagen (JPG, PNG, WEBP), ni una hoja de cálculo (XLSX, XLS).',
            'archivo.*.mimetypes' => 'El contenido del archivo :position no corresponde a un PDF, ni a una imagen, ni a una hoja de cálculo.',
        ];
    }
}
