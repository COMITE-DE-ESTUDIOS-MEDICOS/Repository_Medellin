<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarEnlaceTurnoRequest;
use App\Models\Componente;
use App\Models\EnlaceTurno;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * El enlace compartido con el que se marca turno. Sale como link para
 * WhatsApp y como QR para imprimir: los dos son el mismo enlace.
 *
 * A diferencia del enlace de carga, este sí se vuelve a mostrar completo:
 * es de larga duración, se imprime y se pega, y sin identidad propia no
 * sirve para subir nada en nombre de nadie.
 */
class EnlaceTurnoController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', EnlaceTurno::class);

        return view('admin.turnos.enlaces.index', [
            'enlaces' => EnlaceTurno::with(['componente', 'nodo', 'creador'])
                ->withCount(['marcaciones', 'derivaciones', 'supervisiones'])
                ->latest()
                ->paginate(config('repositorio.por_pagina')),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', EnlaceTurno::class);

        return view('admin.turnos.enlaces.create', [
            'componentes' => Componente::activos()->with([
                'nodos' => fn ($q) => $q->activos(),
                'puntosCriticos' => fn ($q) => $q->activos(),
            ])->orderBy('nombre')->get(),
        ]);
    }

    public function store(GuardarEnlaceTurnoRequest $request): RedirectResponse
    {
        $this->authorize('create', EnlaceTurno::class);

        $token = EnlaceTurno::generarToken();

        $enlace = EnlaceTurno::create([
            'token_hash' => EnlaceTurno::hashDe($token),
            'token_cifrado' => $token,
            'dependencia_id' => $this->contexto->requerida()->id,
            'tipo' => $request->input('tipo') ?: 'turno',
            'componente_id' => $request->integer('componente_id'),
            'nodo_id' => $request->input('nodo_id'),
            'punto_critico_id' => $request->input('punto_critico_id'),
            'nombre' => $request->input('nombre'),
            'expira_at' => $request->filled('expira_at') ? Carbon::parse($request->input('expira_at'))->endOfDay() : null,
            'creado_por' => $request->user()->id,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::EnlaceTurnoCreado,
            $enlace,
            "Creó un enlace ({$enlace->etiquetaTipo()}) para {$this->dondeDe($enlace)}",
        );

        return redirect()
            ->route('admin.turnos.enlaces.show', $enlace)
            ->with('exito', 'Enlace creado. Cópialo o imprime su QR.');
    }

    public function show(EnlaceTurno $enlace): View
    {
        $this->authorize('view', $enlace);

        $enlace->load(['componente', 'nodo', 'creador']);

        return view('admin.turnos.enlaces.show', [
            'enlace' => $enlace,
            'donde' => $this->dondeDe($enlace),
        ]);
    }

    /** Página suelta para imprimir: solo el QR, dónde va y cómo se usa. */
    public function imprimir(EnlaceTurno $enlace): View
    {
        $this->authorize('view', $enlace);

        return view('admin.turnos.enlaces.imprimir', [
            'enlace' => $enlace->load(['componente', 'nodo']),
            'donde' => $this->dondeDe($enlace),
        ]);
    }

    public function revocar(EnlaceTurno $enlace): RedirectResponse
    {
        $this->authorize('revocar', $enlace);

        $enlace->revocar();

        $this->auditor->registrar(
            AccionAuditoria::EnlaceTurnoRevocado,
            $enlace,
            "Revocó el enlace de turno de {$this->dondeDe($enlace)}",
        );

        return back()->with('exito', 'Enlace revocado: el QR impreso deja de funcionar.');
    }

    protected function dondeDe(EnlaceTurno $enlace): string
    {
        return $enlace->componente->nombre
            .($enlace->nodo ? ' · '.$enlace->nodo->nombre : '')
            .($enlace->puntoCritico ? ' · '.$enlace->puntoCritico->nombre : '');
    }
}
