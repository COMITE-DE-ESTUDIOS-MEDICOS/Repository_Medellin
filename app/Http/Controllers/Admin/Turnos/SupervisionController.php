<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\Nodo;
use App\Models\PuntoCritico;
use App\Models\Supervision;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * DOC-11 · Supervisión territorial: consultar y descargar lo que registra el
 * Apoyo al control territorial por su enlace. Nada se edita ni se borra.
 */
class SupervisionController extends Controller
{
    /** Las columnas del Forms «REGISTROS DE SUPERVISIÓN V6», más el promedio. */
    public const ENCABEZADOS = [
        'Marca temporal', 'NOMBRE APOYO AL CONTROL TERRITORIAL', 'PUNTO CRITICO', 'HORA DE LA VISITA',
        'NOMBRE Y CC DINAMIZADOR # 1', 'NOMBRE Y CC DINAMIZADOR # 2', 'NODO', 'LINK DE UBICACIÓN (GOOGLE MAPS)',
        'TURNO DEL DINAMIZADOR', 'DESCRIPCIÓN DEL PUNTO CRÍTICO',
        'CUMPLIMIENTO HORARIO', 'PRESENCIA EN LA ZONA ASIGNADA', 'CONOCIMIENTO DEL TERRITORIO', 'USO ADECUADO DE APLICATIVOS',
        'CUMPLIMIENTO DE PROTOCOLOS', 'UTILIZA LOS EPP', 'MANEJO DE RELACIONES CON LA COMUNIDAD', 'CAPACIDAD PARA MEDIAR',
        'PRESENTACIÓN PERSONAL', 'PROMEDIO', 'INCIDENCIAS DETECTADAS EN TERRITORIO', 'CASOS ESCALADOS AL ENLACE DE NODO?', 'CUAL',
        'CASOS PRIORITARIOS DEL TURNO', 'RECOMENDACIONES DEL APOYO TERRITORIAL', 'CALIFICACION GLOBAL DE LA DUPLA',
        'EVIDENCIAS', 'EVIDENCIAS 2', 'OBSERVACIONES', 'HORA DE INICIO', 'HORA DE FIN',
    ];

