<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Auditoria;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\GrupoCustodia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** DOC-33 (CCTV-02): la casilla «Responsable de Seguridad CCTV» en el formulario de usuarios. */
class CasillaTest extends TestCase
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

    private function usuario(RolDependencia $rol): User
    {
        return $this->usuarioCon($rol, $this->dependencia, $this->calle);
    }

    /** @param array<string, mixed> $extra */
    private function guardar(User $actor, User $destino, array $extra): void
    {
        $this->actingAs($actor)->put(route('admin.usuarios.update', $destino), [
            'name' => $destino->name, 'documento' => $destino->documento, 'rol' => $destino->rolEn($this->dependencia->id)->value,
            'componente_id' => $this->calle->id, 'activo' => '1', ...$extra,
        ])->assertSessionHasNoErrors();
    }

    private function marcada(User $usuario): bool
    {
        return (bool) $usuario->dependencias()->whereKey($this->dependencia->id)->first()->pivot->responsable_cctv;
    }

    private function enElGrupo(User $usuario): bool
    {
        return GrupoCustodia::withoutGlobalScopes()->where('nombre', CasoCctv::GRUPO_CUSTODIA)->first()?->tieneA($usuario) ?? false;
    }

    public function test_doc33_ca03_coordinacion_y_administracion_marcan_y_desmarcan_con_auditoria(): void
    {
        foreach ([RolDependencia::Coordinacion, RolDependencia::Administracion] as $rol) {
            $actor = $this->usuario($rol);
            $edicion = $this->usuario(RolDependencia::Edicion);

            $this->guardar($actor, $edicion, ['responsable_cctv' => '1']);
            $this->assertTrue($this->marcada($edicion), $rol->value);
            $this->assertTrue($this->enElGrupo($edicion));
            $this->actingAs($actor)->get(route('admin.usuarios.edit', $edicion))
                ->assertOk()->assertSee('Responsable de Seguridad CCTV')->assertSee('name="responsable_cctv"', false);

            $this->guardar($actor, $edicion, ['responsable_cctv' => '0']);
            $this->assertFalse($this->marcada($edicion));
            $this->assertFalse($this->enElGrupo($edicion));
        }

        $auditoria = Auditoria::where('accion', AccionAuditoria::UsuarioActualizado->value)->get();
        $this->assertCount(4, $auditoria);
        $this->assertSame(['antes' => false, 'despues' => true], $auditoria->first()->datos['responsable_cctv'] ?? null);
        $this->assertStringContainsString('Seguridad CCTV', $auditoria->first()->descripcion);
    }

    public function test_doc33_ca03_edicion_no_llega_al_formulario_ni_se_marca_a_si_mismo(): void
    {
        $edicion = $this->usuario(RolDependencia::Edicion);

        $this->actingAs($edicion)->get(route('admin.usuarios.edit', $edicion))->assertForbidden();
        $this->actingAs($edicion)->put(route('admin.usuarios.update', $edicion), [
            'name' => $edicion->name, 'documento' => $edicion->documento, 'rol' => 'edicion', 'responsable_cctv' => '1',
        ])->assertForbidden();

        $this->assertFalse($this->marcada($edicion));
    }

    public function test_doc33_ca03_al_dar_acceso_se_puede_marcar(): void
    {
        $admin = $this->usuario(RolDependencia::Administracion);

        $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'Freddy Nuevo', 'documento' => '70123456', 'rol' => 'edicion',
            'componente_id' => $this->calle->id, 'activo' => '1', 'responsable_cctv' => '1',
        ])->assertSessionHasNoErrors();

        $freddy = User::where('documento', '70123456')->firstOrFail();
        $this->assertTrue($this->marcada($freddy));
        $this->assertTrue($this->enElGrupo($freddy));
    }

    public function test_doc33_ca04_la_casilla_no_da_poder_fuera_del_modulo(): void
    {
        $admin = $this->usuario(RolDependencia::Administracion);
        $edicion = $this->usuario(RolDependencia::Edicion);
        $this->guardar($admin, $edicion, ['responsable_cctv' => '1']);
        $edicion = $edicion->fresh()->unsetRelation('dependencias');

        $this->assertTrue($edicion->puedeGestionarCctvEn($this->dependencia));
        $this->assertFalse($edicion->puedeGestionarEn($this->dependencia));
        $this->assertFalse($edicion->puedeAprobarNovedadesEn($this->dependencia));
        $this->actingAs($edicion)->get(route('admin.usuarios.index'))->assertForbidden();
    }

    public function test_doc33_ca19_el_repositorio_aparta_la_carpeta_de_cctv_de_edicion_sin_casilla(): void
    {
        $admin = $this->usuario(RolDependencia::Administracion);
        $sinCasilla = $this->usuario(RolDependencia::Edicion);
        $conCasilla = $this->usuario(RolDependencia::Edicion);
        $carpeta = CasoCctv::carpetaPara($this->calle);

        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($this->dependencia);
        $contexto->establecerComponente($this->calle);

        $this->guardar($admin, $conCasilla, ['responsable_cctv' => '1']);
        $this->guardar($admin, $sinCasilla, []);

        $this->assertFalse(Gate::forUser($sinCasilla->fresh())->allows('view', $carpeta));
        $this->assertTrue(Gate::forUser($conCasilla->fresh())->allows('view', $carpeta));

        // Al desmarcarla, vuelve a quedar fuera.
        $this->guardar($admin, $conCasilla, ['responsable_cctv' => '0']);
        $this->assertFalse(Gate::forUser($conCasilla->fresh())->allows('view', $carpeta));
    }
}
