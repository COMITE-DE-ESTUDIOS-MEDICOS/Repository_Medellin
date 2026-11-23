<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    /** Pestaña => [etiqueta, clases de notificación]. Lo que no esté aquí sale solo en «Todas». */
    public const TIPOS = [
        'derivaciones' => ['Derivaciones', [\App\Notifications\DerivacionPendiente::class, \App\Notifications\CasoAtendido::class]],
        'supervision' => ['Supervisión', [\App\Notifications\SupervisionImportante::class]],
        'turnos' => ['Turnos', [\App\Notifications\NovedadTurnoDetectada::class]],
        'documentos' => ['Documentos', [\App\Notifications\ComentarioEnDocumento::class, \App\Notifications\RecepcionRecibida::class]],
        'avisos' => ['Avisos', [\App\Notifications\AvisoDelSistema::class]],
    ];

    public function index(Request $request): View
    {
        $tipo = array_key_exists($request->input('tipo'), self::TIPOS) ? $request->input('tipo') : null;
        // reorder(): la relación trae su orden por fecha, y MySQL no lo acepta con groupBy.
        $sinLeer = $request->user()->unreadNotifications()->reorder()->toBase()
            ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return view('notificaciones.index', [
            'notificaciones' => $request->user()->notifications()
                ->when($tipo, fn ($q) => $q->whereIn('type', self::TIPOS[$tipo][1]))
                ->latest()->limit(50)->get(),
            'tipo' => $tipo,
            // Sin leer por pestaña, para el numerito.
            'sinLeer' => collect(self::TIPOS)->map(fn ($t) => $sinLeer->only($t[1])->sum()),
        ]);
    }

    /** La pestaña de una notificación, para su etiqueta de color. */
    public static function tipoDe(string $clase): ?string
    {
        return collect(self::TIPOS)->search(fn ($t) => in_array($clase, $t[1], true)) ?: null;
    }

    /**
     * Cuántas hay sin leer. Lo consulta la campana cada tanto para enterarse
     * de las que llegan con la página abierta: sin esto el contador solo se
     * movía al recargar, y no había un momento de «llegada» en el que avisar.
     */
    public function contador(Request $request): JsonResponse
    {
        return response()->json([
            'sin_leer' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function marcarLeidas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /** La X de una notificación puntual: no desaparece sola, solo al pedirlo. */
    public function marcarLeida(Request $request, DatabaseNotification $notificacion): RedirectResponse
    {
        abort_unless($notificacion->notifiable_id === $request->user()->id, 403);

        $notificacion->markAsRead();

        return back();
    }
}
