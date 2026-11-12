<?php

namespace App\Notifications;

use App\Models\Aviso;
use Illuminate\Notifications\Notification;

/** Un aviso de Administración o de una versión nueva, por la campana. */
class AvisoDelSistema extends Notification
{
    public function __construct(protected Aviso $aviso)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'mensaje' => "{$this->aviso->titulo}: {$this->aviso->mensaje}",
            'aviso_id' => $this->aviso->id,
        ];
    }
}
