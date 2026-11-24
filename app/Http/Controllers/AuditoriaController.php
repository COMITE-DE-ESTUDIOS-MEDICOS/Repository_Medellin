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

        // DOC-25 (PA-06): Coordinación ve lo de su componente; Administración y
        // el superadministrador, todo. El filtro «Usuario» ofrece solo a las
        // personas de su componente.
        $delComponente = $this->contexto->puedeAdministrar() ? null : DB::table('dependencia_usuario')
            ->select('user_id')
            ->where('dependencia_id', $dependencia->id)
            // Sin componente no ve nada (CA-15): null no debe encontrar a Administración.
            ->where('componente_id', $this->contexto->componenteId() ?? 0);

        // Los registros, por el componente que guardan (PA-06, Edwar 2026-11-24):
        // así entra lo que llega por la vía pública y lo automático. Los que no
        // tienen (anteriores a DOC-25 y los de documentos por enlace) son del
        // componente que tiene Documentos, que es uno solo.
        $componente = $this->contexto->componenteId() ?? 0;
        $conDocumentos = $this->contexto->componente()?->tieneModulo('documentos') ?? false;

        $registros = Auditoria::query()
            ->where('dependencia_id', $dependencia->id)
            ->when($delComponente, fn ($q) => $q->where(fn ($q) => $q->where('componente_id', $componente)
                ->when($conDocumentos, fn ($q) => $q->orWhereNull('componente_id'))))
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
