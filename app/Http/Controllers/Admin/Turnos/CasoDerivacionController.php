<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Carpeta;
use App\Models\Componente;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * DOC-20: la oficina crea el caso de derivación (DC-NNN) y desde ahí corre
 * el tiempo, con la hora del servidor. Rutas: create, store.
 */
class CasoDerivacionController extends Controller
{
    /** Intentos ante un choque del índice único (dos casos a la vez). */
    protected const INTENTOS = 3;

    public function __construct(
        protected ContextoDependencia $contexto,
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {
    }

    public function create(): View
    {
        $this->authorize('create', Derivacion::class);

        return view('admin.turnos.derivaciones.create', [
            'componentes' => $this->componentes(),
            'clave' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $peticion): RedirectResponse
    {
        $this->authorize('create', Derivacion::class);
        $dependencia = $this->contexto->requerida();

        $datos = $peticion->validate([
            'clave_formulario' => ['required', 'uuid'],
            'componente_id' => ['required', Rule::exists('componentes', 'id')->where('dependencia_id', $dependencia->id)->where('activo', 1)],
            'descripcion' => ['required', 'string', 'max:5000'],
            'remitente' => ['required', Rule::in(array_keys(Derivacion::REMITENTES))],
            'remitente_otra' => ['nullable', 'required_if:remitente,otra', 'string', 'max:255'],
            'ubicacion' => ['required', 'string', 'max:500'],
            'soporte' => ['required', 'file', 'mimes:'.implode(',', config('repositorio.formatos.publico.extensiones')), 'max:'.config('repositorio.tamano_maximo_kb')],
            'confirmar_duplicado' => ['nullable', 'boolean'],
        ], [
            'remitente_otra.required_if' => 'Escribe quién envía el caso.',
            'soporte.required' => 'Falta el soporte del caso: toma una foto o elige una imagen o un PDF.',
        ], [
            'descripcion' => 'mensaje para la atención',
            'remitente' => 'quién lo envía',
            'remitente_otra' => '¿cuál?',
            'ubicacion' => 'ubicación',
            'soporte' => 'soporte del caso',
            'componente_id' => 'componente',
        ]);

        $entidad = $datos['remitente'] === 'otra'
            ? trim($datos['remitente_otra'])
            : Derivacion::REMITENTES[$datos['remitente']];

        for ($intento = 1; ; $intento++) {
            $rutas = [];

            try {
                $caso = DB::transaction(function () use ($dependencia, $datos, $entidad, $peticion, &$rutas) {
                    // Doble toque: la misma clave devuelve el caso ya creado.
                    $existente = Derivacion::withoutGlobalScopes()->where('clave_formulario', $datos['clave_formulario'])->first();
                    if ($existente !== null) {
                        return $existente;
                    }

                    if (! $peticion->boolean('confirmar_duplicado')) {
                        $this->avisarDuplicado($dependencia->id, $datos['remitente'], $entidad, $datos['ubicacion']);
                    }

                    // withoutGlobalScopes también quita SoftDeletes: las eliminadas cuentan.
                    $numero = (int) Derivacion::withoutGlobalScopes()
                        ->where('dependencia_id', $dependencia->id)
                        ->lockForUpdate()
                        ->max('numero') + 1;

                    $ahora = now();
                    $caso = Derivacion::create([
                        'dependencia_id' => $dependencia->id,
                        'componente_id' => $datos['componente_id'],
                        'numero' => $numero,
                        'clave_formulario' => $datos['clave_formulario'],
                        'creado_por' => $peticion->user()->id,
                        'estado' => Derivacion::PENDIENTE,
                        'remitente' => $datos['remitente'],
                        'entidad' => $entidad,
                        'descripcion' => $datos['descripcion'],
                        'ubicacion' => $datos['ubicacion'],
                        'fecha_requerimiento' => $ahora->toDateString(),
                        'hora_requerimiento' => $ahora->format('H:i'),
                    ]);

                    $documento = $this->guardarSoporte($caso, $peticion->file('soporte'));
                    $rutas[] = $documento->versionActual->ruta;
                    $caso->update(['soporte_documento_id' => $documento->id]);

                    $this->auditor->registrar(
                        AccionAuditoria::CasoCreado,
                        $caso,
                        "Creó el caso {$caso->codigo()} ({$caso->entidad})",
                        ['codigo' => $caso->codigo(), 'ubicacion' => $caso->ubicacion],
                    );

                    return $caso;
                });

                break;
            } catch (UniqueConstraintViolationException $e) {
                $this->borrar($rutas);
                if ($intento >= self::INTENTOS) {
                    throw $e;
                }
            } catch (Throwable $e) {
                $this->borrar($rutas);
                throw $e;
            }
        }

        return redirect()->route('admin.turnos.derivaciones.create')->with('caso.creado', [
            'codigo' => $caso->codigo(),
            'hora' => $caso->created_at->hora(),
            'limite' => $caso->created_at->copy()->addMinutes(Derivacion::META_MINUTOS)->hora(),
            'ubicacion' => $caso->ubicacion,
        ]);
    }

    /** La primera vez, un caso abierto igual y reciente detiene el guardado. */
    protected function avisarDuplicado(int $dependenciaId, string $remitente, string $entidad, string $ubicacion): void
    {
        $normal = fn (?string $texto) => Str::lower(Str::squish((string) $texto));

        // ponytail: se compara en PHP (pocos casos en 30 min) para no depender de funciones de MySQL/SQLite.
        $igual = Derivacion::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('dependencia_id', $dependenciaId)
            ->whereNotNull('numero')
            ->where('estado', Derivacion::PENDIENTE)
            ->where('remitente', $remitente)
            ->where('created_at', '>=', now()->subMinutes(Derivacion::VENTANA_DUPLICADO_MIN))
            ->get()
            ->first(fn (Derivacion $d) => $normal($d->ubicacion) === $normal($ubicacion) && $normal($d->entidad) === $normal($entidad));

        if ($igual !== null) {
            throw ValidationException::withMessages([
                'duplicado' => "Ya hay un caso abierto con la misma ubicación y el mismo remitente: {$igual->codigo()} (creado a las {$igual->created_at->hora()}). Si es otro caso, marca «Crear de todas formas» y vuelve a elegir el soporte.",
            ]);
        }
    }

    /** El soporte es un documento en «Derivaciones · <componente>», como en el enlace público. */
    protected function guardarSoporte(Derivacion $caso, UploadedFile $archivo): Documento
    {
        $carpeta = Carpeta::withoutGlobalScopes()->firstOrCreate([
            'dependencia_id' => $caso->dependencia_id,
            'carpeta_id' => null,
            'nombre' => 'Derivaciones · '.$caso->componente->nombre,
        ]);

        $documento = Documento::create([
            'dependencia_id' => $caso->dependencia_id,
            'carpeta_id' => $carpeta->id,
            'nombre' => "Caso {$caso->codigo()} · Soporte",
            'fecha_documento' => $caso->fecha_requerimiento,
        ]);

        $this->almacenamiento->guardarVersion($documento, $archivo, "Soporte del caso {$caso->codigo()}");

        return $documento->load('versionActual');
    }

    protected function borrar(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            $this->almacenamiento->eliminar($ruta);
        }
    }

    protected function componentes()
    {
        return Componente::where('dependencia_id', $this->contexto->requerida()->id)->activos()->orderBy('nombre')->get();
    }
}
