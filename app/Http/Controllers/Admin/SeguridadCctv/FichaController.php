<?php

namespace App\Http\Controllers\Admin\SeguridadCctv;

use App\Enums\AccionAuditoria;
use App\Models\CasoCctv;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** DOC-33: la ficha del caso, cerrar, anular y abrir sus imágenes (con permiso y auditoría). */
class FichaController
{
    public function __construct(
        protected Auditor $auditor,
        protected AlmacenamientoDocumentos $almacenamiento,
    ) {}

    public function show(CasoCctv $caso): View
    {
        Gate::authorize('view', $caso);

        return view('admin.seguridad-cctv.show', [
            'caso' => $caso->load(['asignado', 'colaborador', 'nodo', 'creador', 'cerrador', 'anulador']),
            'archivos' => array_filter($caso->archivos()),
        ]);
    }

    public function cerrar(CasoCctv $caso): RedirectResponse
    {
        Gate::authorize('cerrar', $caso);

        $caso->update(['estado' => CasoCctv::CERRADO, 'cerrado_por' => auth()->id(), 'cerrado_at' => now()]);
        $this->auditor->registrar(AccionAuditoria::CasoCctvCerrado, $caso, "Cerró {$caso->codigo()}");

        return back()->with('exito', "{$caso->codigo()} quedó cerrado.");
    }

    public function anular(Request $request, CasoCctv $caso): RedirectResponse
    {
        Gate::authorize('anular', $caso);

        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:500']], [], ['motivo' => 'motivo'])['motivo'];

        $caso->update([
            'estado' => CasoCctv::ANULADO,
            'anulado_por' => auth()->id(),
            'anulado_at' => now(),
            'motivo_anulacion' => $motivo,
        ]);
        $this->auditor->registrar(AccionAuditoria::CasoCctvAnulado, $caso, "Anuló {$caso->codigo()}: {$motivo}", ['motivo' => $motivo]);

        return back()->with('exito', "{$caso->codigo()} quedó anulado.");
    }

    /** Soporte o evidencia en línea, sin caché; solo quien puede ver imágenes (CA-19). */
    public function archivo(CasoCctv $caso, string $campo): StreamedResponse
    {
        Gate::authorize('verImagenes', $caso);
        abort_unless(array_key_exists($campo, $caso->archivos()), 404);

        $version = $caso->{$campo}()->withoutGlobalScopes()->with('versionActual')->first()?->versionActual;
        abort_if($version === null || ! $this->almacenamiento->existe($version), 404);

        $this->auditor->registrar(AccionAuditoria::CasoCctvArchivoAbierto, $caso, "Abrió {$campo} de {$caso->codigo()}", ['campo' => $campo]);

        return Storage::disk($this->almacenamiento->disco())->response($version->ruta, $version->nombre_original, [
            'Content-Type' => $version->mime,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
