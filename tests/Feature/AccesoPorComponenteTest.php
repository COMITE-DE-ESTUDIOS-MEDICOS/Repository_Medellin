<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\User;
use App\Services\ContextoDependencia;
use Database\Seeders\AccesoPorComponenteSeeder;
use Database\Seeders\CalleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** DOC-25: acceso por componente. */
class AccesoPorComponenteTest extends TestCase
{
    use RefreshDatabase;

    private function inclusionConCalle(): array
    {
        $inclusion = Dependencia::factory()->create(['slug' => 'inclusion-social']);
        (new CalleSeeder)->run();
        // Como en producción antes de DOC-25: Calle sin módulos.
        DB::table('componentes')->update(['modulos' => null]);

        return [$inclusion, Componente::withoutGlobalScopes()->where('dependencia_id', $inclusion->id)->where('slug', 'calle')->sole()];
    }

    public function test_doc25_ca01_calle_tiene_sus_cuatro_modulos_y_basica_y_estabilizacion_existen_sin_modulos(): void
    {
        [$inclusion] = $this->inclusionConCalle();

        (new AccesoPorComponenteSeeder)->run();

        $componentes = Componente::withoutGlobalScopes()->where('dependencia_id', $inclusion->id)->orderBy('id')->get()->keyBy('slug');

        $this->assertSame(['calle', 'basica', 'estabilizacion'], $componentes->keys()->all());
        $this->assertSame(['documentos', 'turnos', 'derivaciones', 'supervisiones'], $componentes['calle']->modulos);
        $this->assertSame(array_keys(Componente::MODULOS), $componentes['calle']->modulos);

        foreach (['basica' => 'Básica', 'estabilizacion' => 'Estabilización'] as $slug => $nombre) {
            $this->assertSame($nombre, $componentes[$slug]->nombre);
            $this->assertTrue($componentes[$slug]->activo);
            $this->assertSame([], $componentes[$slug]->modulos);
            $this->assertNull($componentes[$slug]->config);
            $this->assertSame(0, $componentes[$slug]->nodos()->count());
            $this->assertFalse($componentes[$slug]->tieneModulo('documentos'));
        }

        $this->assertTrue($componentes['calle']->tieneModulo('supervisiones'));
        $this->assertFalse($componentes['calle']->tieneModulo('supervision'));
    }

    public function test_doc25_ca01_correr_la_siembra_dos_veces_no_duplica_ni_pisa_lo_cambiado(): void
    {
        [$inclusion, $calle] = $this->inclusionConCalle();

        (new AccesoPorComponenteSeeder)->run();
        // El superadministrador le quitó un módulo a Calle: la segunda corrida lo respeta.
        $calle->forceFill(['modulos' => ['documentos']])->save();
        (new AccesoPorComponenteSeeder)->run();

        $this->assertSame(3, Componente::withoutGlobalScopes()->where('dependencia_id', $inclusion->id)->count());
        $this->assertSame(['documentos'], $calle->fresh()->modulos);
    }

    public function test_doc25_ca01_no_toca_nada_fuera_de_inclusion_social(): void
    {
        $otra = Dependencia::factory()->create(['slug' => 'salud-mental']);
        $suyo = Componente::factory()->create(['dependencia_id' => $otra->id, 'slug' => 'calle', 'modulos' => null]);
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $otra);
        $usuario->dependencias()->updateExistingPivot($otra->id, ['componente_id' => null]); // como antes de DOC-25
        $persona = Colaborador::factory()->create(['dependencia_id' => $otra->id, 'componente_id' => null]);

        // Sin Inclusión Social no hace nada.
        (new AccesoPorComponenteSeeder)->run();
        $this->assertSame(1, Componente::withoutGlobalScopes()->count());

        $this->inclusionConCalle();
        (new AccesoPorComponenteSeeder)->run();

