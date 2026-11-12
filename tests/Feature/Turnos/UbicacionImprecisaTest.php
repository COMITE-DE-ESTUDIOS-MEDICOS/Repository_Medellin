<?php

namespace Tests\Feature\Turnos;

use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\Nodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DOC-15: ubicación imprecisa (iPhone con «Ubicación precisa» apagada). */
class UbicacionImprecisaTest extends TestCase
{
    use RefreshDatabase;

    private Componente $calle;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Notification::fake();

        $dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $dependencia->id, 'config' => ['foto_obligatoria' => false]]);
        // Zona del nodo: el factory la pone en el centro de Medellín (6.2476, -75.5658).
        $nodo = Nodo::factory()->conZona()->create(['componente_id' => $this->calle->id]);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create();
        Colaborador::factory()->en($this->calle)->create(['documento' => '1017000111', 'nodo_id' => $nodo->id]);
    }

    /** A ~4 km del nodo, con el error que se diga. */
    private function marcar(int $precision)
    {
        return $this->post(route('turno.marcar', ['token' => $this->token]), [
            'cedula' => '1017000111', 'epp' => 'si', 'lat' => 6.2790, 'lng' => -75.5420, 'precision_m' => $precision,
        ])->assertSessionHasNoErrors();
    }

    public function test_ca02_imprecisa_no_es_fuera_de_zona_y_crea_su_novedad(): void
    {
        $this->marcar(3000);

        $m = Marcacion::sole();
        $this->assertTrue($m->ubicacion_imprecisa);
        $this->assertFalse($m->fuera_de_zona);
        $this->assertFalse($m->sin_ubicacion);
        $this->assertSame(6.279, (float) $m->lat, 'La ubicación se guarda aunque sea aproximada.');

        $novedad = NovedadTurno::where('tipo', TipoNovedad::UbicacionImprecisa->value)->sole();
        $this->assertStringContainsString('±3 km', $novedad->detalle);
        $this->assertSame(0, NovedadTurno::where('tipo', TipoNovedad::FueraDeZona->value)->count());
    }

    public function test_ca03_precisa_y_lejos_sigue_siendo_fuera_de_zona(): void
    {
        $this->marcar(15);

        $m = Marcacion::sole();
        $this->assertFalse($m->ubicacion_imprecisa);
        $this->assertTrue($m->fuera_de_zona);
        $this->assertSame(0, NovedadTurno::where('tipo', TipoNovedad::UbicacionImprecisa->value)->count());
    }

    public function test_ca05_la_precision_minima_es_regla_del_componente(): void
    {
        $this->calle->update(['config' => [...$this->calle->config, 'precision_minima_m' => 5000]]);

        $this->marcar(3000);

        $this->assertFalse(Marcacion::sole()->ubicacion_imprecisa, 'Con 5 km de tolerancia, ±3 km no es imprecisa.');
    }

    public function test_ca01_la_pantalla_le_pasa_al_telefono_la_precision_y_si_es_obligatoria(): void
    {
        $this->get(route('turno.enlace', ['token' => $this->token]))
            ->assertSee('data-precision-minima="500"', false)
            ->assertSee('data-ubicacion-obligatoria="0"', false);
    }
}
