<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lo que Edición puede hacer con las carpetas: renombrar, mover y retirar las suyas. */
class EdicionCarpetasTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
    }

    public function test_edicion_renombra_una_carpeta_desde_editar(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Viejo']);

        $this->actingAs($editor)->get(route('carpetas.edit', $carpeta))->assertOk();

        $this->actingAs($editor)
            ->put(route('carpetas.update', $carpeta), ['nombre' => 'Nuevo'])
            ->assertRedirect();

        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'nombre' => 'Nuevo']);
    }

    public function test_lectura_no_puede_renombrar(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Viejo']);

        $this->actingAs($lector)
            ->put(route('carpetas.update', $carpeta), ['nombre' => 'Nuevo'])
            ->assertForbidden();
    }

    /** Dos carpetas «Octubre» se distinguen por su ruta al copiar o mover. */
    public function test_los_selectores_de_carpeta_muestran_la_ruta_completa(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $nodo2 = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Nodo 2']);
        $nodo6 = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Nodo 6']);
        Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'carpeta_id' => $nodo2->id, 'nombre' => 'Octubre']);
        Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'carpeta_id' => $nodo6->id, 'nombre' => 'Octubre']);

        $this->actingAs($editor)->get(route('documentos.create'))
            ->assertOk()
            ->assertSee('Nodo 2 / Octubre')
            ->assertSee('Nodo 6 / Octubre')
            ->assertSee('data-buscable', false);
    }

    /** Edición retira cualquier carpeta que vea (lo custodiado por otros ni lo ve: CustodiaTest). */
    public function test_edicion_inactiva_carpetas_propias_y_ajenas_pero_no_las_reactiva(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $otro = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $ajena = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'creado_por' => $otro->id]);
        Documento::factory()->en($ajena)->create(['dependencia_id' => $this->dependencia->id, 'creado_por' => $otro->id]);

        $this->actingAs($editor)->patch(route('carpetas.inactivar', $ajena))->assertRedirect();
        $this->assertDatabaseHas('carpetas', ['id' => $ajena->id, 'activa' => false]);

        $this->actingAs($editor)->patch(route('carpetas.reactivar', $ajena))->assertForbidden();
    }
}
