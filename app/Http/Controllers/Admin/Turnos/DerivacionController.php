<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\Nodo;
use App\Notifications\DerivacionPendiente;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * DERIVACIONES Y RESPUESTA TERRITORIAL: lo que llega por el enlace por
 * cédula entra pendiente; aquí los líderes lo responden (y se detiene el
 * tiempo), lo corrigen o lo borran, y sale el reporte (Excel y ZIP con las
 * fotos). Crear es del formulario público. Rutas REST: index, show, edit,
 * update, destroy; y responder.
 */
class DerivacionController extends Controller
{
    /** Los encabezados del reporte, tal cual la hoja de cálculo de siempre. */
    public const ENCABEZADOS = [
        'Marca temporal', 'CC DE QUIEN ESCALA LA NOVEDAD', 'NOMBRE DE QUIEN ESCALA LA NOVEDAD',
        'CARGO DE QUIEN ESCALA LA NOVEDAD', 'NODO QUE ATIENDE EL REQUERIEMIENTO', 'FECHA DEL REQUERIMIENTO',
        'HORA DEL REQUERIMIENTO', 'ENTIDAD QUE SOLICITA EL REQUERIMIENTO', 'SOPORTE DEL REQUERIMIENTO',
        'DESCRIPCION DEL CASO', 'UBICACION DEL REQUERIMIENTO', 'EVIDENCIAS # 1', 'EVIDENCIAS # 2',
        'EVIDENCIAS # 3', 'HORA DE RESPUESTA', 'TIEMPO DE RESPUESTA', 'JUSTIFICACION EN CASO DE NO CUMPLIR',
        'ESTADO', 'RESPUESTA', 'RESPONDIDA POR', 'FECHA Y HORA DE RESPUESTA',
    ];

    /** Cuánto dura el enlace a un PDF de soporte dentro del Excel. */
    public const DIAS_ENLACE = 30;

    public function __construct(
        protected Auditor $auditor,
        protected AlmacenamientoDocumentos $almacenamiento,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Derivacion::class);

