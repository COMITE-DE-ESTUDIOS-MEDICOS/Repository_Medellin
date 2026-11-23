<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\ComentarioDerivacion;
use App\Models\Derivacion;
use App\Models\User;
use App\Notifications\ComentarioEnCaso;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * DOC-23: comentarios en la ficha del caso. Coordinación deja indicaciones,
 * quien creó el caso responde. Comentar no cambia nada del caso.
 */
class ComentarioDerivacionController extends Controller
{
    public function __construct(protected Auditor $auditor)
    {
    }

    public function store(Request $request, Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('comentar', $derivacion);

        $datos = $request->validate(['texto' => ['required', 'string', 'max:2000']], [
            'texto.required' => 'Escribe el comentario.',
            'texto.max' => 'El comentario no puede pasar de 2000 caracteres.',
        ]);

        $comentario = $derivacion->comentarios()->create([
            'user_id' => $request->user()->id,
            'texto' => $datos['texto'],
        ])->setRelation('derivacion', $derivacion)->setRelation('autor', $request->user());

        $this->auditor->registrar(AccionAuditoria::CasoComentado, $derivacion, "Comentó en el caso {$derivacion->codigo()}", [
            'comentario_id' => $comentario->id,
        ]);

        $this->interesados($derivacion, $request->user())
            ->each(fn (User $u) => $u->notify(new ComentarioEnCaso($comentario)));

        return redirect()
            ->to(route('admin.turnos.derivaciones.show', $derivacion).'#comentario-'.$comentario->id)
            ->with('exito', 'Comentario publicado.');
    }

    public function destroy(Derivacion $derivacion, ComentarioDerivacion $comentario): RedirectResponse
    {
        abort_unless($comentario->derivacion_id === $derivacion->id, 404);
        $this->authorize('borrarComentario', [$derivacion, $comentario]);

        $comentario->delete();

        $this->auditor->registrar(AccionAuditoria::CasoComentarioBorrado, $derivacion, "Borró un comentario del caso {$derivacion->codigo()}", [
            'comentario_id' => $comentario->id,
            'texto' => $comentario->texto,
        ]);

        return redirect()
            ->to(route('admin.turnos.derivaciones.show', $derivacion).'#comentarios')
            ->with('exito', 'Comentario borrado.');
    }

    /**
     * Quién se entera: quien creó el caso y quienes ya comentaron. Nunca el
     * propio autor, y nadie que ya no pueda ver el caso.
     */
    protected function interesados(Derivacion $derivacion, User $autor): Collection
    {
        $ids = $derivacion->comentarios()->pluck('user_id')->push($derivacion->creado_por);

        return User::whereIn('id', $ids->filter()->unique())
            ->whereKeyNot($autor->id)
            ->get()
            ->filter(fn (User $u) => $u->can('view', $derivacion));
    }
}
