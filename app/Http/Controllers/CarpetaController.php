<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Http\Requests\GuardarCarpetaRequest;
use App\Models\Carpeta;
use App\Models\GrupoCustodia;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CarpetaController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
        protected AlmacenamientoDocumentos $almacenamiento,
    ) {
    }

    public function create(Request $request): View
    {
        $this->autorizarEdicion();

        return view('carpetas.create', [
            'carpetaActual' => $request->filled('carpeta')
                ? Carpeta::where('uuid', $request->string('carpeta'))->first()
                : null,
            'carpetas' => Carpeta::visiblesPara(auth()->user())->activas()->orderBy('nombre')->get(),
        ]);
    }

    public function store(GuardarCarpetaRequest $request): RedirectResponse
    {
        $this->autorizarEdicion();

        $carpeta = Carpeta::create([
            'dependencia_id' => $this->contexto->requerida()->id,
            'carpeta_id' => $request->input('carpeta_id'),
            'nombre' => $request->string('nombre'),
            'descripcion' => $request->input('descripcion'),
            'creado_por' => $request->user()->id,
        ]);

        $this->auditor->registrar(AccionAuditoria::CarpetaCreada, $carpeta, "Creó la carpeta «{$carpeta->nombre}»");

        return redirect()
            ->route('documentos.index', ['carpeta' => $carpeta->uuid])
            ->with('exito', 'Carpeta creada.');
    }

    public function edit(Carpeta $carpeta): View
    {
        $this->authorize('update', $carpeta);

        return view('carpetas.edit', [
            'carpeta' => $carpeta,
            'carpetas' => Carpeta::visiblesPara(auth()->user())->activas()->whereKeyNot($carpeta->id)->orderBy('nombre')->get(),
            // Custodia: la lista de grupos solo para quien la asigna; la
            // heredada, para explicar por qué una subcarpeta ya está custodiada.
            'grupos' => $this->contexto->puedeGestionar() ? GrupoCustodia::orderBy('nombre')->get() : collect(),
            'custodia' => $carpeta->custodiaDe(),
        ]);
    }

    public function update(GuardarCarpetaRequest $request, Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('update', $carpeta);

        $nuevoPadreId = $request->input('carpeta_id');

        if ($nuevoPadreId !== null) {
            $nuevoPadre = Carpeta::find($nuevoPadreId);

            // Una carpeta no puede colgarse de sí misma ni de una descendiente.
            if ($nuevoPadre && ($nuevoPadre->id === $carpeta->id || $carpeta->esAncestroDe($nuevoPadre))) {
                return back()->withInput()->withErrors([
                    'carpeta_id' => 'No puedes mover una carpeta dentro de sí misma o de una de sus subcarpetas.',
                ]);
            }
        }

        $carpeta->update([
            'nombre' => $request->string('nombre'),
            'descripcion' => $request->input('descripcion'),
            'carpeta_id' => $nuevoPadreId,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::CarpetaActualizada,
            $carpeta,
            "Actualizó la carpeta «{$carpeta->nombre}»",
        );

        // La custodia solo la asigna Coordinación en adelante. A quien no
        // puede, ni se le muestra el campo ni se le hace caso si lo manda.
        if ($this->contexto->puedeGestionar() && $request->has('grupo_custodia_id')) {
            $this->asignarCustodia($carpeta, $request->input('grupo_custodia_id'));
        }

        return redirect()
            ->route('documentos.index', ['carpeta' => $carpeta->uuid])
            ->with('exito', 'Carpeta actualizada.');
    }

    /**
     * Copia la carpeta con sus subcarpetas y documentos dentro de otra. Solo
     * lo activo: copiar no resucita lo que alguien retiró de la vista.
     */
    public function copiar(Request $request, Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('view', $carpeta);
        $this->authorize('update', $carpeta);

        $datos = $request->validate([
            'carpeta_id' => [
                'nullable',
                Rule::exists('carpetas', 'id')
                    ->where('dependencia_id', $carpeta->dependencia_id)
                    ->where('activa', true),
                new \App\Rules\CarpetaAccesible,
            ],
        ]);

        $destino = isset($datos['carpeta_id']) ? Carpeta::find($datos['carpeta_id']) : null;

        // Dentro de sí misma se copiaría sin fin.
        if ($destino && ($destino->id === $carpeta->id || $carpeta->esAncestroDe($destino))) {
            return back()->withErrors([
                'carpeta_id' => 'No puedes copiar una carpeta dentro de sí misma o de una de sus subcarpetas.',
            ]);
        }

        [$copia, $documentos] = DB::transaction(
            fn () => $this->almacenamiento->copiarCarpeta($carpeta, $destino?->id)
        );

        $this->auditor->registrar(
            AccionAuditoria::CarpetaCopiada,
            $copia,
            "Copió la carpeta «{$carpeta->nombre}» a «".($destino?->nombre ?? 'Raíz')."» ({$documentos} documentos)",
            ['origen' => $carpeta->uuid, 'documentos' => $documentos],
        );

        return redirect()
            ->route('documentos.index', ['carpeta' => $copia->uuid])
            ->with('exito', "Carpeta copiada con {$documentos} documentos.");
    }

    public function inactivar(Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('inactivar', $carpeta);

        $carpeta->update(['activa' => false]);

        $this->auditor->registrar(
            AccionAuditoria::CarpetaInactivada,
            $carpeta,
            "Inactivó la carpeta «{$carpeta->nombre}»",
        );

        return redirect()
            ->route('documentos.index', ['carpeta' => $carpeta->carpeta_id
                ? $carpeta->padre?->uuid
                : null])
            ->with('exito', 'Carpeta inactivada.');
    }

    /** Vuelve a mostrarla a lectores y editores. Nada se había borrado. */
    public function reactivar(Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('reactivar', $carpeta);

        $carpeta->update(['activa' => true]);

        $this->auditor->registrar(
            AccionAuditoria::CarpetaReactivada,
            $carpeta,
            "Reactivó la carpeta «{$carpeta->nombre}»",
        );

        // A diferencia de inactivar, aquí sí se entra en ella: ya se ve.
        return redirect()
            ->route('documentos.index', ['carpeta' => $carpeta->uuid])
            ->with('exito', 'Carpeta reactivada.');
    }

    protected function asignarCustodia(Carpeta $carpeta, mixed $grupoId): void
    {
        $grupoId = $grupoId ? (int) $grupoId : null;

        if ($carpeta->grupo_custodia_id === $grupoId) {
            return;
        }

        $carpeta->update(['grupo_custodia_id' => $grupoId]);
        $grupo = $grupoId ? GrupoCustodia::find($grupoId) : null;

        $this->auditor->registrar(
            AccionAuditoria::CarpetaCustodia,
            $carpeta,
            $grupo
                ? "Puso la carpeta «{$carpeta->nombre}» bajo custodia de «{$grupo->nombre}»"
                : "Quitó la custodia de la carpeta «{$carpeta->nombre}»",
            ['grupo' => $grupoId],
        );
    }

    protected function autorizarEdicion(): void
    {
        abort_unless($this->contexto->puedeEditar(), 403, 'No tienes permisos de edición en esta dependencia.');
    }
}
