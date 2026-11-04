<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Services\Turnos\Novedades;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * La regla de Talento Humano: lo que se compara con la jornada es el
 * trabajo real, la permanencia menos el descanso (30 min de desayuno + 30
 * de almuerzo). Antes se comparaba la permanencia entera.
 */
class DescansoTest extends TestCase
{
    use RefreshDatabase;

    private Componente $calle;

    private Colaborador $persona;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $dependencia = Dependencia::factory()->create();
        // Los valores de Talento Humano: 8 h netas, 1 h de descanso, 15 min de tolerancia.
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $dependencia->id,
            'config' => [
                'jornada_horas' => 8, 'tolerancia_min' => 15,
                'sin_descanso_hasta_h' => 4, 'descanso_parcial_min' => 30,
                'descanso_completo_desde_h' => 6, 'descanso_min' => 60,
            ],
        ]);
        $this->persona = Colaborador::factory()->en($this->calle)->create();
    }

    /** Un turno de las 7:00 que sale a la hora dada, ya juzgado. */
    private function turnoHasta(string $hora): ?NovedadTurno
    {
        $entrada = now()->subDay()->setTime(7, 0);
        [$h, $m] = explode(':', $hora);

        Marcacion::factory()->de($this->persona)->create(['marcada_at' => $entrada]);
        $salida = Marcacion::factory()->de($this->persona)->salida()->create(['marcada_at' => $entrada->copy()->setTime((int) $h, (int) $m)]);

        app(Novedades::class)->evaluar($salida, soloDuracion: true);

        return NovedadTurno::latest('id')->first();
    }

    public function test_el_caso_de_talento_humano_9h46_en_el_sitio_son_46_min_de_extra(): void
    {
        // 7:00 → 16:46: 9 h 46 min en el sitio, 1 h de descanso, 8 h 46 min de trabajo.
        $novedad = $this->turnoHasta('16:46');

        $this->assertSame(TipoNovedad::HorasExtra, $novedad->tipo);
        $this->assertSame(526, $novedad->minutos, 'Guarda el trabajo real: 8 h 46 min.');
        $this->assertSame('Estuvo 9 h 46 min; con 1 h de descanso, trabajó 8 h 46 min: 46 min más de las 8 h esperadas.', $novedad->detalle);
    }

    public function test_nueve_horas_en_el_sitio_son_exactamente_la_jornada(): void
    {
        // 7:00 → 16:00: 9 h en el sitio menos 1 h de descanso = 8 h. Sin novedad.
        $this->assertNull($this->turnoHasta('16:00'));
    }

    public function test_ocho_horas_en_el_sitio_son_un_turno_corto(): void
    {
        // Antes esto pasaba como jornada completa; con el descanso son 7 h de trabajo.
        $novedad = $this->turnoHasta('15:00');

        $this->assertSame(TipoNovedad::TurnoCorto, $novedad->tipo);
        $this->assertSame(420, $novedad->minutos);
    }

    public function test_dentro_de_la_tolerancia_no_hay_novedad(): void
    {
        // 9 h 10 min en el sitio: 8 h 10 min de trabajo, 10 min de más (< 15).
        $this->assertNull($this->turnoHasta('16:10'));
    }

    public function test_el_reporte_muestra_permanencia_descanso_y_trabajo_real(): void
    {
        $novedad = $this->turnoHasta('16:46');
        $novedad->update(['estado' => 'aprobada']);
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->calle->dependencia);

        $turnos = $this->hojaXlsx($this->actingAs($coordinador)->get(route('admin.turnos.marcaciones.exportar', [
            'desde' => now()->subDays(2)->toDateString(),
        ])), 'Turnos');

        // Permanencia 9,77 · descanso 1,00 · trabajadas 8,77 · jornada 8,00 · ordinarias 8,00 · extra 0,77 · aprobadas SI
        $this->assertEquals([9.77, 1, 8.77, 8, 8, 0.77, 'SI'], array_slice($turnos[1], 8, 7));
    }

    public function test_el_descuento_va_por_tramos_de_permanencia(): void
    {
        // Hasta 4 h: nada. Una pausa corta se paga como trabajo.
        $this->assertSame(0, $this->calle->descansoPara(240));
        $this->assertSame(240, $this->calle->minutosTrabajados(240));

        // Más de 4 h y hasta 6 h: solo el desayuno.
        $this->assertSame(30, $this->calle->descansoPara(241));
        $this->assertSame(270, $this->calle->minutosTrabajados(300));
        $this->assertSame(30, $this->calle->descansoPara(360));

        // Más de 6 h: desayuno y almuerzo.
        $this->assertSame(60, $this->calle->descansoPara(361));
        $this->assertSame(526, $this->calle->minutosTrabajados(586));
    }

    public function test_un_turno_de_cuatro_horas_no_menciona_descanso(): void
    {
        // 7:00 → 11:00: 4 h exactas, sin descuento. Es corto frente a 8 h.
        $novedad = $this->turnoHasta('11:00');

        $this->assertSame(TipoNovedad::TurnoCorto, $novedad->tipo);
        $this->assertSame(240, $novedad->minutos);
        $this->assertSame('Trabajó 4 h de 8 h esperadas.', $novedad->detalle);
    }

    public function test_un_turno_de_cinco_horas_descuenta_solo_el_desayuno(): void
    {
        $novedad = $this->turnoHasta('12:00');

        $this->assertSame(270, $novedad->minutos);
        $this->assertSame('Estuvo 5 h; con 30 min de descanso, trabajó 4 h 30 min de 8 h esperadas.', $novedad->detalle);
    }
}
