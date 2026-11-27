<?php

namespace App\Http\Controllers\Admin\SeguridadCctv;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarCasoCctvRequest;
use App\Models\CasoCctv;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\Nodo;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** DOC-33: registrar (CCTV-NNN) y corregir un caso. La ficha, cerrar y anular son de FichaController. */
class CasoController extends Controller
{
    /** Intentos ante un choque del índice único (dos casos a la vez). */
    protected const INTENTOS = 3;

    public function __construct(
        protected ContextoDependencia $contexto,
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {}

    public function create(): View
    {
        $this->authorize('create', CasoCctv::class);

        return view('admin.seguridad-cctv.create', ['clave' => (string) Str::uuid()] + $this->listas());
    }

    /** El nombre de la persona de campo activa del componente con esa cédula; nada más. */
    public function persona(Request $peticion): JsonResponse
    {
        $this->authorize('create', CasoCctv::class);

        $nombre = Colaborador::query()
            ->where('componente_id', $this->contexto->componenteId())
            ->where('documento', User::normalizarDocumento((string) $peticion->input('cedula')))
            ->where('activo', true)
            ->value('nombre');

        return response()->json(['nombre' => $nombre]);
    }

    public function store(GuardarCasoCctvRequest $peticion): RedirectResponse
    {
        $datos = $this->datos($peticion);
        $componente = $this->contexto->componente();
        abort_if($componente === null, 403, 'Elige un componente para registrar el caso.');

        for ($intento = 1; ; $intento++) {
            $rutas = [];

            try {
                $caso = DB::transaction(function () use ($datos, $componente, $peticion, &$rutas): CasoCctv {
                    // Doble toque: la misma clave devuelve el caso ya creado.
                    $existente = CasoCctv::query()->where('clave_formulario', $datos['clave_formulario'])->first();
                    if ($existente !== null) {
                        return $existente;
                    }

                    // Sin scopes: el consecutivo es por dependencia, no por componente.
                    $numero = (int) CasoCctv::withoutGlobalScopes()
                        ->where('dependencia_id', $componente->dependencia_id)
                        ->lockForUpdate()
                        ->max('numero') + 1;

                    $caso = CasoCctv::create([
                        'dependencia_id' => $componente->dependencia_id,
                        'componente_id' => $componente->id,
                        'numero' => $numero,
                        'estado' => CasoCctv::ABIERTO,
                        'creado_por' => $peticion->user()->id,
                        'actualizado_por' => $peticion->user()->id,
                    ] + collect($datos)->except('soporte')->all());

                    if ($peticion->hasFile('soporte')) {
                        $documento = $this->guardarSoporte($caso, $peticion->file('soporte'));
                        $rutas[] = $documento->versionActual->ruta;
                        $caso->update(['soporte_documento_id' => $documento->id]);
                    }

                    $this->auditor->registrar(
                        AccionAuditoria::CasoCctvRegistrado,
                        $caso,
                        "Registró el caso {$caso->codigo()} ({$caso->entidadLegible()})",
                        ['codigo' => $caso->codigo(), 'ubicacion' => $caso->ubicacion, 'comuna' => $caso->comuna],
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

        return redirect()->route('admin.turnos.cctv.create')->with('caso.creado', [
            'id' => $caso->id,
            'codigo' => $caso->codigo(),
            'ubicacion' => $caso->ubicacion,
        ]);
    }

    public function edit(CasoCctv $caso): View
    {
        $this->authorize('update', $caso);

        return view('admin.seguridad-cctv.edit', ['caso' => $caso] + $this->listas($caso));
    }

    public function update(GuardarCasoCctvRequest $peticion, CasoCctv $caso): RedirectResponse
    {
        $datos = $this->datos($peticion);
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        unset($datos['motivo']);

        // Los segundos no cuentan: una hora igual hasta el minuto no es un cambio.
        $horaCambiada = ! Carbon::parse($datos['requerido_at'])->equalTo($caso->requerido_at->copy()->startOfMinute());
        if ($horaCambiada && $motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo del cambio de la fecha y hora del requerimiento: queda en la auditoría.']);
        }
        if (! $horaCambiada) {
            unset($datos['requerido_at']);
        }

        $caso->fill($datos);
        $cambios = collect($caso->getDirty())->map(fn (mixed $nuevo, string $campo): array => [
            'antes' => $caso->getOriginal($campo),
            'despues' => $nuevo,
        ])->all();

        DB::transaction(function () use ($caso, $cambios, $motivo, $horaCambiada, $peticion): void {
            if ($horaCambiada && $caso->atendido_at !== null) {
                $caso->tiempo_respuesta_min = CasoCctv::minutosEntre($caso->requerido_at, $caso->atendido_at);
            }
            $caso->actualizado_por = $peticion->user()->id;
            $caso->save();

            $this->auditor->registrar(
                AccionAuditoria::CasoCctvCorregido,
                $caso,
                "Corrigió el caso {$caso->codigo()}",
                ['cambios' => $cambios, 'motivo' => $motivo !== '' ? $motivo : null],
            );
        });

        return redirect()->route('admin.turnos.cctv.show', $caso)->with('status', "Caso {$caso->codigo()} corregido.");
    }

    /**
     * Lo validado, con la cédula ya resuelta a la persona asignada y «Otras» limpio.
     *
     * @return array<string, mixed>
     */
    protected function datos(GuardarCasoCctvRequest $peticion): array
    {
        $datos = $peticion->validated();
        unset($datos['cedula_asignado']);

        $datos['colaborador_asignado_id'] = $peticion->colaboradorAsignadoId();
        $datos['entidad_otra'] = $datos['entidad'] === 'otras' ? trim((string) $datos['entidad_otra']) : null;

        return $datos;
    }

    /**
     * Los nodos del formulario, del componente donde está parado el usuario (los
     * scopes lo aíslan). Al corregir se conserva el nodo ya elegido.
     *
     * @return array<string, mixed>
     */
    protected function listas(?CasoCctv $caso = null): array
    {
        $actual = $caso?->nodo_id;

        return [
            'nodos' => Nodo::query()
                ->where(fn (Builder $q) => $q->where('activo', true)->when($actual, fn (Builder $q) => $q->orWhere('id', $actual)))
                ->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    /** El soporte es un documento en «Seguridad CCTV · <componente>», con la custodia de los responsables. */
    protected function guardarSoporte(CasoCctv $caso, UploadedFile $archivo): Documento
    {
        $carpeta = CasoCctv::carpetaPara($caso->componente);

        $documento = Documento::create([
            'dependencia_id' => $caso->dependencia_id,
            'carpeta_id' => $carpeta->id,
            'nombre' => "Caso {$caso->codigo()} · Soporte",
            'fecha_documento' => $caso->requerido_at->toDateString(),
        ]);

        $this->almacenamiento->guardarVersion($documento, $archivo, "Soporte del caso {$caso->codigo()}");

        return $documento->load('versionActual');
    }

    /** @param  list<string>  $rutas */
    protected function borrar(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            $this->almacenamiento->eliminar($ruta);
        }
    }
}
