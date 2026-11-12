<?php

/*
|--------------------------------------------------------------------------
| Turnos — vía pública
|--------------------------------------------------------------------------
| Se carga desde bootstrap/app.php con el middleware 'web' y nada más: sin
| sesión de usuario ni dependencia en contexto. La dependencia la da el
| enlace (EnlaceTurno::porToken).
|
| Contrato con administración: la pantalla del enlace se llama 'turno.enlace'
| y vive en /t/{token}. EnlaceTurno::url() y los QR ya impresos dependen de
| ese nombre y de esa ruta: no se cambian.
*/

use App\Http\Controllers\Admin\Turnos\DerivacionController as AdminDerivacionController;
use App\Http\Controllers\Publico\DerivacionController;
use App\Http\Controllers\Publico\SupervisionController;
use App\Http\Controllers\Publico\TurnoController;
use Illuminate\Support\Facades\Route;

Route::get('t/{token}', [TurnoController::class, 'mostrar'])
    ->middleware('throttle:envio-formulario')
    ->name('turno.enlace');

Route::post('t/{token}/cedula', [TurnoController::class, 'cedula'])
    ->middleware('throttle:cedula-consulta')
    ->name('turno.cedula');

Route::post('t/{token}/marcar', [TurnoController::class, 'marcar'])
    ->middleware('throttle:turno-marca')
    ->name('turno.marcar');

// Derivaciones y respuesta territorial: el mismo enlace por cédula, otro formulario.
Route::get('d/{token}', [DerivacionController::class, 'mostrar'])
    ->middleware('throttle:envio-formulario')
    ->name('derivacion.enlace');

Route::post('d/{token}/cedula', [DerivacionController::class, 'cedula'])
    ->middleware('throttle:cedula-consulta')
    ->name('derivacion.cedula');

// DOC-20: ver el caso por su código exacto (POST, límite estricto y auditoría)
// y su soporte por una URL firmada que vence a los 10 minutos.
Route::post('d/{token}/caso', [DerivacionController::class, 'caso'])
    ->middleware('throttle:caso-consulta')
    ->name('derivacion.caso');

Route::get('d/{token}/soporte/{derivacion}', [DerivacionController::class, 'soporte'])
    ->middleware(['signed', 'throttle:envio-formulario'])
    ->name('derivacion.soporte');

// Enviar la atención: comparte el límite de la consulta, porque también dice si un número existe.
Route::post('d/{token}', [DerivacionController::class, 'atender'])
    ->middleware(['throttle:turno-marca', 'throttle:caso-consulta'])
    ->name('derivacion.atender');

// Supervisión territorial (DOC-11): el mismo enlace por cédula, su propio formulario.
Route::get('s/{token}', [SupervisionController::class, 'mostrar'])
    ->middleware('throttle:envio-formulario')
    ->name('supervision.enlace');

Route::post('s/{token}/cedula', [SupervisionController::class, 'cedula'])
    ->middleware('throttle:cedula-consulta')
    ->name('supervision.cedula');

Route::post('s/{token}/dinamizador', [SupervisionController::class, 'dinamizador'])
    ->middleware('throttle:cedula-consulta')
    ->name('supervision.dinamizador');

Route::post('s/{token}', [SupervisionController::class, 'registrar'])
    ->middleware('throttle:turno-marca')
    ->name('supervision.registrar');

// Un archivo de una derivación desde el Excel del reporte: enlace firmado y con vencimiento.
Route::get('derivaciones/archivo/{documento}', [AdminDerivacionController::class, 'archivo'])
    ->middleware(['signed', 'throttle:envio-formulario'])
    ->name('derivaciones.archivo');
