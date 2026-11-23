<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Carpeta;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use App\Support\Xlsx;
use App\Support\XlsxImagenes;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

/**
 * DOC-24: carga única de los casos que ya se atendieron en la calle y no se
 * habían subido. Cada fila del Excel nace como un caso atendido, con las horas
 * del archivo (excepción autorizada a RN-30 y RN-32) y con el soporte y las
 * tres evidencias tomados de las imágenes de sus celdas. Todo o nada.
 *
 * ponytail: temporal (noviembre de 2026). Se cierra sola en
 * casos_carga_masiva.abierta_hasta; después se quita con sus dos rutas, los
 * botones de derivaciones/index y cargarMasivo de la política.
 */
class CargaMasivaCasosController extends Controller
{
    public const ENCABEZADOS = [
        'Componente *', 'Mensaje para la atención *', 'Quién lo envía *', '¿Cuál? (solo con «Otra»)', 'Comuna *',
        'Ubicación *', 'Fecha y hora de inicio *', 'Fecha y hora de cierre *', 'Nodo que atendió *',
        'Cédula de quien atendió *', 'Atención o gestión realizada *', '¿Se cumplió? *',
        'Observación (si no se cumplió)', 'Justificación (si pasó de 3 horas)',
        'Soporte del caso (imagen)', 'Evidencia 1 * (imagen)', 'Evidencia 2 * (imagen)', 'Evidencia 3 * (imagen)',
    ];

    /** Columnas de las imágenes: campo del caso => índice en ENCABEZADOS. */
    protected const IMAGENES = ['soporte' => 14, 'evidencia1' => 15, 'evidencia2' => 16, 'evidencia3' => 17];

    protected const EXTENSIONES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        protected ContextoDependencia $contexto,
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {}

    /** El formato vacío y, en otra hoja, los valores válidos de esta dependencia. */
    public function formato(): BinaryFileResponse
    {
        $this->authorize('cargarMasivo', Derivacion::class);

        // Una columna por lista: de aquí salen las listas desplegables de la hoja «Casos».
        $componentes = Componente::with('nodos')->where('activo', true)->orderBy('nombre')->get();
        $columnas = [
            $componentes->pluck('nombre')->all(),
            // Un nodo con el mismo nombre en dos componentes sale una vez; al cargar se revisa que sea del componente.
            $componentes->flatMap->nodos->pluck('nombre')->unique()->sort()->values()->all(),
            array_values(Derivacion::REMITENTES),
            collect(config('repositorio.comunas'))->map(fn ($nombre, $numero) => "{$numero} · {$nombre}")->values()->all(),
            $componentes->flatMap(fn ($c) => $c->nodos->map(fn ($nodo) => "{$c->nombre}: {$nodo->nombre}"))->all(),
        ];
        $listas = [];
        for ($i = 0; $i < max(array_map('count', $columnas)); $i++) {
            $listas[] = array_map(fn ($columna) => $columna[$i] ?? null, $columnas);
        }
        $rango = fn (int $columna) => "'Valores válidos'!\$".Xlsx::columna($columna).'$2:$'.Xlsx::columna($columna).'$'.(max(1, count($columnas[$columna])) + 1);

        $ruta = Xlsx::libro(tempnam(sys_get_temp_dir(), 'formato'), [
            // Listas desplegables: componente, quién lo envía, comuna, nodo y ¿se cumplió?
            'Casos' => [self::ENCABEZADOS, [], [0 => $rango(0), 2 => $rango(2), 4 => $rango(3), 8 => $rango(1), 11 => '"Sí,No"']],
            'Valores válidos' => [['Componente', 'Nodo', 'Quién lo envía', 'Comuna', 'Nodos de cada componente'], $listas],
            'Instrucciones' => [['Cómo llenar la hoja «Casos»'], array_map(fn ($t) => [$t], [
                'Solo para casos que YA se atendieron y no se habían subido a Documenta. Los casos nuevos se crean en «Nuevo caso».',
                'Una fila por caso, desde la fila 2. No cambie ni mueva los encabezados. Las columnas con * son obligatorias.',
                'El código del caso (DC-…) lo pone el sistema, en el orden de las filas.',
                'Fecha y hora de inicio: cuándo llegó el requerimiento. Fecha y hora de cierre: cuándo se atendió. Escríbalas así: 13/11/2026 14:30 (hora de 0 a 23).',
                'Escriba la fecha y la hora reales del caso, de cualquier día. No pueden ser futuras y el cierre va después del inicio.',
                'Componente, Quién lo envía, Comuna, Nodo y ¿Se cumplió? se eligen de la lista desplegable de la celda. El nodo tiene que ser del componente de esa fila (vea «Valores válidos»).',
                'Cédula de quien atendió: una persona de campo ya registrada. Solo números, sin puntos.',
                '¿Se cumplió?: Sí o No. Con «No», la observación es obligatoria. Si el caso tardó más de 3 horas, la justificación es obligatoria.',
                'Imágenes: párese en la celda y use Insertar > Imágenes > Colocar en la celda. Una imagen por celda (JPG o PNG). Las tres evidencias son obligatorias; el soporte, si lo hay.',
                'Máximo '.config('repositorio.casos_carga_masiva.maximo_filas').' casos por archivo. Si son más, suba varios archivos.',
                'Si una sola fila tiene un error no se carga ninguna: el sistema dice qué fila corregir.',
            ])],
        ]);

        return response()->download($ruta, 'formato_carga_de_casos_atendidos.xlsx')->deleteFileAfterSend();
    }

