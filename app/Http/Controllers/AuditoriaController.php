<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Services\ContextoDependencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function __construct(protected ContextoDependencia $contexto)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($this->contexto->puedeGestionar(), 403, 'No tienes permiso para consultar la auditoría.');

        $dependencia = $this->contexto->requerida();

        // DOC-25 (PA-06): Coordinación ve lo que hicieron los usuarios de su
        // componente; Administración y el superadministrador, todo. Subconsulta
        // y no junta: la lista y el filtro «Usuario» salen de la misma.
        $delComponente = $this->contexto->puedeAdministrar() ? null : DB::table('dependencia_usuario')
            ->select('user_id')
            ->where('dependencia_id', $dependencia->id)
            // Sin componente no ve nada (CA-15): null no debe encontrar a Administración.
            ->where('componente_id', $this->contexto->componenteId() ?? 0);

        $registros = Auditoria::query()
            ->where('dependencia_id', $dependencia->id)
            ->when($delComponente, fn ($q) => $q->whereIn('user_id', $delComponente))
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
            'usuarios' => $dependencia->usuarios()
                ->when($delComponente, fn ($q) => $q->whereIn('users.id', $delComponente))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
