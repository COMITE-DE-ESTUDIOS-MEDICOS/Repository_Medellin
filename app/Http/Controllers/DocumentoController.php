<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Http\Requests\GuardarDocumentoRequest;
use App\Models\Carpeta;
use App\Models\Documento;
use App\Models\Etiqueta;
use App\Models\TipoDocumento;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\CargaPorTandas;
use App\Services\ContextoDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentoController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {
    }

    /** Explorador de archivos: carpetas y documentos del nivel actual. */
    public function index(Request $request): View
    {
        $this->contexto->requerida();
        $usuario = $request->user();

        $carpetaActual = $request->filled('carpeta')
            ? Carpeta::where('uuid', $request->string('carpeta'))->firstOrFail()
            : null;

        if ($carpetaActual !== null) {
            $this->authorize('view', $carpetaActual);
        }

        $busqueda = $request->string('q')->toString() ?: null;

        $carpetas = Carpeta::query()
            ->visiblesPara($usuario)
            ->when($busqueda === null,
                fn ($q) => $q->where('carpeta_id', $carpetaActual?->id),
                fn ($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'),
            )
            // El contador respeta lo que ese usuario puede ver: sin esto la
            // tarjeta prometía cinco documentos y dentro solo había dos, los
            // activos. Los inactivos los cuenta solo quien puede abrirlos.
            ->withCount(['documentos' => fn ($q) => $q->visiblesPara($usuario)])
            ->orderBy('nombre')
            ->get();

        $documentos = Documento::query()
            ->visiblesPara($usuario)
            ->when($busqueda === null, fn ($q) => $q->where('carpeta_id', $carpetaActual?->id))
            ->buscar($busqueda)
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_documento_id', $request->integer('tipo')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_documento', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_documento', '<=', $request->date('hasta')))
            ->with(['versionActual', 'tipoDocumento', 'etiquetas', 'creador', 'recepcion'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('documentos.index', [
            'carpetaActual' => $carpetaActual,
            'migas' => $carpetaActual?->ruta() ?? collect(),
            'carpetas' => $carpetas,
            'documentos' => $documentos,
            'busqueda' => $busqueda,
            'tipos' => $this->tiposDisponibles(),
            // A dónde se puede copiar o mover lo marcado (solo quien edita).
            'destinos' => $this->contexto->puedeEditar() ? Carpeta::destinosLegibles() : [],
            // Qué carpetas están bajo custodia, para el candado.
            'custodiadas' => Carpeta::custodiaEfectiva($this->contexto->id()),
        ]);
    }

    public function create(Request $request): View
    {
        $this->autorizarEdicion();

        return view('documentos.create', [
            'carpetaActual' => $request->filled('carpeta')
                ? Carpeta::where('uuid', $request->string('carpeta'))->first()
                : null,
            'carpetas' => Carpeta::visiblesPara(auth()->user())->activas()->orderBy('nombre')->get(),
            'tipos' => $this->tiposDisponibles(),
        ]);
    }

    /**
     * Cada archivo se convierte en un documento propio, con su versión 1.
     * Los metadatos del formulario —carpeta, tipo, fecha, etiquetas— son
     * comunes a todos: es lo que hace útil subir un lote de actas de una vez.
     */
    public function store(GuardarDocumentoRequest $request): RedirectResponse|JsonResponse
    {
        $this->autorizarEdicion();
        $dependencia = $this->contexto->requerida();

        $archivos = $request->archivos();

        // Si viene de una carga grande partida en tandas (carga-tandas.js),
        // esto la recuerda entre tanda y tanda.
        $tanda = CargaPorTandas::desde($request, 'usuario:'.$request->user()->id);
        $hashes = [];

        // Todo o nada: si el quinto archivo falla, no quedan cuatro
        // documentos a medias sin que nadie se entere.
        $documentos = DB::transaction(function () use ($request, $dependencia, $archivos, $tanda, &$hashes) {
            $etiquetas = Etiqueta::resolverDesdeTexto($request->input('etiquetas'), $dependencia->id);
            $creados = [];

            foreach ($archivos as $indice => $archivo) {
                // Reintentar una tanda que sí había llegado no la duplica.
                $hash = hash_file('sha256', $archivo->getRealPath());

                if ($tanda && ($tanda->yaRecibido($hash) || in_array($hash, $hashes, true))) {
                    continue;
                }

                $hashes[] = $hash;

                $documento = Documento::create([
                    'dependencia_id' => $dependencia->id,
                    'carpeta_id' => $request->input('carpeta_id'),
                    'tipo_documento_id' => $request->input('tipo_documento_id'),
                    'nombre' => $this->nombrePara($request, $archivo, $indice, count($archivos)),
                    'descripcion' => $request->input('descripcion'),
                    'fecha_documento' => $request->input('fecha_documento'),
                    'creado_por' => $request->user()->id,
                    'actualizado_por' => $request->user()->id,
                ]);

                $documento->etiquetas()->sync($etiquetas);

                $this->almacenamiento->guardarVersion(
                    $documento,
                    $archivo,
                    $request->input('comentario_version') ?: 'Versión inicial',
                );

                $creados[] = $documento;
            }

            return $creados;
        });

        // Una entrada por documento: la auditoría sigue documentos, no cargas.
        // Con el origen y la carpeta, para cuando alguien dice que no subió
        // algo, o que lo subió en otro lado.
        $carpeta = $documentos ? ($documentos[0]->carpeta?->rutaTexto() ?? 'Raíz') : null;

        foreach ($documentos as $documento) {
            $this->auditor->registrar(
                AccionAuditoria::DocumentoCreado,
                $documento,
                "Cargó «{$documento->nombre}»",
                ['origen' => 'Aplicación', 'carpeta' => $carpeta],
            );
        }

        if ($tanda) {
            return $this->respuestaDeTanda($request, $tanda, $hashes, $documentos);
        }

        if (count($documentos) === 1) {
            return redirect()
                ->route('documentos.show', $documentos[0])
                ->with('exito', 'Documento cargado.');
        }

        return redirect()
            ->route('documentos.index', ['carpeta' => $documentos[0]->carpeta?->uuid])
            ->with('exito', count($documentos).' documentos cargados.');
    }

    /**
     * Lo que se contesta a cada tanda de una carga grande. La última deja el
     * aviso para la pantalla a la que se vuelve y dice cuál es.
     *
     * @param  list<string>  $hashes
     * @param  list<Documento>  $documentos
     */
    protected function respuestaDeTanda(Request $request, CargaPorTandas $tanda, array $hashes, array $documentos): JsonResponse
    {
        $tanda->anotar($hashes, array_map(fn (Documento $d) => $d->nombre, $documentos));

        if (! $tanda->esUltima()) {
            return response()->json(['cargados' => count($documentos)]);
        }

        $total = count($tanda->nombres());
        $request->session()->flash('exito', $total === 1 ? 'Documento cargado.' : "{$total} documentos cargados.");

        $carpeta = Carpeta::find($request->input('carpeta_id'));

        return response()->json([
            'cargados' => count($documentos),
            'total' => $total,
            'redirect' => route('documentos.index', ['carpeta' => $carpeta?->uuid]),
        ]);
    }

    /**
     * El nombre de cada documento, por orden de preferencia:
     *
     *   1. Lo escrito en la tarjeta de ese archivo (nombres[i]).
     *   2. El campo suelto 'nombre', si la carga es de un solo archivo.
     *      Es el respaldo de quien no tenga JavaScript, que no ve tarjetas.
     *   3. El nombre del archivo, sin extensión.
     */
    protected function nombrePara(GuardarDocumentoRequest $request, $archivo, int $indice, int $total): string
    {
        $deLaTarjeta = trim((string) $request->input('nombres.'.$indice, ''));

        if ($deLaTarjeta !== '') {
            return Str::squish($deLaTarjeta);
        }

        $suelto = $request->string('nombre')->squish()->toString();

        if ($total === 1 && $suelto !== '') {
            return $suelto;
        }

        $delArchivo = trim(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME));

        return $delArchivo ?: 'Documento sin nombre';
    }

    public function show(Documento $documento): View
    {
        $this->authorize('view', $documento);

        // Abrir un documento custodiado queda en la auditoría: con lo
        // custodiado importa también quién lo miró, no solo quién lo descargó.
        if ($documento->carpeta_id && isset(Carpeta::custodiaEfectiva($documento->dependencia_id)[$documento->carpeta_id])) {
            $this->auditor->registrar(AccionAuditoria::DocumentoAbierto, $documento, "Abrió «{$documento->nombre}»");
        }

        $documento->load([
            'versiones.autor', 'etiquetas', 'tipoDocumento', 'carpeta', 'creador', 'inactivador',
            'recepcion.enlace', 'comentarios.autor',
        ]);

        return view('documentos.show', [
            'documento' => $documento,
            'vecinos' => $this->vecinos($documento),
            'migas' => $documento->carpeta?->ruta() ?? collect(),
            'carpetas' => Carpeta::visiblesPara(auth()->user())->activas()->orderBy('nombre')->get(),
        ]);
    }

    /**
     * El anterior y el siguiente de la misma carpeta, en el orden del
     * explorador (lo más nuevo primero), para pasar de un archivo a otro con
     * las flechas sin volver a la lista.
     *
     * ponytail: trae los uuid de toda la carpeta; con carpetas de decenas de
     * miles de archivos, buscar solo el vecino de cada lado con dos consultas.
     *
     * @return array{anterior: ?string, siguiente: ?string, posicion: int, total: int}
     */
    protected function vecinos(Documento $documento): array
    {
        $orden = Documento::query()
            ->visiblesPara(auth()->user())
            ->where('carpeta_id', $documento->carpeta_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('uuid')
            ->values();

        $i = $orden->search($documento->uuid);

        return [
            'anterior' => $i === false ? null : $orden->get($i - 1),
            'siguiente' => $i === false ? null : $orden->get($i + 1),
            'posicion' => $i === false ? 0 : $i + 1,
            'total' => $orden->count(),
        ];
    }

    /**
     * Copia el documento a otra carpeta: documento nuevo con su propio
     * archivo y sus mismos datos. El original no se toca.
     */
    public function copiar(Request $request, Documento $documento): RedirectResponse
    {
        // Copiar es crear contenido: lo mismo que editar.
        $this->authorize('update', $documento);

        $datos = $request->validate([
            'carpeta_id' => [
                'nullable',
                Rule::exists('carpetas', 'id')
                    ->where('dependencia_id', $documento->dependencia_id)
                    ->where('activa', true),
                new \App\Rules\CarpetaAccesible,
            ],
        ]);

        $copia = DB::transaction(
            fn () => $this->almacenamiento->copiarDocumento($documento, $datos['carpeta_id'] ?? null)
        );
        abort_if($copia === null, 404, 'El documento no tiene archivo.');

        $destino = $copia->carpeta?->nombre ?? 'Raíz';

        $this->auditor->registrar(
            AccionAuditoria::DocumentoCopiado,
            $copia,
            "Copió «{$documento->nombre}» a la carpeta «{$destino}»",
            ['origen' => $documento->uuid],
        );

        return redirect()
            ->route('documentos.show', $copia)
            ->with('exito', "Copiado a «{$destino}».");
    }

    public function edit(Documento $documento): View
    {
        $this->authorize('update', $documento);

        return view('documentos.edit', [
            'documento' => $documento->load('etiquetas'),
            'carpetas' => Carpeta::visiblesPara(auth()->user())->activas()->orderBy('nombre')->get(),
            'tipos' => $this->tiposDisponibles(),
        ]);
    }

    public function update(GuardarDocumentoRequest $request, Documento $documento): RedirectResponse
    {
        $this->authorize('update', $documento);

        $documento->update([
            'carpeta_id' => $request->input('carpeta_id'),
            'tipo_documento_id' => $request->input('tipo_documento_id'),
            'nombre' => $request->string('nombre')->squish(),
            'descripcion' => $request->input('descripcion'),
            'fecha_documento' => $request->input('fecha_documento'),
            'actualizado_por' => $request->user()->id,
        ]);

        $documento->etiquetas()->sync(
            Etiqueta::resolverDesdeTexto($request->input('etiquetas'), $documento->dependencia_id)
        );

        $this->auditor->registrar(
            AccionAuditoria::DocumentoActualizado,
            $documento,
            "Actualizó los datos de «{$documento->nombre}»",
            ['cambios' => array_keys($documento->getChanges())],
        );

        return redirect()
            ->route('documentos.show', $documento)
            ->with('exito', 'Documento actualizado.');
    }

    protected function autorizarEdicion(): void
    {
        abort_unless($this->contexto->puedeEditar(), 403, 'No tienes permisos de edición en esta dependencia.');
    }

    protected function tiposDisponibles()
    {
        return TipoDocumento::activos()
            ->disponiblesPara($this->contexto->id())
            ->orderBy('nombre')
            ->get();
    }
}
