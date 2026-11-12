<?php

namespace App\Notifications;

use App\Models\ComentarioDocumento;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** «Fulano comentó en tu archivo»: el enlace lleva directo al archivo, a sus comentarios. */
class ComentarioEnDocumento extends Notification
{
    public function __construct(protected ComentarioDocumento $comentario)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $documento = $this->comentario->documento;

        return [
            'mensaje' => "{$this->comentario->autor->name} comentó en «{$documento->nombre}»: "
                .'«'.Str::limit($this->comentario->texto, 120).'»',
            // Relativa: la notificación sirve igual en pruebas y en producción.
            'url' => route('documentos.show', $documento, false).'#comentario-'.$this->comentario->id,
        ];
    }
}
