<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\NovedadTurno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

/**
 * Lo que salió de probar el módulo: el turno del día se cierra (no se marca
 * dos veces), las horas se corrigen sin perder la historia, y el reporte
 * separa jornada y horas extra con su aprobación.
 */
class CorreccionYTurnoCerradoTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private string $token;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Calle',
            'config' => ['jornada_horas' => 8, 'tolerancia_min' => 15, 'foto_obligatoria' => false, 'turnos_por_dia' => 1, 'descanso_min' => 0, 'descanso_parcial_min' => 0],
        ]);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id]);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create();
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function marcar(array $datos = [])
    {
        return $this->post(route('turno.marcar', ['token' => $this->token]), $datos + [
            'cedula' => '1017000111', 'lat' => 6.2476, 'lng' => -75.5658, 'epp' => 'si',
        ]);
    }

    private function primeraVez(): array
    {
        return ['colaborador' => ['nombre' => 'Edwar Prueba', 'nodo_id' => $this->nodo->id, 'cargo' => 'Monitor de ruta']];
    }

    /** Entrada a las 7:00 y salida a la hora que se diga, del mismo día. */
    private function turno(string $salida): array
    {
        $this->travelTo(now()->setTime(7, 0));
        $this->marcar($this->primeraVez());
        [$h, $m] = explode(':', $salida);
        $this->travelTo(now()->setTime((int) $h, (int) $m));
        $this->marcar();

        return [
            Marcacion::withoutGlobalScopes()->where('tipo', 'entrada')->sole(),
            Marcacion::withoutGlobalScopes()->where('tipo', 'salida')->sole(),
        ];
    }

    public function test_con_el_turno_del_dia_cerrado_no_se_vuelve_a_marcar(): void
    {
        $this->turno('11:00');
        $this->travel(40)->minutes();

        $this->marcar()->assertSessionHasErrors('cedula');
        $this->assertSame(2, Marcacion::withoutGlobalScopes()->count(), 'No se abrió otro turno.');

        $this->postJson(route('turno.cedula', ['token' => $this->token]), ['cedula' => '1017000111'])
            ->assertJson(['accion' => 'cerrado']);
    }

    public function test_al_dia_siguiente_vuelve_a_marcar(): void
    {
        $this->turno('15:00');
        $this->travelTo(now()->addDay()->setTime(7, 0));

        $this->marcar()->assertSessionHas('turno.marcado.tipo', 'Entrada');
    }

    public function test_si_el_componente_permite_dos_turnos_el_segundo_entra(): void
    {
        $this->calle->update(['config' => array_merge($this->calle->config, ['turnos_por_dia' => 2])]);
        $this->turno('11:00');
        $this->travel(1)->hours();

        $this->marcar()->assertSessionHas('turno.marcado.tipo', 'Entrada');
    }

    public function test_corregir_la_salida_anula_la_original_y_vuelve_a_juzgar_el_turno(): void
    {
        [, $salida] = $this->turno('11:00');
        $corto = NovedadTurno::withoutGlobalScopes()->sole();
        $this->assertSame(TipoNovedad::TurnoCorto, $corto->tipo);

        // Salió a las 15:00 de verdad: marcó mal. Se corrige al final del día.
        $this->travelTo(now()->setTime(18, 0));
        $this->actingAs($this->editor)
            ->patch(route('admin.turnos.marcaciones.corregir', $salida), [
                'marcada_at' => now()->setTime(15, 0)->format('Y-m-d\TH:i'),
                'motivo' => 'Marcó al salir de la primera visita',
            ])
            ->assertSessionHasNoErrors();

        $salida->refresh();
        $this->assertTrue($salida->estaAnulada());
        $this->assertSame($this->editor->id, $salida->anulada_por);
        $this->assertSame('11:00', $salida->marcada_at->format('H:i'), 'La original no cambia: queda anulada.');

        $nueva = $salida->reemplazo;
        $this->assertSame('15:00', $nueva->marcada_at->format('H:i'));
        $this->assertSame(OrigenMarcacion::Manual, $nueva->origen);

        $this->assertSame(EstadoNovedad::Corregida, $corto->fresh()->estado);
        $this->assertSame(1, NovedadTurno::withoutGlobalScopes()->count(), 'Con 8 horas exactas no queda otra novedad.');
    }

    public function test_corregir_la_entrada_tambien_vuelve_a_juzgar_el_turno(): void
    {
        [$entrada] = $this->turno('15:00');
        $this->assertSame(0, NovedadTurno::withoutGlobalScopes()->count());

        // Entró a las 5:00, no a las 7:00: diez horas.
        $this->actingAs($this->editor)->patch(route('admin.turnos.marcaciones.corregir', $entrada), [
            'marcada_at' => now()->setTime(5, 0)->format('Y-m-d\TH:i'),
            'motivo' => 'Llegó antes, olvidó marcar',
        ]);

        $extra = NovedadTurno::withoutGlobalScopes()->sole();
        $this->assertSame(TipoNovedad::HorasExtra, $extra->tipo);
        $this->assertSame(600, $extra->minutos);
    }

    public function test_una_salida_no_puede_quedar_antes_de_su_entrada(): void
    {
        [, $salida] = $this->turno('15:00');

        $this->actingAs($this->editor)
            ->patch(route('admin.turnos.marcaciones.corregir', $salida), [
                'marcada_at' => now()->setTime(6, 0)->format('Y-m-d\TH:i'), 'motivo' => 'Intento al revés',
            ])
            ->assertSessionHasErrors('marcada_at');

        $this->assertFalse($salida->fresh()->estaAnulada());
    }

    public function test_una_marca_anulada_no_se_corrige_dos_veces_ni_se_edita(): void
    {
        [, $salida] = $this->turno('15:00');
        $this->actingAs($this->editor)->patch(route('admin.turnos.marcaciones.corregir', $salida), [
            'marcada_at' => now()->setTime(14, 0)->format('Y-m-d\TH:i'), 'motivo' => 'Primera corrección',
        ]);

        $this->actingAs($this->editor)
            ->patch(route('admin.turnos.marcaciones.corregir', $salida->fresh()), [
                'marcada_at' => now()->setTime(13, 0)->format('Y-m-d\TH:i'), 'motivo' => 'Segunda sobre la misma',
            ])
            ->assertForbidden();

        $this->expectException(LogicException::class);
        $salida->fresh()->update(['marcada_at' => now()->subHours(3)]);
    }

    public function test_lectura_no_corrige(): void
    {
        [, $salida] = $this->turno('15:00');
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)->patch(route('admin.turnos.marcaciones.corregir', $salida), [
            'marcada_at' => now()->format('Y-m-d\TH:i'), 'motivo' => 'No debería poder',
        ])->assertForbidden();
    }

    public function test_el_reporte_separa_jornada_y_horas_extra_con_su_aprobacion(): void
    {
        $this->turno('17:00');
        $extra = NovedadTurno::withoutGlobalScopes()->sole();
        $this->editor->dependencias()->updateExistingPivot($this->dependencia->id, ['aprueba_novedades' => true]);
        $this->actingAs($this->editor)->patch(route('admin.turnos.novedades.revisar', $extra), ['estado' => 'aprobada'])->assertRedirect();

        $turnos = $this->hojaXlsx($this->actingAs($this->editor)->get(route('admin.turnos.marcaciones.exportar')), 'Turnos');

        $this->assertSame(['Permanencia (h)', 'Descanso (h)', 'Horas trabajadas', 'Jornada (h)', 'Horas ordinarias', 'Horas extra', 'Extras aprobadas'], array_slice($turnos[0], 8, 7));
        $this->assertEquals([10, 0, 10, 8, 8, 2, 'SI'], array_slice($turnos[1], 8, 7));
    }

    public function test_un_turno_dentro_de_la_tolerancia_no_da_horas_extra(): void
    {
        $this->turno('15:10');

        $turnos = $this->hojaXlsx($this->actingAs($this->editor)->get(route('admin.turnos.marcaciones.exportar')), 'Turnos');

        $this->assertEquals([8.17, 0, 8.17, 8, 8.17, 0, null], array_slice($turnos[1], 8, 7));
    }

    /**
     * El caso de Jean Paul (N7, 21:00–04:30): salió a las 04:41, reintentó a
     * las 04:43 y eso abría una entrada; desde ahí todo quedaba al revés.
     */
    public function test_doc17_ca04_un_reintento_despues_de_la_salida_no_abre_una_entrada(): void
    {
        $this->calle->update(['config' => array_merge($this->calle->config, ['jornada_horas' => 7])]);
        $this->travelTo(now()->setTime(21, 0));
        $this->marcar($this->primeraVez());
        $this->travelTo(now()->addDay()->setTime(4, 41));
        $this->marcar()->assertSessionHas('turno.marcado.tipo', 'Salida');

        $this->travel(125)->seconds();
        $this->marcar()->assertSessionHas('turno.marcado.repetida', true);
        $this->postJson(route('turno.cedula', ['token' => $this->token]), ['cedula' => '1017000111'])
            ->assertJsonPath('accion', 'reciente');

        $this->travelTo(now()->setTime(21, 0));
        $this->marcar()->assertSessionHas('turno.marcado.tipo', 'Entrada');
        $this->assertSame(3, Marcacion::withoutGlobalScopes()->count());
    }

    private function coordinador(): User
    {
        return $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    public function test_doc18_ca01_coordinacion_cambia_el_tipo_y_la_original_queda_anulada(): void
    {
        [, $salida] = $this->turno('15:00');
        $this->travel(1)->hours();

        $this->actingAs($this->coordinador())
            ->patch(route('admin.turnos.marcaciones.tipo', $salida), ['motivo' => 'Era su entrada del turno de noche'])
            ->assertSessionHasNoErrors();

        $salida->refresh();
        $this->assertTrue($salida->estaAnulada());
        $this->assertSame(\App\Enums\TipoMarcacion::Entrada, $salida->reemplazo->tipo);
        $this->assertSame('15:00', $salida->reemplazo->marcada_at->format('H:i'), 'Misma hora, otro tipo.');
    }

    public function test_doc18_ca02_anular_quita_la_marca_sin_borrarla_y_cierra_sus_novedades(): void
    {
        [, $salida] = $this->turno('11:00');
        $corto = NovedadTurno::withoutGlobalScopes()->sole();
        $this->travel(1)->hours();

        $this->actingAs($this->coordinador())
            ->patch(route('admin.turnos.marcaciones.anular', $salida), ['motivo' => 'Reintento: quedó de más'])
            ->assertSessionHasNoErrors();

        $salida->refresh();
        $this->assertTrue($salida->estaAnulada());
        $this->assertNull($salida->reemplazada_por_id);
        $this->assertSame(2, Marcacion::withoutGlobalScopes()->count(), 'No se borra nada.');
        $this->assertSame(1, Marcacion::withoutGlobalScopes()->vigentes()->count());
        $this->assertSame(EstadoNovedad::Corregida, $corto->fresh()->estado);
        $this->assertDatabaseHas('auditorias', ['accion' => 'turno.correccion']);
    }

    public function test_doc18_ca03_edicion_no_anula_ni_cambia_el_tipo(): void
    {
        [, $salida] = $this->turno('15:00');

        $this->actingAs($this->editor)
            ->patch(route('admin.turnos.marcaciones.anular', $salida), ['motivo' => 'No debería poder'])
            ->assertForbidden();
        $this->actingAs($this->editor)
            ->patch(route('admin.turnos.marcaciones.tipo', $salida), ['motivo' => 'No debería poder'])
            ->assertForbidden();
        $this->assertFalse($salida->fresh()->estaAnulada());
    }

    public function test_doc18_ca04_una_marca_anulada_no_se_vuelve_a_anular(): void
    {
        [, $salida] = $this->turno('15:00');
        $coordinador = $this->coordinador();
        $this->actingAs($coordinador)->patch(route('admin.turnos.marcaciones.anular', $salida), ['motivo' => 'Quedó de más']);

        $this->actingAs($coordinador)
            ->patch(route('admin.turnos.marcaciones.anular', $salida), ['motivo' => 'Otra vez'])
            ->assertForbidden();
    }
}
