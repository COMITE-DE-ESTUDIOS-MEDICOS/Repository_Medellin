<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Http\Controllers\Controller;
use App\Models\Componente;
use App\Models\Marcacion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Quién está trabajando ahora: entradas sin salida, la más antigua primero. */
class EnTurnoController extends Controller
{
    public function index(Request $request): View
    {
        // Desde Edición: revisar los turnos (MarcacionPolicy).
        $this->authorize('viewAny', Marcacion::class);

        $abiertas = Marcacion::entradasAbiertas()
            ->with(['colaborador', 'nodo', 'componente', 'tipoTurno', 'puntoCritico'])
            ->when($request->filled('componente'), fn ($q) => $q->where('componente_id', $request->integer('componente')))
            ->when($request->filled('nodo'), fn ($q) => $q->where('nodo_id', $request->integer('nodo')))
            ->orderBy('marcada_at')
            ->get();

        return view('admin.turnos.en-turno', [
            'abiertas' => $abiertas,
            'componentes' => Componente::activos()->with(['nodos' => fn ($q) => $q->activos()])->orderBy('nombre')->get(),
        ]);
    }
}
