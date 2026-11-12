<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Models\ComentarioDocumento;
use App\Models\Documento;
use App\Models\User;
use App\Notifications\ComentarioEnDocumento;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Comentar un archivo («la foto está mal tomada, repítela»). Comenta quien
 * puede ver el archivo, y se enteran por la campana quien lo subió y quienes
 * ya habían comentado.
 */
class ComentarioDocumentoController extends Controller
{
    public function __construct(protected Auditor $auditor)
    {
    }

    public function store(Request $request, Documento $documento): RedirectResponse
    {
        $this->authorize('view', $documento);

        $datos = $request->validate(['texto' => ['required', 'string', 'max:2000']], attributes: ['texto' => 'comentario']);

        $comentario = $documento->comentarios()->create([
            'user_id' => $request->user()->id,
            'texto' => $datos['texto'],
        ]);

        $this->auditor->registrar(AccionAuditoria::DocumentoComentado, $documento, "Comentó en «{$documento->nombre}»");

        $this->interesados($documento, $request->user())
            ->each(fn (User $u) => $u->notify(new ComentarioEnDocumento($comentario)));

        return redirect()
            ->to(route('documentos.show', $documento).'#comentario-'.$comentario->id)
            ->with('exito', 'Comentario publicado.');
    }

    /** Lo borra quien lo escribió, o Coordinación en adelante. */
    public function destroy(Request $request, Documento $documento, ComentarioDocumento $comentario): RedirectResponse
    {
        abort_unless($comentario->documento_id === $documento->id, 404);
        abort_unless(
            $comentario->user_id === $request->user()->id || $request->user()->puedeGestionarEn($documento->dependencia_id),
            403,
        );

        $comentario->delete();

        return redirect()->to(route('documentos.show', $documento).'#comentarios')->with('exito', 'Comentario borrado.');
    }

    /**
     * Quién se entera: quien subió el archivo (o, si llegó por un enlace de
     * carga, quien creó el enlace) y quienes ya comentaron. Nunca el propio
     * autor, y nadie que no pueda ver el archivo.
     */
    protected function interesados(Documento $documento, User $autor)
    {
        $ids = $documento->comentarios()->pluck('user_id')
            ->push($documento->creado_por ?? $documento->recepcion?->enlace?->creado_por);

        return User::whereIn('id', $ids->filter()->unique())
            ->whereKeyNot($autor->id)
            ->get()
            ->filter(fn (User $u) => $u->can('view', $documento));
    }
}
