<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\Nodo;
use App\Notifications\CasoAtendido;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * DERIVACIONES Y RESPUESTA TERRITORIAL (DOC-20): la oficina crea el caso
 * (CasoDerivacionController) y la persona de campo lo atiende por su enlace.
 * Aquí está la trazabilidad: la lista, la ficha, la corrección de textos
 * (Edición), la de horas y el cierre a mano (solo el superadministrador, en
 * contingencia), el borrado y el reporte (Excel y ZIP con las fotos).
 */
class DerivacionController extends Controller
{
    /**
     * Los encabezados del reporte, tal cual la hoja de cálculo de siempre. Lo
     * de DOC-20 va al final para no mover las columnas viejas.
     */
    public const ENCABEZADOS = [
        'Marca temporal', 'CC DE QUIEN ESCALA LA NOVEDAD', 'NOMBRE DE QUIEN ESCALA LA NOVEDAD',
        'CARGO DE QUIEN ESCALA LA NOVEDAD', 'NODO QUE ATIENDE EL REQUERIEMIENTO', 'FECHA DEL REQUERIMIENTO',
        'HORA DEL REQUERIMIENTO', 'ENTIDAD QUE SOLICITA EL REQUERIMIENTO', 'SOPORTE DEL REQUERIMIENTO',
        'DESCRIPCION DEL CASO', 'UBICACION DEL REQUERIMIENTO', 'EVIDENCIAS # 1', 'EVIDENCIAS # 2',
        'EVIDENCIAS # 3', 'HORA DE RESPUESTA', 'TIEMPO DE RESPUESTA', 'JUSTIFICACION EN CASO DE NO CUMPLIR',
        'ESTADO', 'RESPUESTA', 'RESPONDIDA POR', 'FECHA Y HORA DE RESPUESTA',
        'CÓDIGO DEL CASO', 'QUIÉN LO CREÓ', 'FECHA Y HORA DE CREACIÓN', 'FECHA Y HORA DE ATENCIÓN',
        '¿SE CUMPLIÓ EL REQUERIMIENTO?', 'OBSERVACIÓN',
    ];

    /** Cuánto dura el enlace a un PDF de soporte dentro del Excel. */
    public const DIAS_ENLACE = 30;

    /** Lo que solo cambia el superadministrador (corregirHoras). */
    protected const CAMPOS_HORAS = ['fecha_requerimiento', 'hora_requerimiento', 'atendida_at', 'hora_respuesta', 'cumplio_requerimiento'];

    public function __construct(
        protected Auditor $auditor,
        protected AlmacenamientoDocumentos $almacenamiento,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Derivacion::class);

