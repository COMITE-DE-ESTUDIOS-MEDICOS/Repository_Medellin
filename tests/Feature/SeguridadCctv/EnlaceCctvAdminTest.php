<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\RolDependencia;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DOC-33 (CCTV-07): crear y revocar el enlace CCTV en la pantalla de enlaces de Turnos. */
class EnlaceCctvAdminTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
    }

    private function usuario(RolDependencia $rol, bool $casilla = false): User
    {
        $usuario = $this->usuarioCon($rol, $this->dependencia, $this->calle);
        $usuario->dependencias()->updateExistingPivot($this->dependencia->id, ['responsable_cctv' => $casilla]);

        return $usuario->unsetRelation('dependencias');
    }

    /** @return array<string, int|string> */
    private function datos(): array
    {
        return ['tipo' => 'seguridad_cctv', 'componente_id' => $this->calle->id];
    }

    public function test_doc33_ca04_quien_tiene_permiso_crea_el_enlace_y_lo_revoca(): void
    {
        foreach ([$this->usuario(RolDependencia::Edicion, true), $this->usuario(RolDependencia::Coordinacion)] as $usuario) {
            $this->actingAs($usuario)->post(route('admin.turnos.enlaces.store'), $this->datos())->assertSessionHasNoErrors();
        }

        $enlace = EnlaceTurno::where('tipo', 'seguridad_cctv')->firstOrFail();
        $this->assertSame(2, EnlaceTurno::where('tipo', 'seguridad_cctv')->count());

        $this->actingAs($this->usuario(RolDependencia::Edicion, true))
            ->patch(route('admin.turnos.enlaces.revocar', $enlace))->assertSessionHas('exito');
        $this->assertFalse($enlace->fresh()->activo);
    }

    public function test_doc33_ca04_edicion_sin_casilla_no_crea_ni_revoca_ni_ve_la_opcion(): void
    {
        $edicion = $this->usuario(RolDependencia::Edicion);
        $enlace = EnlaceTurno::factory()->de($this->calle)->create(['tipo' => 'seguridad_cctv']);

        $this->actingAs($edicion)->post(route('admin.turnos.enlaces.store'), $this->datos())->assertForbidden();
        $this->assertSame(1, EnlaceTurno::count());

        $this->actingAs($edicion)->patch(route('admin.turnos.enlaces.revocar', $enlace))->assertForbidden();
        $this->assertTrue($enlace->fresh()->activo);

        $this->actingAs($edicion)->get(route('admin.turnos.enlaces.create'))
            ->assertOk()->assertDontSee('value="seguridad_cctv"', false)->assertSee('value="turno"', false);

        // Los enlaces de turno siguen igual para él.
        $this->actingAs($edicion)->post(route('admin.turnos.enlaces.store'), ['componente_id' => $this->calle->id])->assertSessionHasNoErrors();
    }

    public function test_doc33_ca04_la_opcion_aparece_para_quien_puede_y_el_contador_cuenta_casos(): void
    {
        $this->actingAs($this->usuario(RolDependencia::Edicion, true))->get(route('admin.turnos.enlaces.create'))
            ->assertOk()->assertSee('value="seguridad_cctv"', false);

        $enlace = EnlaceTurno::factory()->de($this->calle)->create(['tipo' => 'seguridad_cctv']);
        CasoCctv::factory()->de($this->calle)->count(3)->create(['enlace_turno_id' => $enlace->id]);

        $this->actingAs($this->usuario(RolDependencia::Coordinacion))->get(route('admin.turnos.enlaces.index'))
            ->assertOk()->assertSee('Seguridad CCTV')->assertSeeInOrder(['Seguridad CCTV', '3']);
    }
}
