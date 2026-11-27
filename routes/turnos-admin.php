<?php

/*
|--------------------------------------------------------------------------
| Turnos — administración (dueño: Edwar)
|--------------------------------------------------------------------------
| Se carga desde bootstrap/app.php ya dentro de:
|   middleware: web, auth, usuario.activo, dependencia
|   prefijo:    /admin/turnos
|   nombres:    admin.turnos.*
| Aquí solo van las rutas, sin repetir nada de eso.
*/

use App\Http\Controllers\Admin\EvidenciaFichaController;
use App\Http\Controllers\Admin\SeguridadCctv\CasoController as CasoCctvController;
use App\Http\Controllers\Admin\SeguridadCctv\FichaController as FichaCctvController;
use App\Http\Controllers\Admin\SeguridadCctv\ReporteController as ReporteCctvController;
use App\Http\Controllers\Admin\Turnos\AusenciaController;
use App\Http\Controllers\Admin\Turnos\CargaMasivaCasosController;
use App\Http\Controllers\Admin\Turnos\CargaMasivaColaboradoresController;
use App\Http\Controllers\Admin\Turnos\CasoDerivacionController;
use App\Http\Controllers\Admin\Turnos\ColaboradorController;
use App\Http\Controllers\Admin\Turnos\ComentarioDerivacionController;
use App\Http\Controllers\Admin\Turnos\DerivacionController;
use App\Http\Controllers\Admin\Turnos\EnlaceTurnoController;
use App\Http\Controllers\Admin\Turnos\EnTurnoController;
use App\Http\Controllers\Admin\Turnos\MarcacionController;
use App\Http\Controllers\Admin\Turnos\NovedadController;
use App\Http\Controllers\Admin\Turnos\SupervisionController;
use Illuminate\Support\Facades\Route;

// DOC-25: cada grupo es un módulo (Componente::MODULOS). Si el componente donde está
// parado el usuario no lo tiene, 403 aunque su rol alcance. El módulo va entre comillas:
// sin ellas, `can` lo busca como parámetro de ruta y, si no lo halla, pasa null.

// Componentes con sus reglas, cargos y nodos (Coordinación): tabla y panel
// de Filament. Fuera de los módulos: es de Administración.
Route::view('componentes', 'admin.turnos.catalogo', [
    'titulo' => 'Componentes y nodos',
    'descripcion' => 'Calle, Básica, Estabilización… con sus reglas de turno, sus cargos y sus nodos.',
    'componente' => 'turnos.componentes',
])->middleware('can:viewAny,App\Models\Componente')->name('componentes.index');

