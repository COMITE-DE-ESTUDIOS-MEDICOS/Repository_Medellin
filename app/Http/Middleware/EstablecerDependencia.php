<?php

namespace App\Http\Middleware;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la dependencia activa del usuario y la deja disponible para
 * controladores y vistas. Si el usuario no pertenece a ninguna, no puede
 * ver nada: es la primera línea del aislamiento entre dependencias.
 */
class EstablecerDependencia
{
    public function __construct(protected ContextoDependencia $contexto)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return $next($request);
        }

        $disponibles = $usuario->es_superadmin
            ? Dependencia::where('activa', true)->orderBy('nombre')->get()
            : $usuario->dependencias()->where('activa', true)->orderBy('nombre')->get();

        $seleccionada = $disponibles->firstWhere('id', $request->session()->get('dependencia_id'))
            ?? $disponibles->first();

        if ($seleccionada !== null) {
            $request->session()->put('dependencia_id', $seleccionada->id);
        }

        $this->contexto->establecer($seleccionada);

        View::share('dependenciaActual', $seleccionada);
        View::share('dependenciasDisponibles', $disponibles);
        View::share('rolActual', $usuario->rolEn($seleccionada));

        $this->establecerComponente($request, $usuario, $seleccionada);

        return $next($request);
    }

    /**
     * DOC-25: Lectura, Edición y Coordinación trabajan en el componente de su
     * pivote (si está activo; si no, en ninguno: CA-15). Administración y el
     * superadministrador lo eligen (sesión), empezando por el primero con módulos.
     */
    protected function establecerComponente(Request $request, User $usuario, ?Dependencia $dependencia): void
    {
        // Una sola consulta. Va después de establecer(): el contexto aún no filtra por componente.
        $activos = $dependencia === null ? collect() : Componente::where('dependencia_id', $dependencia->id)
            ->where('activo', true)->orderBy('id')->get();

        $elige = $usuario->es_superadmin || $usuario->rolEn($dependencia) === RolDependencia::Administracion;

        if ($elige) {
            $componente = $activos->firstWhere('id', $request->session()->get('componente_id'))
                ?? $activos->first(fn (Componente $c) => ! empty($c->modulos))
                ?? $activos->first();
            $request->session()->put('componente_id', $componente?->id);
        } else {
            $componente = $activos->firstWhere('id', $usuario->componenteEn($dependencia));
        }

        $this->contexto->establecerComponente($componente);

        View::share('componenteActual', $componente);
        View::share('componentesDisponibles', $elige ? $activos : collect());
    }
}
