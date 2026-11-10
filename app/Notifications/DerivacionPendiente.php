<?php

namespace App\Notifications;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\Derivacion;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notificaciones;

/**
 * DOC-20: un solo aviso a Edición y Coordinación cuando un caso pasa
 * Derivacion::META_MINUTOS sin atención. Lo retira CasoAtendido al atenderse.
 */
class DerivacionPendiente extends Notification
{
    public function __construct(protected Derivacion $derivacion)
    {
    }

    /** Avisa a Edición y Coordinación de su dependencia y anota que ya se avisó. */
    public static function enviar(Derivacion $derivacion): void
    {
        self::retirar($derivacion);

        $destinatarios = Dependencia::find($derivacion->dependencia_id)
            ?->usuarios()
            ->wherePivotIn('rol', [RolDependencia::Edicion->value, RolDependencia::Coordinacion->value])
            ->where('activo', true)
            ->get();

        if ($destinatarios?->isNotEmpty()) {
            Notificaciones::send($destinatarios, new self($derivacion));
        }

        $derivacion->forceFill(['ultimo_aviso_at' => now()])->saveQuietly();
    }

    /** Los avisos de un caso, fuera de la campana (al atenderlo). */
    public static function retirar(Derivacion $derivacion): void
    {
        DatabaseNotification::where('type', self::class)->where('data->derivacion_id', $derivacion->id)->delete();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $d = $this->derivacion;

        return [
            'derivacion_id' => $d->id,
            'mensaje' => "{$d->codigo()} venció sin atención: lleva {$d->tiempoLegible()}.",
            // Relativa: la notificación sirve igual en pruebas y en producción.
            'url' => route('admin.turnos.derivaciones.show', $d, false),
        ];
    }
}