    public function importar(Request $request): RedirectResponse
    {
        $this->authorize('cargarMasivo', Derivacion::class);
        $dependencia = $this->contexto->requerida();

        $request->validate(
            ['archivo' => ['required', 'file', 'mimes:xlsx', 'max:'.(config('repositorio.carga.mb_por_tanda') * 1024)]],
            [],
            ['archivo' => 'Excel de casos'],
        );

        $archivo = $request->file('archivo');
        $filas = $this->leer($archivo->getRealPath());
        $maximo = (int) config('repositorio.casos_carga_masiva.maximo_filas');

        if ($filas === []) {
            return back()->with('error', 'La hoja «Casos» no tiene filas para cargar.');
        }
        if (count($filas) > $maximo) {
            return back()->with('error', "Máximo {$maximo} casos por archivo: divídelo y sube cada parte.");
        }

        $zip = new ZipArchive;
        $zip->open($archivo->getRealPath());
        $temporales = [];

        try {
            $imagenes = XlsxImagenes::dePrimeraHoja($zip);
            $componentes = Componente::with('nodos')->where('activo', true)->get();
            $errores = [];
            $casos = [];
            $vistos = [];

            foreach ($filas as $numero => $celdas) {
                $problemas = [];
                $datos = $this->aDatos($celdas, $componentes, $dependencia->id, $problemas);

                foreach (self::IMAGENES as $campo => $columna) {
                    $datos[$campo] = $this->imagen($zip, $imagenes[Xlsx::columna($columna).$numero] ?? null, $campo, $problemas, $temporales);
                }

                if ($problemas === [] && ($repetido = $this->repetido($datos, $dependencia->id, $vistos)) !== null) {
                    $problemas[] = "está repetida: mismo remitente, ubicación y hora de inicio que {$repetido}";
                }

                foreach ($problemas as $problema) {
                    $errores[] = "Fila {$numero}: {$problema}.";
                }

                if ($problemas === []) {
                    $vistos[$this->huella($datos['entidad'], $datos['ubicacion'], $datos['inicio'])] = "la fila {$numero}";
                    $casos[$numero] = $datos;
                }
            }

            if ($errores !== []) {
                return back()->with('carga_errores', array_slice($errores, 0, 50))
                    ->with('error', 'No se cargó ningún caso: corrige '.(count($errores) === 1 ? 'el error' : 'los '.count($errores).' errores').' y vuelve a subir el archivo.');
            }

            $codigos = $this->guardar($casos, $dependencia->id, $request->user()->id, $archivo->getClientOriginalName());
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'Alguien creó un caso en ese mismo instante y no se cargó ninguno. Sube el archivo otra vez.');
        } finally {
            $zip->close();
            array_map(fn ($ruta) => @unlink($ruta), $temporales);
        }