Route::middleware("can:modulo,'turnos'")->group(function () {
    // Personas de campo (Coordinación).
    Route::get('colaboradores', [ColaboradorController::class, 'index'])->name('colaboradores.index');
    // Carga masiva desde Excel (temporal, para el ingreso inicial).
    Route::get('colaboradores/carga-masiva/plantilla', [CargaMasivaColaboradoresController::class, 'plantilla'])->name('colaboradores.carga.plantilla');
    Route::post('colaboradores/carga-masiva', [CargaMasivaColaboradoresController::class, 'importar'])->name('colaboradores.carga.importar');
    Route::get('colaboradores/nuevo', [ColaboradorController::class, 'create'])->name('colaboradores.create');
    Route::post('colaboradores', [ColaboradorController::class, 'store'])->name('colaboradores.store');
    Route::get('colaboradores/{colaborador}', [ColaboradorController::class, 'show'])->name('colaboradores.show');
    Route::get('colaboradores/{colaborador}/editar', [ColaboradorController::class, 'edit'])->name('colaboradores.edit');
    Route::put('colaboradores/{colaborador}', [ColaboradorController::class, 'update'])->name('colaboradores.update');
    Route::patch('colaboradores/{colaborador}/verificar', [ColaboradorController::class, 'verificar'])->name('colaboradores.verificar');
    Route::patch('colaboradores/{colaborador}/estado', [ColaboradorController::class, 'estado'])->name('colaboradores.estado');

    // Enlaces de turno (link + QR) y quién está en turno ahora (Coordinación).
    Route::get('enlaces', [EnlaceTurnoController::class, 'index'])->name('enlaces.index');
    Route::get('enlaces/nuevo', [EnlaceTurnoController::class, 'create'])->name('enlaces.create');
    Route::post('enlaces', [EnlaceTurnoController::class, 'store'])->name('enlaces.store');
    Route::get('enlaces/{enlace}', [EnlaceTurnoController::class, 'show'])->name('enlaces.show');
    Route::get('enlaces/{enlace}/imprimir', [EnlaceTurnoController::class, 'imprimir'])->name('enlaces.imprimir');
    Route::patch('enlaces/{enlace}/revocar', [EnlaceTurnoController::class, 'revocar'])->name('enlaces.revocar');
    Route::get('en-turno', [EnTurnoController::class, 'index'])->name('en-turno');

    // Marcaciones (consulta, reporte y corrección) y novedades (Coordinación).
    Route::get('marcaciones', [MarcacionController::class, 'index'])->name('marcaciones.index');
    Route::get('marcaciones/exportar', [MarcacionController::class, 'exportar'])->name('marcaciones.exportar');
    Route::post('colaboradores/{colaborador}/marcaciones', [MarcacionController::class, 'store'])->name('marcaciones.store');
    Route::patch('marcaciones/{marcacion}/corregir', [MarcacionController::class, 'corregir'])->name('marcaciones.corregir');
    Route::patch('marcaciones/{marcacion}/tipo', [MarcacionController::class, 'cambiarTipo'])->name('marcaciones.tipo');
    Route::patch('marcaciones/{marcacion}/anular', [MarcacionController::class, 'anular'])->name('marcaciones.anular');
    Route::get('novedades', [NovedadController::class, 'index'])->name('novedades.index');
    Route::get('novedades/exportar', [NovedadController::class, 'exportar'])->name('novedades.exportar');
    Route::patch('novedades/{novedad}', [NovedadController::class, 'revisar'])->name('novedades.revisar');

    // Ausencias (D, VAC, I, P…): esos días no se espera a la persona.
    Route::post('colaboradores/{colaborador}/ausencias', [AusenciaController::class, 'store'])->name('ausencias.store');
    Route::delete('ausencias/{ausencia}', [AusenciaController::class, 'destroy'])->name('ausencias.destroy');
    Route::post('novedades/{novedad}/justificar', [AusenciaController::class, 'justificar'])->name('novedades.justificar');

    // Catálogos de la malla (Edición): tabla de Filament dentro de Documenta.
    Route::view('tipos-turno', 'admin.turnos.catalogo', [
        'titulo' => 'Tipos de turno',
        'descripcion' => 'Los códigos de la malla con su horario y las horas que cuenta nómina. Son los mismos códigos de nómina.',
        'componente' => 'turnos.tipos-turno',
        'volver' => ['admin.turnos.colaboradores.index', 'Personas de campo'],
    ])->middleware('can:viewAny,App\Models\TipoTurno')->name('tipos-turno');

    Route::view('puntos-criticos', 'admin.turnos.catalogo', [
        'titulo' => 'Puntos críticos',
        'descripcion' => 'Las zonas a las que se asigna personal. Con sus coordenadas se ven en el mapa.',
        'componente' => 'turnos.puntos-criticos',
        'volver' => ['admin.turnos.en-turno', 'En turno ahora'],
    ])->middleware('can:viewAny,App\Models\PuntoCritico')->name('puntos-criticos');
});

