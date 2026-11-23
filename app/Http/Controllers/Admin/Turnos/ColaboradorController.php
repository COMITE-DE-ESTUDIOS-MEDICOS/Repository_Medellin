<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Enums\EstadoNovedad;
use App\Enums\OrigenColaborador;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarColaboradorRequest;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\TipoTurno;
use App\Models\User;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use App\Services\Turnos\Jornadas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Personas de campo: marcan turno y envían evidencias sin cuenta en
 * Documenta. Aquí Coordinación las busca por cédula, las corrige y confirma
 * a las que se registraron solas por el enlace. Nunca se borran.
 */
class ColaboradorController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Colaborador::class);

        $colaboradores = Colaborador::query()
            ->with(['componente', 'nodo'])
            ->when($request->filled('q'), fn (Builder $q) => $this->buscar($q, $request->string('q')->trim()->toString()))
            ->when($request->filled('componente'), fn (Builder $q) => $q->where('componente_id', $request->integer('componente')))
            ->when($request->filled('nodo'), fn (Builder $q) => $q->where('nodo_id', $request->integer('nodo')))
            ->when($request->input('verificacion') === 'pendientes', fn (Builder $q) => $q->whereNull('verificado_at'))
            ->when($request->input('estado', 'activos') !== 'todos', fn (Builder $q) => $q->where('activo', $request->input('estado', 'activos') === 'activos'))
            ->orderBy('nombre')
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('admin.turnos.colaboradores.index', [
            'colaboradores' => $colaboradores,
            'componentes' => $this->componentesConNodos(),
            'pendientes' => Colaborador::activos()->whereNull('verificado_at')->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Colaborador::class);

        return view('admin.turnos.colaboradores.create', ['componentes' => $this->componentesConNodos()]);
    }

    public function store(GuardarColaboradorRequest $request): RedirectResponse
    {
        $this->authorize('create', Colaborador::class);

        // Quien lo registra desde aquí es Coordinación: queda verificado.
        $colaborador = Colaborador::create($request->validated() + [
            'dependencia_id' => $this->contexto->requerida()->id,
            'origen' => OrigenColaborador::Admin,
            'verificado_at' => now(),
            'verificado_por' => $request->user()->id,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorRegistrado,
            $colaborador,
            "Registró a {$colaborador->nombre} como persona de campo",
        );

        return redirect()
            ->route('admin.turnos.colaboradores.show', $colaborador)
            ->with('exito', 'Persona registrada.');
    }

    public function show(Request $request, Colaborador $colaborador, Jornadas $jornadas): View
    {
        $this->authorize('view', $colaborador);

        $colaborador->load(['componente', 'nodo', 'verificador', 'tipoTurno', 'puntoCritico']);

        return view('admin.turnos.colaboradores.show', $this->quincena($request, $colaborador, $jornadas) + [
            'colaborador' => $colaborador,
            'ausencias' => $colaborador->ausencias()->with(['tipo', 'registrador'])->latest('desde')->limit(10)->get(),
            // D, VAC, I, P… del componente: los códigos que no son turno.
            'tiposAusencia' => TipoTurno::where('componente_id', $colaborador->componente_id)
                ->activos()->where('es_turno', false)->orderBy('orden')->get(),
            'recepciones' => $colaborador->recepciones()->with('documento')->latest()->limit(10)->get(),
        ]);
    }

    /**
     * Los turnos de una quincena (1–15 o 16–fin de mes), uno por fila: así la
     * ficha pesa lo mismo con dos semanas de marcas que con tres años. Las
     * anuladas van aparte, al historial; las marcas sueltas (una salida sin
     * entrada) se muestran para poder corregirlas.
     */
    protected function quincena(Request $request, Colaborador $colaborador, Jornadas $jornadas): array
    {
        $dia = rescue(fn () => $request->filled('q') ? Carbon::parse($request->query('q')) : null, null, false) ?? now();
        $desde = $dia->day <= 15 ? $dia->copy()->startOfMonth() : $dia->copy()->setDay(16)->startOfDay();
        $hasta = $dia->day <= 15 ? $dia->copy()->setDay(15)->endOfDay() : $dia->copy()->endOfMonth();
        $dePersona = fn (Builder $q) => $q->where('colaborador_id', $colaborador->id);

        // Un día antes: la salida del día 1 a las 4:30 es del turno de noche del día anterior.
        $todos = $jornadas->entre($desde->copy()->subDay(), $hasta, $dePersona);
        $turnos = $todos->filter(fn (array $t) => $t['entrada']->marcada_at->gte($desde))->reverse()->values();

        $vigentes = Marcacion::query()->vigentes()->tap($dePersona)->with('nodo')
            ->whereBetween('marcada_at', [$desde, $hasta])->get();
        $emparejadas = $todos->flatMap(fn (array $t) => [$t['entrada']->id, $t['salida']?->id])->filter()->flip();
        $sueltas = $vigentes->reject(fn (Marcacion $m) => isset($emparejadas[$m->id]))->sortByDesc('marcada_at')->values();

        $novedades = NovedadTurno::whereIn('marcacion_id', $turnos->flatMap(fn (array $t) => [$t['entrada']->id, $t['salida']?->id])->filter())
            ->where('estado', '!=', EstadoNovedad::Corregida->value)
            ->get()
            ->groupBy('marcacion_id');

        $turnos = $turnos->map(fn (array $t) => $t + [
            'trabajado' => $t['minutos'] !== null ? $colaborador->componente?->minutosTrabajados($t['minutos'], $t['tipo'] ?? null) ?? $t['minutos'] : null,
            'novedades' => collect([$novedades->get($t['entrada']->id), $novedades->get($t['salida']?->id)])->flatten()->filter(),
        ]);

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'turnos' => $turnos,
            'sueltas' => $sueltas,
            'anuladas' => Marcacion::query()->tap($dePersona)->whereNotNull('anulada_at')
                ->with('anulador')->whereBetween('marcada_at', [$desde, $hasta->copy()->addDay()])
                ->latest('marcada_at')->get(),
            // Sin salida y es su última marca: está en turno, no le falta la salida.
            'ultimaId' => $colaborador->marcaciones()->vigentes()->latest('marcada_at')->value('id'),
            'haMarcado' => $vigentes->isNotEmpty() || $colaborador->marcaciones()->exists(),
        ];
    }

    public function edit(Colaborador $colaborador): View
    {
        $this->authorize('update', $colaborador);

        return view('admin.turnos.colaboradores.edit', [
            'colaborador' => $colaborador,
            'componentes' => $this->componentesConNodos(),
        ]);
    }

    public function update(GuardarColaboradorRequest $request, Colaborador $colaborador): RedirectResponse
    {
        $this->authorize('update', $colaborador);

        $colaborador->update($request->validated());

        if ($colaborador->wasChanged()) {
            $this->auditor->registrar(
                AccionAuditoria::ColaboradorActualizado,
                $colaborador,
                "Actualizó los datos de {$colaborador->nombre}",
                ['cambios' => array_keys($colaborador->getChanges())],
            );
        }

        return redirect()
            ->route('admin.turnos.colaboradores.show', $colaborador)
            ->with('exito', 'Datos actualizados.');
    }

    public function verificar(Request $request, Colaborador $colaborador): RedirectResponse
    {
        $this->authorize('verificar', $colaborador);

        $colaborador->update(['verificado_at' => now(), 'verificado_por' => $request->user()->id]);

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorVerificado,
            $colaborador,
            "Verificó a {$colaborador->nombre}",
        );

        return back()->with('exito', "{$colaborador->nombre} quedó verificado.");
    }

    /**
     * Desactivar en vez de borrar: sus marcaciones lo referencian para
     * siempre. Desactivado, el enlace deja de reconocer su cédula.
     */
    public function estado(Colaborador $colaborador): RedirectResponse
    {
        $this->authorize('update', $colaborador);

        $colaborador->update(['activo' => ! $colaborador->activo]);
        $accion = $colaborador->activo ? 'Reactivó' : 'Desactivó';

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorActualizado,
            $colaborador,
            "{$accion} a {$colaborador->nombre}",
            ['cambios' => ['activo']],
        );

        return back()->with('exito', "{$colaborador->nombre}: ".($colaborador->activo ? 'reactivado.' : 'desactivado.'));
    }

    /**
     * Lo que se escribe puede ser una cédula (con o sin puntos) o un nombre.
     * Si parece cédula, se busca por prefijo sobre la cédula normalizada, y
     * la coincidencia exacta sale primero; si no, por nombre.
     */
    protected function buscar(Builder $consulta, string $texto): Builder
    {
        $cedula = User::normalizarDocumento($texto);

        if ($cedula !== '' && ctype_digit($cedula)) {
            return $consulta
                ->where('documento', 'like', $cedula.'%')
                ->orderByRaw('documento = ? desc', [$cedula]);
        }

        return $consulta->where(fn (Builder $q) => $q
            ->where('nombre', 'like', '%'.$texto.'%')
            ->orWhere('documento', 'like', $cedula.'%'));
    }

    protected function componentesConNodos()
    {
        return Componente::activos()->with([
            'nodos' => fn ($q) => $q->activos(),
            'tiposTurno' => fn ($q) => $q->activos()->where('es_turno', true),
            'puntosCriticos' => fn ($q) => $q->activos(),
        ])->orderBy('nombre')->get();
    }
}
