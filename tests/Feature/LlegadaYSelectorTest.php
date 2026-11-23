<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/** DOC-25, ACC-05: la llegada (`inicio`) y el selector de componente. */
class LlegadaYSelectorTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    protected function setUp(): void
    {
        parent::setUp();

        // La vista `inicio` es de ACC-06 (frontend). Mientras no exista, una de paso.
        if (! View::exists('inicio')) {
            $dir = sys_get_temp_dir().'/documenta-acc05';
            @mkdir($dir);
            file_put_contents($dir.'/inicio.blade.php', '{{ $sinComponente ? "sin" : "vacio" }}');
            View::addLocation($dir);
        }

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
    }

    public function test_doc25_ca04_basica_con_cualquier_rol_llega_a_la_bienvenida(): void
    {
        foreach ([RolDependencia::Lectura, RolDependencia::Edicion, RolDependencia::Coordinacion] as $rol) {
            $this->actingAs($this->usuarioCon($rol, $this->dependencia, $this->basica))
                ->get(route('inicio'))
                ->assertOk()
                ->assertViewIs('inicio')
                ->assertViewHas('sinComponente', false);
        }
    }

    public function test_doc25_ca04_sin_componente_llega_a_la_bienvenida_que_pide_uno(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->dependencias()->updateExistingPivot($this->dependencia->id, ['componente_id' => null]);

        $this->actingAs($usuario->unsetRelation('dependencias'))
            ->get(route('inicio'))
            ->assertOk()
            ->assertViewHas('sinComponente', true);
    }

    public function test_doc25_ca04_calle_llega_al_primer_modulo_que_su_rol_puede_abrir(): void
    {
        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->calle))
            ->get(route('inicio'))->assertRedirect(route('documentos.index'));

        // Sin Documentos: Edición va a Turnos; Lectura no abre Turnos, así que bienvenida.
        $this->calle->forceFill(['modulos' => ['turnos', 'derivaciones']])->save();

        $this->actingAs($this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle))
            ->get(route('inicio'))->assertRedirect(route('admin.turnos.enlaces.index'));

        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->calle))
            ->get(route('inicio'))->assertViewIs('inicio');
    }

    public function test_doc25_ca04_el_login_y_el_cambio_de_dependencia_llevan_a_inicio(): void
    {
        // El formulario es solo del superadministrador (DOC-22); Authentik, en IngresoAuthentikTest.
        $super = User::factory()->create(['es_superadmin' => true]);

        $this->post(route('login'), ['identificador' => $super->documento, 'password' => 'password'])
            ->assertRedirect(route('inicio'));

        $this->put(route('dependencia.cambiar', $this->dependencia))->assertRedirect(route('inicio'));
        $this->get(route('inicio'))->assertRedirect(route('documentos.index'));
    }

    public function test_doc25_ca09_administracion_cambia_de_componente_con_el_selector(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->actingAs($admin)->get(route('inicio'))->assertRedirect(route('documentos.index'));

        $this->put(route('componente.cambiar', $this->basica->id))
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('componente_id', $this->basica->id);

        $this->get(route('inicio'))->assertViewIs('inicio');
        $this->assertSame($this->basica->id, app(ContextoDependencia::class)->componenteId());
        $this->assertFalse(Gate::forUser($admin)->allows('modulo', 'documentos'));

        $this->put(route('componente.cambiar', $this->calle->id))->assertSessionHas('componente_id', $this->calle->id);
        $this->get(route('inicio'))->assertRedirect(route('documentos.index'));
    }

    public function test_doc25_ca09_el_superadministrador_tambien_usa_el_selector(): void
    {
        $super = User::factory()->create(['es_superadmin' => true]);

        $this->actingAs($super)
            ->withSession(['dependencia_id' => $this->dependencia->id])
            ->put(route('componente.cambiar', $this->basica->id))
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('componente_id', $this->basica->id);
    }

    public function test_doc25_ca09_lectura_edicion_y_coordinacion_no_usan_el_selector(): void
    {
        foreach ([RolDependencia::Lectura, RolDependencia::Edicion, RolDependencia::Coordinacion] as $rol) {
            $this->actingAs($this->usuarioCon($rol, $this->dependencia, $this->calle))
                ->put(route('componente.cambiar', $this->basica->id))
                ->assertForbidden();
        }
    }

    public function test_doc25_ca09_el_selector_solo_acepta_componentes_activos_de_la_dependencia(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $inactivo = Componente::factory()->inactivo()->create(['dependencia_id' => $this->dependencia->id]);
        $ajeno = Componente::factory()->create();

        foreach ([$inactivo, $ajeno] as $componente) {
            $this->actingAs($admin)
                ->put(route('componente.cambiar', $componente->id))
                ->assertNotFound();
        }

        $this->assertNotContains(session('componente_id'), [$inactivo->id, $ajeno->id]);
    }
}