        return redirect()->route('admin.turnos.derivaciones.index')->with('exito', count($codigos) === 1
            ? "Se cargó 1 caso: {$codigos[0]}."
            : 'Se cargaron '.count($codigos)." casos: de {$codigos[0]} a ".end($codigos).'.');
    }

    /** @return list<string> los códigos creados, en el orden del archivo */
    protected function guardar(array $casos, int $dependenciaId, int $usuarioId, string $archivo): array
    {
        $rutas = [];

        try {
            return DB::transaction(function () use ($casos, $dependenciaId, $usuarioId, $archivo, &$rutas) {
                // Como en «Nuevo caso»: las eliminadas cuentan, el código nunca se repite.
                $numero = (int) Derivacion::withoutGlobalScopes()->where('dependencia_id', $dependenciaId)->lockForUpdate()->max('numero');
                $codigos = [];

                foreach ($casos as $fila => $d) {
                    $persona = $d['persona'];
                    $minutos = (int) $d['inicio']->diffInMinutes($d['cierre']);

                    $caso = Derivacion::create([
                        'dependencia_id' => $dependenciaId,
                        'componente_id' => $d['componente']->id,
                        'numero' => ++$numero,
                        'clave_formulario' => (string) Str::uuid(),
                        'creado_por' => $usuarioId,
                        'carga_masiva' => true,
                        'estado' => Derivacion::SOLUCIONADA,
                        'remitente' => $d['remitente'],
                        'entidad' => $d['entidad'],
                        'descripcion' => $d['descripcion'],
                        'ubicacion' => $d['ubicacion'],
                        'comuna' => $d['comuna'],
                        'fecha_requerimiento' => $d['inicio']->toDateString(),
                        'hora_requerimiento' => $d['inicio']->format('H:i'),
                        'colaborador_id' => $persona->id,
                        'cedula' => $persona->documento,
                        'nombre' => $persona->nombre,
                        'cargo' => $persona->cargo,
                        'nodo_id' => $d['nodo']->id,
                        'cumplio_requerimiento' => $d['cumplio'],
                        'observacion' => $d['observacion'],
                        'gestion_realizada' => $d['gestion_realizada'],
                        'justificacion' => $d['justificacion'],
                        'atendida_at' => $d['cierre'],
                        'hora_respuesta' => $d['cierre']->format('H:i'),
                        'tiempo_respuesta_min' => $minutos,
                    ]);

                    foreach (array_keys(self::IMAGENES) as $n => $campo) {
                        if ($d[$campo] === null) {
                            continue; // Solo el soporte puede faltar.
                        }

                        $documento = $this->documento($caso, $d[$campo], $n === 0
                            ? ["Caso {$caso->codigo()} · Soporte", $d['inicio'], "Soporte del caso {$caso->codigo()}"]
                            : ["Caso {$caso->codigo()} · Evidencia {$n} · {$caso->nombre}", $d['cierre'], "Evidencia {$n} del caso {$caso->codigo()}"]);
                        $rutas[] = $documento->versionActual->ruta;
                        $caso->{"{$campo}_documento_id"} = $documento->id;
                    }
                    $caso->save();

                    $this->auditor->registrar(
                        AccionAuditoria::CasoCreado,
                        $caso,
                        "Cargó el caso {$caso->codigo()} ya atendido (carga masiva): {$caso->nombre}, {$caso->tiempoLegible()}",
                        [
                            'origen' => 'Carga masiva', 'archivo' => $archivo, 'fila' => $fila, 'codigo' => $caso->codigo(),
                            'inicio' => $d['inicio']->toDateTimeString(), 'cierre' => $d['cierre']->toDateTimeString(),
                            'tiempo_respuesta_min' => $minutos, 'cedula' => $persona->documento,
                        ],
                    );

                    $codigos[] = $caso->codigo();
                }

                return $codigos;
            });
        } catch (Throwable $e) {
            // Los documentos se deshacen con la transacción; los archivos físicos, aquí.
            array_map(fn ($ruta) => $this->almacenamiento->eliminar($ruta), $rutas);

            throw $e;
        }
    }

    /** Un documento en «Derivaciones · <componente>», como el soporte y las evidencias de un caso normal. */
    protected function documento(Derivacion $caso, UploadedFile $imagen, array $de): Documento
    {
        [$nombre, $fecha, $comentario] = $de;

        $carpeta = Carpeta::withoutGlobalScopes()->firstOrCreate([
            'dependencia_id' => $caso->dependencia_id,
            'carpeta_id' => null,
            'nombre' => 'Derivaciones · '.$caso->componente->nombre,
        ]);

        $documento = Documento::create([
            'dependencia_id' => $caso->dependencia_id,
            'carpeta_id' => $carpeta->id,
            'nombre' => $nombre,
            'fecha_documento' => $fecha->toDateString(),
        ]);

        $this->almacenamiento->guardarVersion($documento, $imagen, $comentario.' (carga masiva)');

        return $documento->load('versionActual');
    }

    /** @return array<int, list<mixed>> número de fila en Excel => celdas, sin filas vacías */
    protected function leer(string $ruta): array
    {
        // Con las filas vacías, el número coincide con el de Excel (y con el de las imágenes).
        $opciones = new Options;
        $opciones->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $lector = new Reader($opciones);
        $lector->open($ruta);
        $filas = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $n => $fila) {
                // Solo las columnas de texto: las de imagen se leen aparte.
                $celdas = array_slice(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $fila->toArray()), 0, self::IMAGENES['soporte']);
                if ($n > 1 && array_filter($celdas, fn ($v) => $v !== null && $v !== '') !== []) {
                    $filas[$n] = $celdas;
                }
            }
            break; // Solo la primera hoja: «Casos».
        }

        $lector->close();

        return $filas;
    }

    /** La fila, ya con sus modelos y sus fechas. Lo que esté mal se anota en $problemas. */
    protected function aDatos(array $c, $componentes, int $dependenciaId, array &$problemas): array
    {
        // Excel guarda los números como 5.0: una cédula o una comuna vuelven a entero.
        $texto = fn (int $i) => match (true) {
            is_float($c[$i] ?? null) => (string) (int) $c[$i],
            ($c[$i] ?? null) instanceof DateTimeInterface => $c[$i]->format('d/m/Y H:i'),
            default => trim((string) ($c[$i] ?? '')),
        };
        $igual = fn (string $a, string $b) => Str::lower(Str::ascii($a)) === Str::lower(Str::ascii($b));

        $componente = $componentes->first(fn ($x) => $igual($x->nombre, $texto(0)));
        $nodo = $componente?->nodos->first(fn ($x) => $igual($x->nombre, $texto(8)));
        $persona = $texto(9) === '' ? null : Colaborador::porDocumento($dependenciaId, $texto(9));
        $remitente = collect(Derivacion::REMITENTES)->search(fn ($nombre) => $igual($nombre, $texto(2)));
        $cumplio = match (Str::lower(Str::ascii($texto(11)))) {
            'si' => true, 'no' => false, default => null,
        };
        $inicio = $this->fecha($c[6] ?? null);
        $cierre = $this->fecha($c[7] ?? null);

        $datos = [
            'descripcion' => $texto(1),
            'remitente' => $remitente === false ? null : $remitente,
            'remitente_otra' => $texto(3),
            'comuna' => preg_match('/^\d+/', $texto(4), $m) ? (int) $m[0] : null,
            'ubicacion' => $texto(5),
            'gestion_realizada' => $texto(10),
            'observacion' => $texto(12) ?: null,
            'justificacion' => $texto(13) ?: null,
        ];

        $validador = Validator::make($datos, [
            'descripcion' => ['required', 'string', 'max:5000'],
            'remitente_otra' => ['nullable', 'string', 'max:255'],
            'comuna' => ['required', Rule::in(array_keys(config('repositorio.comunas')))],
            'ubicacion' => ['required', 'string', 'max:500'],
            'gestion_realizada' => ['required', 'string', 'min:10', 'max:2000'],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
        ], [
            'comuna.required' => 'falta la comuna o no es un número',
            'comuna.in' => 'la comuna no está en la lista',
            'required' => 'falta :attribute',
            'max' => ':attribute no puede pasar de :max caracteres',
            'min' => ':attribute debe tener al menos :min caracteres',
        ], [
            'descripcion' => 'el mensaje para la atención', 'remitente_otra' => '«¿Cuál?»', 'ubicacion' => 'la ubicación',
            'gestion_realizada' => 'la atención o gestión realizada', 'observacion' => 'la observación', 'justificacion' => 'la justificación',
        ]);

        array_push($problemas, ...$validador->errors()->all(), ...array_filter([
            $componente === null ? "el componente «{$texto(0)}» no existe o no está activo" : null,
            $componente !== null && $nodo === null ? "el nodo «{$texto(8)}» no es de {$componente->nombre}" : null,
            $persona === null ? "la cédula «{$texto(9)}» no es de una persona de campo registrada" : null,
            $datos['remitente'] === null ? "«Quién lo envía» debe ser Alcaldía, Autoridades locales del sector u Otra (dice «{$texto(2)}»)" : null,
            $datos['remitente'] === 'otra' && $datos['remitente_otra'] === '' ? 'con «Otra» hay que escribir «¿Cuál?»' : null,
            $cumplio === null ? '«¿Se cumplió?» debe ser Sí o No' : null,
            $cumplio === false && $datos['observacion'] === null ? 'si no se cumplió, falta la observación' : null,
            $inicio === null ? 'la fecha y hora de inicio no se entiende (escríbela así: 13/11/2026 14:30)' : null,
            $cierre === null ? 'la fecha y hora de cierre no se entiende (escríbela así: 13/11/2026 14:30)' : null,
            $inicio?->isFuture() ? 'la fecha y hora de inicio es futura' : null,
            $cierre?->isFuture() ? 'la fecha y hora de cierre es futura' : null,
            $inicio && $cierre && $cierre->lte($inicio) ? 'el cierre debe ser posterior al inicio' : null,
            $inicio && $cierre && $inicio->diffInMinutes($cierre) > Derivacion::META_MINUTOS && $datos['justificacion'] === null
                ? 'el caso tardó más de '.intdiv(Derivacion::META_MINUTOS, 60).' horas: falta la justificación' : null,
        ]));

        return $datos + [
            'componente' => $componente, 'nodo' => $nodo, 'persona' => $persona, 'cumplio' => $cumplio,
            'inicio' => $inicio, 'cierre' => $cierre,
            'entidad' => $datos['remitente'] === 'otra' ? $datos['remitente_otra'] : (Derivacion::REMITENTES[$datos['remitente']] ?? ''),
        ];
    }

    /** Una celda de fecha de Excel, o el texto «13/11/2026 14:30». Al minuto. */
    protected function fecha(mixed $valor): ?Carbon
    {
        if ($valor instanceof DateTimeInterface) {
            // Excel guarda la hora como fracción del día: 14:30 puede llegar como 14:29:59.
            return Carbon::parse($valor->format('Y-m-d H:i:s'))->addSeconds(30)->startOfMinute();
        }

        // Una fecha que perdió su formato llega como el número de serie de Excel (días desde 1899-12-30).
        if ((is_float($valor) || is_int($valor)) && $valor > 40000 && $valor < 80000) {
            // En UTC: la zona de Bogotá de 1899 no era -05:00 y correría los minutos.
            return Carbon::parse(Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC')->addSeconds((int) round($valor * 86400) + 30)->format('Y-m-d H:i'));
        }

        // «13/11/2026 14:30» o «2026-11-13 14:30», con o sin segundos.
        if (is_string($valor) && (preg_match('#^(?<d>\d{1,2})/(?<m>\d{1,2})/(?<a>\d{4})\s+(?<h>\d{1,2}):(?<i>\d{2})(:\d{2})?$#', $valor, $p)
            || preg_match('#^(?<a>\d{4})-(?<m>\d{1,2})-(?<d>\d{1,2})[ T]+(?<h>\d{1,2}):(?<i>\d{2})(:\d{2})?$#', $valor, $p))
            && checkdate((int) $p['m'], (int) $p['d'], (int) $p['a']) && $p['h'] < 24 && $p['i'] < 60) {
            return Carbon::create((int) $p['a'], (int) $p['m'], (int) $p['d'], (int) $p['h'], (int) $p['i']);
        }

        return null;
    }

    /** La imagen de una celda, lista para guardarse como documento. El soporte puede faltar. */
    protected function imagen(ZipArchive $zip, ?string $entrada, string $campo, array &$problemas, array &$temporales): ?UploadedFile
    {
        $nombre = $campo === 'soporte' ? 'el soporte' : 'la evidencia '.substr($campo, -1);

        if ($entrada === null) {
            if ($campo !== 'soporte') {
                $problemas[] = "falta la imagen de {$nombre}";
            }

            return null;
        }

        if (($zip->statName($entrada)['size'] ?? PHP_INT_MAX) > config('repositorio.tamano_maximo_kb') * 1024) {
            $problemas[] = "la imagen de {$nombre} pesa más de ".round(config('repositorio.tamano_maximo_kb') / 1024).' MB';

            return null;
        }

        $temporales[] = $ruta = tempnam(sys_get_temp_dir(), 'caso');
        file_put_contents($ruta, $zip->getFromName($entrada));
        // El tipo sale del contenido, no del nombre que trae dentro del Excel.
        $extension = self::EXTENSIONES[mime_content_type($ruta) ?: ''] ?? null;

        if ($extension === null) {
            $problemas[] = "la imagen de {$nombre} no es JPG, PNG ni WEBP";

            return null;
        }

        return new UploadedFile($ruta, "{$campo}.{$extension}", null, null, true);
    }

    /** «la fila 4» o «DC-012» si ya hay un caso igual; null si no. */
    protected function repetido(array $datos, int $dependenciaId, array $vistos): ?string
    {
        $huella = $this->huella($datos['entidad'], $datos['ubicacion'], $datos['inicio']);

        if (isset($vistos[$huella])) {
            return $vistos[$huella];
        }

        // ponytail: se compara en PHP (pocos casos a esa misma hora), como en «Nuevo caso».
        return Derivacion::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('dependencia_id', $dependenciaId)
            // DOC-25: solo casos del componente de la fila, para no delatar códigos de otro.
            ->where('componente_id', $datos['componente']->id)
            ->whereNotNull('numero')
            ->whereDate('fecha_requerimiento', $datos['inicio']->toDateString())
            ->get()
            ->first(fn (Derivacion $d) => $this->huella($d->entidad, $d->ubicacion, $d->requeridaAt()) === $huella)
            ?->codigo();
    }

    protected function huella(string $entidad, string $ubicacion, Carbon $inicio): string
    {
        return Str::lower(Str::squish($entidad)).'|'.Str::lower(Str::squish($ubicacion)).'|'.$inicio->format('Y-m-d H:i');
    }
}
