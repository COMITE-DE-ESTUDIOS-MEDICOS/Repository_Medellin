<?php

use Illuminate\Support\Facades\Route;

/*
| DOC-27 (plan D2): Ingresos y egresos. Cargado desde bootstrap/app.php con prefijo
| «ingresos-egresos», nombres «ingresos-egresos.*» y el módulo «ingresos_egresos».
| IYE-01: solo la pantalla, vacía y sin can:viewAny (la Policy llega con IYE-03).
*/

Route::view('/', 'ingresos-egresos.index')->name('index');
