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
use Database\Seeders\CalleSeeder;
use Database\Seeders\CatalogoTurnosCalleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * DOC-8: cada turno se compara con la jornada de SU tipo (su horario menos el
 * descanso que le toca), y las horas extra tienen su propia tolerancia (40 a
 * 60 min). Valores de producción en Calle: tolerancia 20, descanso largo 30,
 * horas extra 60. Tipos reales (CatalogoTurnosCalleSeeder).
 */
class JornadaPorTurnoTest extends TestCase
{
    use RefreshDatabase;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Dependencia::factory()->create(['slug' => 'inclusion-social']);
        (new CalleSeeder)->run();
        (new CatalogoTurnosCalleSeeder)->run();
        $this->calle = Componente::withoutGlobalScopes()->where('slug', 'calle')->sole();
        $this->calle->update(['config' => [
            'jornada_horas' => 7, 'tolerancia_min' => 20, 'tolerancia_extra_min' => 60,
            'sin_descanso_hasta_h' => 4, 'descanso_parcial_min' => 30,
            'descanso_completo_desde_h' => 6, 'descanso_min' => 30,
        ] + $this->calle->config]);
        $this->travelTo(Carbon::parse('2026-11-16 09:00'));
    }

    /** El turno de esa entrada a esa salida, ya juzgado: la novedad de duración, si hubo. */
    private function turno(string $entrada, string $salida): ?NovedadTurno
    {
        $persona = Colaborador::factory()->en($this->calle)->create();
        Marcacion::factory()->de($persona)->create(['marcada_at' => Carbon::parse($entrada)]);
        $marca = Marcacion::factory()->de($persona)->salida()->create(['marcada_at' => Carbon::parse($salida)]);

        app(Novedades::class)->evaluar($marca, soloDuracion: true);

        return NovedadTurno::withoutGlobalScopes()->where('colaborador_id', $persona->id)
            ->whereIn('tipo', [TipoNovedad::TurnoCorto->value, TipoNovedad::HorasExtra->value])->first();
    }

    public function test_doc8_ca01_cada_turno_se_compara_con_su_horario(): void
    {
        $this->assertNull($this->turno('2026-11-13 06:00', '2026-11-13 10:00'), 'T4 del viernes: 4 h sin descanso, no es turno corto.');
        $this->assertNull($this->turno('2026-11-09 05:00', '2026-11-09 12:30'), 'N1: 7 h 30 min en el sitio, 30 de descanso, 7 h de trabajo.');
        $this->assertNull($this->turno('2026-11-15 06:00', '2026-11-15 18:00'), 'C13 del domingo: 12 h, no son horas extra.');
        $this->assertNull($this->turno('2026-11-09 21:00', '2026-11-10 04:30'), 'N7: cruza la medianoche.');

        $corto = $this->turno('2026-11-09 05:00', '2026-11-09 11:30');
        $this->assertSame(TipoNovedad::TurnoCorto, $corto->tipo, 'N1 que sale una hora antes sí es corto.');
        $this->assertStringContainsString('de 7 h esperadas en su turno N1', $corto->detalle);
    }

    public function test_doc8_ca02_las_horas_extra_tienen_su_propia_tolerancia(): void
    {
        $this->assertNull($this->turno('2026-11-09 05:00', '2026-11-09 13:20'), '50 min después del fin no es hora extra (tolerancia 60).');

        $extra = $this->turno('2026-11-10 05:00', '2026-11-10 13:45');
        $this->assertSame(TipoNovedad::HorasExtra, $extra->tipo, '75 min después del fin sí.');
        $this->assertStringContainsString('1 h 15 min más de las 7 h esperadas en su turno N1', $extra->detalle);

        // La de tardanza y corto sigue en 20: salir 30 min antes es corto.
        $this->assertSame(TipoNovedad::TurnoCorto, $this->turno('2026-11-11 05:00', '2026-11-11 12:00')->tipo);
    }

    /** Edwar, 2026-11-10: el descanso va por el turno. El T4 no tiene; los demás, el de su horario. */
    public function test_doc8_el_descanso_va_por_el_turno(): void
    {
        $t4 = \App\Models\TipoTurno::where('componente_id', $this->calle->id)->where('codigo', 'T4')->sole();
        $n1 = \App\Models\TipoTurno::where('componente_id', $this->calle->id)->where('codigo', 'N1')->sole();

        $this->assertSame(0, $this->calle->descansoPara(245, $t4), 'Un T4 que se queda 4 h 5 min no pierde 30 min.');
        $this->assertSame(0, $this->calle->descansoPara(300, $t4), 'Ni con 5 h: el T4 no tiene descanso.');
        $this->assertSame(30, $this->calle->descansoPara(450, $n1), 'N1: sus 30 min.');
        $this->assertSame(0, $this->calle->descansoPara(180, $n1), 'Un N1 que se va a las 3 h no descuenta un descanso que no tomó.');
        $this->assertSame(30, $this->calle->descansoPara(300), 'Sin turno, por el tiempo en el sitio, como antes.');

        $this->assertNull($this->turno('2026-11-13 06:00', '2026-11-13 10:05'), 'T4 de 6:00 a 10:05: 4 h 5 min de trabajo, sin novedad.');
    }

    public function test_doc8_ca03_el_reporte_usa_la_jornada_del_turno(): void
    {
        $this->turno('2026-11-13 06:00', '2026-11-13 10:00');
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->calle->dependencia);

        $hoja = $this->hojaXlsx($this->actingAs($coordinador)->get(route('admin.turnos.marcaciones.exportar', [
            'desde' => '2026-11-13', 'hasta' => '2026-11-13',
        ]))->assertOk(), 'Turnos');
        $fila = array_combine($hoja[0], $hoja[1]);

        $this->assertSame('T4', $fila['Turno']);
        $this->assertEquals(4, $fila['Jornada (h)']);
        $this->assertEquals(0, $fila['Horas extra']);
    }
}
