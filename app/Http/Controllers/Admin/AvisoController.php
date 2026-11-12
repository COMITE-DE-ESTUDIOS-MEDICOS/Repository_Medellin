<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RolDependencia;
use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Services\Avisos;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mensajes libres a los usuarios por la campana: un mantenimiento, un
 * recordatorio. Los de cada versión salen solos (avisos:version).
 */
class AvisoController extends Controller
{
    public function __construct(protected ContextoDependencia $contexto)
    {
    }

    public function index(): View
    {
        $this->autorizar();

        return view('admin.avisos.index', [
            'roles' => RolDependencia::cases(),
            // Los de esta dependencia y los de versión, que fueron para todos.
            'avisos' => Aviso::with('autor')
                ->where(fn ($q) => $q->where('dependencia_id', $this->contexto->id())->orWhereNull('dependencia_id'))
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    public function store(Request $request, Avisos $avisos): RedirectResponse
    {
        $this->autorizar();

        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'mensaje' => ['required', 'string', 'max:1000'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::enum(RolDependencia::class)],
        ]);

        $aviso = $avisos->enviar(
            $datos['titulo'],
            $datos['mensaje'],
            $this->contexto->requerida(),
            $datos['roles'] ?? null,
            $request->user(),
        );

        return back()->with('exito', "Aviso enviado a {$aviso->destinatarios} ".($aviso->destinatarios === 1 ? 'persona.' : 'personas.'));
    }

    protected function autorizar(): void
    {
        abort_unless($this->contexto->puedeAdministrar(), 403, 'Solo Administración envía avisos.');
    }
}
