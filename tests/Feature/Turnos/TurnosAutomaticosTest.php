<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Enums\TipoNovedad;
use App\Models\Ausencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\PuntoCritico;
use App\Models\TipoTurno;
use App\Notifications\NovedadTurnoDetectada;
use App\Services\Turnos\Programacion;
use App\Services\Turnos\RevisorEntradas;
use Database\Seeders\CalleSeeder;
use Database\Seeders\CatalogoTurnosCalleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Turno automático por la hora de entrada, punto crítico (habitual o el del
 * QR) y «no registró entrada» para quien se esperaba según su turno habitual.
 */
class TurnosAutomaticosTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $inclusion;

    private Componente $calle;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Notification::fake();

        $this->inclusion = Dependencia::factory()->create(['slug' => 'inclusion-social']);
        (new CalleSeeder)->run();
        (new CatalogoTurnosCalleSeeder)->run();
        $this->calle = Componente::withoutGlobalScopes()->where('slug', 'calle')->sole();
        $this->calle->update(['config' => ['foto_obligatoria' => false] + ($this->calle->config ?? [])]);

        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create();
    }

    private function tipo(string $codigo): TipoTurno
    {
        return TipoTurno::where('codigo', $codigo)->sole();
    }

    private function persona(string $habitual = 'N1', array $extra = []): Colaborador
    {
        return Colaborador::factory()->en($this->calle)->create($extra + [
            'tipo_turno_id' => $this->tipo($habitual)->id, 'dias_descanso' => [7],
        ]);
    }

    private function marcar(Colaborador $persona, string $cuando, ?string $token = null): void
    {
        $this->travelTo(Carbon::parse($cuando));
        $this->post(route('turno.marcar', ['token' => $token ?? $this->token]), [
            'cedula' => $persona->documento, 'lat' => 6.25, 'lng' => -75.56, 'epp' => 'si',
        ])->assertSessionHasNoErrors();
    }

    public function test_reconoce_el_turno_por_la_hora_de_entrada(): void
    {
        $p = app(Programacion::class);
        $reconocer = fn (string $cuando, ?string $habitual = null) => $p->reconocer($this->calle, Carbon::parse($cuando), $habitual ? $this->tipo($habitual) : null);

        // Martes 20 de octubre de 2026.
        $this->assertSame(['N1', 5], [$reconocer('2026-10-20 05:05')['tipo']->codigo, $reconocer('2026-10-20 05:05')['minutos_tarde']]);
        $this->assertSame('N1.', $reconocer('2026-10-20 12:20')['tipo']->codigo);
        $this->assertSame(0, $reconocer('2026-10-20 12:20')['minutos_tarde'], 'Antes de la hora no es tardanza.');
        $this->assertSame('N7', $reconocer('2026-10-20 20:50')['tipo']->codigo);
        $this->assertSame('T4', $reconocer('2026-10-23 06:05')['tipo']->codigo, 'El viernes existe T4.');
        $this->assertSame('C13', $reconocer('2026-10-25 06:10')['tipo']->codigo, 'Domingo.');
        $this->assertSame('C13', $reconocer('2026-11-02 06:00')['tipo']->codigo, 'Lunes festivo: C13, no N1.');

        // A las 8:40, T3 (10:00) empieza más cerca que N1 (5:00, fuera de la ventana).
        $this->assertSame('T3', $reconocer('2026-10-20 08:40', 'T3')['tipo']->codigo);
        $this->assertNull($reconocer('2026-10-20 02:00'), 'A las 2 a. m. no empieza ningún turno.');
    }

    /**
     * DOC-19 CA-01: el reporte y la ficha dicen qué turno es cada entrada por
     * su hora, también en las manuales (que no lo guardan); sin uno que
     * encaje, «Sin reconocer».
     */
    public function test_doc19_ca01_el_reporte_y_la_ficha_traen_el_turno_reconocido_por_la_hora(): void
    {
        $persona = $this->persona('N1');
        $this->marcar($persona, '2026-10-20 05:05');
        $this->marcar($persona, '2026-10-20 12:40');
        Marcacion::factory()->de($persona)->create(['marcada_at' => Carbon::parse('2026-10-21 20:50'), 'origen' => \App\Enums\OrigenMarcacion::Manual]);
        Marcacion::factory()->de($persona)->salida()->create(['marcada_at' => Carbon::parse('2026-10-22 04:30')]);
        Marcacion::factory()->de($persona)->create(['marcada_at' => Carbon::parse('2026-10-23 02:00')]);
        $this->travelTo(Carbon::parse('2026-10-24 10:00'));
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->inclusion);

        $turnos = $this->hojaXlsx(
            $this->actingAs($coordinador)->get(route('admin.turnos.marcaciones.exportar', ['desde' => '2026-10-20', 'hasta' => '2026-10-23']))->assertOk(),
            'Turnos',
        );

        $this->assertSame(['Nodo', 'Turno', 'Entrada'], array_slice($turnos[0], 4, 3));
        $this->assertSame('N1', $turnos[1][5]);
        $this->assertSame('N7', $turnos[2][5], 'La manual no guardó turno: se reconoce por la hora.');
        $this->assertSame('Sin reconocer', $turnos[3][5]);

        $this->actingAs($coordinador)
            ->get(route('admin.turnos.colaboradores.show', $persona).'?q=2026-10-20')
            ->assertOk()
            ->assertSeeTextInOrder(['21/10', 'N7', '8:50 p. m.']);
    }

    public function test_al_marcar_queda_el_turno_el_punto_y_la_tardanza(): void
    {
        $habitual = PuntoCritico::where('nombre', 'Terminal del Norte')->sole();
        $persona = $this->persona('N1', ['punto_critico_id' => $habitual->id]);

        $this->marcar($persona, '2026-10-20 05:40');

        $entrada = Marcacion::withoutGlobalScopes()->sole();
        $this->assertSame(['N1', 40], [$entrada->tipoTurno->codigo, $entrada->minutos_tarde]);
        $this->assertTrue($entrada->puntoCritico->is($habitual));
        $this->assertSame(40, NovedadTurno::withoutGlobalScopes()->where('tipo', TipoNovedad::Tardanza->value)->sole()->minutos);
    }

    public function test_el_qr_de_un_punto_manda_sobre_el_habitual(): void
    {
        $otro = PuntoCritico::where('nombre', 'San Benito')->sole();
        $persona = $this->persona('T3', ['punto_critico_id' => PuntoCritico::where('nombre', 'Terminal del Norte')->value('id')]);
        $tokenPunto = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($tokenPunto)->create(['punto_critico_id' => $otro->id]);

        $this->marcar($persona, '2026-10-20 10:02', $tokenPunto);

        $entrada = Marcacion::withoutGlobalScopes()->sole();
        $this->assertTrue($entrada->puntoCritico->is($otro));
        $this->assertSame(0, NovedadTurno::withoutGlobalScopes()->where('tipo', TipoNovedad::Tardanza->value)->count(), '2 min: dentro de la tolerancia.');
    }

    public function test_quien_no_marco_queda_como_novedad_y_avisa_a_editores_y_coordinacion(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->inclusion);
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->inclusion);
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->inclusion);

        $falta = $this->persona('N1');
        $vino = $this->persona('N1');
        $vacaciones = $this->persona('N1');
        $descansa = $this->persona('N1', ['dias_descanso' => [2]]); // martes
        Ausencia::create([
            'dependencia_id' => $this->inclusion->id, 'colaborador_id' => $vacaciones->id,
            'tipo_turno_id' => $this->tipo('VAC')->id, 'desde' => '2026-10-19', 'hasta' => '2026-10-23',
        ]);

        $this->marcar($vino, '2026-10-20 05:10');

        // A las 5:20 aún no: el margen es de 30 minutos.
        $this->travelTo(Carbon::parse('2026-10-20 05:20'));
        $this->assertSame(0, app(RevisorEntradas::class)->revisar());

        $this->travelTo(Carbon::parse('2026-10-20 05:45'));
        $this->assertSame(1, app(RevisorEntradas::class)->revisar());
        $this->assertSame(0, app(RevisorEntradas::class)->revisar(), 'Una sola vez por persona y día.');

        $novedad = NovedadTurno::withoutGlobalScopes()->where('tipo', TipoNovedad::SinEntrada->value)->sole();
        $this->assertTrue($novedad->colaborador->is($falta));
        $this->assertSame('2026-10-20', $novedad->fecha->toDateString());
        $this->assertNull($novedad->marcacion_id);
        $this->assertStringContainsString('N1 a las 5:00 a. m.', $novedad->detalle);

        Notification::assertSentTo($editor, NovedadTurnoDetectada::class);
        Notification::assertSentTo($coordinador, NovedadTurnoDetectada::class);
        Notification::assertNotSentTo($lector, NovedadTurnoDetectada::class);
        $this->assertSame(0, NovedadTurno::withoutGlobalScopes()->whereIn('colaborador_id', [$vacaciones->id, $descansa->id])->count());
    }

    /** El aviso sale a tiempo (para llamarla); si luego marca, se cierra solo y queda la tardanza. */
    public function test_si_llega_tarde_despues_del_aviso_el_aviso_se_cierra_solo(): void
    {
        $persona = $this->persona('N1');

        $this->travelTo(Carbon::parse('2026-10-20 05:45'));
        app(RevisorEntradas::class)->revisar();
        $aviso = NovedadTurno::withoutGlobalScopes()->where('tipo', TipoNovedad::SinEntrada->value)->sole();

        $this->marcar($persona, '2026-10-20 06:10');

        $this->assertSame('corregida', $aviso->fresh()->estado->value);
        $this->assertStringContainsString('6:10 a. m.', $aviso->fresh()->comentario);
        $this->assertSame(70, NovedadTurno::withoutGlobalScopes()->where('tipo', TipoNovedad::Tardanza->value)->sole()->minutos);
    }

    public function test_el_lider_justifica_la_falta_con_un_codigo_de_ausencia(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->inclusion);
        $persona = $this->persona('N1');
        $this->travelTo(Carbon::parse('2026-10-20 06:00'));
        app(RevisorEntradas::class)->revisar();
        $novedad = NovedadTurno::withoutGlobalScopes()->sole();

        // Sin «Aprueba novedades», un editor la ve pero no la justifica.
        $this->actingAs($editor)->get(route('admin.turnos.novedades.index'))->assertOk()->assertDontSee('Justificar');
        $this->actingAs($editor)->post(route('admin.turnos.novedades.justificar', $novedad), ['tipo_turno_id' => $this->tipo('I')->id])->assertForbidden();
        $editor->dependencias()->updateExistingPivot($this->inclusion->id, ['aprueba_novedades' => true]);
        $editor->unsetRelation('dependencias');

        $this->actingAs($editor)->get(route('admin.turnos.novedades.index'))->assertOk()->assertSee('No registró entrada')->assertSee('Justificar');

        $this->actingAs($editor)->post(route('admin.turnos.novedades.justificar', $novedad), [
            'tipo_turno_id' => $this->tipo('I')->id, 'motivo' => 'Incapacidad EPS',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('aprobada', $novedad->fresh()->estado->value);
        $ausencia = Ausencia::withoutGlobalScopes()->sole();
        $this->assertSame(['I', '2026-10-20', '2026-10-20'], [$ausencia->tipo->codigo, $ausencia->desde->toDateString(), $ausencia->hasta->toDateString()]);

        // Un código de trabajo (N1) no sirve como ausencia.
        $this->travelTo(Carbon::parse('2026-10-21 06:00'));
        app(RevisorEntradas::class)->revisar();
        $otra = NovedadTurno::withoutGlobalScopes()->whereDate('fecha', '2026-10-21')->sole();
        $this->actingAs($editor)->post(route('admin.turnos.novedades.justificar', $otra), ['tipo_turno_id' => $this->tipo('N1')->id])
            ->assertSessionHasErrors('tipo_turno_id');
    }

    public function test_desde_la_ficha_se_registra_el_turno_habitual_y_las_ausencias(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->inclusion);
        $persona = Colaborador::factory()->en($this->calle, $this->calle->nodos()->first())->create();

        $this->actingAs($editor)->put(route('admin.turnos.colaboradores.update', $persona), [
            'documento' => $persona->documento, 'nombre' => $persona->nombre, 'cargo' => 'Monitor de ruta',
            'componente_id' => $this->calle->id, 'nodo_id' => $persona->nodo_id,
            'tipo_turno_id' => $this->tipo('N7')->id, 'dias_descanso' => [3],
            'punto_critico_id' => PuntoCritico::where('nombre', 'San Benito')->value('id'),
        ])->assertSessionHasNoErrors();

        $persona->refresh();
        $this->assertSame(['N7', [3]], [$persona->tipoTurno->codigo, $persona->dias_descanso]);

        $this->actingAs($editor)->post(route('admin.turnos.ausencias.store', $persona), [
            'tipo_turno_id' => $this->tipo('VAC')->id, 'desde' => '2026-12-14', 'hasta' => '2026-12-28',
        ])->assertSessionHasNoErrors();

        $this->actingAs($editor)->get(route('admin.turnos.colaboradores.show', $persona))
            ->assertSee('Turno y ausencias')->assertSee('14/12 al 28/12/2026');

        // Ni un turno de otro componente ni un código de ausencia como habitual.
        $this->actingAs($editor)->put(route('admin.turnos.colaboradores.update', $persona), [
            'documento' => $persona->documento, 'nombre' => $persona->nombre, 'cargo' => 'Monitor de ruta',
            'componente_id' => $this->calle->id, 'nodo_id' => $persona->nodo_id, 'tipo_turno_id' => $this->tipo('VAC')->id,
        ])->assertSessionHasErrors('tipo_turno_id');
    }
}