        return view('admin.turnos.derivaciones.index', [
            'derivaciones' => $this->consulta($request)->with('respondedor')->paginate(config('repositorio.por_pagina'))->withQueryString(),
            'nodos' => Nodo::whereHas('componente')->with('componente')->orderBy('nombre')->get(),
            'traza' => $this->trazabilidad($request),
        ]);
    }

    /**
     * El seguimiento del filtro: cuántas hay pendientes (y vencidas), cuántas
     * se respondieron a tiempo, qué nodos tienen casos sin respuesta y quién
     * ha respondido.
     */
    protected function trazabilidad(Request $request): array
    {
        $base = fn () => $this->consulta($request)->reorder()->setEagerLoads([]);
        $meta = Derivacion::META_MINUTOS;

        $pendientes = $base()->where('estado', Derivacion::PENDIENTE)->with('nodo')->get();
        $solucionadas = $base()->where('estado', Derivacion::SOLUCIONADA)->toBase()
            ->selectRaw('count(*) as total, sum(case when tiempo_respuesta_min <= ? then 1 else 0 end) as a_tiempo, avg(tiempo_respuesta_min) as promedio', [$meta])
            ->first();

        return [
            'pendientes' => $pendientes->count(),
            'vencidas' => $pendientes->reject->cumplio()->count(),
            'solucionadas' => (int) $solucionadas->total,
            'a_tiempo' => (int) $solucionadas->a_tiempo,
            'promedio' => $solucionadas->promedio !== null ? Derivacion::duracion((int) round($solucionadas->promedio)) : null,
            // Quién no: los nodos con casos sin respuesta, el más atrasado primero.
            'sin_respuesta' => $pendientes->groupBy(fn ($d) => $d->nodo?->nombre ?? 'Sin nodo')
                ->map(fn ($casos) => ['casos' => $casos->count(), 'vencidas' => $casos->reject->cumplio()->count(), 'mas_antigua' => $casos->max->minutos()])
                ->sortByDesc('mas_antigua'),
            // Quién sí: cada líder que ha respondido, con su cumplimiento.
            'respondedores' => $base()->where('estado', Derivacion::SOLUCIONADA)->whereNotNull('respondida_por')
                ->join('users', 'users.id', '=', 'derivaciones.respondida_por')->toBase()
                ->selectRaw('users.name as nombre, count(*) as total, sum(case when tiempo_respuesta_min <= ? then 1 else 0 end) as a_tiempo, avg(tiempo_respuesta_min) as promedio', [$meta])
                ->groupBy('users.id', 'users.name')->orderByDesc('total')->get(),
        ];
    }

    public function show(Derivacion $derivacion): View
    {
        $this->authorize('view', $derivacion);

        return view('admin.turnos.derivaciones.show', [
            'derivacion' => $derivacion->load(['nodo', 'componente', 'respondedor', 'soporte', 'evidencia1', 'evidencia2', 'evidencia3']),
        ]);
    }

    public function edit(Derivacion $derivacion): View
    {
        $this->authorize('update', $derivacion);

        return view('admin.turnos.derivaciones.edit', [
            'derivacion' => $derivacion,
            'nodos' => $derivacion->componente->nodos()->activos()->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('update', $derivacion);

        // La hora de respuesta solo se corrige en una ya solucionada: la de una
        // pendiente la pone «Responder».
        $solucionada = ! $derivacion->estaPendiente();
        $datos = $request->validate(Derivacion::reglas($derivacion->componente_id)
            + ($solucionada ? Derivacion::REGLAS_HORA_RESPUESTA + ['respuesta' => ['nullable', 'string', 'max:5000']] : []),
            [], Derivacion::ATRIBUTOS);

        $derivacion->fill($datos + ['actualizado_por' => $request->user()->id]
            + ($solucionada ? ['tiempo_respuesta_min' => Derivacion::tiempoValidado($datos)] : []));
        $cambios = array_keys($derivacion->getDirty());
        $derivacion->save();

        $this->auditor->registrar(
            AccionAuditoria::DerivacionActualizada,
            $derivacion,
            "Corrigió la derivación #{$derivacion->id}",
            ['cambios' => array_values(array_diff($cambios, ['actualizado_por', 'updated_at']))],
        );

        return redirect()->route('admin.turnos.derivaciones.show', $derivacion)->with('exito', 'Derivación corregida.');
    }

    /**
     * El líder responde: queda solucionada y el tiempo se detiene ahora. Si la
     * respuesta real fue antes, la hora se ajusta después en «Corregir»
     * (update), desde Edición.
     */
    public function responder(Request $request, Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('update', $derivacion);
        abort_unless($derivacion->estaPendiente(), 422, 'Esta derivación ya fue respondida.');

        $datos = $request->validate([
            'respuesta' => ['required', 'string', 'max:5000'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
        ], [], Derivacion::ATRIBUTOS);

        $minutos = $derivacion->minutos();

        if ($minutos > Derivacion::META_MINUTOS && blank($datos['justificacion'] ?? null)) {
            return back()->withInput()->withErrors([
                'justificacion' => 'Pasaron más de '.intdiv(Derivacion::META_MINUTOS, 60).' horas: escribe la justificación.',
            ]);
        }

        $derivacion->update([
            'estado' => Derivacion::SOLUCIONADA,
            'respuesta' => $datos['respuesta'],
            'justificacion' => $datos['justificacion'] ?? null,
            'hora_respuesta' => now()->format('H:i'),
            'tiempo_respuesta_min' => $minutos,
            'respondida_at' => now(),
            'respondida_por' => $request->user()->id,
        ]);

        DerivacionPendiente::retirar($derivacion);

        $this->auditor->registrar(
            AccionAuditoria::DerivacionRespondida,
            $derivacion,
            "Respondió la derivación #{$derivacion->id} en {$derivacion->tiempoLegible()}",
        );

        return redirect()->route('admin.turnos.derivaciones.show', $derivacion)
            ->with('exito', "Derivación solucionada en {$derivacion->tiempoLegible()}.");
    }

    /** Se retira del reporte; sus archivos siguen en el repositorio. */
    public function destroy(Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('delete', $derivacion);

        $derivacion->delete();

        $this->auditor->registrar(AccionAuditoria::DerivacionEliminada, $derivacion, "Eliminó la derivación #{$derivacion->id}");

        return redirect()->route('admin.turnos.derivaciones.index')->with('exito', 'Derivación eliminada.');
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Derivacion::class);

        return response()
            ->download($this->excel($request, tempnam(sys_get_temp_dir(), 'deriv')), $this->nombreArchivo($request, 'xlsx'))
            ->deleteFileAfterSend();
    }

    /** El mismo filtro, en un ZIP: el Excel y una carpeta por caso con sus fotos y su soporte. */
    public function fotos(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Derivacion::class);

        $temporal = tempnam(sys_get_temp_dir(), 'derivzip');
        $excel = $this->excel($request, tempnam(sys_get_temp_dir(), 'deriv'));

        $zip = new ZipArchive();
        $zip->open($temporal, ZipArchive::OVERWRITE);
        $zip->addFile($excel, $this->nombreArchivo($request, 'xlsx'));

        // ponytail: addFile sobre el disco local, como el ZIP de carpetas.
        foreach ($this->consulta($request)->with(['soporte.versionActual', 'evidencia1.versionActual', 'evidencia2.versionActual', 'evidencia3.versionActual'])->lazy() as $derivacion) {
            $carpeta = sprintf('%04d %s %s', $derivacion->id, $derivacion->fecha_requerimiento->format('Y-m-d'), Str::slug($derivacion->nombre));

            foreach ($derivacion->archivos() as $etiqueta => $documento) {
                $version = $documento?->versionActual;

                if ($version && $this->almacenamiento->existe($version)) {
                    $zip->addFile($this->almacenamiento->rutaLocal($version), "{$carpeta}/{$etiqueta}.{$version->extension}");
                }
            }
        }

        $zip->close();
        @unlink($excel);

        return response()->download($temporal, $this->nombreArchivo($request, 'zip'))->deleteFileAfterSend();
    }

    protected function excel(Request $request, string $ruta): string
    {
        $filas = $this->consulta($request)
            ->with(['nodo', 'respondedor', 'soporte.versionActual', 'evidencia1.versionActual', 'evidencia2.versionActual', 'evidencia3.versionActual'])
            ->lazy()
            ->map(fn (Derivacion $d) => [
                $d->created_at->format('d/m/Y H:i:s'),
                $d->cedula,
                $d->nombre,
                $d->cargo,
                $d->nodo?->nombre,
                $d->fecha_requerimiento->format('d/m/Y'),
                substr($d->hora_requerimiento, 0, 5),
                $d->entidad,
                $this->archivoEnCelda($d->soporte),
                $d->descripcion,
                $d->ubicacion,
                $this->archivoEnCelda($d->evidencia1),
                $this->archivoEnCelda($d->evidencia2),
                $this->archivoEnCelda($d->evidencia3),
                $d->estaPendiente() ? null : substr($d->hora_respuesta, 0, 5),
                sprintf('%d:%02d', intdiv($d->minutos(), 60), $d->minutos() % 60).($d->estaPendiente() ? ' (corriendo)' : ''),
                $d->justificacion,
                $d->estaPendiente() ? ['alerta' => 'Pendiente'] : 'Solucionada',
                $d->respuesta,
                $d->respondedor?->name,
                $d->respondida_at?->format('d/m/Y H:i'),
            ]);

        return Xlsx::guardar($ruta, 'Derivaciones', self::ENCABEZADOS, $filas);
    }

    /**
     * Una foto va dentro de la celda: se ve sin entrar a Documenta. Lo que no
     * es foto (un PDF de soporte) va como enlace firmado que abre sin cuenta
     * y vence a los DIAS_ENLACE días.
     */
    protected function archivoEnCelda(?Documento $documento): ?array
    {
        $version = $documento?->versionActual;

        if ($version === null || ! $this->almacenamiento->existe($version)) {
            return null;
        }

        if (str_starts_with((string) $version->mime, 'image/')
            && ($miniatura = Xlsx::miniatura($this->almacenamiento->contenido($version)))) {
            return $miniatura;
        }

        return [
            'url' => URL::temporarySignedRoute('derivaciones.archivo', now()->addDays(self::DIAS_ENLACE), ['documento' => $documento->uuid]),
            'texto' => 'Abrir '.strtoupper((string) $version->extension),
        ];
    }

    /** Sirve un archivo de una derivación a quien tenga el enlace firmado del reporte. */
    public function archivo(string $documento)
    {
        $documento = Documento::withoutGlobalScopes()->where('uuid', $documento)->with('versionActual')->firstOrFail();

        $deUnaDerivacion = Derivacion::withoutGlobalScopes()->withTrashed()
            ->where(function ($q) use ($documento) {
                foreach (['soporte', 'evidencia1', 'evidencia2', 'evidencia3'] as $campo) {
                    $q->orWhere("{$campo}_documento_id", $documento->id);
                }
            })
            ->exists();

        abort_unless($deUnaDerivacion && $documento->versionActual, 404);

        return $this->almacenamiento->descargar($documento->versionActual);
    }

    /** Filtros: estado, fechas del requerimiento, nodo, cumplimiento y texto libre. Las pendientes, primero. */
    protected function consulta(Request $request): Builder
    {
        return Derivacion::query()
            ->with('nodo')
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_requerimiento', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_requerimiento', '<=', $request->date('hasta')))
            ->when(in_array($request->input('estado'), [Derivacion::PENDIENTE, Derivacion::SOLUCIONADA], true), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('nodo'), fn ($q) => $q->where('nodo_id', $request->integer('nodo')))
            ->when($request->input('cumplio') === 'si', fn ($q) => $q->where('tiempo_respuesta_min', '<=', Derivacion::META_MINUTOS))
            ->when($request->input('cumplio') === 'no', fn ($q) => $q->where('tiempo_respuesta_min', '>', Derivacion::META_MINUTOS))
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('nombre', 'like', $texto)->orWhere('cedula', 'like', $texto)
                    ->orWhere('entidad', 'like', $texto)->orWhere('descripcion', 'like', $texto));
            })
            ->orderByRaw('estado = ? desc', [Derivacion::PENDIENTE])
            ->orderByDesc('fecha_requerimiento')
            ->orderByDesc('hora_requerimiento');
    }

    protected function nombreArchivo(Request $request, string $extension): string
    {
        $rango = collect([$request->input('desde'), $request->input('hasta')])->filter()->join('_');

        return 'derivaciones'.($rango ? '_'.$rango : '').'.'.$extension;
    }
}
