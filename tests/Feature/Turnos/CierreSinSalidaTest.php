<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Livewire\Turnos\Componentes;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Services\ContextoDependencia;
use App\Services\Turnos\Marcador;
use App\Services\Turnos\TurnoCerrado;
use Database\Seeders\CalleSeeder;
use Database\Seeders\CatalogoTurnosCalleSeeder;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * DOC-21 §2 y §3 (v0.4): el turno sin salida se cierra a las horas de su
 * cargo si las tiene; si no, al fin del turno más largo que podría ser más
 * el margen de horas extra (60 min); sin turno reconocible, a la duración
 * máxima del componente. No depende del cron: quien marca con una entrada
 * vencida la cierra y marca entrada.
 * Tipos reales de Calle (CatalogoTurnosCalleSeeder): N1 5:00–12:30 y N1.
 * 12:30–20:00 (L–S), T4 6:00–10:00 y T4. 12:00–16:00 (viernes). El viernes
 * de las pruebas es el 13 de noviembre de 2026.
 */
class CierreSinSalidaTest extends TestCase
{
    use RefreshDatabase;

    private const CONDUCTOR = 'Conductor camioneta';

    private Dependencia $dependencia;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->dependencia = Dependencia::factory()->create(['slug' => 'inclusion-social']);
        (new CalleSeeder)->run();
        (new CatalogoTurnosCalleSeeder)->run();
        $this->calle = Componente::withoutGlobalScopes()->where('slug', 'calle')->sole();
        $this->calle->update(['config' => [
            'horas_max_turno' => 8, 'tolerancia_min' => 20, 'foto_obligatoria' => false,
            'horas_max_por_cargo' => [self::CONDUCTOR => 15],
        ] + $this->calle->config]);
    }

    private function persona(string $cargo = 'Monitor de ruta'): Colaborador
    {
        return Colaborador::factory()->en($this->calle)->create(['cargo' => $cargo]);
    }

    private function entrada(Colaborador $persona, string $cuando): Marcacion
    {
        return Marcacion::factory()->de($persona)->create(['marcada_at' => Carbon::parse($cuando)]);
    }

    private function salidaDe(Colaborador $persona): ?Marcacion
    {
        return Marcacion::withoutGlobalScopes()->where('colaborador_id', $persona->id)->where('tipo', TipoMarcacion::Salida->value)->first();
    }

    private function tope(Marcacion $entrada): string
    {
        return app(Marcador::class)->tope($entrada->fresh())->format('Y-m-d H:i');
    }

    public function test_doc21_ca04_sin_salida_se_cierra_al_tope_del_cargo(): void
    {
        $general = $this->persona();
        $conductor = $this->persona(self::CONDUCTOR);
        $e1 = $this->entrada($general, '2026-11-12 07:00');
        $e2 = $this->entrada($conductor, '2026-11-12 07:00');

        $this->assertSame('2026-11-12 13:30', $this->tope($e1), 'Se reconoce N1: 12:30 + 60 min de margen.');
        $this->assertSame('2026-11-12 22:00', $this->tope($e2));

        $this->travelTo(Carbon::parse('2026-11-12 16:00'));
        $this->assertSame(1, app(Marcador::class)->cerrarAbiertas(), 'El conductor sigue en turno.');
        $salida = $this->salidaDe($general);
        $this->assertSame('2026-11-12 13:30', $salida->marcada_at->format('Y-m-d H:i'));
        $this->assertSame(OrigenMarcacion::CierreAutomatico, $salida->origen);
        $this->assertStringContainsString('su turno (N1)', $salida->motivo);
        $this->assertNull($this->salidaDe($conductor));

        $this->travelTo(Carbon::parse('2026-11-12 23:00'));
        $this->assertSame(1, app(Marcador::class)->cerrarAbiertas());
        $this->assertSame('2026-11-12 22:00', $this->salidaDe($conductor)->marcada_at->format('Y-m-d H:i'));
        $this->assertSame(0, app(Marcador::class)->cerrarAbiertas(), 'Lo ya cerrado no se cierra dos veces.');
        // Sin turno reconocible (2:00 a. m. no encaja con ninguno): la duración máxima del componente.
        $sinTurno = $this->entrada($this->persona(), '2026-11-12 02:00');
        $this->assertSame('2026-11-12 10:00', $this->tope($sinTurno));
    }

    public function test_doc21_ca05_turno_corto_se_cierra_al_fin_mas_tolerancia(): void
    {
        $t4 = $this->persona();
        $t4Tarde = $this->persona();
        $duda = $this->persona();
        $e1 = $this->entrada($t4, '2026-11-13 06:00');
        $e2 = $this->entrada($t4Tarde, '2026-11-13 12:05');
        $e3 = $this->entrada($duda, '2026-11-13 12:15');

        // DOC-21 v0.4: al fin del turno más largo que podría ser, más 60 min de margen.
        $this->assertSame('2026-11-13 13:30', $this->tope($e1), 'Parece T4, pero podría ser un N1 que llegó tarde: N1 termina 12:30 + 60.');
        $this->assertSame('2026-11-13 21:00', $this->tope($e2), 'Parece T4., pero podría ser N1.: 20:00 + 60.');
        $this->assertSame('2026-11-13 21:00', $this->tope($e3), 'N1.: 20:00 + 60.');

        // A las 11:00 nadie se cortó: el N1 tardío sigue trabajando.
        $this->travelTo(Carbon::parse('2026-11-13 11:00'));
        $this->assertSame(0, app(Marcador::class)->cerrarAbiertas());

        $this->travelTo(Carbon::parse('2026-11-13 14:00'));
        $this->assertSame(1, app(Marcador::class)->cerrarAbiertas());
        $salida = $this->salidaDe($t4);
        $this->assertSame('2026-11-13 13:30', $salida->marcada_at->format('Y-m-d H:i'));
        $this->assertStringContainsString('su turno (N1)', $salida->motivo);

        // Un conductor (cargo con su propio máximo) no se corta por turno corto.
        $this->calle->update(['config' => ['horas_max_por_cargo' => [self::CONDUCTOR => 15]] + $this->calle->config]);
        $conductor = $this->persona(self::CONDUCTOR);
        $this->assertSame('2026-11-13 21:00', $this->tope($this->entrada($conductor, '2026-11-13 06:00')));
    }

    public function test_doc21_ca06_queda_novedad_y_el_reporte_marca_por_corregir(): void
    {
        $persona = $this->persona();
        $this->entrada($persona, '2026-11-12 07:00');
        $this->travelTo(Carbon::parse('2026-11-12 16:00'));
        app(Marcador::class)->cerrarAbiertas();
        app(ContextoDependencia::class)->establecer(null);

        $novedad = NovedadTurno::withoutGlobalScopes()->where('tipo', TipoNovedad::SinSalida->value)->sole();
        $this->assertSame(EstadoNovedad::Pendiente, $novedad->estado);

        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $rango = ['desde' => '2026-11-12', 'hasta' => '2026-11-12'];
        $marca = 'Cierre automático · por corregir';

        $this->actingAs($coordinador)->get(route('admin.turnos.marcaciones.index', $rango))->assertOk()->assertSee($marca);
        $this->actingAs($coordinador)->get(route('admin.turnos.colaboradores.show', [$persona, 'q' => '2026-11-12']))->assertOk()->assertSee($marca);
        $fila = $this->filaDelExcel($coordinador, $rango);
        $this->assertStringContainsString($marca, $fila['Salida']);
        $this->assertEquals(6.5, $fila['Permanencia (h)'], 'Las horas cuentan hasta el cierre.');

        // Revisada, la marca se va.
        $novedad->update(['estado' => EstadoNovedad::Aprobada->value]);

        $this->actingAs($coordinador)->get(route('admin.turnos.marcaciones.index', $rango))->assertDontSee($marca);
        $this->actingAs($coordinador)->get(route('admin.turnos.colaboradores.show', [$persona, 'q' => '2026-11-12']))->assertDontSee($marca);
        $this->assertSame('12/11/2026 13:30', $this->filaDelExcel($coordinador, $rango)['Salida']);
    }

    /** @return array<string, mixed> la fila del turno en la pestaña «Turnos», por encabezado */
    private function filaDelExcel($usuario, array $rango): array
    {
        $hoja = $this->hojaXlsx($this->actingAs($usuario)->get(route('admin.turnos.marcaciones.exportar', $rango))->assertOk(), 'Turnos');

        return array_combine($hoja[0], $hoja[1]);
    }

    public function test_doc21_ca07_al_marcar_con_entrada_vencida_se_cierra_y_lo_nuevo_es_entrada(): void
    {
        $marcador = app(Marcador::class);
        $enlace = EnlaceTurno::factory()->de($this->calle)->create();
        $persona = $this->persona();
        $this->entrada($persona, '2026-11-12 05:00');

        // Sin cron: al día siguiente a la misma hora.
        $this->travelTo(Carbon::parse('2026-11-13 05:00'));
        $this->assertSame('entrada', $marcador->estadoParaMarcar($persona, $this->calle));
        $this->assertNull($this->salidaDe($persona), 'Preguntar no escribe nada.');

        $nueva = $marcador->marcar($persona, $enlace, ['ip' => '127.0.0.1']);

        $this->assertSame(TipoMarcacion::Entrada, $nueva->tipo);
        $cierre = $this->salidaDe($persona);
        $this->assertSame(OrigenMarcacion::CierreAutomatico, $cierre->origen);
        $this->assertSame('2026-11-12 13:30', $cierre->marcada_at->format('Y-m-d H:i'), 'La de ayer, a su tope (N1: 12:30 + 60).');
        $this->assertTrue($cierre->marcada_at->lt($nueva->marcada_at));
        $this->assertSame(1, NovedadTurno::withoutGlobalScopes()->where('tipo', TipoNovedad::SinSalida->value)->count());

        // Antes del tope sigue siendo salida.
        $this->travelTo(Carbon::parse('2026-11-13 09:00'));
        $this->assertSame('salida', $marcador->estadoParaMarcar($persona, $this->calle));
    }

    /** Turnos por día se respeta: el T4 de hoy, ya vencido, cuenta como el turno de hoy; el cierre se guarda igual. */
    public function test_doc21_ca07_con_el_turno_de_hoy_vencido_se_cierra_y_respeta_los_turnos_por_dia(): void
    {
        $marcador = app(Marcador::class);
        $enlace = EnlaceTurno::factory()->de($this->calle)->create();
        $persona = $this->persona();
        $this->entrada($persona, '2026-11-13 06:00');

        $this->travelTo(Carbon::parse('2026-11-13 14:00'));
        $this->assertSame('cerrado', $marcador->estadoParaMarcar($persona, $this->calle));

        try {
            $marcador->marcar($persona, $enlace, []);
            $this->fail('Con turnos_por_dia = 1 no deja abrir otro.');
        } catch (TurnoCerrado) {
        }

        $this->assertSame('2026-11-13 13:30', $this->salidaDe($persona)->marcada_at->format('Y-m-d H:i'), 'El cierre no se deshace con el rechazo.');
        $this->assertSame(1, Marcacion::withoutGlobalScopes()->where('tipo', TipoMarcacion::Entrada->value)->count());
    }

    /** El caso que encontró la revisión: un N1 que llega tarde el viernes no se corta como T4. */
    public function test_doc21_pa02_un_n1_tardio_del_viernes_marca_su_salida_normal(): void
    {
        $marcador = app(Marcador::class);
        $enlace = EnlaceTurno::factory()->de($this->calle)->create();
        $persona = $this->persona();
        $this->entrada($persona, '2026-11-13 05:40');

        $this->travelTo(Carbon::parse('2026-11-13 12:30'));
        $marcador->cerrarAbiertas();
        $this->assertSame('salida', $marcador->estadoParaMarcar($persona, $this->calle));
        $this->assertSame(TipoMarcacion::Salida, $marcador->marcar($persona, $enlace, [])->tipo);
        $this->assertSame(0, Marcacion::withoutGlobalScopes()->where('origen', \App\Enums\OrigenMarcacion::CierreAutomatico->value)->count());
    }

    public function test_doc21_ca08_el_tope_por_cargo_se_edita_y_solo_admite_cargos_del_componente(): void
    {
        $this->calle->update(['config' => ['horas_max_por_cargo' => []] + $this->calle->config]);
        $this->actingAs($this->usuarioCon(RolDependencia::Administracion, $this->dependencia));
        // Livewire::test no pasa por los middlewares: la dependencia se pone a mano.
        app(ContextoDependencia::class)->establecer($this->dependencia);

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->calle), ['config' => ['horas_max_por_cargo' => [
                ['cargo' => self::CONDUCTOR, 'horas' => 15],
                ['cargo' => 'Conductor microbús', 'horas' => 15],
            ]]])
            ->assertHasNoFormErrors();

        $this->assertSame([self::CONDUCTOR => 15, 'Conductor microbús' => 15], $this->calle->fresh()->regla('horas_max_por_cargo'));

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->calle->fresh()), ['config' => ['horas_max_por_cargo' => [
                ['cargo' => 'Astronauta', 'horas' => 15],
                ['cargo' => self::CONDUCTOR, 'horas' => 25],
                ['cargo' => self::CONDUCTOR, 'horas' => 10],
            ]]])
            ->assertHasFormErrors([
                'config.horas_max_por_cargo.0.cargo',
                'config.horas_max_por_cargo.1.horas',
                'config.horas_max_por_cargo.2.cargo',
            ]);

        $this->assertSame([self::CONDUCTOR => 15, 'Conductor microbús' => 15], $this->calle->fresh()->regla('horas_max_por_cargo'), 'Con errores no se guarda.');

        // Editar otra regla conserva el mapa (se carga como filas y se vuelve a guardar).
        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->calle->fresh()), ['config' => ['tolerancia_min' => 25]])
            ->assertHasNoFormErrors();
        $this->assertSame([self::CONDUCTOR => 15, 'Conductor microbús' => 15], $this->calle->fresh()->regla('horas_max_por_cargo'));
        $this->assertSame(25, $this->calle->fresh()->regla('tolerancia_min'));
    }
}
