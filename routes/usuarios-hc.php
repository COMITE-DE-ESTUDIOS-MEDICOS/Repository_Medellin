<?php

use Illuminate\Support\Facades\Route;

/*
| DOC-26 (plan D2): Usuarios HC. Cargado desde bootstrap/app.php con prefijo
| «usuarios-hc», nombres «usuarios-hc.*» y el módulo «usuarios_hc».
| UHC-01: solo la lista, vacía. La tabla y su Policy llegan con UHC-04 y UHC-06.
*/

Route::view('/', 'usuarios-hc.index')->name('index');
