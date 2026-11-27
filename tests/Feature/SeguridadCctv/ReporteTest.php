<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\RolDependencia;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DOC-33 (CCTV-11): el reporte ejecutivo, calculado en PHP sobre una consulta plana. */
class ReporteTest extends TestCase
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

    private function usuario(Componente $componente): User
    {
        return $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $componente);
    }

    public function test_doc33_ca16_las_cifras_con_tiempos_conocidos(): void
    {
        foreach ([30, 60, 90, 240] as $minutos) {
            CasoCctv::factory()->de($this->calle)->atendido($minutos)->create(['entidad' => 'policia', 'comuna' => 10]);
        }
        CasoCctv::factory()->de($this->calle)->atendido(30)->create(['estado' => CasoCctv::CERRADO, 'entidad' => 'camaras', 'comuna' => 1]);
        CasoCctv::factory()->de($this->calle)->create(['entidad' => 'camaras', 'comuna' => 1]);

        $respuesta = $this->actingAs($this->usuario($this->calle))->get(route('admin.turnos.cctv.reporte'));

        $resumen = $respuesta->assertOk()->viewData('resumen');
        $this->assertSame(6, $resumen['total']);
        $this->assertSame(['Abiertos' => 1, 'Atendidos' => 4, 'Cerrados' => 1], $resumen['estados']);
        $this->assertSame(['Policía' => 4, 'Cámaras' => 2], $resumen['entidades']);
        $this->assertSame(2, $resumen['comunas']['1 · '.config('repositorio.comunas')[1]]);
        $this->assertSame(5, $resumen['tiempos']['Casos con tiempo']);
    }

    public function test_doc33_ca16_promedio_mediana_minimo_y_maximo(): void
    {
        foreach ([30, 60, 90, 240] as $minutos) {
            CasoCctv::factory()->de($this->calle)->atendido($minutos)->create();
        }

        $tiempos = $this->actingAs($this->usuario($this->calle))->get(route('admin.turnos.cctv.reporte'))->viewData('resumen')['tiempos'];

        $this->assertSame('1 h 45 min', $tiempos['Promedio']);
        $this->assertSame('1 h 15 min', $tiempos['Mediana']);
        $this->assertSame('30 min', $tiempos['Mínimo']);
        $this->assertSame('4 h 0 min', $tiempos['Máximo']);
    }

    public function test_doc33_ca16_el_rango_por_defecto_es_el_mes_y_se_puede_cambiar(): void
    {
        CasoCctv::factory()->de($this->calle)->create(['requerido_at' => now()->startOfMonth()->addHour()]);
        CasoCctv::factory()->de($this->calle)->create(['requerido_at' => now()->subMonths(2)]);
        $usuario = $this->usuario($this->calle);

        $this->actingAs($usuario)->get(route('admin.turnos.cctv.reporte'))
            ->assertViewHas('desde', now()->startOfMonth()->toDateString())
            ->assertViewHas('resumen', fn (array $r): bool => $r['total'] === 1);

        $anterior = now()->subMonths(2)->toDateString();
        $this->get(route('admin.turnos.cctv.reporte', ['desde' => $anterior, 'hasta' => $anterior]))
            ->assertViewHas('resumen', fn (array $r): bool => $r['total'] === 1);

        $this->get(route('admin.turnos.cctv.reporte', ['desde' => '2026-12-01', 'hasta' => '2026-11-01']))->assertSessionHasErrors('hasta');
    }

    public function test_doc33_ca14_un_caso_anulado_no_cuenta(): void
    {
        CasoCctv::factory()->de($this->calle)->create();
        CasoCctv::factory()->de($this->calle)->atendido(500)->create(['estado' => CasoCctv::ANULADO]);

        $resumen = $this->actingAs($this->usuario($this->calle))->get(route('admin.turnos.cctv.reporte'))->viewData('resumen');

        $this->assertSame(1, $resumen['total']);
        $this->assertNull($resumen['tiempos']);
    }

    public function test_doc33_ca01_ca02_sin_modulo_no_abre_y_otro_componente_no_entra(): void
    {
        CasoCctv::factory()->de($this->calle)->create();
        CasoCctv::factory()->de($this->basica)->create();

        $this->actingAs($this->usuario($this->basica))->get(route('admin.turnos.cctv.reporte'))->assertForbidden();
        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->calle))->get(route('admin.turnos.cctv.reporte'))->assertForbidden();

        $this->actingAs($this->usuario($this->calle))->get(route('admin.turnos.cctv.reporte'))
            ->assertViewHas('resumen', fn (array $r): bool => $r['total'] === 1)
            ->assertSee('Descargar Excel');
    }
}