    public function __construct(protected AlmacenamientoDocumentos $almacenamiento)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supervision::class);

        return view('admin.turnos.supervisiones.index', [
            'supervisiones' => $this->consulta($request)->paginate(config('repositorio.por_pagina'))->withQueryString(),
            'nodos' => Nodo::whereHas('componente')->with('componente')->orderBy('nombre')->get(),
            'puntos' => PuntoCritico::whereHas('componente')->orderBy('nombre')->get(['id', 'nombre']),
            'analitica' => $this->analitica($request),
        ]);
    }

    /**
     * DOC-14: la analítica del filtro, con consultas agrupadas (no se recorre
     * registro por registro): resumen, promedio por criterio, por dinamizador
     * (sea el 1 o el 2 de la visita), por quien supervisa y por nodo.
     */
    protected function analitica(Request $request): array
    {
        $base = fn () => $this->consulta($request)->reorder()->setEagerLoads([])->toBase();
        $promedio = '('.implode(' + ', array_keys(Supervision::CRITERIOS)).') / 9.0';
        $relevante = "case when incidencia in ('relevante', 'critica') then 1 else 0 end";

        $resumen = $base()->selectRaw(implode(', ', [
            'count(*) as total',
            "avg({$promedio}) as promedio",
            'avg(calificacion_global) as global',
            "sum({$relevante}) as relevantes",
            "sum(case when incidencia = 'critica' then 1 else 0 end) as criticas",
            'sum(case when casos_escalados = 1 then 1 else 0 end) as escalados',
            'sum(case when lat is null then 1 else 0 end) as sin_ubicacion',
            ...array_map(fn ($c) => "avg({$c}) as {$c}", array_keys(Supervision::CRITERIOS)),
        ]))->first();

        $criterios = collect(Supervision::CRITERIOS)
            ->map(fn ($texto, $c) => ['criterio' => $texto, 'promedio' => $resumen->{$c} !== null ? round((float) $resumen->{$c}, 2) : null])
            ->sortBy('promedio')->values();

        // Cada visita cuenta para sus dos dinamizadores: se agrupa por cada columna y se suma.
        $porDinamizador = collect(['dinamizador1_id', 'dinamizador2_id'])
            ->flatMap(fn ($columna) => $base()->whereNotNull($columna)
                ->selectRaw("{$columna} as id, count(*) as visitas, sum({$promedio}) as suma, sum(calificacion_global) as suma_global, max(visita_at) as ultima")
                ->groupBy($columna)->get())
            ->groupBy('id')
            ->map(fn ($filas) => [
                'visitas' => $filas->sum('visitas'),
                'promedio' => round($filas->sum('suma') / $filas->sum('visitas'), 2),
                'global' => round($filas->sum('suma_global') / $filas->sum('visitas'), 2),
                'ultima' => $filas->max('ultima'),
            ]);
        $personas = Colaborador::withoutGlobalScopes()->whereIn('id', $porDinamizador->keys())->get(['id', 'nombre', 'documento'])->keyBy('id');
        $dinamizadores = $porDinamizador
            ->map(fn ($fila, $id) => $fila + ['nombre' => $personas[$id]->nombre ?? '—', 'documento' => $personas[$id]->documento ?? '—'])
            ->sortBy('promedio')->values();

        $supervisores = $base()
            ->selectRaw("supervisor_nombre as nombre, count(*) as visitas, avg({$promedio}) as promedio, avg(calificacion_global) as global")
            ->groupBy('supervisor_id', 'supervisor_nombre')->get()
            ->sortByDesc('visitas')->values();

        $nombresNodo = Nodo::withoutGlobalScopes()->pluck('nombre', 'id');
        $nodos = $base()
            ->selectRaw("nodo_id, count(*) as visitas, avg({$promedio}) as promedio, sum({$relevante}) as relevantes")
            ->groupBy('nodo_id')->get()
            ->map(fn ($n) => ['nodo' => $nombresNodo[$n->nodo_id] ?? 'Sin nodo', 'visitas' => (int) $n->visitas, 'promedio' => round((float) $n->promedio, 2), 'relevantes' => (int) $n->relevantes])
            ->sortBy('promedio')->values();

        return [
            'total' => (int) $resumen->total,
            'promedio' => $resumen->promedio !== null ? round((float) $resumen->promedio, 2) : null,
            'global' => $resumen->global !== null ? round((float) $resumen->global, 2) : null,
            'relevantes' => (int) $resumen->relevantes,
            'criticas' => (int) $resumen->criticas,
            'escalados' => (int) $resumen->escalados,
            'sin_ubicacion' => (int) $resumen->sin_ubicacion,
            'criterios' => $criterios,
            'dinamizadores' => $dinamizadores,
            'supervisores' => $supervisores,
            'nodos' => $nodos,
        ];
    }

    public function show(Supervision $supervision): View
    {
        $this->authorize('view', $supervision);

        return view('admin.turnos.supervisiones.show', [
            'supervision' => $supervision->load(['componente', 'nodo', 'puntoCritico', 'tipoTurno', 'supervisor', 'dinamizador1', 'dinamizador2', 'evidencia1', 'evidencia2']),
        ]);
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Supervision::class);

        $filas = $this->consulta($request)
            ->with(['dinamizador1', 'dinamizador2', 'tipoTurno', 'evidencia1.versionActual', 'evidencia2.versionActual'])
            ->lazy()
            ->map(fn (Supervision $s) => [
                $s->created_at->format('d/m/Y H:i:s'),
                $s->supervisor_nombre,
                $s->puntoNombre(),
                $s->visita_at->format('d/m/Y H:i'),
                $s->dinamizador1 ? "{$s->dinamizador1->nombre} · CC {$s->dinamizador1->documento}" : null,
                $s->dinamizador2 ? "{$s->dinamizador2->nombre} · CC {$s->dinamizador2->documento}" : null,
                $s->nodo?->nombre,
                $s->mapa() ? ['url' => $s->mapa(), 'texto' => 'Ver en el mapa'] : ['alerta' => 'Sin ubicación'],
                $s->tipoTurno ? "{$s->tipoTurno->codigo} · {$s->tipoTurno->nombre}" : null,
                $s->descripcion_punto,
                ...collect(array_keys(Supervision::CRITERIOS))->map(fn ($c) => (int) $s->{$c})->all(),
                $s->promedio(),
                Supervision::INCIDENCIAS[$s->incidencia] ?? $s->incidencia,
                $s->casos_escalados ? 'SI' : 'NO',
                $s->casos_escalados_cual,
                $s->casos_prioritarios,
                $s->recomendaciones,
                $s->calificacion_global,
                $this->fotoEnCelda($s->evidencia1),
                $this->fotoEnCelda($s->evidencia2),
                $s->observaciones,
                $s->inicio_at?->format('d/m/Y H:i'),
                $s->created_at->format('d/m/Y H:i'),
            ]);

        $a = $this->analitica($request);
        $ruta = Xlsx::libro(tempnam(sys_get_temp_dir(), 'superv'), [
            'Supervisiones' => [self::ENCABEZADOS, $filas],
            'Por dinamizador' => [
                ['Cédula', 'Dinamizador', 'Visitas', 'Promedio criterios', 'Promedio global', 'Última visita'],
                $a['dinamizadores']->map(fn ($d) => [$d['documento'], $d['nombre'], $d['visitas'], $this->nota($d['promedio']), $this->nota($d['global']), substr((string) $d['ultima'], 0, 16)]),
            ],
            'Por criterio' => [
                ['Criterio', 'Promedio'],
                $a['criterios']->map(fn ($c) => [$c['criterio'], $this->nota($c['promedio'])]),
            ],
            'Por supervisor' => [
                ['Supervisor', 'Visitas', 'Promedio que da', 'Global que da'],
                $a['supervisores']->map(fn ($s) => [$s->nombre, (int) $s->visitas, round((float) $s->promedio, 2), round((float) $s->global, 2)]),
            ],
            'Por nodo' => [
                ['Nodo', 'Visitas', 'Promedio', 'Con incidencia relevante o crítica'],
                $a['nodos']->map(fn ($n) => [$n['nodo'], $n['visitas'], $this->nota($n['promedio']), $n['relevantes']]),
            ],
        ]);
        $rango = collect([$request->input('desde'), $request->input('hasta')])->filter()->join('_');

        return response()->download($ruta, 'supervisiones'.($rango ? '_'.$rango : '').'.xlsx')->deleteFileAfterSend();
    }

    /** Un promedio bajo (menos de 3) va resaltado en el Excel. */
    protected function nota(?float $valor): mixed
    {
        return $valor !== null && $valor < 3 ? ['alerta' => $valor] : $valor;
    }

    /** La foto dentro de la celda, como en el reporte de derivaciones. */
    protected function fotoEnCelda(?Documento $documento): ?array
    {
        $version = $documento?->versionActual;

        return $version && $this->almacenamiento->existe($version)
            ? Xlsx::miniatura($this->almacenamiento->contenido($version))
            : null;
    }

    /** Filtros: fechas de la visita, nodo, punto, quién supervisó y cédula del dinamizador. */
    protected function consulta(Request $request): Builder
    {
        return Supervision::query()
            ->with(['nodo', 'puntoCritico'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('visita_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('visita_at', '<=', $request->date('hasta')))
            ->when($request->filled('nodo'), fn ($q) => $q->where('nodo_id', $request->integer('nodo')))
            ->when($request->filled('punto'), fn ($q) => $q->where('punto_critico_id', $request->integer('punto')))
            ->when($request->filled('supervisor'), fn ($q) => $q->where('supervisor_nombre', 'like', '%'.$request->string('supervisor').'%'))
            ->when($request->filled('dinamizador'), function ($q) use ($request) {
                $cc = User::normalizarDocumento($request->string('dinamizador'));
                $q->where(fn ($q) => $q->whereHas('dinamizador1', fn ($d) => $d->where('documento', $cc))
                    ->orWhereHas('dinamizador2', fn ($d) => $d->where('documento', $cc)));
            })
            ->latest('visita_at');
    }
}
