<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\RolDependencia;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** DOC-33 (CCTV-01): la base compartida: política, módulo, enlace y rutas. */
class BaseTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
    }

    /** Usuario parado en un componente, con la casilla opcional. */
    private function usuario(RolDependencia $rol, Componente $componente, bool $casilla = false): User
    {
        $usuario = $this->usuarioCon($rol, $this->dependencia, $componente);
        $usuario->dependencias()->updateExistingPivot($this->dependencia->id, ['responsable_cctv' => $casilla]);

        return $usuario->unsetRelation('dependencias');
    }

    private function parar(Componente $componente): void
    {
        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($this->dependencia);
        $contexto->establecerComponente($componente);
    }

    public function test_doc33_ca01_el_modulo_existe_y_sin_el_nadie_pasa_ni_el_superadministrador(): void
    {
        $this->assertSame(['Seguridad CCTV', 'admin.turnos.cctv.index'], Componente::MODULOS['seguridad_cctv']);
        $this->assertTrue($this->calle->tieneModulo('seguridad_cctv'));
        $this->assertFalse($this->basica->tieneModulo('seguridad_cctv'));

        $admin = $this->usuario(RolDependencia::Administracion, $this->calle);
        $super = User::factory()->superadmin()->create();

        $this->parar($this->basica);
        foreach ([$admin, $super] as $usuario) {
            $this->assertFalse(Gate::forUser($usuario)->allows('viewAny', CasoCctv::class));
            $this->assertFalse(Gate::forUser($usuario)->allows('create', CasoCctv::class));
        }

        $this->parar($this->calle);
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', CasoCctv::class));
    }

    public function test_doc33_ca04_matriz_de_la_politica(): void
    {
        $this->parar($this->calle);
        $abierto = CasoCctv::factory()->de($this->calle)->create();
        $atendido = CasoCctv::factory()->de($this->calle)->atendido()->create();
        $anulado = CasoCctv::factory()->de($this->calle)->create(['estado' => CasoCctv::ANULADO]);

        $lectura = $this->usuario(RolDependencia::Lectura, $this->calle);
        $edicion = $this->usuario(RolDependencia::Edicion, $this->calle);
        $conCasilla = $this->usuario(RolDependencia::Edicion, $this->calle, true);
        $coordinacion = $this->usuario(RolDependencia::Coordinacion, $this->calle);
        $admin = $this->usuario(RolDependencia::Administracion, $this->calle);

        $puede = fn (User $u, string $habilidad, mixed $objetivo): bool => Gate::forUser($u)->allows($habilidad, $objetivo);

        // Ver: Edición en adelante; Lectura no.
        $this->assertFalse($puede($lectura, 'viewAny', CasoCctv::class));
        $this->assertFalse($puede($lectura, 'view', $abierto));
        foreach ([$edicion, $conCasilla, $coordinacion, $admin] as $u) {
            $this->assertTrue($puede($u, 'viewAny', CasoCctv::class));
            $this->assertTrue($puede($u, 'view', $abierto));
        }

        // Registrar, compartir el enlace, ver imágenes: la casilla, Coordinación y Administración.
        foreach (['create' => CasoCctv::class, 'compartirEnlace' => CasoCctv::class, 'verImagenes' => $abierto] as $habilidad => $objetivo) {
            $this->assertFalse($puede($lectura, $habilidad, $objetivo), $habilidad);
            $this->assertFalse($puede($edicion, $habilidad, $objetivo), $habilidad);
            foreach ([$conCasilla, $coordinacion, $admin] as $u) {
                $this->assertTrue($puede($u, $habilidad, $objetivo), $habilidad);
            }
        }

        // Corregir: abierto o atendido; cerrar: solo atendido.
        $this->assertTrue($puede($conCasilla, 'update', $abierto));
        $this->assertTrue($puede($conCasilla, 'update', $atendido));
        $this->assertFalse($puede($conCasilla, 'update', $anulado));
        $this->assertFalse($puede($edicion, 'update', $abierto));
        $this->assertTrue($puede($conCasilla, 'cerrar', $atendido));
        $this->assertFalse($puede($conCasilla, 'cerrar', $abierto));

        // Anular: Coordinación en adelante (la casilla no alcanza) y si no está anulado.
        $this->assertFalse($puede($conCasilla, 'anular', $abierto));
        $this->assertTrue($puede($coordinacion, 'anular', $abierto));
        $this->assertTrue($puede($admin, 'anular', $atendido));
        $this->assertFalse($puede($admin, 'anular', $anulado));
    }

    public function test_doc33_ca04_la_casilla_no_da_poder_a_lectura(): void
    {
        $this->parar($this->calle);
        $lecturaConCasilla = $this->usuario(RolDependencia::Lectura, $this->calle, true);

        $this->assertFalse(Gate::forUser($lecturaConCasilla)->allows('create', CasoCctv::class));
        $this->assertFalse($lecturaConCasilla->puedeGestionarCctvEn($this->dependencia));
    }

    public function test_doc33_el_enlace_cctv_se_arma_en_c_token_y_la_casilla_viaja_en_la_pivote(): void
    {
        $enlace = EnlaceTurno::factory()->de($this->calle)->create(['tipo' => 'seguridad_cctv']);

        $this->assertTrue($enlace->esSeguridadCctv());
        $this->assertSame('Seguridad CCTV', $enlace->etiquetaTipo());
        $this->assertSame(url('c/'.$enlace->token()), $enlace->url());
        $this->assertFalse(EnlaceTurno::factory()->de($this->calle)->create()->esSeguridadCctv());

        $usuario = $this->usuario(RolDependencia::Edicion, $this->calle, true);
        $this->assertTrue((bool) $usuario->dependencias()->first()->pivot->responsable_cctv);
    }

    public function test_doc33_las_rutas_existen_con_sus_nombres_y_el_modulo_en_las_de_administracion(): void
    {
        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'cerrar', 'anular', 'archivo', 'reporte', 'exportar'] as $nombre) {
            $ruta = Route::getRoutes()->getByName("admin.turnos.cctv.{$nombre}");
            $this->assertNotNull($ruta, $nombre);
            $this->assertContains("can:modulo,'seguridad_cctv'", $ruta->gatherMiddleware(), $nombre);
        }

        foreach (['enlace', 'cedula', 'caso', 'soporte', 'atender'] as $nombre) {
            $this->assertNotNull(Route::getRoutes()->getByName("cctv.{$nombre}"), $nombre);
        }
    }

    public function test_doc33_el_codigo_el_tiempo_y_la_carpeta_con_custodia(): void
    {
        $this->assertSame('CCTV-007', CasoCctv::codigoDe(7));
        $this->assertSame('CCTV-1250', CasoCctv::codigoDe(1250));

        $caso = CasoCctv::factory()->de($this->calle)->atendido(85)->create();
        $this->assertSame('1 h 25 min', $caso->tiempoLegible());

        $carpeta = CasoCctv::carpetaPara($this->calle);
        $this->assertSame('Seguridad CCTV · Calle', $carpeta->nombre);
        $this->assertSame(CasoCctv::GRUPO_CUSTODIA, $carpeta->grupoCustodia->nombre);
        $this->assertTrue($carpeta->is(CasoCctv::carpetaPara($this->calle)), 'Es la misma carpeta, no una nueva.');
    }
}