Route::middleware("can:modulo,'derivaciones'")->group(function () {
    // Derivaciones y respuesta territorial (Edición). DOC-20: la oficina crea el caso DC-NNN.
    Route::get('derivaciones/crear', [CasoDerivacionController::class, 'create'])->name('derivaciones.create');
    Route::post('derivaciones', [CasoDerivacionController::class, 'store'])->name('derivaciones.store');
    // DOC-24: carga única de casos ya atendidos (Coordinación). Temporal: se quita con su controlador.
    Route::get('derivaciones/carga-masiva/formato', [CargaMasivaCasosController::class, 'formato'])->name('derivaciones.carga.formato');
    Route::post('derivaciones/carga-masiva', [CargaMasivaCasosController::class, 'importar'])->name('derivaciones.carga.importar');
    Route::get('derivaciones/exportar', [DerivacionController::class, 'exportar'])->name('derivaciones.exportar');
    // Plan de contingencia: el superadministrador cierra a mano un caso atendido que no se pudo enviar.
    Route::patch('derivaciones/{derivacion}/contingencia', [DerivacionController::class, 'contingencia'])->name('derivaciones.contingencia');
    // DOC-23: comentarios en la ficha del caso.
    Route::post('derivaciones/{derivacion}/comentarios', [ComentarioDerivacionController::class, 'store'])
        ->middleware('throttle:30,1')->name('derivaciones.comentarios.store');
    Route::delete('derivaciones/{derivacion}/comentarios/{comentario}', [ComentarioDerivacionController::class, 'destroy'])
        ->name('derivaciones.comentarios.destroy');
    // DOC-34: completar una evidencia vacía desde la ficha.
    Route::post('derivaciones/{derivacion}/evidencias/{n}', [EvidenciaFichaController::class, 'derivacion'])
        ->whereIn('n', ['1', '2', '3'])->middleware('throttle:30,1')->name('derivaciones.evidencia');
    Route::get('derivaciones/fotos', [DerivacionController::class, 'fotos'])->name('derivaciones.fotos');
    Route::resource('derivaciones', DerivacionController::class)
        ->only(['index', 'show', 'edit', 'update', 'destroy'])
        ->parameters(['derivaciones' => 'derivacion']);
});

Route::middleware("can:modulo,'seguridad_cctv'")->prefix('cctv')->name('cctv.')->group(function () {
    // Seguridad CCTV (DOC-33). La lista es el componente Livewire de CCTV-05; el permiso va en la Policy.
    Route::view('/', 'admin.seguridad-cctv.index')->middleware('can:viewAny,App\Models\CasoCctv')->name('index');
    Route::get('nuevo', [CasoCctvController::class, 'create'])->name('create');
    Route::post('/', [CasoCctvController::class, 'store'])->name('store');
    Route::post('persona', [CasoCctvController::class, 'persona'])->middleware('throttle:60,1')->name('persona');
    Route::get('reporte', [ReporteCctvController::class, 'index'])->name('reporte');
    Route::get('exportar', [ReporteCctvController::class, 'exportar'])->name('exportar');
    Route::get('{caso}', [FichaCctvController::class, 'show'])->name('show');
    Route::get('{caso}/editar', [CasoCctvController::class, 'edit'])->name('edit');
    Route::put('{caso}', [CasoCctvController::class, 'update'])->name('update');
    Route::patch('{caso}/cerrar', [FichaCctvController::class, 'cerrar'])->name('cerrar');
    Route::patch('{caso}/anular', [FichaCctvController::class, 'anular'])->name('anular');
    Route::post('{caso}/evidencias/{n}', [EvidenciaFichaController::class, 'cctv'])
        ->whereIn('n', ['1', '2', '3'])->middleware('throttle:30,1')->name('evidencia');
    Route::get('{caso}/archivo/{campo}', [FichaCctvController::class, 'archivo'])->name('archivo');
});

Route::middleware("can:modulo,'supervisiones'")->group(function () {
    // Supervisión territorial (DOC-11): consultar y descargar.
    Route::get('supervisiones', [SupervisionController::class, 'index'])->name('supervisiones.index');
    Route::get('supervisiones/exportar', [SupervisionController::class, 'exportar'])->name('supervisiones.exportar');
    Route::get('supervisiones/{supervision}', [SupervisionController::class, 'show'])->name('supervisiones.show');
});
