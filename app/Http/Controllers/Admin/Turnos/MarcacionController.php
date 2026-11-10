<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\User;
use App\Services\Turnos\Jornadas;
use App\Services\Turnos\Marcador;
use App\Services\Turnos\TurnoCerrado;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Las marcaciones tal como quedaron: se consultan, se exportan y, si falta
 * una, se agrega con su motivo. Nunca se editan ni se borran.
 */
class MarcacionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Marcacion::class);

        [$desde, $hasta] = $this->rango($request);

        $marcaciones = Marcacion::query()
            ->with(['colaborador', 'componente', 'nodo', 'foto', 'registrador', 'anulador'])
            ->tap(fn (Builder $q) => $this->filtrar($q, $request))
            ->whereBetween('marcada_at', [$desde, $hasta])
            ->latest('marcada_at')
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('admin.turnos.marcaciones.index', [
            'marcaciones' => $marcaciones,
            // DOC-21 (CA-06): los cierres automáticos que nadie ha revisado ni corregido.
            'porCorregir' => NovedadTurno::query()
                ->whereIn('marcacion_id', $marcaciones->getCollection()->where('origen', OrigenMarcacion::CierreAutomatico)->pluck('id'))
                ->where('tipo', TipoNovedad::SinSalida->value)
                ->where('estado', EstadoNovedad::Pendiente->value)
                ->pluck('marcacion_id')
                ->flip(),
            'componentes' => Componente::activos()->with(['nodos' => fn ($q) => $q->activos()])->orderBy('nombre')->get(),
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    /** Tope legal de la semana (Ley 2101 de 2021): pasarlo se señala, no se bloquea. */
    public const HORAS_SEMANA = 42;

    /**
     * El reporte en Excel, con dos pestañas:
     *  - «Turnos»: un turno por fila (entrada, salida, horas), como siempre.
     *  - «Resumen mensual»: una fila por persona con días, horas, extras,
     *    novedades y las horas de cada semana contra las 42 h.
     */
    public function exportar(Request $request, Jornadas $jornadas): BinaryFileResponse
    {
        $this->authorize('viewAny', Marcacion::class);

        [$desde, $hasta] = $this->rango($request);
        // El nodo no recorta los turnos de una persona: quien cubrió en otro
        // nodo los tiene todos en el resumen. La pestaña «Turnos» sí se queda
        // con los marcados en ese nodo.
        $turnos = $jornadas->entre($desde, $hasta, fn (Builder $q) => $this->filtrar($q, $request, nodo: false));

        // Las novedades de duración de cada turno (las de su salida), sin las
        // ya corregidas: esas quedaron reemplazadas por la nueva evaluación.
        $novedades = NovedadTurno::query()
            ->whereIn('marcacion_id', $turnos->pluck('salida.id')->filter())
            ->whereIn('tipo', [TipoNovedad::HorasExtra->value, TipoNovedad::TurnoCorto->value, TipoNovedad::SinSalida->value])
            ->where('estado', '!=', EstadoNovedad::Corregida->value)
            ->get()
            ->keyBy('marcacion_id');

        $filas = $turnos->map(fn (array $turno) => $this->calcular($turno, $novedades));
        $delNodo = $request->filled('nodo')
            ? $filas->filter(fn (array $f) => $f['entrada']->nodo_id === $request->integer('nodo'))->values()
            : $filas;

        $ruta = Xlsx::libro(tempnam(sys_get_temp_dir(), 'turnos'), [
            'Turnos' => [self::ENCABEZADOS_TURNOS, $delNodo->map(fn (array $f) => $this->filaTurno($f))],
            'Resumen mensual' => $this->resumen($request, $filas, $delNodo, $desde, $hasta),
        ]);

        return response()
            ->download($ruta, 'turnos_'.$desde->format('Y-m-d').'_'.$hasta->format('Y-m-d').'.xlsx')
            ->deleteFileAfterSend();
    }

    protected const ENCABEZADOS_TURNOS = [
        'Cédula', 'Nombre', 'Cargo', 'Componente', 'Nodo', 'Turno', 'Entrada', 'Salida',
        'Permanencia (h)', 'Descanso (h)', 'Horas trabajadas', 'Jornada (h)', 'Horas ordinarias', 'Horas extra', 'Extras aprobadas',
        'Novedad', 'Cómo cerró', 'EPP',
    ];

    /** Las cuentas de un turno, en minutos. Lo usan las dos pestañas. */
    protected function calcular(array $turno, $novedades): array
    {
        $entrada = $turno['entrada'];
        $novedad = $turno['salida'] ? $novedades->get($turno['salida']->id) : null;
        // DOC-8: la jornada de su turno (el reconocido con entrada y salida).
        $jornada = $entrada->componente->jornadaPara($turno['tipo'] ?? null);

        // Lo que cuenta es el trabajo real: la permanencia menos el descanso
        // (Componente::minutosTrabajados, el mismo cálculo de las novedades).
        $permanencia = $turno['minutos'];
        $trabajado = $permanencia !== null ? $entrada->componente->minutosTrabajados($permanencia) : null;

        // Horas extra solo cuando hay novedad de horas extra (pasó de la
        // tolerancia); lo de dentro de la tolerancia cuenta como jornada.
        $extra = $novedad?->tipo === TipoNovedad::HorasExtra ? max(0, $trabajado - $jornada) : 0;

        return compact('turno', 'entrada', 'novedad', 'jornada', 'permanencia', 'trabajado', 'extra');
    }

    protected function filaTurno(array $f): array
    {
        $c = $f['entrada']->colaborador;
        $salida = $f['turno']['salida'];

        return [
            $c->documento,
            $c->nombre,
            $c->cargo,
            $f['entrada']->componente->nombre,
            $f['entrada']->nodo?->nombre,
            // El turno por la hora de entrada (Programacion::reconocer). Sin
            // uno que encaje va en rojo: hay que mirar esa entrada.
            $f['turno']['tipo']?->codigo ?? ['alerta' => 'Sin reconocer'],
            $f['entrada']->marcada_at->format('d/m/Y H:i'),
            // DOC-21 (CA-06): cerrado solo y sin revisar, va en rojo hasta que Coordinación lo corrija o lo confirme.
            $this->porCorregir($salida, $f['novedad'])
                ? ['alerta' => $salida->marcada_at->format('d/m/Y H:i').' · Cierre automático · por corregir']
                : ($salida?->marcada_at->format('d/m/Y H:i') ?? 'Sin salida'),
            self::horas($f['permanencia']),
            $f['permanencia'] !== null ? self::horas($f['permanencia'] - $f['trabajado']) : null,
            self::horas($f['trabajado']),
            self::horas($f['jornada']),
            $f['trabajado'] !== null ? self::horas($f['trabajado'] - $f['extra']) : null,
            self::horas($f['extra']),
            match (true) {
                $f['extra'] === 0 => '',
                $f['novedad']->estado === EstadoNovedad::Aprobada => 'SI',
                $f['novedad']->estado === EstadoNovedad::Rechazada => 'NO',
                default => 'PENDIENTE',
            },
            $f['novedad'] ? "{$f['novedad']->tipo->etiqueta()} ({$f['novedad']->estado->etiqueta()})" : '',
            $salida?->origen->etiqueta() ?? 'Sigue abierto',
            // DOC-12: lo que respondió al entrar; las entradas viejas o manuales no lo tienen.
            match ($f['entrada']->epp_recibidos) {
                true => 'SI',
                false => ['alerta' => 'NO'],
                null => 'Sin dato',
            },
        ];
    }

    /** Una salida del cierre automático cuya novedad «no marcó salida» sigue pendiente. */
    protected function porCorregir(?Marcacion $salida, ?NovedadTurno $novedad): bool
    {
        return $salida?->origen === OrigenMarcacion::CierreAutomatico
            && $novedad?->tipo === TipoNovedad::SinSalida
            && $novedad->estado === EstadoNovedad::Pendiente;
    }

    /**
     * Una fila por persona. Entran también las personas activas del filtro
     * que no marcaron nada en el período (0 días, en rojo): es lo primero
     * que hay que mirar. Las faltas contra un turno programado llegan cuando
     * cada componente tenga sus turnos preestablecidos.
     *
     * @return array{0: array, 1: array}
     */
    protected function resumen(Request $request, $filas, $delNodo, Carbon $desde, Carbon $hasta): array
    {
        // Semanas de lunes a domingo que tocan el período.
        $semanas = [];
        for ($inicio = $desde->copy()->startOfWeek(Carbon::MONDAY); $inicio->lte($hasta); $inicio->addWeek()) {
            $semanas[$inicio->format('o-W')] = 'Sem. '.$inicio->translatedFormat('j M').'–'.$inicio->copy()->endOfWeek(Carbon::SUNDAY)->translatedFormat('j M');
        }

        $dias = (int) $desde->copy()->startOfDay()->diffInDays($hasta->copy()->startOfDay()) + 1;
        $esperadas = self::HORAS_SEMANA * $dias / 7;

        $porPersona = $filas->groupBy(fn (array $f) => $f['entrada']->colaborador_id);

        $personas = Colaborador::activos()
            ->when($request->filled('componente'), fn ($q) => $q->where('componente_id', $request->integer('componente')))
            ->when($request->filled('nodo'), fn ($q) => $q->where('nodo_id', $request->integer('nodo')))
            ->when($request->filled('cedula'), fn ($q) => $q->where('documento', 'like', User::normalizarDocumento($request->input('cedula')).'%'))
            ->with(['componente', 'nodo'])
            ->get()
            ->keyBy('id');

        // Quien marcó en el nodo (o en cualquiera, sin filtro) también sale,
        // aunque ya no esté activo o su nodo habitual sea otro.
        $delNodo->groupBy(fn (array $f) => $f['entrada']->colaborador_id)->each(function ($turnos, $id) use ($personas) {
            $personas->put($id, $turnos->first()['entrada']->colaborador);
        });

        $resumen = $personas->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->map(function (Colaborador $c) use ($porPersona, $semanas, $esperadas, $dias) {
            $turnos = $porPersona->get($c->id, collect());
            $trabajado = $turnos->sum('trabajado');
            $extra = $turnos->sum('extra');
            $extraDe = fn (EstadoNovedad $estado) => $turnos->filter(fn ($f) => $f['extra'] > 0 && $f['novedad']->estado === $estado)->sum('extra');
            $deTipo = fn (TipoNovedad $tipo) => $turnos->filter(fn ($f) => $f['novedad']?->tipo === $tipo)->count();
            $diasTrabajados = $turnos->map(fn ($f) => $f['entrada']->marcada_at->toDateString())->unique()->count();

            $porSemana = array_fill_keys(array_keys($semanas), 0);
            foreach ($turnos as $f) {
                $porSemana[$f['entrada']->marcada_at->format('o-W')] += (int) $f['trabajado'];
            }

            $semanasSobre = collect($porSemana)->filter(fn ($m) => $m > self::HORAS_SEMANA * 60)->count();
            $cumplimiento = $esperadas > 0 ? round($trabajado / 60 / $esperadas * 100) : null;
            $primero = $turnos->first()['entrada'] ?? null;

            return [
                $c->documento,
                $c->nombre,
                $c->cargo,
                $primero?->componente->nombre ?? $c->componente?->nombre,
                $c->nodo?->nombre ?? $primero?->nodo?->nombre,
                $diasTrabajados === 0 ? ['alerta' => 0] : $diasTrabajados,
                $turnos->count(),
                self::horas($trabajado),
                self::horas($trabajado - $extra),
                self::horas($extra),
                self::horas($extraDe(EstadoNovedad::Aprobada)),
                self::horas($extraDe(EstadoNovedad::Pendiente)),
                round($trabajado / 60 / ($dias / 7), 2),
                $semanasSobre ? ['alerta' => $semanasSobre] : 0,
                ...array_map(fn ($m) => $m > self::HORAS_SEMANA * 60 ? ['alerta' => self::horas($m)] : self::horas($m), array_values($porSemana)),
                $deTipo(TipoNovedad::TurnoCorto),
                $deTipo(TipoNovedad::SinSalida),
                $turnos->filter(fn ($f) => $f['turno']['salida'] === null)->count(),
                $cumplimiento === null ? null : $cumplimiento.' %',
            ];
        })->values()->all();

        return [[
            'Cédula', 'Nombre', 'Cargo', 'Componente', 'Nodo',
            'Días trabajados', 'Turnos', 'Horas trabajadas', 'Horas ordinarias', 'Horas extra',
            'Extras aprobadas (h)', 'Extras pendientes (h)', 'Promedio semanal (h)', 'Semanas sobre '.self::HORAS_SEMANA.' h',
            ...array_values($semanas),
            'Turnos cortos', 'Sin salida (novedad)', 'Turnos abiertos',
            'Cumplimiento vs '.self::HORAS_SEMANA.' h/sem',
        ], $resumen];
    }

    /** Minutos a horas con dos decimales, como número: Excel las suma. */
    protected static function horas(?int $minutos): ?float
    {
        return $minutos !== null ? round($minutos / 60, 2) : null;
    }

    /** Agregar la marca que faltó. Una fila nueva con motivo y autor: la original no se toca. */
    public function store(Request $request, Colaborador $colaborador, Marcador $marcador): RedirectResponse
    {
        $this->authorize('update', $colaborador);

        $datos = $request->validate([
            'tipo' => ['required', Rule::enum(TipoMarcacion::class)],
            'marcada_at' => ['required', 'date', 'before_or_equal:now'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'nodo_id' => [
                'nullable',
                Rule::exists('nodos', 'id')->where('componente_id', $colaborador->componente_id),
            ],
        ], [], [
            'marcada_at' => 'fecha y hora',
            'motivo' => 'motivo',
        ]);

        $marcador->registrarManual(
            $colaborador,
            TipoMarcacion::from($datos['tipo']),
            Carbon::parse($datos['marcada_at']),
            $datos['motivo'],
            $request->user(),
            isset($datos['nodo_id']) ? $colaborador->componente?->nodos()->find($datos['nodo_id']) : null,
        );

        return back()->with('exito', 'Marca agregada. Queda como corrección, con tu nombre y el motivo.');
    }

    /**
     * «Editar» la hora de una marca: la original queda anulada con el motivo
     * y la reemplaza una nueva (Marcador::corregir). Desde Edición.
     */
    public function corregir(Request $request, Marcacion $marcacion, Marcador $marcador): RedirectResponse
    {
        $this->authorize('corregir', $marcacion);

        $datos = $request->validate([
            'marcada_at' => ['required', 'date', 'before_or_equal:now'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['marcada_at' => 'hora corregida', 'motivo' => 'motivo']);

        try {
            $marcador->corregir($marcacion, Carbon::parse($datos['marcada_at']), $datos['motivo'], $request->user());
        } catch (TurnoCerrado $e) {
            return back()->withInput()->withErrors(['marcada_at' => $e->getMessage()]);
        }

        return back()->with('exito', 'Hora corregida. La marca anterior queda anulada en el historial.');
    }

    /** DOC-18: entrada ↔ salida, a la misma hora. Para enderezar un turno invertido. */
    public function cambiarTipo(Request $request, Marcacion $marcacion, Marcador $marcador): RedirectResponse
    {
        $this->authorize('anular', $marcacion);

        $motivo = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']])['motivo'];
        $tipo = $marcacion->esEntrada() ? TipoMarcacion::Salida : TipoMarcacion::Entrada;

        try {
            $marcador->corregir($marcacion, $marcacion->marcada_at, $motivo, $request->user(), $tipo);
        } catch (TurnoCerrado $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return back()->with('exito', "Ahora es una {$tipo->etiqueta()}. La marca anterior queda anulada en el historial.");
    }

    /** DOC-18: quitar una marca de más. Queda tachada en el historial y deja de contar. */
    public function anular(Request $request, Marcacion $marcacion, Marcador $marcador): RedirectResponse
    {
        $this->authorize('anular', $marcacion);

        $motivo = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']])['motivo'];

        try {
            $marcador->anular($marcacion, $motivo, $request->user());
        } catch (TurnoCerrado $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return back()->with('exito', 'Marca anulada. Ya no cuenta, y queda en el historial con tu nombre y el motivo.');
    }

    /** Del primer día del mes hasta hoy, si no se pide otro rango. */
    protected function rango(Request $request): array
    {
        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : now()->startOfMonth();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : now()->endOfDay();

        return [$desde, $hasta];
    }

    protected function filtrar(Builder $q, Request $request, bool $nodo = true): void
    {
        $q->when($request->filled('cedula'), fn ($q) => $q->whereHas('colaborador', fn ($c) => $c
                ->where('documento', 'like', User::normalizarDocumento($request->input('cedula')).'%')))
            ->when($request->filled('componente'), fn ($q) => $q->where('componente_id', $request->integer('componente')))
            ->when($nodo && $request->filled('nodo'), fn ($q) => $q->where('nodo_id', $request->integer('nodo')));
    }
}
