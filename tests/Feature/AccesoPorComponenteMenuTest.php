<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * DOC-25, ACC-06: el menú lateral por módulos del componente, el selector de
 * componente y la pantalla de inicio sin módulos.
 *
 * El menú se mira desde el perfil, que sigue abierto para todos con o sin
 * módulos (spec §4): así estas pruebas no dependen de los rechazos de ACC-04.
 */
class AccesoPorComponenteMenuTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    /** Los textos de cada enlace de módulo, tal como los pinta el menú. */
    private const ENLACES_DE_MODULO = [
        'Documentos', 'Enlaces de carga',
        'Enlaces por cédula', 'Personas de campo', 'En turno ahora', 'Novedades', 'Marcaciones y reporte',
        'Derivaciones', 'Seguridad CCTV', 'Supervisión',
        'Tipos de documento', 'Grupos de custodia',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
    }

    private function menu(User $usuario): TestResponse
    {
        return $this->actingAs($usuario)->get(route('perfil.edit'))->assertOk();
    }

    private function enlace(string $texto): string
    {
        return '<span class="truncate">'.$texto.'</span>';
    }

    /** Ruta de prueba que pinta la vista como lo hará el controlador de ACC-05. */
    private function inicio(User $usuario, array $datos = []): TestResponse
    {
        Route::middleware(['web', 'auth', 'usuario.activo', 'dependencia'])
            ->get('/prueba-inicio', fn () => view('inicio', $datos));

        return $this->actingAs($usuario)->get('/prueba-inicio')->assertOk();
    }

    public function test_doc25_ca04_un_usuario_de_calle_ve_los_enlaces_de_sus_modulos(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);

        $respuesta = $this->menu($coordinador);

        foreach (array_diff(self::ENLACES_DE_MODULO, ['Enlaces de carga']) as $texto) {
            $respuesta->assertSee($this->enlace($texto), false);
        }

        $respuesta->assertSee('data-grupo-menu="principal"', false)
            ->assertSee('data-grupo-menu="turnos"', false)
            ->assertSee('data-grupo-menu="territorio"', false)
            ->assertSee('data-grupo-menu="administracion"', false);
    }

    public function test_doc25_ca04_un_lector_de_calle_ve_documentos_como_hoy(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->calle);

        $this->menu($lector)
            ->assertSee($this->enlace('Documentos'), false)
            ->assertDontSee('data-grupo-menu="turnos"', false)
            ->assertDontSee('data-grupo-menu="territorio"', false);
    }

    public function test_doc25_ca04_coordinacion_de_un_componente_sin_modulos_no_ve_ningun_modulo_ni_grupos_vacios(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica);

        $respuesta = $this->menu($coordinador);

        foreach (self::ENLACES_DE_MODULO as $texto) {
            $respuesta->assertDontSee($this->enlace($texto), false);
        }

        $respuesta->assertDontSee('data-grupo-menu="principal"', false)
            ->assertDontSee('data-grupo-menu="turnos"', false)
            ->assertDontSee('data-grupo-menu="territorio"', false)
            ->assertDontSee('<span>Turnos</span>', false)
            ->assertDontSee('<span>Atención territorial</span>', false)
            // Lo que no depende de un módulo sigue ahí.
            ->assertSee('data-grupo-menu="administracion"', false)
            ->assertSee($this->enlace('Componentes y nodos'), false)
            ->assertSee($this->enlace('Usuarios'), false)
            ->assertSee($this->enlace('Auditoría'), false)
            ->assertSee($this->enlace('Manual de usuario'), false);
    }

    public function test_doc25_ca04_un_lector_de_un_componente_sin_modulos_no_ve_ningun_grupo(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica);

        $this->menu($lector)->assertDontSee('data-grupo-menu=', false);
    }

    public function test_doc25_ca11_tipos_de_documento_y_grupos_de_custodia_piden_el_modulo_documentos(): void
    {
        $soloTurnos = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $soloTurnos->forceFill(['modulos' => ['turnos']])->save();
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $soloTurnos);

        $this->menu($coordinador)
            ->assertSee($this->enlace('Enlaces por cédula'), false)
            ->assertSee($this->enlace('Componentes y nodos'), false)
            ->assertDontSee($this->enlace('Documentos'), false)
            ->assertDontSee($this->enlace('Tipos de documento'), false)
            ->assertDontSee($this->enlace('Grupos de custodia'), false)
            ->assertDontSee($this->enlace('Derivaciones'), false)
            ->assertDontSee('data-grupo-menu="territorio"', false);
    }

    public function test_doc25_ca11_encender_y_apagar_un_modulo_cambia_el_menu_sin_tocar_el_programa(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);

        $this->menu($editor)->assertDontSee($this->enlace('Derivaciones'), false);

        $this->basica->forceFill(['modulos' => ['derivaciones']])->save();

        $this->menu($editor)
            ->assertSee($this->enlace('Derivaciones'), false)
            ->assertDontSee($this->enlace('Supervisión'), false);

        $this->basica->forceFill(['modulos' => []])->save();

        $this->menu($editor)->assertDontSee($this->enlace('Derivaciones'), false);
    }

    public function test_cctv08_ca01_el_menu_muestra_seguridad_cctv_solo_con_modulo_y_rol_de_edicion(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);
        $this->menu($editor)->assertSee($this->enlace('Seguridad CCTV'), false);

        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->calle);
        $this->menu($lector)->assertDontSee($this->enlace('Seguridad CCTV'), false);

        $this->calle->forceFill(['modulos' => ['derivaciones']])->save();
        $this->menu($editor)->assertDontSee($this->enlace('Seguridad CCTV'), false);
    }

    public function test_doc25_ca04_administracion_con_dos_componentes_ve_el_selector_de_componente(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->menu($admin)
            ->assertSee('data-grupo-menu="componente"', false)
            // Cada componente es una opción; el actual no se envía a sí mismo.
            ->assertSee('action="'.url('componente/'.$this->basica->id).'"', false)
            ->assertDontSee('action="'.url('componente/'.$this->calle->id).'"', false)
            ->assertSee('Básica')
            // El logotipo lleva a la llegada (plan D7).
            ->assertSee('href="'.route('inicio').'"', false)
            // Parado en Calle (el primero con módulos), ve sus módulos.
            ->assertSee($this->enlace('Documentos'), false);
    }

    public function test_doc25_ca04_administracion_con_un_solo_componente_ve_su_nombre_sin_selector(): void
    {
        $this->basica->update(['activo' => false]);
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->menu($admin)
            ->assertDontSee('data-grupo-menu="componente"', false)
            ->assertSee('data-componente-actual', false)
            ->assertSee('Calle');
    }

    public function test_doc25_ca04_coordinacion_ve_el_nombre_de_su_componente_sin_selector(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica);

        $this->menu($coordinador)
            ->assertDontSee('data-grupo-menu="componente"', false)
            ->assertDontSee('action="'.url('componente/'), false)
            ->assertSee('data-componente-actual', false)
            ->assertSee('Básica');
    }

    public function test_doc25_ca04_sin_componente_el_menu_no_muestra_componente_ni_modulos(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $editor->dependencias()->updateExistingPivot($this->dependencia->id, ['componente_id' => null]);

        $respuesta = $this->menu($editor)
            ->assertDontSee('data-grupo-menu="componente"', false)
            ->assertDontSee('data-componente-actual', false);

        foreach (self::ENLACES_DE_MODULO as $texto) {
            $respuesta->assertDontSee($this->enlace($texto), false);
        }
    }

    public function test_doc25_ca04_inicio_dice_que_el_componente_no_tiene_modulos_activos(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);

        $this->inicio($editor, ['sinComponente' => false])
            ->assertSee('Este componente todavía no tiene módulos activos')
            ->assertSee('Básica')
            ->assertDontSee('Pide a tu coordinación que te asigne un componente');
    }

    public function test_doc25_ca04_inicio_le_sugiere_a_administracion_elegir_otro_componente(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->calle->forceFill(['modulos' => []])->save(); // así queda parado en uno sin módulos

        $this->inicio($admin, ['sinComponente' => false])
            ->assertSee('Este componente todavía no tiene módulos activos')
            ->assertSee('Puedes elegir otro componente en el menú');
    }

    public function test_doc25_ca04_inicio_sin_componente_pide_que_se_lo_asignen(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $editor->dependencias()->updateExistingPivot($this->dependencia->id, ['componente_id' => null]);

        $this->inicio($editor, ['sinComponente' => true])
            ->assertSee('Pide a tu coordinación que te asigne un componente')
            ->assertDontSee('Este componente todavía no tiene módulos activos');
    }

    public function test_doc25_ca04_inicio_sin_componente_aunque_el_controlador_no_pase_la_marca(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $editor->dependencias()->updateExistingPivot($this->dependencia->id, ['componente_id' => null]);

        $this->inicio($editor)
            ->assertSee('Pide a tu coordinación que te asigne un componente');
    }

    public function test_doc25_ca04_administracion_sin_componentes_activos_no_lee_que_pida_uno(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        Componente::withoutGlobalScopes()->where('dependencia_id', $this->dependencia->id)->update(['activo' => false]);

        $this->actingAs($admin)->get(route('inicio'))->assertOk()
            ->assertSee('Esta dependencia todavía no tiene componentes activos')
            ->assertDontSee('Pide a tu coordinación que te asigne un componente');
    }
}
