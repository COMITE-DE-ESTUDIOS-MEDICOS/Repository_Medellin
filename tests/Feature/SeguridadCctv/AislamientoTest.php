<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\RolDependencia;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DOC-33 (CA-02) y DOC-25: un caso de otro componente no se abre por su
 * dirección. El modelo de la ruta se resuelve con el componente ya puesto,
 * así que el filtro de componente actúa también ahí (no solo en las listas).
 */
class AislamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_doc33_ca02_un_caso_de_otro_componente_da_no_encontrado_por_su_direccion(): void
    {
        $dependencia = Dependencia::factory()->create();
        $calle = Componente::factory()->create(['dependencia_id' => $dependencia->id]);
        $otro = Componente::factory()->create(['dependencia_id' => $dependencia->id]);
        $caso = CasoCctv::factory()->de($otro)->create();
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $dependencia, $calle);

        $this->actingAs($coordinador)->get(route('admin.turnos.cctv.show', $caso))->assertNotFound();
        $this->actingAs($coordinador)->get(route('admin.turnos.cctv.edit', $caso))->assertNotFound();
        $this->actingAs($coordinador)->patch(route('admin.turnos.cctv.anular', $caso), ['motivo' => 'x'])->assertNotFound();
        $this->assertSame(CasoCctv::ABIERTO, CasoCctv::withoutGlobalScopes()->find($caso->id)->estado);
    }
}