        $this->assertSame(0, Componente::withoutGlobalScopes()->where('dependencia_id', $otra->id)->whereKeyNot($suyo->id)->count());
        $this->assertNull($suyo->fresh()->modulos);
        $this->assertNull($usuario->fresh()->componenteEn($otra));
        $this->assertNull(Colaborador::withoutGlobalScopes()->find($persona->id)->componente_id);
    }

    public function test_doc25_ca02_lectura_edicion_y_coordinacion_quedan_en_calle_y_administracion_sin_componente(): void
    {
        [$inclusion, $calle] = $this->inclusionConCalle();

        $lectura = $this->usuarioCon(RolDependencia::Lectura, $inclusion);
        $edicion = $this->usuarioCon(RolDependencia::Edicion, $inclusion);
        $edicion->dependencias()->updateExistingPivot($inclusion->id, ['aprueba_novedades' => true]);
        $coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $inclusion);
        $administracion = $this->usuarioCon(RolDependencia::Administracion, $inclusion);
        $persona = Colaborador::factory()->create(['dependencia_id' => $inclusion->id, 'componente_id' => null]);

        (new AccesoPorComponenteSeeder)->run();
        (new AccesoPorComponenteSeeder)->run();

        foreach ([$lectura, $edicion, $coordinacion] as $usuario) {
            $this->assertSame($calle->id, $usuario->fresh()->componenteEn($inclusion));
        }
        $this->assertNull($administracion->fresh()->componenteEn($inclusion));

        // Conservan rol y «Aprueba novedades».
        $this->assertSame(RolDependencia::Lectura, $lectura->fresh()->rolEn($inclusion));
        $this->assertSame(RolDependencia::Edicion, $edicion->fresh()->rolEn($inclusion));
        $this->assertSame(RolDependencia::Coordinacion, $coordinacion->fresh()->rolEn($inclusion));
        $this->assertSame(RolDependencia::Administracion, $administracion->fresh()->rolEn($inclusion));
        $this->assertTrue($edicion->fresh()->puedeAprobarNovedadesEn($inclusion));
        $this->assertSame(4, DB::table('dependencia_usuario')->where('dependencia_id', $inclusion->id)->count());

        $this->assertSame($calle->id, Colaborador::withoutGlobalScopes()->find($persona->id)->componente_id);
    }

    public function test_doc25_ca02_quien_ya_tiene_componente_no_se_mueve(): void
    {
        [$inclusion] = $this->inclusionConCalle();
        (new AccesoPorComponenteSeeder)->run();
        $basica = Componente::withoutGlobalScopes()->where('slug', 'basica')->sole();

        $usuario = $this->usuarioCon(RolDependencia::Edicion, $inclusion);
        $usuario->dependencias()->updateExistingPivot($inclusion->id, ['componente_id' => $basica->id]);
        (new AccesoPorComponenteSeeder)->run();

        $this->assertSame($basica->id, $usuario->fresh()->componenteEn($inclusion));
    }

    public function test_doc25_ca01_la_fabrica_trae_todos_los_modulos_o_ninguno(): void
    {
        $this->assertSame(array_keys(Componente::MODULOS), Componente::factory()->create()->modulos);
        $this->assertSame([], Componente::factory()->sinModulos()->create()->fresh()->modulos);
    }

    public function test_doc25_ca01_el_down_del_esquema_deja_las_tablas_como_estaban(): void
    {
        $migracion = require database_path('migrations/2026_11_24_000001_acceso_por_componente.php');

        $migracion->down();
        $this->assertFalse(Schema::hasColumn('componentes', 'modulos'));
        $this->assertFalse(Schema::hasColumn('dependencia_usuario', 'componente_id'));
        $this->assertTrue(Schema::hasColumns('dependencia_usuario', ['dependencia_id', 'user_id', 'rol', 'aprueba_novedades']));

        $migracion->up();
        $this->assertTrue(Schema::hasColumn('componentes', 'modulos'));
        $this->assertTrue(Schema::hasColumn('dependencia_usuario', 'componente_id'));
    }

    /*
    | ACC-02: contexto, Gate 'modulo' y variables de vista. Sin filtrar datos todavía.
    */

    private function contexto(): ContextoDependencia
    {
        return app(ContextoDependencia::class);
    }

    public function test_doc25_ca15_el_contexto_tiene_tres_estados(): void
    {
        $dependencia = Dependencia::factory()->create();
        $sinModulos = Componente::factory()->sinModulos()->create(['dependencia_id' => $dependencia->id]);
        $calle = Componente::factory()->create(['dependencia_id' => $dependencia->id]);

        // Sin establecer (consola, vía pública, Livewire::test): no restringe.
        $this->assertFalse($this->contexto()->filtraComponente());
        $this->assertNull($this->contexto()->componenteId());
        $this->assertTrue($this->contexto()->tieneModulo('turnos'));

        // Con componente.
        $this->contexto()->establecerComponente($calle);
        $this->assertTrue($this->contexto()->filtraComponente());
        $this->assertSame($calle->id, $this->contexto()->componenteId());
        $this->assertTrue($this->contexto()->componente()->is($calle));
        $this->assertTrue($this->contexto()->tieneModulo('turnos'));

        $this->contexto()->establecerComponente($sinModulos);
        $this->assertFalse($this->contexto()->tieneModulo('documentos'));

        // Sin componente: no abre nada.
        $this->contexto()->establecerComponente(null);
        $this->assertTrue($this->contexto()->filtraComponente());
        $this->assertNull($this->contexto()->componente());
        $this->assertFalse($this->contexto()->tieneModulo('documentos'));

        // Cambiar de dependencia lo deja sin establecer.
        $this->contexto()->establecer($dependencia);
        $this->assertFalse($this->contexto()->filtraComponente());
        $this->assertTrue($this->contexto()->tieneModulo('documentos'));
    }

    public function test_doc25_ca15_edicion_trabaja_en_el_componente_de_su_pivote(): void
    {
        $dependencia = Dependencia::factory()->create();
        Componente::factory()->create(['dependencia_id' => $dependencia->id]);
        $basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $dependencia->id]);
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $dependencia, $basica);

        $this->actingAs($usuario)->get(route('perfil.edit'))
            ->assertOk()
            ->assertViewHas('componenteActual', fn ($c) => $c?->is($basica))
            ->assertViewHas('componentesDisponibles', fn ($lista) => $lista->isEmpty());

        $this->assertSame($basica->id, $this->contexto()->componenteId());
        $this->assertFalse(Gate::forUser($usuario)->allows('modulo', 'documentos'));
        $this->assertNull(session('componente_id'));
    }

    public function test_doc25_ca15_el_gate_modulo_abre_lo_que_tiene_el_componente(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        $this->actingAs($usuario)->get(route('perfil.edit'))->assertOk();

        foreach (array_keys(Componente::MODULOS) as $modulo) {
            $this->assertTrue(Gate::forUser($usuario)->allows('modulo', $modulo), $modulo);
        }
        $this->assertFalse(Gate::forUser($usuario)->allows('modulo', 'no-existe'));
    }

    public function test_doc25_ca15_sin_componente_o_con_uno_inactivo_no_abre_ningun_modulo(): void
    {
        $dependencia = Dependencia::factory()->create();
        $inactivo = Componente::factory()->inactivo()->create(['dependencia_id' => $dependencia->id]);
        $sinComponente = $this->usuarioCon(RolDependencia::Coordinacion, $dependencia);
        $sinComponente->dependencias()->updateExistingPivot($dependencia->id, ['componente_id' => null]);
        $enInactivo = $this->usuarioCon(RolDependencia::Coordinacion, $dependencia, $inactivo);

        foreach ([$sinComponente, $enInactivo] as $usuario) {
            $this->actingAs($usuario)->get(route('perfil.edit'))
                ->assertOk()
                ->assertViewHas('componenteActual', null);

            $this->assertTrue($this->contexto()->filtraComponente());
            $this->assertFalse(Gate::forUser($usuario)->allows('modulo', 'documentos'));
        }
    }

    public function test_doc25_ca15_administracion_elige_por_sesion_y_empieza_en_el_primero_con_modulos(): void
    {
        $dependencia = Dependencia::factory()->create();
        $vacio = Componente::factory()->sinModulos()->create(['dependencia_id' => $dependencia->id]);
        $calle = Componente::factory()->create(['dependencia_id' => $dependencia->id]);
        Componente::factory()->inactivo()->create(['dependencia_id' => $dependencia->id]);
        $deOtra = Componente::factory()->create();
        $admin = $this->usuarioCon(RolDependencia::Administracion, $dependencia);

        $this->assertNull($admin->componenteEn($dependencia));

        $this->actingAs($admin)->get(route('perfil.edit'))
            ->assertViewHas('componenteActual', fn ($c) => $c?->is($calle))
            ->assertViewHas('componentesDisponibles', fn ($lista) => $lista->modelKeys() === [$vacio->id, $calle->id]);
        $this->assertSame($calle->id, session('componente_id'));
        $this->assertTrue(Gate::forUser($admin)->allows('modulo', 'turnos'));

        // Lo que eligió queda en la sesión, aunque no tenga módulos.
        $this->withSession(['componente_id' => $vacio->id])->get(route('perfil.edit'))
            ->assertViewHas('componenteActual', fn ($c) => $c?->is($vacio));
        $this->assertFalse(Gate::forUser($admin)->allows('modulo', 'turnos'));

        // Uno de otra dependencia no vale.
        $this->withSession(['componente_id' => $deOtra->id])->get(route('perfil.edit'))
            ->assertViewHas('componenteActual', fn ($c) => $c?->is($calle));
    }

    public function test_doc25_ca15_el_superadministrador_elige_como_administracion(): void
    {
        $dependencia = Dependencia::factory()->create();
        $calle = Componente::factory()->create(['dependencia_id' => $dependencia->id]);
        $basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $dependencia->id]);
        $super = User::factory()->superadmin()->create();

        $this->actingAs($super)->get(route('perfil.edit'))
            ->assertViewHas('componenteActual', fn ($c) => $c?->is($calle))
            ->assertViewHas('componentesDisponibles', fn ($lista) => $lista->count() === 2);
        $this->assertTrue(Gate::forUser($super)->allows('modulo', 'derivaciones'));

        $this->withSession(['componente_id' => $basica->id])->get(route('perfil.edit'));
        $this->assertFalse(Gate::forUser($super)->allows('modulo', 'derivaciones'));
    }

    public function test_doc25_ca14_la_via_publica_no_establece_el_componente(): void
    {
        $enlace = EnlaceTurno::factory()->create();
        $basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $enlace->dependencia_id]);
        $usuario = $this->usuarioCon(RolDependencia::Edicion, Dependencia::find($enlace->dependencia_id), $basica);

        // Con una sesión abierta en el mismo navegador.
        $this->actingAs($usuario)->get('/t/'.$enlace->token_cifrado)->assertOk();

        $this->assertFalse($this->contexto()->filtraComponente());
        $this->assertTrue($this->contexto()->tieneModulo('turnos'));
    }
}
