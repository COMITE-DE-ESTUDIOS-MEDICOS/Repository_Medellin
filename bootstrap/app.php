<?php

use App\Http\Middleware\EstablecerDependencia;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Turnos en dos archivos, uno por dueño, para que no choquen en git.
        then: function () {
            Route::middleware(['web', 'auth', 'usuario.activo', 'dependencia'])
                ->prefix('admin/turnos')
                ->name('admin.turnos.')
                ->group(base_path('routes/turnos-admin.php'));

            Route::middleware('web')->group(base_path('routes/turnos-publico.php'));

            // DOC-26 (plan D2): Usuarios HC. La clave del módulo entre comillas.
            Route::middleware(['web', 'auth', 'usuario.activo', 'dependencia', "can:modulo,'usuarios_hc'"])
                ->prefix('usuarios-hc')
                ->name('usuarios-hc.')
                ->group(base_path('routes/usuarios-hc.php'));

            // DOC-27 (plan D2): Ingresos y egresos de Usuarios HC.
            Route::middleware(['web', 'auth', 'usuario.activo', 'dependencia', "can:modulo,'ingresos_egresos'"])
                ->prefix('ingresos-egresos')
                ->name('ingresos-egresos.')
                ->group(base_path('routes/ingresos-egresos.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'dependencia' => EstablecerDependencia::class,
            'usuario.activo' => VerificarUsuarioActivo::class,
        ]);

        // La dependencia y el componente se ponen ANTES de resolver el modelo de la
        // ruta: si no, sus filtros (DependenciaScope, ComponenteScope) no actúan ahí
        // y un registro de otro componente se abre por su dirección.
        $middleware->prependToPriorityList(SubstituteBindings::class, EstablecerDependencia::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
