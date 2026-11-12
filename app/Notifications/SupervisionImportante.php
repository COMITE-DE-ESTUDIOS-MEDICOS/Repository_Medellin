<?php

namespace App\Notifications;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\Supervision;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notificaciones;

/**
 * DOC-13 (RN-45): lo importante de una supervisión, de inmediato, a Edición y
 * Coordinación. Lo de todos los días se consulta en la lista, sin avisos.
 */
class SupervisionImportante extends Notification
{
    public function __construct(protected Supervision $supervision, protected array $motivos)
    {
    }

    /** Los motivos para avisar; vacío si no hay nada importante. */
    public static function motivos(Supervision $s): array
    {
        return array_values(array_filter([
            in_array($s->incidencia, ['relevante', 'critica'], true) ? 'incidencia '.mb_strtolower(Supervision::INCIDENCIAS[$s->incidencia]) : null,
            $s->casos_escalados ? 'casos escalados' : null,
            $s->calificacion_global <= 2 ? "calificación global {$s->calificacion_global}" : null,
        ]));
    }

    public static function enviarSiHace(Supervision $supervision): void
    {
        $motivos = self::motivos($supervision);

        if ($motivos === []) {
            return;
        }

        $quienes = Dependencia::find($supervision->dependencia_id)
            ?->usuarios()
            ->wherePivotIn('rol', [RolDependencia::Edicion->value, RolDependencia::Coordinacion->value])
            ->where('activo', true)
            ->get();

        if ($quienes?->isNotEmpty()) {
            Notificaciones::send($quienes, new self($supervision, $motivos));
        }
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $s = $this->supervision;

        return [
            'mensaje' => "Supervisión #{$s->id} en {$s->puntoNombre()}: ".implode(', ', $this->motivos).'.',
            // Relativa: la notificación sirve igual en pruebas y en producción.
            'url' => route('admin.turnos.supervisiones.show', $s, false),
        ];
    }
}
