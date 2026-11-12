<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Services\ContextoDependencia;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function __construct(protected ContextoDependencia $contexto)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($this->contexto->puedeGestionar(), 403, 'No tienes permiso para consultar la auditoría.');

        $registros = Auditoria::query()
            ->where('dependencia_id', $this->contexto->requerida()->id)
            // Por el nombre del archivo o de quien lo envió: va en la descripción.
            ->when($request->filled('q'), fn ($q) => $q->where('descripcion', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->string('accion')))
            ->when($request->filled('usuario'), fn ($q) => $q->where('user_id', $request->integer('usuario')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('hasta')))
            ->with('usuario')
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('auditorias.index', [
            'registros' => $registros,
            'usuarios' => $this->contexto->requerida()->usuarios()->orderBy('name')->get(),
        ]);
    }
}
