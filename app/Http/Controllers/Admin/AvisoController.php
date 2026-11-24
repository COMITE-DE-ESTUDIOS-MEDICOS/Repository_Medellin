<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Models\Componente;
use App\Models\Scopes\ComponenteScope;
use App\Services\Auditor;
use App\Services\Avisos;
use App\Services\ContextoDependencia;
use Illuminate\Database\Eloquent\Collection;
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
            'componentes' => $this->componentes(),
            'componentePorDefecto' => $this->contexto->componenteId(),
            // Los de esta dependencia y los de versión, que fueron para todos.
            'avisos' => Aviso::with('autor')
                ->where(fn ($q) => $q->where('dependencia_id', $this->contexto->id())->orWhereNull('dependencia_id'))
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    public function store(Request $request, Avisos $avisos, Auditor $auditor): RedirectResponse
    {
        $this->autorizar();

        $disponibles = $this->componentes();

        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'mensaje' => ['required', 'string', 'max:1000'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::enum(RolDependencia::class)],
            // DOC-25: a qué componentes va; al menos uno, activo y de esta dependencia.
            'componentes' => ['required', 'array', 'min:1'],
            'componentes.*' => ['integer', Rule::in($disponibles->modelKeys())],
        ], [
            'componentes.required' => 'Marca al menos un componente.',
        ]);

        $componentes = array_map('intval', $datos['componentes']);

        $aviso = $avisos->enviar(
            $datos['titulo'],
            $datos['mensaje'],
            $this->contexto->requerida(),
            $datos['roles'] ?? null,
            $request->user(),
            componentes: $componentes,
        );

        $auditor->registrar(AccionAuditoria::AvisoEnviado, $aviso, "Aviso «{$aviso->titulo}»", [
            'componentes' => $disponibles->whereIn('id', $componentes)->pluck('nombre')->all(),
            'roles' => collect($aviso->roles)->map(fn ($r) => RolDependencia::from($r)->etiqueta())->all() ?: 'Todos',
            'destinatarios' => $aviso->destinatarios,
        ]);

        return back()->with('exito', "Aviso enviado a {$aviso->destinatarios} ".($aviso->destinatarios === 1 ? 'persona.' : 'personas.'));
    }

    /**
     * Los componentes activos de la dependencia. Sin el scope de componente:
     * Administración elige entre todos, no solo aquel donde está parado.
     *
     * @return Collection<int, Componente>
     */
    protected function componentes(): Collection
    {
        return Componente::withoutGlobalScope(ComponenteScope::class)
            ->where('dependencia_id', $this->contexto->requerida()->id)
            ->where('activo', true)
            ->orderBy('id')
            ->get();
    }

    protected function autorizar(): void
    {
        abort_unless($this->contexto->puedeAdministrar(), 403, 'Solo Administración envía avisos.');
    }
}
