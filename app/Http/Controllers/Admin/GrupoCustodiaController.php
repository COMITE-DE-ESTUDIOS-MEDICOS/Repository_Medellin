<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\GrupoCustodia;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Los grupos de custodia («Líderes Nodo 6») y sus miembros. Una carpeta se
 * pone bajo custodia de un grupo desde «Editar carpeta». Todo de
 * Coordinación en adelante: el custodio no se da ni se pasa la custodia solo.
 */
class GrupoCustodiaController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function index(): View
    {
        $this->autorizar();

        return view('admin.custodia.index', [
            'grupos' => GrupoCustodia::with(['usuarios', 'carpetas'])->orderBy('nombre')->get(),
            // Cualquiera de la dependencia puede ser custodio, también Lectura
            // (para revisar sin modificar).
            'usuarios' => $this->contexto->requerida()->usuarios()->where('activo', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autorizar();

        $grupo = GrupoCustodia::create($this->validar($request) + ['dependencia_id' => $this->contexto->requerida()->id]);
        $grupo->usuarios()->sync($request->input('usuarios', []));

        $this->auditar($grupo, 'Creó el grupo de custodia');

        return back()->with('exito', "Grupo «{$grupo->nombre}» creado. Ahora asígnale carpetas desde «Editar carpeta».");
    }

    public function update(Request $request, GrupoCustodia $grupo): RedirectResponse
    {
        $this->autorizar();

        $grupo->update($this->validar($request, $grupo));
        $grupo->usuarios()->sync($request->input('usuarios', []));

        $this->auditar($grupo, 'Actualizó el grupo de custodia');

        return back()->with('exito', "Grupo «{$grupo->nombre}» actualizado.");
    }

    protected function validar(Request $request, ?GrupoCustodia $grupo = null): array
    {
        $dependenciaId = $this->contexto->id();

        return $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('grupos_custodia', 'nombre')->where('dependencia_id', $dependenciaId)->ignore($grupo?->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'usuarios' => ['nullable', 'array'],
            // Solo personas de esta dependencia.
            'usuarios.*' => [Rule::exists('dependencia_usuario', 'user_id')->where('dependencia_id', $dependenciaId)],
        ], ['nombre.unique' => 'Ya hay un grupo con ese nombre.'], ['usuarios.*' => 'miembro']) ;
    }

    protected function auditar(GrupoCustodia $grupo, string $que): void
    {
        $grupo->load('usuarios');

        $this->auditor->registrar(
            AccionAuditoria::GrupoCustodiaGuardado,
            $grupo,
            "{$que} «{$grupo->nombre}»",
            ['miembros' => $grupo->usuarios->pluck('name')->all()],
        );
    }

    protected function autorizar(): void
    {
        abort_unless($this->contexto->puedeGestionar(), 403, 'Los grupos de custodia los maneja Coordinación.');
    }
}
