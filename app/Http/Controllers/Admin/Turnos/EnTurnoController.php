<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Http\Controllers\Controller;
use App\Models\Marcacion;
use Illuminate\View\View;

/** Quién está trabajando ahora: entradas sin salida, la más antigua primero. */
class EnTurnoController extends Controller
{
    public function index(): View
    {
        // Desde Edición: revisar los turnos (MarcacionPolicy).
        $this->authorize('viewAny', Marcacion::class);

        // La lista (páginas, búsqueda y filtros) es la tabla App\Livewire\Turnos\EnTurno.
        return view('admin.turnos.en-turno', ['total' => Marcacion::entradasAbiertas()->count()]);
    }
}
