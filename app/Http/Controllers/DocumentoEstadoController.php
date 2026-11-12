<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Models\Documento;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Inactivación y reactivación: exclusivas de administración.
 * El registro nunca se borra de la base de datos, solo deja de ser visible
 * para lectores y editores.
 */
class DocumentoEstadoController extends Controller
{
    public function __construct(protected Auditor $auditor)
    {
    }

    public function inactivar(Request $request, Documento $documento): RedirectResponse
    {
        $this->authorize('inactivar', $documento);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ], attributes: ['motivo' => 'motivo de la inactivación']);

        $documento->update([
            'activo' => false,
            'motivo_inactivacion' => $datos['motivo'],
            'inactivado_at' => now(),
            'inactivado_por' => $request->user()->id,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::DocumentoInactivado,
            $documento,
            "Inactivó «{$documento->nombre}»",
            ['motivo' => $datos['motivo']],
        );

        if ($request->user()->cannot('view', $documento)) {
            return redirect()
                ->route('documentos.index', ['carpeta' => $documento->carpeta?->uuid])
                ->with('exito', "Inactivaste «{$documento->nombre}». Administración puede reactivarlo.");
        }

        return back()->with('exito', 'Documento inactivado.');
    }

    public function reactivar(Documento $documento): RedirectResponse
    {
        $this->authorize('reactivar', $documento);

        $documento->update([
            'activo' => true,
            'motivo_inactivacion' => null,
            'inactivado_at' => null,
            'inactivado_por' => null,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::DocumentoReactivado,
            $documento,
            "Reactivó «{$documento->nombre}»",
        );

        return back()->with('exito', 'Documento reactivado.');
    }
}
