<?php

namespace App\Http\Controllers;

use App\Models\Dependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Cambia la dependencia sobre la que trabaja un usuario con varias asignadas. */
class DependenciaActualController extends Controller
{
    public function update(Request $request, Dependencia $dependencia): RedirectResponse
    {
        abort_unless($request->user()->perteneceA($dependencia), 403);

        $request->session()->put('dependencia_id', $dependencia->id);

        return redirect()->route('inicio')
            ->with('exito', "Ahora estás en {$dependencia->nombre}.");
    }
}
