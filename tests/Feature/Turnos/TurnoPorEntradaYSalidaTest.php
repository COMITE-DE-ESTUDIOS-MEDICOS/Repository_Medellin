<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\TipoTurno;
use App\Services\Turnos\Jornadas;
use App\Services\Turnos\Novedades;
use App\Services\Turnos\Programacion;
use Database\Seeders\CalleSeeder;
use Database\Seeders\CatalogoTurnosCalleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * DOC-21 §1: con la entrada y la salida, el turno es el que mejor encaja con
 * las dos horas. Con los tipos reales de Calle (CatalogoTurnosCalleSeeder):
 * N1 5:00–12:30 y N1. 12:30–20:00 (L–S), T3 10:00–17:30 (L–S), T4 6:00–10:00
 * y T4. 12:00–16:00 (viernes), N7 21:00–4:30 (todos), C13 (domingo y festivos).
 * El viernes de las pruebas es el 13 de noviembre de 2026.
 */
class TurnoPorEntradaYSalidaTest extends TestCase
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
        $this->travelTo(Carbon::parse('2026-11-14 09:00'));
    }

    private function tipo(string $codigo): TipoTurno
    {
        return TipoTurno::where('codigo', $codigo)->sole();
    }

    /** [código, minutos tarde] del turno de esa entrada y esa salida. */
    private function turno(string $entrada, ?string $salida = null, ?string $habitual = null): array
    {
        $r = app(Programacion::class)->reconocer(
            $this->calle,
            Carbon::parse($entrada),
            $habitual ? $this->tipo($habitual) : null,
            $salida ? Carbon::parse($salida) : null,
        );

        return [$r['tipo']->codigo, $r['minutos_tarde']];
    }

    private function persona(?string $habitual = null): Colaborador
    {
        return Colaborador::factory()->en($this->calle)->create(['tipo_turno_id' => $habitual ? $this->tipo($habitual)->id : null]);
    }

    /** Una entrada como la guardó el marcador: con su turno provisional y su tardanza ya juzgada. */
    private function entrada(Colaborador $persona, string $cuando, string $provisional, int $tarde): Marcacion
    {
        $entrada = Marcacion::factory()->de($persona)->create([
            'marcada_at' => Carbon::parse($cuando),
            'tipo_turno_id' => $this->tipo($provisional)->id,
            'minutos_tarde' => $tarde,
        ]);
        app(Novedades::class)->evaluar($entrada);

        return $entrada;
    }

    private function salida(Colaborador $persona, string $cuando, OrigenMarcacion $origen = OrigenMarcacion::Enlace): Marcacion
    {
        $salida = Marcacion::factory()->de($persona)->salida()->create(['marcada_at' => Carbon::parse($cuando), 'origen' => $origen]);
        app(Novedades::class)->evaluar($salida);

        return $salida;
    }

    private function tardanzas(Marcacion $entrada)
    {
        return NovedadTurno::withoutGlobalScopes()->where('marcacion_id', $entrada->id)->where('tipo', TipoNovedad::Tardanza->value)->get();
    }

    public function test_doc21_ca01_con_entrada_y_salida_gana_el_que_mejor_encaja(): void
    {
        $this->assertSame(['T4', 0], $this->turno('2026-11-13 06:00', '2026-11-13 10:05'));
        $this->assertSame(['N1', 60], $this->turno('2026-11-13 06:00', '2026-11-13 12:35'));
        $this->assertSame(['N1', 40], $this->turno('2026-11-13 05:40', '2026-11-13 12:30'));
        $this->assertSame(['T4.', 20], $this->turno('2026-11-13 12:20', '2026-11-13 16:00'));
        $this->assertSame(['N1.', 0], $this->turno('2026-11-13 12:20', '2026-11-13 20:00'));
        $this->assertSame(['N7', 5], $this->turno('2026-11-12 21:05', '2026-11-13 04:30'), 'N7 termina al día siguiente.');
        $this->assertSame(['N1', 60], $this->turno('2026-11-10 06:00', '2026-11-10 12:30'), 'El martes no hay T4.');
    }

    public function test_doc21_ca02_el_habitual_solo_desempata(): void
    {
        $this->assertSame(['T4', 0], $this->turno('2026-11-13 06:00', '2026-11-13 10:00', 'N1'), 'El habitual N1 no gana con 60 min de tardanza.');
        $this->assertSame(['T4', 0], $this->turno('2026-11-13 06:00', null, 'N1'), 'Sin salida tampoco: T4 empieza más cerca.');

        // 5:30 del viernes, sin salida: N1 (30 min tarde) y T4 (30 min antes) encajan igual.
        $this->assertSame(['N1', 30], $this->turno('2026-11-13 05:30', null, 'N1'));
        $this->assertSame(['T4', 0], $this->turno('2026-11-13 05:30', null, 'T4'));
        $this->assertSame(['N1', 30], $this->turno('2026-11-13 05:30'), 'Sin habitual, desempata el orden de la malla.');
    }

    public function test_doc21_ca03_el_reporte_usa_el_turno_con_la_salida(): void
    {
        // Guardada como la habría guardado antes el marcador: N1 habitual, 60 min tarde.
        $persona = $this->persona('N1');
        $this->entrada($persona, '2026-11-13 06:00', 'N1', 60);
        $this->salida($persona, '2026-11-13 10:00');

        // Solo con la entrada sería T4 a tiempo; con la salida es N1 con 40.
        $tarde = $this->persona();
        $this->entrada($tarde, '2026-11-13 05:40', 'T4', 0);
        $this->salida($tarde, '2026-11-13 12:30');

        // Un cierre automático no es una hora real: cuenta solo la entrada (5:40 → T4, más cercano que N1).
        $otra = $this->persona();
        $this->entrada($otra, '2026-11-13 05:40', 'T4', 0);
        $this->salida($otra, '2026-11-13 12:30', OrigenMarcacion::CierreAutomatico);

        $turnos = app(Jornadas::class)->entre(Carbon::parse('2026-11-13'), Carbon::parse('2026-11-13 23:59:59'))
            ->keyBy(fn ($t) => $t['entrada']->colaborador_id);

        $this->assertSame(['T4', 0], [$turnos[$persona->id]['tipo']->codigo, $turnos[$persona->id]['minutos_tarde']]);
        $this->assertSame(['N1', 40], [$turnos[$tarde->id]['tipo']->codigo, $turnos[$tarde->id]['minutos_tarde']]);
        $this->assertSame(['T4', 0], [$turnos[$otra->id]['tipo']->codigo, $turnos[$otra->id]['minutos_tarde']], 'Con la salida real sería N1 con 40.');
    }

    public function test_doc21_la_tardanza_pendiente_se_corrige_al_marcar_la_salida(): void
    {
        $persona = $this->persona('N1');
        $entrada = $this->entrada($persona, '2026-11-13 06:00', 'N1', 60);
        $this->assertSame(EstadoNovedad::Pendiente, $this->tardanzas($entrada)->sole()->estado);

        $this->salida($persona, '2026-11-13 10:00');

        $tardanza = $this->tardanzas($entrada)->sole();
        $this->assertSame(EstadoNovedad::Corregida, $tardanza->estado);
        $this->assertSame('Con la salida, el turno es T4 (6:00 a. m. – 10:00 a. m.): llegó a tiempo.', $tardanza->comentario);
        $this->assertNotNull($tardanza->revisada_at);

        $entrada->refresh();
        $this->assertSame([$this->tipo('N1')->id, 60], [$entrada->tipo_turno_id, $entrada->minutos_tarde], 'La entrada no se reescribe.');
    }

    public function test_doc21_la_tardanza_cambia_o_aparece_con_la_salida(): void
    {
        // 6:40 → T4 con 40; con salida a las 12:30 es N1 con 1 h 40 min.
        $persona = $this->persona();
        $entrada = $this->entrada($persona, '2026-11-13 06:40', 'T4', 40);
        $this->salida($persona, '2026-11-13 12:30');

        $tardanza = $this->tardanzas($entrada)->sole();
        $this->assertSame([EstadoNovedad::Pendiente, 100], [$tardanza->estado, $tardanza->minutos]);
        $this->assertSame('Llegó 1 h 40 min tarde a su turno N1 (5:00 a. m. – 12:30 p. m.).', $tardanza->detalle);

        // 5:40 → T4 a tiempo; con salida a las 12:30 es N1 con 40: ahora sí hay tardanza, una sola.
        $otra = $this->persona();
        $entrada = $this->entrada($otra, '2026-11-13 05:40', 'T4', 0);
        $this->assertCount(0, $this->tardanzas($entrada));
        $salida = $this->salida($otra, '2026-11-13 12:30');
        app(Novedades::class)->evaluar($salida, soloDuracion: true);

        $tardanza = $this->tardanzas($entrada)->sole();
        $this->assertSame([EstadoNovedad::Pendiente, 40], [$tardanza->estado, $tardanza->minutos]);

        // Un cierre automático no toca la tardanza.
        $tercera = $this->persona();
        $entrada = $this->entrada($tercera, '2026-11-13 05:40', 'T4', 0);
        $this->salida($tercera, '2026-11-13 12:30', OrigenMarcacion::CierreAutomatico);
        $this->assertCount(0, $this->tardanzas($entrada));
    }
}
