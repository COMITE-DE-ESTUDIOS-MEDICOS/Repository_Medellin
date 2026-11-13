<?php

namespace App\Notifications;

use App\Models\ComentarioDerivacion;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** DOC-23: «Comentario en el caso DC-012: …». El enlace lleva al comentario en la ficha. */
class ComentarioEnCaso extends Notification
{
    public function __construct(protected ComentarioDerivacion $comentario)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $caso = $this->comentario->derivacion;

        return [
            'mensaje' => "Comentario en el caso {$caso->codigo()} de {$this->comentario->autor->name}: "
                .'«'.Str::limit($this->comentario->texto, 120).'»',
            // Relativa: la notificación sirve igual en pruebas y en producción.
            'url' => route('admin.turnos.derivaciones.show', $caso, false).'#comentario-'.$this->comentario->id,
        ];
    }
}
