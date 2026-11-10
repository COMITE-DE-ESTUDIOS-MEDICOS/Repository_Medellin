<?php

namespace App\Notifications;

use App\Models\Derivacion;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * DOC-20: a quien creó el caso, cuando la persona de campo lo atiende. Retira
 * el aviso de vencido (DerivacionPendiente) del mismo caso.
 */
class CasoAtendido extends Notification
{
    public function __construct(protected Derivacion $derivacion)
    {
    }

    public static function enviar(Derivacion $derivacion): void
    {
        DerivacionPendiente::retirar($derivacion);

        $creador = User::find($derivacion->creado_por);

        if ($creador?->activo) {
            $creador->notify(new self($derivacion));
        }
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $d = $this->derivacion;
        $cumplio = $d->cumplio_requerimiento ? 'se cumplió' : 'no se cumplió';
        $meta = $d->cumplio() ? 'a tiempo' : 'fuera de la meta de '.intdiv(Derivacion::META_MINUTOS, 60).' h';

        return [
            'derivacion_id' => $d->id,
            'mensaje' => "{$d->codigo()} atendido por {$d->nombre} en {$d->tiempoLegible()} ({$meta}): {$cumplio} el requerimiento.",
            'url' => route('admin.turnos.derivaciones.show', $d, false),
        ];
    }
}
