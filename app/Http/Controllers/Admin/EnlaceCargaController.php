<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarEnlaceCargaRequest;
use App\Models\Carpeta;
use App\Models\EnlaceCarga;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quién puede crear y revocar aquí no es solo "administración": un líder
 * también puede, pero limitado a las carpetas que lidera. La comprobación
 * fina vive en EnlaceCargaPolicy y en GuardarEnlaceCargaRequest, no aquí.
 */
class EnlaceCargaController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', EnlaceCarga::class);

        $usuario = auth()->user();
        $dependencia = $this->contexto->requerida();

        $enlaces = EnlaceCarga::query()
            ->with(['carpeta', 'creador'])
            // Quien gestiona ve todos los de la dependencia; un líder solo
            // los que él mismo generó.
            ->when(! $usuario->puedeGestionarEn($dependencia->id), fn ($q) => $q->where('creado_por', $usuario->id))
            ->latest()
            ->paginate(config('repositorio.por_pagina'));

        return view('admin.enlaces.index', ['enlaces' => $enlaces]);
    }

    /**
     * El parámetro 'carpeta' llega del botón que hay en cada carpeta del
     * explorador: deja el destino ya elegido para que crear el enlace sea
     * un clic y un botón.
     */
    public function create(Request $peticion): View
    {
        $this->authorize('create', EnlaceCarga::class);

        $usuario = auth()->user();
        $dependencia = $this->contexto->requerida();
        // Edición elige cualquier carpeta; un líder sin rol de edición, solo las suyas.
        $esAdministrador = $usuario->puedeEditarEn($dependencia->id);

        $carpetas = $esAdministrador
            ? Carpeta::visiblesPara(auth()->user())->activas()->orderBy('nombre')->get()
            : $usuario->carpetasLideradas()->where('dependencia_id', $dependencia->id)->activas()->orderBy('nombre')->get();

        $sugerida = $peticion->filled('carpeta')
            ? Carpeta::where('uuid', $peticion->string('carpeta'))->first()
            : null;

        return view('admin.enlaces.create', [
            'carpetas' => $carpetas,
            'esAdministrador' => $esAdministrador,
            'componentes' => \App\Models\Componente::activos()->orderBy('nombre')->get(),
            // Solo si de verdad puede usarla: el uuid viene de la URL.
            'carpetaPorDefecto' => $carpetas->firstWhere('id', $sugerida?->id)?->id,
        ]);
    }

    public function store(GuardarEnlaceCargaRequest $request): RedirectResponse
    {
        $this->authorize('create', EnlaceCarga::class);

        $dependencia = $this->contexto->requerida();
        $token = EnlaceCarga::generarToken();

        $enlace = EnlaceCarga::create([
            'token_hash' => EnlaceCarga::hashDe($token),
            'token_cifrado' => $token,
            'dependencia_id' => $dependencia->id,
            'carpeta_id' => $request->input('carpeta_id'),
            'componente_id' => $request->input('componente_id'),
            'proposito' => $request->input('proposito'),
            'expira_at' => $request->input('expira_at'),
            'max_usos' => $request->input('max_usos'),
            'creado_por' => $request->user()->id,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::EnlaceCreado,
            $enlace,
            "Creó un enlace de carga hacia «{$enlace->carpeta?->nombre}»",
        );

        // La URL solo se puede volver a ver aquí, en esta respuesta: después
        // de este redirect el token descifrado no vuelve a viajar al navegador.
        return redirect()
            ->route('admin.enlaces.index')
            ->with('enlace_url', $enlace->url())
            ->with('exito', 'Enlace creado.');
    }

    /**
     * La fecha de envío se pone sola al crear el enlace, porque generarlo
     * suele ser entregarlo. Cuando no —se generó un viernes y se entregó el
     * lunes—, el tiempo de respuesta del remitente saldría inflado en tres
     * días. Esto es el arreglo, y queda auditado porque de ese dato salen
     * informes.
     */
    public function corregirFechaEnvio(Request $peticion, EnlaceCarga $enlace): RedirectResponse
    {
        $this->authorize('corregirEnvio', $enlace);

        $datos = $peticion->validate([
            'enviado_at' => [
                'required',
                'date',
                // No se pudo entregar antes de existir ni se entrega mañana.
                'after_or_equal:'.$enlace->created_at->toDateTimeString(),
                'before_or_equal:now',
            ],
        ], [
            'enviado_at.required' => 'Escribe la fecha en que se entregó el enlace.',
            'enviado_at.date' => 'La fecha de envío no es una fecha válida.',
            'enviado_at.after_or_equal' => 'El enlace no pudo entregarse antes de haberse creado.',
            'enviado_at.before_or_equal' => 'La fecha de envío no puede estar en el futuro.',
        ]);

        $antes = $enlace->enviado_at?->fechaHora() ?? 'sin fecha';

        $enlace->update(['enviado_at' => $datos['enviado_at']]);

        $this->auditor->registrar(
            AccionAuditoria::EnlaceFechaEnvioCorregida,
            $enlace,
            "Corrigió la fecha de envío del enlace hacia «{$enlace->carpeta?->nombre}»",
            ['antes' => $antes, 'despues' => $enlace->enviado_at->fechaHora()],
        );

        return back()->with('exito', 'Fecha de envío corregida.');
    }

    public function revocar(EnlaceCarga $enlace): RedirectResponse
    {
        $this->authorize('revocar', $enlace);

        $enlace->revocar();

        $this->auditor->registrar(
            AccionAuditoria::EnlaceRevocado,
            $enlace,
            "Revocó el enlace de carga hacia «{$enlace->carpeta?->nombre}»",
        );

        return back()->with('exito', 'Enlace revocado.');
    }
}
