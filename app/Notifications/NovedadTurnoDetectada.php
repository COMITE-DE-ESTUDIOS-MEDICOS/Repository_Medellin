<?php

namespace App\Notifications;

use App\Models\NovedadTurno;
use Illuminate\Notifications\Notification;

/** Avisa a Coordinación que un turno tiene una novedad por validar. */
class NovedadTurnoDetectada extends Notification
{
    public function __construct(protected NovedadTurno $novedad)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'mensaje' => "{$this->novedad->tipo->etiqueta()}: {$this->novedad->colaborador->nombre}. {$this->novedad->detalle}",
            // Relativa: la notificación sirve igual en pruebas y en producción.
            'url' => route('admin.turnos.novedades.index', ['estado' => 'pendiente'], false),
        ];
    }
}