        return view('admin.turnos.derivaciones.index', [
            'derivaciones' => $this->consulta($request)->paginate(config('repositorio.por_pagina'))->withQueryString(),
            'nodos' => Nodo::whereHas('componente')->with('componente')->orderBy('nombre')->get(),
            'traza' => $this->trazabilidad($request),
        ]);
    }

    /**
     * El seguimiento del filtro: cuántos hay abiertos (y vencidos), cuántos se
     * atendieron a tiempo, qué nodos tienen casos sin atender y cuántos
     * atendió cada persona de campo.
     */
    protected function trazabilidad(Request $request): array
    {
        $base = fn () => $this->consulta($request)->reorder()->setEagerLoads([]);
        $meta = Derivacion::META_MINUTOS;
        $cuenta = 'count(*) as total, sum(case when tiempo_respuesta_min <= ? then 1 else 0 end) as a_tiempo, avg(tiempo_respuesta_min) as promedio';

        $pendientes = $base()->where('estado', Derivacion::PENDIENTE)->with('nodo')->get();
        $solucionadas = $base()->where('estado', Derivacion::SOLUCIONADA)->toBase()->selectRaw($cuenta, [$meta])->first();

        return [
            'pendientes' => $pendientes->count(),
            'vencidas' => $pendientes->reject->cumplio()->count(),
            'solucionadas' => (int) $solucionadas->total,
            'a_tiempo' => (int) $solucionadas->a_tiempo,
            'promedio' => $solucionadas->promedio !== null ? Derivacion::duracion((int) round($solucionadas->promedio)) : null,
            // Quién no: los nodos con casos sin atender, el más atrasado primero.
            'sin_respuesta' => $pendientes->groupBy(fn ($d) => $d->nodo?->nombre ?? 'Sin nodo')
                ->map(fn ($casos) => ['casos' => $casos->count(), 'vencidas' => $casos->reject->cumplio()->count(), 'mas_antigua' => $casos->max->minutos()])
                ->sortByDesc('mas_antigua'),
            // Quién sí: cada persona de campo que atendió casos DC. Los registros
            // anteriores los respondía un líder: su nombre sigue en la ficha y en el Excel.
            'personas' => $base()->where('estado', Derivacion::SOLUCIONADA)->whereNotNull('numero')->toBase()
                ->selectRaw("nombre, cedula, {$cuenta}", [$meta])
                ->groupBy('colaborador_id', 'nombre', 'cedula')->orderByDesc('total')->get(),
        ];
    }

    public function show(Derivacion $derivacion): View
    {
        $this->authorize('view', $derivacion);

        $contingencia = $derivacion->estaPendiente() && request()->user()->can('corregirHoras', $derivacion);

        return view('admin.turnos.derivaciones.show', [
            'derivacion' => $derivacion->load(['nodo', 'componente', 'creador', 'respondedor', 'soporte', 'evidencia1', 'evidencia2', 'evidencia3']),
            'contingencia' => $contingencia,
            'colaboradores' => $contingencia ? Colaborador::activos()->where('componente_id', $derivacion->componente_id)->orderBy('nombre')->get() : collect(),
            'nodosActivos' => $contingencia ? $derivacion->componente->nodos()->activos()->orderBy('nombre')->get() : collect(),
        ]);
    }

    public function edit(Derivacion $derivacion): View
    {
        $this->authorize('update', $derivacion);

        return view('admin.turnos.derivaciones.edit', [
            'derivacion' => $derivacion,
            'nodos' => $derivacion->componente->nodos()->activos()->orderBy('nombre')->get(),
            'horas' => request()->user()->can('corregirHoras', $derivacion),
        ]);
    }

    /**
     * Edición y Coordinación corrigen los textos. Las horas y el ¿se cumplió?
     * solo los cambia el superadministrador, con motivo: si alguien más los
     * manda, se ignoran (solo se valida y se guarda lo que le toca).
     */
    public function update(Request $request, Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('update', $derivacion);

        $horas = $request->user()->can('corregirHoras', $derivacion);
        $atendida = ! $derivacion->estaPendiente();

        $reglas = $derivacion->esCaso()
            ? [
                'descripcion' => ['required', 'string', 'max:5000'],
                'remitente' => ['required', Rule::in(array_keys(Derivacion::REMITENTES))],
                'remitente_otra' => ['nullable', 'required_if:remitente,otra', 'string', 'max:255'],
                'ubicacion' => ['required', 'string', 'max:500'],
            ] + ($atendida ? ['observacion' => ['nullable', 'string', 'max:5000']] : [])
            : array_diff_key(Derivacion::reglas($derivacion->componente_id), array_flip(['fecha_requerimiento', 'hora_requerimiento']))
                + ($atendida ? ['respuesta' => ['nullable', 'string', 'max:5000']] : []);

        $reglas += $atendida ? ['justificacion' => ['nullable', 'string', 'max:5000']] : [];

        if ($horas) {
            $reglas += [
                'fecha_requerimiento' => ['required', 'date', 'before_or_equal:today'],
                'hora_requerimiento' => ['required', 'date_format:H:i'],
                'motivo' => ['nullable', 'string', 'max:1000'],
            ] + match (true) {
                ! $atendida => [],
                $derivacion->esCaso() => [
                    'atendida_at' => ['required', 'date', 'before_or_equal:now'],
                    'cumplio_requerimiento' => ['required', 'boolean'],
                ],
                default => ['hora_respuesta' => ['required', 'date_format:H:i']],
            };

            if ($atendida && $derivacion->esCaso()) {
                // Con «No», la observación es obligatoria (como al atender).
                $reglas['observacion'] = ['nullable', 'required_if:cumplio_requerimiento,0', 'string', 'max:5000'];
            }
        }

        $datos = $request->validate($reglas, ['remitente_otra.required_if' => 'Escribe quién envía el caso.'], Derivacion::ATRIBUTOS + [
            'remitente' => 'quién lo envía', 'remitente_otra' => '¿cuál?', 'observacion' => 'observación',
            'atendida_at' => 'hora de cierre', 'cumplio_requerimiento' => '¿se cumplió el requerimiento?', 'motivo' => 'motivo',
        ]);

        if (isset($datos['remitente'])) {
            $datos['entidad'] = $datos['remitente'] === 'otra' ? trim($datos['remitente_otra']) : Derivacion::REMITENTES[$datos['remitente']];
        }

        $motivo = $datos['motivo'] ?? null;
        $antes = $derivacion->getAttributes();
        $derivacion->fill(collect($datos)->except(['remitente_otra', 'motivo'])->all());

        // Comparar por valor: «10:00» y «10:00:00» son la misma hora.
        $horasCambiadas = collect(self::CAMPOS_HORAS)->filter(fn ($c) => $derivacion->isDirty($c)
            && $this->normal($antes[$c] ?? null) !== $this->normal($derivacion->getAttributes()[$c] ?? null))->values();

        if ($horasCambiadas->isNotEmpty() && blank($motivo)) {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo de la corrección de horas: queda en la auditoría.']);
        }

        if ($atendida) {
            $derivacion->tiempo_respuesta_min = $this->tiempo($derivacion);

            if ($derivacion->esCaso()) {
                $derivacion->hora_respuesta = $derivacion->atendida_at?->format('H:i');
            }
        }

        $derivacion->actualizado_por = $request->user()->id;
        $cambios = array_values(array_diff(array_keys($derivacion->getDirty()), ['actualizado_por', 'updated_at']));
        $derivacion->save();

        if ($horasCambiadas->isNotEmpty()) {
            $this->auditor->registrar(
                AccionAuditoria::CasoCorregidoContingencia,
                $derivacion,
                "Corrigió horas de {$derivacion->codigo()} (contingencia): {$motivo}",
                [
                    'motivo' => $motivo,
                    'antes' => collect($cambios)->mapWithKeys(fn ($c) => [$c => $antes[$c] ?? null])->all(),
                    'despues' => collect($cambios)->mapWithKeys(fn ($c) => [$c => $derivacion->getAttributes()[$c] ?? null])->all(),
                ],
            );
        } else {
            $this->auditor->registrar(
                AccionAuditoria::DerivacionActualizada,
                $derivacion,
                "Corrigió la derivación {$derivacion->codigo()}",
                ['cambios' => $cambios],
            );
        }

        return redirect()->route('admin.turnos.derivaciones.show', $derivacion)->with('exito', 'Corrección guardada.');
    }

    /**
     * Plan de contingencia: el caso se atendió y no se pudo enviar por el
     * enlace. El superadministrador lo cierra a mano, eligiendo a la persona
     * de campo, con motivo. Vale también para un registro anterior abierto.
     */
    public function contingencia(Request $request, Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('corregirHoras', $derivacion);

        $datos = $request->validate([
            'colaborador_id' => ['required', Rule::exists('colaboradores', 'id')
                ->where('dependencia_id', $derivacion->dependencia_id)->where('componente_id', $derivacion->componente_id)->where('activo', 1)],
            'nodo_id' => ['required', Rule::exists('nodos', 'id')->where('componente_id', $derivacion->componente_id)->where('activo', 1)],
            'atendida_at' => ['required', 'date', 'before_or_equal:now'],
            'cumplio_requerimiento' => ['required', 'boolean'],
            'observacion' => ['nullable', 'required_if:cumplio_requerimiento,0', 'string', 'max:5000'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
            'motivo' => ['required', 'string', 'max:1000'],
        ], ['observacion.required_if' => 'Si no se cumplió el requerimiento, escribe la observación.'], [
            'colaborador_id' => 'persona de campo', 'nodo_id' => 'nodo que atiende', 'atendida_at' => 'hora de cierre',
            'cumplio_requerimiento' => '¿se cumplió el requerimiento?', 'observacion' => 'observación', 'justificacion' => 'justificación',
        ]);

        $derivacion = DB::transaction(function () use ($derivacion, $datos, $request) {
            $caso = Derivacion::whereKey($derivacion->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($caso->estaPendiente(), 422, 'Este caso ya fue atendido.');

            $colaborador = Colaborador::findOrFail($datos['colaborador_id']);
            $cierre = Carbon::parse($datos['atendida_at']);

            $caso->fill([
                'estado' => Derivacion::SOLUCIONADA,
                'colaborador_id' => $colaborador->id,
                'cedula' => $colaborador->documento,
                'nombre' => $colaborador->nombre,
                'cargo' => $colaborador->cargo,
                'nodo_id' => $datos['nodo_id'],
                'atendida_at' => $cierre,
                'hora_respuesta' => $cierre->format('H:i'),
                'cumplio_requerimiento' => (bool) $datos['cumplio_requerimiento'],
                'observacion' => $datos['observacion'] ?? null,
                'justificacion' => $datos['justificacion'] ?? null,
                'actualizado_por' => $request->user()->id,
            ]);
            $caso->tiempo_respuesta_min = $this->tiempo($caso);
            $caso->save();

            $this->auditor->registrar(
                AccionAuditoria::CasoCorregidoContingencia,
                $caso,
                "Cerró a mano {$caso->codigo()} (contingencia): {$datos['motivo']}",
                [
                    'motivo' => $datos['motivo'],
                    'antes' => ['estado' => Derivacion::PENDIENTE],
                    'despues' => $caso->only(['estado', 'colaborador_id', 'nodo_id', 'hora_respuesta', 'tiempo_respuesta_min', 'cumplio_requerimiento', 'observacion'])
                        + ['atendida_at' => $cierre->toDateTimeString()],
                ],
            );

            return $caso;
        });

        CasoAtendido::enviar($derivacion);

        return redirect()->route('admin.turnos.derivaciones.show', $derivacion)
            ->with('exito', "{$derivacion->codigo()} cerrado a mano en {$derivacion->tiempoLegible()}.");
    }

    /**
     * Minutos desde el requerimiento hasta el cierre. En un caso, entre fechas
     * completas (puede pasar de 24 h); en un registro anterior, entre horas,
     * como siempre (minutosEntre). Pasada la meta, sin justificación, no se guarda.
     */
    protected function tiempo(Derivacion $derivacion): int
    {
        if ($derivacion->esCaso()) {
            if ($derivacion->atendida_at->lt($derivacion->requeridaAt())) {
                throw ValidationException::withMessages(['atendida_at' => 'La hora de cierre no puede ser antes del inicio del caso.']);
            }

            $minutos = (int) $derivacion->requeridaAt()->diffInMinutes($derivacion->atendida_at);
        } else {
            $minutos = Derivacion::minutosEntre($derivacion->hora_requerimiento, $derivacion->hora_respuesta);
        }

        if ($minutos > Derivacion::META_MINUTOS && blank($derivacion->justificacion)) {
            throw ValidationException::withMessages([
                'justificacion' => 'El tiempo pasa de '.intdiv(Derivacion::META_MINUTOS, 60).' horas: escribe la justificación.',
            ]);
        }

        return $minutos;
    }

    protected function normal(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_string($valor) && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $valor) ? substr($valor.':00', 0, 5) : (string) $valor;
    }

    /** Se retira del reporte; sus archivos siguen en el repositorio. */
    public function destroy(Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('delete', $derivacion);

        $derivacion->delete();

        $this->auditor->registrar(AccionAuditoria::DerivacionEliminada, $derivacion, "Eliminó la derivación {$derivacion->codigo()}");

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
            $carpeta = collect([
                $derivacion->esCaso() ? $derivacion->codigo() : sprintf('%04d', $derivacion->id),
                $derivacion->fecha_requerimiento->format('Y-m-d'),
                Str::slug((string) $derivacion->nombre),
            ])->filter()->join(' ');

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
            ->with(['nodo', 'creador', 'respondedor', 'soporte.versionActual', 'evidencia1.versionActual', 'evidencia2.versionActual', 'evidencia3.versionActual'])
            ->lazy()
            ->map(function (Derivacion $d) {
                // Un caso abierto todavía no tiene persona de campo ni nodo.
                $abierto = $d->esCaso() && $d->estaPendiente();

                return [
                    $d->created_at->format('d/m/Y H:i:s'),
                    $abierto ? null : $d->cedula,
                    $abierto ? null : $d->nombre,
                    $abierto ? null : $d->cargo,
                    $abierto ? null : $d->nodo?->nombre,
                    $d->fecha_requerimiento->format('d/m/Y'),
                    substr($d->hora_requerimiento, 0, 5),
                    $d->entidad,
                    $this->archivoEnCelda($d->soporte),
                    $d->descripcion,
                    $d->ubicacion,
                    $this->archivoEnCelda($d->evidencia1),
                    $this->archivoEnCelda($d->evidencia2),
                    $this->archivoEnCelda($d->evidencia3),
                    $d->estaPendiente() ? null : substr((string) $d->hora_respuesta, 0, 5),
                    sprintf('%d:%02d', intdiv($d->minutos(), 60), $d->minutos() % 60).($d->estaPendiente() ? ' (corriendo)' : ''),
                    $d->justificacion,
                    $d->estaPendiente() ? ['alerta' => $d->estadoLegible()] : $d->estadoLegible(),
                    $d->respuesta,
                    $d->respondedor?->name,
                    $d->respondida_at?->format('d/m/Y H:i'),
                    $d->esCaso() ? $d->codigo() : 'Registro anterior',
                    $d->creador?->name,
                    $d->esCaso() ? $d->created_at->format('d/m/Y H:i') : null,
                    $d->atendida_at?->format('d/m/Y H:i'),
                    $d->cumplio_requerimiento === null ? null : ($d->cumplio_requerimiento ? 'Sí' : 'No'),
                    $d->observacion,
                ];
            });

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

    /**
     * Filtros: estado, fechas del requerimiento, nodo, cumplimiento y texto
     * libre (también el código: «DC-12» o «12»). Los abiertos, primero.
     */
    protected function consulta(Request $request): Builder
    {
        return Derivacion::query()
            ->with(['nodo', 'creador'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_requerimiento', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_requerimiento', '<=', $request->date('hasta')))
            ->when(in_array($request->input('estado'), [Derivacion::PENDIENTE, Derivacion::SOLUCIONADA], true), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('nodo'), fn ($q) => $q->where('nodo_id', $request->integer('nodo')))
            ->when($request->input('cumplio') === 'si', fn ($q) => $q->where('tiempo_respuesta_min', '<=', Derivacion::META_MINUTOS))
            ->when($request->input('cumplio') === 'no', fn ($q) => $q->where('tiempo_respuesta_min', '>', Derivacion::META_MINUTOS))
            ->when($request->filled('q'), function ($q) use ($request) {
                $buscado = trim($request->string('q'));
                $texto = '%'.$buscado.'%';
                $numero = preg_match('/^(?:dc\s*-?\s*)?0*(\d+)$/i', $buscado, $m) ? (int) $m[1] : null;

                $q->where(fn ($q) => $q->where('nombre', 'like', $texto)->orWhere('cedula', 'like', $texto)
                    ->orWhere('entidad', 'like', $texto)->orWhere('descripcion', 'like', $texto)
                    ->when($numero !== null, fn ($q) => $q->orWhere('numero', $numero)));
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
