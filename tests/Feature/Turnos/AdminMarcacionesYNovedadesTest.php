<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\NovedadTurno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Fase 3b: marcaciones, reporte, corrección manual y novedades. */
class AdminMarcacionesYNovedadesTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Colaborador $persona;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Calle',
            'config' => ['jornada_horas' => 8, 'tolerancia_min' => 15, 'descanso_min' => 0, 'descanso_parcial_min' => 0],
        ]);
        $this->persona = Colaborador::factory()->en($this->calle)->create([
            'nombre' => 'Laura Gómez', 'documento' => '43555666', 'cargo' => 'Monitor de ruta',
        ]);
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    public function test_el_listado_filtra_por_cedula_y_por_rango(): void
    {
        $otra = Colaborador::factory()->en($this->calle)->create(['nombre' => 'Otra Persona', 'documento' => '71000111']);
        Marcacion::factory()->de($this->persona)->create(['marcada_at' => now()->subHour()]);
        Marcacion::factory()->de($otra)->create(['marcada_at' => now()->subHour()]);
        Marcacion::factory()->de($this->persona)->create(['marcada_at' => now()->subMonths(3)]);

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.marcaciones.index', ['cedula' => '43.555']))
            ->assertOk()
            ->assertSee('Laura Gómez')
            ->assertDontSee('Otra Persona');

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.marcaciones.index', ['cedula' => '43555666']))
            ->assertViewHas('marcaciones', fn ($m) => $m->total() === 1);
    }

    public function test_el_reporte_suma_bien_un_turno_de_noche(): void
    {
        $noche = now()->subDays(2)->setTime(22, 0);
        Marcacion::factory()->de($this->persona)->create(['marcada_at' => $noche]);
        Marcacion::factory()->de($this->persona)->salida()->create(['marcada_at' => $noche->copy()->addHours(8)->addMinutes(30)]);

        $turnos = $this->hojaXlsx(
            $this->actingAs($this->coordinador)->get(route('admin.turnos.marcaciones.exportar'))->assertOk(),
            'Turnos',
        );

        $this->assertSame(['Cédula', 'Nombre', 'Cargo'], array_slice($turnos[0], 0, 3));
        $this->assertSame(['43555666', 'Laura Gómez', 'Monitor de ruta', 'Calle'], array_slice($turnos[1], 0, 4));
        $this->assertSame(8.5, $turnos[1][array_search('Permanencia (h)', $turnos[0], true)], '8 h 30 min en el sitio, como número.');
    }

    /** La segunda pestaña: una fila por persona, la semana sobre 42 h en rojo, y quien no marcó con 0 días. */
    public function test_el_resumen_mensual_compara_cada_semana_con_las_42_horas(): void
    {
        // Seis turnos de 7 h 30 en la semana del 7 de septiembre: 45 h.
        foreach (range(0, 5) as $d) {
            $entrada = \Illuminate\Support\Carbon::parse('2026-09-07 07:00')->addDays($d);
            Marcacion::factory()->de($this->persona)->create(['marcada_at' => $entrada]);
            Marcacion::factory()->de($this->persona)->salida()->create(['marcada_at' => $entrada->copy()->addMinutes(450)]);
        }
        Colaborador::factory()->en($this->calle)->create(['nombre' => 'Sin Marcas', 'documento' => '71999000']);

        $respuesta = $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.marcaciones.exportar', ['desde' => '2026-09-01', 'hasta' => '2026-09-30']))
            ->assertOk();
        $resumen = collect($this->hojaXlsx($respuesta, 'Resumen mensual'));
        $columnas = array_flip($resumen->first());

        $laura = $resumen->first(fn ($f) => ($f[1] ?? null) === 'Laura Gómez');
        $this->assertEquals(6, $laura[$columnas['Días trabajados']]);
        $this->assertEquals(45, $laura[$columnas['Horas trabajadas']]);
        $this->assertEquals(1, $laura[$columnas['Semanas sobre 42 h']]);
        $this->assertEquals(45, $laura[$columnas['Sem. 7 sep.–13 sep.']]);

        $sinMarcas = $resumen->first(fn ($f) => ($f[1] ?? null) === 'Sin Marcas');
        $this->assertEquals(0, $sinMarcas[$columnas['Días trabajados']]);

        // Lo que pasa de 42 va resaltado (estilo de alerta).
        $zip = new \ZipArchive();
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $this->assertGreaterThanOrEqual(2, substr_count($zip->getFromName('xl/worksheets/sheet2.xml'), 's="3"'));
        $zip->close();
    }

    /** DOC-19 CA-02: con el filtro de nodo, quien cubrió en otro nodo sale con sus días, no en 0. */
    public function test_doc19_ca02_el_resumen_por_nodo_cuenta_los_turnos_hechos_en_otro_nodo(): void
    {
        $propio = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 2']);
        $otro = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 6']);
        $this->persona->update(['nodo_id' => $propio->id]);

        foreach (['2026-09-07 07:00', '2026-09-08 07:00'] as $dia) {
            Marcacion::factory()->de($this->persona)->create(['marcada_at' => Carbon::parse($dia), 'nodo_id' => $otro->id]);
            Marcacion::factory()->de($this->persona)->salida()->create(['marcada_at' => Carbon::parse($dia)->addHours(8), 'nodo_id' => $otro->id]);
        }

        $exportar = fn (int $nodo) => $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.marcaciones.exportar', ['nodo' => $nodo, 'desde' => '2026-09-01', 'hasta' => '2026-09-30']))
            ->assertOk();
        $dias = function ($respuesta) {
            $resumen = collect($this->hojaXlsx($respuesta, 'Resumen mensual'));
            $columnas = array_flip($resumen->first());

            return $resumen->first(fn ($f) => ($f[1] ?? null) === 'Laura Gómez')[$columnas['Días trabajados']] ?? null;
        };

        $this->assertEquals(2, $dias($exportar($propio->id)), 'En su nodo habitual: con los días que trabajó, aunque fueran en otro.');
        $this->assertEquals(2, $dias($exportar($otro->id)), 'En el nodo donde cubrió: también aparece.');

        // La pestaña Turnos sí se queda con los marcados en ese nodo.
        $this->assertCount(1, $this->hojaXlsx($exportar($propio->id), 'Turnos'), 'Solo el encabezado: no marcó en su nodo.');
        $this->assertCount(3, $this->hojaXlsx($exportar($otro->id), 'Turnos'));
    }

    public function test_la_correccion_manual_agrega_una_fila_y_evalua_el_turno(): void
    {
        $entrada = Marcacion::factory()->de($this->persona)->create(['marcada_at' => now()->subHours(12)]);

        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.marcaciones.store', $this->persona), [
                'tipo' => 'salida',
                'marcada_at' => now()->subHours(1)->format('Y-m-d\TH:i'),
                'motivo' => 'Olvidó marcar la salida',
            ])
            ->assertSessionHasNoErrors();

        $salida = Marcacion::where('tipo', 'salida')->sole();
        $this->assertSame(OrigenMarcacion::Manual, $salida->origen);
        $this->assertSame($this->coordinador->id, $salida->registrada_por);
        $this->assertNull($entrada->fresh()->motivo, 'La entrada original no se toca.');
        $this->assertSame(TipoNovedad::HorasExtra, NovedadTurno::sole()->tipo, '11 horas: horas extra.');
    }

    public function test_la_correccion_no_acepta_fechas_futuras_ni_sin_motivo(): void
    {
        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.marcaciones.store', $this->persona), [
                'tipo' => 'salida', 'marcada_at' => now()->addDay()->format('Y-m-d\TH:i'), 'motivo' => '',
            ])
            ->assertSessionHasErrors(['marcada_at', 'motivo']);
    }

    public function test_aprobar_y_rechazar_queda_con_autor_y_rechazar_pide_comentario(): void
    {
        $marca = Marcacion::factory()->de($this->persona)->sinUbicacion()->create();
        $novedad = NovedadTurno::create([
            'dependencia_id' => $this->dependencia->id, 'colaborador_id' => $this->persona->id,
            'componente_id' => $this->calle->id, 'marcacion_id' => $marca->id,
            'tipo' => TipoNovedad::SinUbicacion, 'detalle' => 'Marcó entrada sin ubicación.',
        ]);

        $this->actingAs($this->coordinador)
            ->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'rechazada'])
            ->assertSessionHasErrors('comentario');

        $this->actingAs($this->coordinador)
            ->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'aprobada', 'comentario' => 'Sin señal en la zona'])
            ->assertSessionHasNoErrors();

        $novedad->refresh();
        $this->assertSame(EstadoNovedad::Aprobada, $novedad->estado);
        $this->assertSame($this->coordinador->id, $novedad->revisada_por);
        $this->assertDatabaseHas('auditorias', ['accion' => 'turno.novedad_revisada', 'auditable_id' => $novedad->id]);

        $this->actingAs($this->coordinador)
            ->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'rechazada', 'comentario' => 'x'])
            ->assertStatus(422);
    }

    public function test_la_pantalla_muestra_las_pendientes_por_defecto(): void
    {
        $marca = Marcacion::factory()->de($this->persona)->create();
        foreach ([EstadoNovedad::Pendiente, EstadoNovedad::Aprobada] as $estado) {
            NovedadTurno::create([
                'dependencia_id' => $this->dependencia->id, 'colaborador_id' => $this->persona->id,
                'componente_id' => $this->calle->id, 'marcacion_id' => $marca->id,
                'tipo' => $estado === EstadoNovedad::Pendiente ? TipoNovedad::SinUbicacion : TipoNovedad::FueraDeZona,
                'detalle' => 'Detalle '.$estado->value, 'estado' => $estado,
            ]);
        }

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.novedades.index'))
            ->assertSee('Detalle pendiente')
            ->assertDontSee('Detalle aprobada');

        // El Excel sale con el mismo filtro: todas, si se piden todas.
        $excel = $this->actingAs($this->coordinador)->get(route('admin.turnos.novedades.exportar', ['estado' => 'todas']))->assertOk();
        $zip = new \ZipArchive();
        $zip->open($excel->getFile()->getPathname());
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('Detalle pendiente', $hoja);
        $this->assertStringContainsString('Detalle aprobada', $hoja);
    }

    public function test_solo_el_editor_con_aprueba_novedades_aprueba_y_se_marca_desde_usuarios(): void
    {
        $marca = Marcacion::factory()->de($this->persona)->create();
        $novedad = NovedadTurno::create([
            'dependencia_id' => $this->dependencia->id, 'colaborador_id' => $this->persona->id,
            'componente_id' => $this->calle->id, 'marcacion_id' => $marca->id,
            'tipo' => TipoNovedad::SinUbicacion, 'detalle' => 'Sin ubicación',
        ]);
        $deisy = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        // Sin la casilla la ve, pero no la aprueba.
        $this->actingAs($deisy)->get(route('admin.turnos.novedades.index'))->assertOk()->assertSee('Sin ubicación')->assertDontSee('Aprobar');
        $this->actingAs($deisy)->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'aprobada'])->assertForbidden();

        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->actingAs($admin)->put(route('admin.usuarios.update', $deisy), [
            'name' => $deisy->name, 'documento' => $deisy->documento, 'rol' => 'edicion', 'activo' => '1', 'aprueba_novedades' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($deisy->fresh()->puedeAprobarNovedadesEn($this->dependencia));
        $this->actingAs($admin)->get(route('admin.usuarios.edit', $deisy))->assertSee('Aprueba novedades de turno');

        $this->actingAs($deisy->fresh())->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'aprobada'])->assertRedirect();
        $this->assertSame(EstadoNovedad::Aprobada, $novedad->fresh()->estado);
    }

    public function test_edicion_revisa_lectura_no_y_una_novedad_ajena_no_se_revisa(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->actingAs($editor)->get(route('admin.turnos.marcaciones.index'))->assertOk();
        $this->actingAs($editor)->get(route('admin.turnos.novedades.index'))->assertOk();
        $this->actingAs($editor)->get(route('admin.turnos.marcaciones.exportar'))->assertOk();

        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $this->actingAs($lector)->get(route('admin.turnos.marcaciones.index'))->assertForbidden();
        $this->actingAs($lector)->get(route('admin.turnos.novedades.index'))->assertForbidden();

        $ajena = Colaborador::factory()->en(Componente::factory()->create())->create();
        $marca = Marcacion::factory()->de($ajena)->create();
        $novedad = NovedadTurno::withoutGlobalScopes()->create([
            'dependencia_id' => $ajena->dependencia_id, 'colaborador_id' => $ajena->id,
            'componente_id' => $ajena->componente_id, 'marcacion_id' => $marca->id,
            'tipo' => TipoNovedad::SinUbicacion, 'detalle' => 'Ajena',
        ]);

        $respuesta = $this->actingAs($this->coordinador)
            ->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'aprobada']);
        $this->assertContains($respuesta->status(), [403, 404]);
        $this->assertTrue($novedad->fresh()->estaPendiente());
    }
}
