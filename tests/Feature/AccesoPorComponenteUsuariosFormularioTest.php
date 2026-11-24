<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * DOC-25 (ACC-08): el campo «Componente» del formulario de usuarios. Las
 * reglas las cumple el servidor (ACC-07, AccesoPorComponenteUsuariosTest);
 * aquí, que la pantalla ofrezca lo que el servidor acepta.
 */
class AccesoPorComponenteUsuariosFormularioTest extends TestCase
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

    private function opcion(Componente $componente, bool $elegida = false): string
    {
        // @selected deja un espacio cuando no aplica: <option value="1" >Calle</option>.
        return '<option value="'.$componente->id.'" '.($elegida ? 'selected' : '').'>'.e($componente->nombre).'</option>';
    }

    private function oculto(Componente $componente): string
    {
        return '<input type="hidden" name="componente_id" value="'.$componente->id.'">';
    }

    private function formulario(User $quien, ?User $ficha = null): TestResponse
    {
        return $this->actingAs($quien)
            ->get($ficha ? route('admin.usuarios.edit', $ficha) : route('admin.usuarios.create'))
            ->assertOk();
    }

    public function test_doc25_ca08_administracion_elige_entre_todos_los_componentes_activos(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $inactivo = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Cerrado', 'activo' => false]);

        $this->formulario($admin)
            ->assertSee('<select id="componente_id" name="componente_id"', false)
            ->assertSee($this->opcion($this->calle), false)
            ->assertSee($this->opcion($this->basica), false)
            ->assertDontSee($this->opcion($inactivo), false)
            ->assertDontSee('<input type="hidden" name="componente_id"', false);
    }

    public function test_doc25_ca08_al_editar_viene_elegido_el_componente_de_la_persona(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $deBasica = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);

        $this->formulario($admin, $deBasica)
            ->assertSee($this->opcion($this->basica, elegida: true), false)
            ->assertSee($this->opcion($this->calle), false);
    }

    public function test_doc25_ca08_si_el_formulario_vuelve_con_errores_conserva_el_componente_elegido(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->actingAs($admin)->from(route('admin.usuarios.create'))
            ->post(route('admin.usuarios.store'), ['name' => '', 'documento' => '1122334455', 'rol' => 'edicion', 'componente_id' => $this->basica->id])
            ->assertSessionHasErrors('name');

        $this->formulario($admin)->assertSee($this->opcion($this->basica, elegida: true), false);
    }

    public function test_doc25_ca08_coordinacion_ve_su_componente_fijo_sin_poder_elegir_otro(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);

        foreach ([null, $editor] as $ficha) {
            $this->formulario($coordinador, $ficha)
                ->assertSee($this->oculto($this->calle), false)
                ->assertSee('Las personas que gestionas quedan en tu componente.')
                ->assertDontSee('<select id="componente_id"', false)
                ->assertDontSee('Básica');
        }
    }

    public function test_doc25_ca08_lo_que_envia_el_formulario_de_coordinacion_se_guarda_en_su_componente(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $this->formulario($coordinador)->assertSee($this->oculto($this->calle), false);

        $this->actingAs($coordinador)->post(route('admin.usuarios.store'), [
            'name' => 'Nueva Persona', 'documento' => '1122334455', 'rol' => 'edicion',
            'componente_id' => $this->calle->id, 'activo' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->calle->id, User::where('documento', '1122334455')->sole()->componenteEn($this->dependencia));
    }

    public function test_doc25_ca09_con_el_rol_administracion_el_campo_explica_que_no_lleva_componente(): void
    {
        $jefe = User::factory()->superadmin()->create();

        $this->formulario($jefe)
            ->assertSee('value="administracion"', false)
            ->assertSee('data-componente-administracion', false)
            ->assertSee('Administración no tiene componente: trabaja en todos los de la dependencia.')
            // El selector se esconde con el rol Administración marcado (solo con CSS).
            ->assertSee('group-has-[[value=administracion]:checked]/rol:hidden', false);
    }

    /** Solo el bloque del campo «Componente», para no confundirlo con el menú (que también nombra el componente). */
    private function bloqueComponente(TestResponse $respuesta): string
    {
        $html = $respuesta->getContent();
        $desde = strpos($html, 'data-campo-componente');
        $this->assertNotFalse($desde, 'No está el bloque del campo «Componente».');

        return substr($html, $desde, strpos($html, 'name="aprueba_novedades"', $desde) - $desde);
    }

    public function test_doc25_ca08_en_su_propia_ficha_ve_su_componente_sin_poder_cambiarlo(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica);

        $respuesta = $this->formulario($coordinador, $coordinador)
            ->assertSee('Tu rol, tu componente y el estado de tu cuenta los cambia otra persona');
        $bloque = $this->bloqueComponente($respuesta);
        $this->assertStringContainsString('Básica', $bloque);
        $this->assertStringNotContainsString('name="componente_id"', $bloque);

        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->formulario($admin, $admin)
            ->assertSee('Todos: Administración no tiene componente.')
            ->assertDontSee('name="componente_id"', false);
    }

    /** Plan P-02 (Edwar, 2026-11-24): nadie se desactiva a sí mismo; el servidor lo deja activo. */
    public function test_doc25_ca08_en_su_propia_ficha_no_ofrece_desactivar_la_cuenta(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);

        $this->formulario($coordinador, $coordinador)
            ->assertSee('data-estado-propio', false)
            ->assertSee('Es tu propia cuenta: solo otra persona puede desactivarla.')
            ->assertDontSee('name="activo"', false);

        // A otra persona sí.
        $this->formulario($coordinador, $editor)->assertSee('<input type="checkbox" name="activo"', false);
    }

    public function test_doc25_ca08_ficha_propia_con_su_componente_inactivo_lo_nombra(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $this->calle->forceFill(['activo' => false])->save();

        $bloque = $this->bloqueComponente($this->formulario($coordinador, $coordinador));

        $this->assertStringContainsString('Calle (inactivo)', $bloque);
        $this->assertStringNotContainsString('Sin componente asignado', $bloque);
    }

    public function test_doc25_ca08_al_editar_a_alguien_con_su_componente_inactivo_avisa_que_elija_otro(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $deBasica = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);
        $this->basica->forceFill(['activo' => false])->save();

        $bloque = $this->bloqueComponente($this->formulario($admin, $deBasica));

        $this->assertStringContainsString('Su componente (Básica) está inactivo: elige otro para guardar.', $bloque);
        $this->assertStringContainsString($this->opcion($this->calle), $bloque);

        // Con uno activo, sin aviso.
        $this->basica->forceFill(['activo' => true])->save();
        $this->assertStringNotContainsString('data-componente-inactivo', $this->bloqueComponente($this->formulario($admin, $deBasica)));
    }

    /** CA-09: bajar una cuenta de Administración a Coordinación obliga a elegir componente. */
    public function test_doc25_ca09_el_superadministrador_edita_una_cuenta_de_administracion(): void
    {
        $jefe = User::factory()->superadmin()->create();
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $bloque = $this->bloqueComponente($this->formulario($jefe, $admin)->assertSee('value="administracion"', false));

        $this->assertStringContainsString('data-componente-administracion', $bloque);
        $this->assertStringContainsString('<select id="componente_id" name="componente_id"', $bloque);
        $this->assertStringNotContainsString('selected', $bloque);
        $this->assertStringNotContainsString('<input type="hidden" name="componente_id"', $bloque);
    }

    public function test_doc25_ca09_tras_un_error_vuelve_marcado_el_rol_administracion(): void
    {
        $jefe = User::factory()->superadmin()->create();

        $this->actingAs($jefe)->from(route('admin.usuarios.create'))
            ->post(route('admin.usuarios.store'), ['name' => '', 'documento' => '1122334455', 'rol' => 'administracion', 'componente_id' => $this->basica->id])
            ->assertSessionHasErrors('name');

        $html = $this->formulario($jefe)->getContent();
        $this->assertMatchesRegularExpression('/value="administracion"[^>]*\schecked/', $html);
    }

    public function test_doc25_ca08_sin_componentes_activos_lo_dice_en_vez_de_un_selector_vacio(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        Componente::withoutGlobalScopes()->where('dependencia_id', $this->dependencia->id)->update(['activo' => false]);

        $this->formulario($admin)
            ->assertSee('Esta dependencia no tiene componentes activos todavía.')
            ->assertDontSee('<select id="componente_id"', false);
    }

    public function test_doc25_ca10_la_ayuda_de_los_roles_habla_del_componente(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->formulario($admin)
            ->assertSee('Administración solo la da el superadministrador.')
            ->assertSee(RolDependencia::Coordinacion->descripcion())
            // Administración no da ese rol: no ve su ayuda.
            ->assertDontSee(RolDependencia::Administracion->descripcion());

        $this->formulario(User::factory()->superadmin()->create())
            ->assertSee(RolDependencia::Administracion->descripcion());

        $this->assertStringContainsString('de su componente', RolDependencia::Coordinacion->descripcion());
        $this->assertStringContainsString('Solo la da el superadministrador', RolDependencia::Administracion->descripcion());
    }
}
