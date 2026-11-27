<?php

use App\Http\Controllers\UsuarioHcController;
use App\Models\UsuarioHc;
use Illuminate\Support\Facades\Route;

/*
| DOC-26 (plan D2): Usuarios HC. Cargado desde bootstrap/app.php con prefijo
| «usuarios-hc», nombres «usuarios-hc.*» y el módulo «usuarios_hc».
| UHC-01: solo la lista, vacía. La tabla y su Policy llegan con UHC-04 y UHC-06.
*/

Route::view('/', 'usuarios-hc.index')->middleware('can:viewAny,'.UsuarioHc::class)->name('index');

// UHC-07: crear. Antes de {usuarioHc} (UHC-11) para que «nueva» no se lea como una ficha.
Route::get('nueva', [UsuarioHcController::class, 'create'])->middleware('can:create,'.UsuarioHc::class)->name('create');
Route::post('/', [UsuarioHcController::class, 'store'])->middleware('can:create,'.UsuarioHc::class)->name('store');
Route::post('coincidencias', [UsuarioHcController::class, 'coincidencias'])
    ->middleware(['can:create,'.UsuarioHc::class, 'throttle:60,1'])->name('coincidencias');
