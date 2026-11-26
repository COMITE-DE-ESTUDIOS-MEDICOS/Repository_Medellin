<?php

namespace Tests\Feature\UsuariosHc;

use App\Enums\RolDependencia;
use App\Livewire\UsuariosHc\Listado;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\IdentidadUsuarioHc;
use App\Models\User;
use App\Models\UsuarioHc;
use App\Services\ContextoDependencia;
use Database\Seeders\AccesoPorComponenteSeeder;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/** DOC-26 (UHC-06, plan D11): la lista de Usuarios HC. */
class UsuariosHcListadoTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    private Componente $estabilizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        // Calle como queda en producción: sus cuatro módulos, sin Usuarios HC.
        $this->calle = $this->componente('Calle', 'calle', AccesoPorComponenteSeeder::MODULOS_CALLE);
        $this->basica = $this->componente('Básica', 'basica', ['usuarios_hc']);
        $this->estabilizacion = $this->componente('Estabilización', 'estabilizacion', ['usuarios_hc']);
    }

    private function componente(string $nombre, string $slug, array $modulos, ?Dependencia $dependencia = null): Componente
    {
        return Componente::factory()->create(['dependencia_id' => ($dependencia ?? $this->dependencia)->id, 'nombre' => $nombre, 'slug' => $slug, 'modulos' => $modulos]);
    }

    /** Livewire::test no pasa por los middlewares: dependencia y componente a mano. */
    private function parado(User $usuario, Componente $componente): User
    {
        $this->actingAs($usuario);
        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($this->dependencia);
        $contexto->establecerComponente($componente);

        return $usuario;
    }

    /** Las rutas de ver y editar llegan con UHC-11 y UHC-12; mientras tanto, unas de prueba con el mismo nombre. */
    private function conRutasDeLaFicha(): void
    {
        foreach (['show' => '{usuarioHc}', 'edit' => '{usuarioHc}/editar'] as $nombre => $uri) {
            if (! Route::has("usuarios-hc.{$nombre}")) {
                Route::get("usuarios-hc/{$uri}", fn () => 'ficha')->name("usuarios-hc.{$nombre}");
            }
        }
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_doc26_ca01_el_menu_muestra_usuarios_hc_segun_el_componente(): void
    {
        $enlace = '<span class="truncate">Usuarios HC</span>';

        foreach ([$this->basica, $this->estabilizacion] as $componente) {
            foreach ([RolDependencia::Lectura, RolDependencia::Edicion, RolDependencia::Coordinacion] as $rol) {
                $usuario = $this->usuarioCon($rol, $this->dependencia, $componente);
                $this->actingAs($usuario)->get(route('perfil.edit'))->assertOk()->assertSee($enlace, false);
            }
        }

        foreach ([RolDependencia::Lectura, RolDependencia::Edicion, RolDependencia::Coordinacion] as $rol) {
            $deCalle = $this->usuarioCon($rol, $this->dependencia, $this->calle);
            $this->actingAs($deCalle)->get(route('perfil.edit'))->assertOk()->assertDontSee($enlace, false);
        }

        // El coordinador líder: parado en Básica lo ve; parado en Calle, no.
        $lider = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->actingAs($lider)->withSession(['componente_id' => $this->basica->id])->get(route('perfil.edit'))->assertSee($enlace, false);
        $this->actingAs($lider)->withSession(['componente_id' => $this->calle->id])->get(route('perfil.edit'))->assertDontSee($enlace, false);
    }

    /** El enlace va dentro de su grupo «Personas atendidas», que solo aparece con el módulo. */
    public function test_doc26_uhc06_usuarios_hc_va_en_el_grupo_personas_atendidas(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica);
        $this->actingAs($lector)->get(route('perfil.edit'))
            ->assertSee('<details data-grupo-menu="personas"', false)
            ->assertSeeInOrder(['<span>Personas atendidas</span>', '<span class="truncate">Usuarios HC</span>'], false);

        // En la lista, el grupo viene abierto.
        $this->actingAs($lector)->get(route('usuarios-hc.index'))
            ->assertSeeInOrder(['data-grupo-menu="personas"', 'open data-grupo-activo', 'Personas atendidas'], false);

        $deCalle = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->calle);
        $this->actingAs($deCalle)->get(route('perfil.edit'))
            ->assertDontSee('data-grupo-menu="personas"', false)
            ->assertDontSee('Personas atendidas');
    }

    public function test_doc26_ca02_la_lista_incluye_fichas_del_otro_componente_y_nunca_de_otra_dependencia(): void
    {
        $deBasica = UsuarioHc::factory()->en($this->basica)->create(['primer_nombre' => 'Ana', 'primer_apellido' => 'Básica']);
        $deEstabilizacion = UsuarioHc::factory()->en($this->estabilizacion)->create(['primer_nombre' => 'Luis', 'primer_apellido' => 'Estable']);

        $otraDependencia = Dependencia::factory()->create();
        $otroComponente = $this->componente('Básica', 'basica', ['usuarios_hc'], $otraDependencia);
        $ajena = UsuarioHc::factory()->en($otroComponente)->create(['primer_nombre' => 'Ajena', 'primer_apellido' => 'Otra']);

        $this->parado($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->basica);

        Livewire::test(Listado::class)
            ->assertCanSeeTableRecords([$deBasica, $deEstabilizacion])
            ->assertCanNotSeeTableRecords([$ajena])
            // Con su componente de registro, aunque sea el otro (salto de ComponenteScope en la relación).
            ->assertTableColumnStateSet('componenteRegistro.nombre', 'Estabilización', $deEstabilizacion)
            ->assertTableColumnStateSet('componenteRegistro.nombre', 'Básica', $deBasica)
            ->assertDontSee('Ajena')
            // Ni buscándola por su documento.
            ->searchTable($ajena->numero_documento)
            ->assertCanNotSeeTableRecords([$ajena]);
    }

    public function test_doc26_uhc06_la_pagina_pinta_la_lista_dentro_de_documenta(): void
    {
        UsuarioHc::factory()->en($this->estabilizacion)->create(['primer_nombre' => 'Luis', 'primer_apellido' => 'Estable']);
        $otroComponente = $this->componente('Básica', 'basica', ['usuarios_hc'], Dependencia::factory()->create());
        UsuarioHc::factory()->en($otroComponente)->create(['primer_nombre' => 'Ajena', 'primer_apellido' => 'Otra']);

        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica))
            ->get(route('usuarios-hc.index'))
            ->assertOk()
            ->assertSee('id="menu-lateral"', false)
            ->assertSee('Luis Estable')
            ->assertDontSee('Ajena Otra');
    }

    public function test_doc26_ca17_la_lista_no_muestra_fotos(): void
    {
        $ficha = UsuarioHc::factory()->en($this->basica)->create(['primer_nombre' => 'Con', 'primer_apellido' => 'Foto']);
        $foto = $ficha->fotos()->create(['ruta' => 'usuarios-hc/'.$ficha->id.'/foto-secreta.jpg', 'tamano' => 10]);
        $ficha->forceFill(['foto_id' => $foto->id])->save();

        $this->parado($this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica), $this->basica);

        $html = Livewire::test(Listado::class)->assertCanSeeTableRecords([$ficha])->html();

        $this->assertStringNotContainsString('foto-secreta', $html);
        $this->assertStringNotContainsString('/foto', $html);
        // Ninguna imagen dentro de la tabla (los íconos de Filament son svg).
        $this->assertDoesNotMatchRegularExpression('/<img\b/i', $html);
    }

    public function test_doc26_ca21_lista_coincidencias_y_mensajes_solo_muestran_identificacion(): void
    {
        $ficha = UsuarioHc::factory()->en($this->estabilizacion)->create([
            'primer_nombre' => 'Marta', 'primer_apellido' => 'Gómez', 'alias' => 'La Mona', 'numero_ficha' => 'F-77',
        ]);
        $ficha->caracterizacion()->create(['fecha_nacimiento' => now()->subYears(43)->subDay()->toDateString(), 'calle_anios' => 9]);
        $ficha->respuestas()->create(['campo' => 'sustancias', 'fuente' => 'config:sustancias', 'opcion' => 'xyz', 'texto' => 'Sustancia de prueba']);
        $ficha->cambios()->create(['seccion' => 'caracterizacion', 'campo' => 'eps', 'antes' => 'EPS vieja de prueba', 'despues' => 'EPS nueva de prueba', 'motivo' => 'Motivo de prueba']);
        $ficha->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Marta Zuluaga']);

        // Desde Básica y con Lectura: no puede ver la caracterización de esta ficha.
        $this->parado($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->basica);

        $html = Livewire::test(Listado::class)
            ->assertCanSeeTableRecords([$ficha])
            ->assertSee(['Marta Gómez', 'La Mona', 'F-77', 'Estabilización'])
            ->html();

        foreach (['Sustancia de prueba', 'EPS vieja de prueba', 'EPS nueva de prueba', 'Motivo de prueba', '43 años', 'Edad'] as $sensible) {
            $this->assertStringNotContainsString($sensible, $html);
        }

        // Buscar el nombre anterior la trae con la marca, sin mostrar el nombre viejo.
        Livewire::test(Listado::class)
            ->searchTable('zuluaga')
            ->assertCanSeeTableRecords([$ficha])
            ->assertSee('Coincide con un nombre anterior')
            ->assertDontSee('Zuluaga')
            // Ni el nombre viejo completo, como se guardó ni normalizado (el término buscado sí va en el campo).
            ->assertDontSee('Marta Zuluaga', false)->assertDontSee('marta zuluaga', false);
    }

    public function test_doc26_uhc06_calle_recibe_rechazo_en_la_lista(): void
    {
        UsuarioHc::factory()->en($this->basica)->create();

        foreach ([RolDependencia::Lectura, RolDependencia::Coordinacion] as $rol) {
            $deCalle = $this->usuarioCon($rol, $this->dependencia, $this->calle);
            $this->actingAs($deCalle)->get(route('usuarios-hc.index'))->assertForbidden();

            $this->parado($deCalle, $this->calle);
            Livewire::test(Listado::class)->assertForbidden();
        }
    }

    public function test_doc26_uhc06_la_busqueda_usa_el_scope_y_marca_el_dato_anterior(): void
    {
        $juan = UsuarioHc::factory()->en($this->basica)->create(['primer_nombre' => 'Juan', 'segundo_nombre' => 'Carlos', 'primer_apellido' => 'Pérez', 'numero_documento' => '1234567', 'numero_ficha' => 'F-120']);
        $otra = UsuarioHc::factory()->en($this->basica)->create(['primer_nombre' => 'Rosa', 'primer_apellido' => 'Díaz', 'numero_documento' => '999888']);
        $otra->identidades()->create(['campo' => IdentidadUsuarioHc::DOCUMENTO, 'valor' => '5551234']);
        $conFichaVieja = UsuarioHc::factory()->en($this->basica)->create(['primer_nombre' => 'Pedro', 'primer_apellido' => 'Ruiz', 'numero_ficha' => 'F-300']);
        $conFichaVieja->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-9']);

        $this->parado($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->basica);

        // Palabras en cualquier orden, sin tildes; documento con puntos; ficha sin guion.
        foreach (['perez juan', '1.234.567', 'f120'] as $termino) {
            Livewire::test(Listado::class)
                ->searchTable($termino)
                ->assertCanSeeTableRecords([$juan])
                ->assertCanNotSeeTableRecords([$otra, $conFichaVieja])
                ->assertDontSee('Coincide con un');
        }

        Livewire::test(Listado::class)
            ->searchTable('555.1234')
            ->assertCanSeeTableRecords([$otra])
            ->assertCanNotSeeTableRecords([$juan])
            ->assertSee('Coincide con un documento anterior')
            ->assertDontSee('5551234');

        Livewire::test(Listado::class)
            ->searchTable('F9')
            ->assertCanSeeTableRecords([$conFichaVieja])
            ->assertSee('Coincide con un número de ficha anterior')
            ->assertDontSee('F-9');

        // La búsqueda se recorta al tope de UsuarioHc.
        $larga = Livewire::test(Listado::class)->set('tableSearch', str_repeat('a', 150));
        $this->assertSame(UsuarioHc::BUSQUEDA_MAX_CARACTERES, mb_strlen($larga->get('tableSearch')));
    }

    public function test_doc26_uhc06_paginas_de_25(): void
    {
        UsuarioHc::factory()->count(30)->en($this->basica)->create();

        $this->parado($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->basica);

        $lista = Livewire::test(Listado::class)->assertCountTableRecords(30);
        $this->assertSame(25, $lista->instance()->getTableRecords()->count());
        $this->assertSame(25, $lista->instance()->getTableRecordsPerPage());
    }

    public function test_doc26_uhc06_el_filtro_de_componente_ofrece_los_que_tienen_el_modulo_y_filtra(): void
    {
        $deBasica = UsuarioHc::factory()->en($this->basica)->create();
        $deEstabilizacion = UsuarioHc::factory()->en($this->estabilizacion)->create();

        $this->parado($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->basica);

        // Desde Básica, el filtro ofrece también Estabilización (salto de ComponenteScope), no Calle.
        $this->assertSame(
            [$this->basica->id => 'Básica', $this->estabilizacion->id => 'Estabilización'],
            Listado::componentesConElModulo(),
        );

        Livewire::test(Listado::class)
            ->filterTable('componente_registro_id', $this->estabilizacion->id)
            ->assertCanSeeTableRecords([$deEstabilizacion])
            ->assertCanNotSeeTableRecords([$deBasica]);
    }

    public function test_doc26_uhc06_busqueda_y_filtros_se_conservan_y_la_pagina_va_en_la_direccion(): void
    {
        $deBasica = UsuarioHc::factory()->en($this->basica)->create(['primer_nombre' => 'Sofía']);
        UsuarioHc::factory()->en($this->estabilizacion)->create(['primer_nombre' => 'Sofía']);

        $this->parado($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->basica);

        Livewire::test(Listado::class)
            ->searchTable('sofia')
            ->filterTable('componente_registro_id', $this->basica->id);

        // Al volver a la lista (otra carga), siguen puestas.
        $otraVez = Livewire::test(Listado::class)->assertCanSeeTableRecords([$deBasica])->assertCountTableRecords(1);
        $this->assertSame('sofia', $otraVez->get('tableSearch'));
        $this->assertEquals($this->basica->id, $otraVez->get('tableFilters.componente_registro_id.value'));

        // La página es la de Livewire, en la dirección (?page=2).
        $this->assertSame('page', $otraVez->instance()->getTablePaginationPageName());
        $this->assertArrayHasKey('paginators.page', $otraVez->instance()->queryStringHandlesPagination());
    }

    public function test_doc26_uhc06_lectura_ve_la_lista_y_ver_pero_no_editar(): void
    {
        $this->conRutasDeLaFicha();
        $ficha = UsuarioHc::factory()->en($this->basica)->create();

        $this->parado($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->basica);
        Livewire::test(Listado::class)
            ->assertCanSeeTableRecords([$ficha])
            ->assertActionVisible(TestAction::make('ver')->table($ficha))
            ->assertActionHidden(TestAction::make('editar')->table($ficha));

        // Edición del componente de registro: las dos, con la dirección de la ficha.
        $this->parado($this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica), $this->basica);
        Livewire::test(Listado::class)
            ->assertActionVisible(TestAction::make('ver')->table($ficha))
            ->assertActionVisible(TestAction::make('editar')->table($ficha))
            ->assertActionHasUrl(TestAction::make('ver')->table($ficha), route('usuarios-hc.show', $ficha))
            ->assertActionHasUrl(TestAction::make('editar')->table($ficha), route('usuarios-hc.edit', $ficha));

        // Edición de otro componente: la ve, no la edita (CA-13). El coordinador líder, sí.
        $this->parado($this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->estabilizacion), $this->estabilizacion);
        Livewire::test(Listado::class)
            ->assertActionVisible(TestAction::make('ver')->table($ficha))
            ->assertActionHidden(TestAction::make('editar')->table($ficha));

        $this->parado($this->usuarioCon(RolDependencia::Administracion, $this->dependencia), $this->estabilizacion);
        Livewire::test(Listado::class)->assertActionVisible(TestAction::make('editar')->table($ficha));
    }

    public function test_doc26_uhc06_cada_accion_tiene_authorize(): void
    {
        $ficha = UsuarioHc::factory()->en($this->basica)->create();
        $this->parado($this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica), $this->basica);

        // Mientras UHC-11 y UHC-12 no traigan sus rutas, Ver y Editar no se pintan (no se inventan direcciones).
        foreach (['show' => 'ver', 'edit' => 'editar'] as $ruta => $accion) {
            if (! Route::has("usuarios-hc.{$ruta}")) {
                Livewire::test(Listado::class)->assertActionHidden(TestAction::make($accion)->table($ficha));
            }
        }

        $this->conRutasDeLaFicha();
        $tabla = Livewire::test(Listado::class)->instance()->getTable();
        $acciones = $tabla->getRecordActions();
        $this->assertNotEmpty($acciones);

        $sinPermiso = new \ReflectionProperty(Action::class, 'authorization');
        foreach ($acciones as $accion) {
            $this->assertNotNull($sinPermiso->getValue($accion), "La acción «{$accion->getName()}» no tiene ->authorize().");
        }
    }
}
