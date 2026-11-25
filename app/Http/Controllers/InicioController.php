<?php

namespace App\Http\Controllers;

use App\Models\Componente;
use App\Models\Scopes\ComponenteScope;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DOC-25 (plan D7): la llegada. Lleva al primer módulo del componente que el
 * usuario puede abrir (Documentos y Usuarios HC para todos, DOC-26 D2; los demás desde Edición, como el
 * menú); si no hay ninguno, la bienvenida. El selector de componente de
 * Administración y el superadministrador vive aquí también (PA-05).
 */
class InicioController extends Controller
{
    public function __invoke(ContextoDependencia $contexto): RedirectResponse|View
    {
        $componente = $contexto->componente();

        foreach (Componente::MODULOS as $modulo => [, $ruta]) {
            if ($componente?->tieneModulo($modulo) && (in_array($modulo, ['documentos', 'usuarios_hc'], true) || $contexto->rol()?->puedeEditar())) {
                return redirect()->route($ruta);
            }
        }

        return view('inicio', ['sinComponente' => $componente === null]);
    }

    public function cambiarComponente(Request $request, ContextoDependencia $contexto, int $componente): RedirectResponse
    {
        abort_unless($contexto->rol()?->puedeAdministrar(), 403);

        // Sin el scope de componente (salto a propósito, plan D4): se elige uno distinto
        // del actual. El de dependencia sigue: solo los de la dependencia actual.
        $elegido = Componente::withoutGlobalScope(ComponenteScope::class)
            ->where('activo', true)->findOrFail($componente);

        $request->session()->put('componente_id', $elegido->id);

        return redirect()->route('inicio')->with('exito', "Ahora estás en {$elegido->nombre}.");
    }
}
