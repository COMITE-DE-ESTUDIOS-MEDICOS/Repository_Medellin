<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Nodo;
use App\Models\PuntoCritico;
use App\Models\Supervision;
use App\Models\TipoTurno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** DOC-11: registro de supervisión territorial (documentacion/specs/supervision-territorial). */
class SupervisionTerritorialTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private PuntoCritico $punto;

    private TipoTurno $n1;

    private string $token;

    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Calle',
            'config' => ['cargos' => ['Educador', 'Apoyo al control territorial'], 'cargos_supervision' => ['Apoyo al control territorial']],
        ]);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 3']);
        $this->punto = PuntoCritico::create(['componente_id' => $this->calle->id, 'nombre' => 'Parque Bolívar', 'activo' => true]);
        $this->n1 = TipoTurno::create(['componente_id' => $this->calle->id, 'codigo' => 'N1', 'nombre' => 'Mañana', 'es_turno' => true, 'hora_inicio' => '05:00', 'hora_fin' => '12:30', 'horas_nomina' => 7, 'activo' => true]);

        // DOC-13: todos en turno (entrada marcada, sin salida).
        $persona = function (string $cc, string $nombre, string $cargo) {
            $p = Colaborador::factory()->en($this->calle)->create([
                'documento' => $cc, 'nombre' => $nombre, 'cargo' => $cargo, 'nodo_id' => $this->nodo->id,
            ]);
            \App\Models\Marcacion::factory()->de($p)->create(['marcada_at' => now()->subHours(2)]);

            return $p;
        };
        $persona('1001', 'Felipe Apoyo', 'apoyo al  control territorial');
        $persona('2001', 'Ana Dinamizadora', 'Educador');
        $persona('2002', 'Bruno Dinamizador', 'Educador');

        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create(['tipo' => 'supervision']);
        $this->lider = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'cedula' => '1.001',
            'punto_critico_id' => $this->punto->id,
            'nodo_id' => $this->nodo->id,
            'fecha_visita' => now()->toDateString(),
            'hora_visita' => '10:15',
            'dinamizador1' => '2001',
            'dinamizador2' => '2.002',
            'tipo_turno_id' => $this->n1->id,
            'descripcion_punto' => 'Punto con 12 personas, consumo activo.',
            ...array_fill_keys(array_keys(Supervision::CRITERIOS), 5),
            'c_presentacion' => 3,
            'calificacion_global' => 4,
            'incidencia' => 'leve',
            'casos_escalados' => 'no',
            'recomendaciones' => 'Reforzar la ruta de la tarde.',
            'evidencia1' => $this->archivoJpg('uno.jpg'),
            'lat' => 6.2547, 'lng' => -75.5655, 'precision_m' => 12, 'municipio' => 'Medellín',
        ];
    }

    private function registrar(array $cambios = [])
    {
        return $this->post(route('supervision.registrar', ['token' => $this->token]), $this->datos($cambios));
    }

    public function test_ca01_registra_la_supervision_con_sus_calificaciones_y_evidencias(): void
    {
        $this->get(route('supervision.enlace', ['token' => $this->token]))->assertOk()->assertSee('Registro de supervisión');

        $this->registrar()->assertRedirect(route('supervision.enlace', ['token' => $this->token]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('supervision.registrada');

        $s = Supervision::withoutGlobalScopes()->sole();
        $this->assertSame(['Felipe Apoyo', '2001', '2002'], [$s->supervisor_nombre, $s->dinamizador1->documento, $s->dinamizador2->documento]);
        $this->assertSame([5, 3, 4], [$s->c_horario, $s->c_presentacion, $s->calificacion_global]);
        $this->assertEqualsWithDelta(4.8, $s->promedio(), 0.01);
        $this->assertSame('10:15', $s->visita_at->format('H:i'));
        $this->assertNotNull($s->evidencia1_documento_id);
        $this->assertNull($s->evidencia2_documento_id);
        $this->assertSame(6.2547, $s->lat);
        $this->assertDatabaseHas('auditorias', ['accion' => 'supervision.registrada', 'auditable_id' => $s->id]);
    }

    public function test_ca02_solo_el_cargo_que_supervisa(): void
    {
        $this->postJson(route('supervision.cedula', ['token' => $this->token]), ['cedula' => '2001'])
            ->assertExactJson(['existe' => false, 'saludo' => '', 'aviso' => 'Este enlace es solo para el Apoyo al control territorial.']);
        $this->postJson(route('supervision.cedula', ['token' => $this->token]), ['cedula' => '1001'])
            ->assertJson(['existe' => true]);

        $this->registrar(['cedula' => '2001', 'dinamizador1' => '2002', 'dinamizador2' => null])
            ->assertSessionHasErrors(['cedula' => 'Este enlace es solo para el Apoyo al control territorial.']);
        $this->assertSame(0, Supervision::withoutGlobalScopes()->count());
    }

    public function test_ca03_el_dinamizador_tiene_que_estar_registrado(): void
    {
        $this->postJson(route('supervision.dinamizador', ['token' => $this->token]), ['cedula' => '9999'])
            ->assertExactJson(['existe' => false, 'saludo' => '']);

        $this->registrar(['dinamizador2' => '9999'])
            ->assertSessionHasErrors(['dinamizador2' => 'Esta cédula no está registrada como persona de campo.']);
        $this->registrar(['dinamizador1' => '1001'])->assertSessionHasErrors('dinamizador1');
        $this->assertSame(0, Supervision::withoutGlobalScopes()->count());
    }

    public function test_ca04_las_calificaciones_van_completas_y_de_1_a_5(): void
    {
        $this->registrar(['c_epp' => null])->assertSessionHasErrors(['c_epp' => 'Califica: Utiliza los EPP.']);
        $this->registrar(['calificacion_global' => 6])->assertSessionHasErrors('calificacion_global');
        $this->assertSame(0, Supervision::withoutGlobalScopes()->count());
    }

    public function test_ca05_casos_escalados_en_si_piden_cual(): void
    {
        $this->registrar(['casos_escalados' => 'si'])
            ->assertSessionHasErrors(['casos_escalados_cual' => 'Escribe cuál caso se escaló.']);

        $this->registrar(['casos_escalados' => 'si', 'casos_escalados_cual' => 'Menor en riesgo, a Comisaría.'])->assertSessionHasNoErrors();
        $this->assertTrue(Supervision::withoutGlobalScopes()->sole()->casos_escalados);
    }

    public function test_ca06_las_evidencias_solo_con_la_camara(): void
    {
        $this->get(route('supervision.enlace', ['token' => $this->token]))
            ->assertSee('Tomar foto')->assertDontSee('Elegir archivo')->assertSee('capture="environment"', false);

        $this->registrar(['evidencia1' => null])->assertSessionHasErrors(['evidencia1' => 'Falta la evidencia #1.']);
    }

    public function test_ca07_sin_ubicacion_se_registra_igual(): void
    {
        $this->registrar(['lat' => null, 'lng' => null, 'precision_m' => null, 'municipio' => null])->assertSessionHasNoErrors();

        $s = Supervision::withoutGlobalScopes()->sole();
        $this->assertNull($s->lat);
        $this->actingAs($this->lider)->get(route('admin.turnos.supervisiones.index'))->assertSee('sin ubicación');
    }

    public function test_ca08_los_lideres_consultan_filtran_y_descargan(): void
    {
        $this->registrar();
        $s = Supervision::withoutGlobalScopes()->sole();

        $this->actingAs($this->lider)->get(route('admin.turnos.supervisiones.index', ['dinamizador' => '2.002']))
            ->assertOk()->assertSee('Parque Bolívar')->assertSee('Felipe Apoyo');
        $this->actingAs($this->lider)->get(route('admin.turnos.supervisiones.index', ['dinamizador' => '7777']))
            ->assertDontSee('Felipe Apoyo');
        $this->actingAs($this->lider)->get(route('admin.turnos.supervisiones.show', $s))
            ->assertOk()->assertSee('Ana Dinamizadora')->assertSee('Utiliza los EPP');

        $excel = $this->actingAs($this->lider)->get(route('admin.turnos.supervisiones.exportar'))->assertOk();
        $zip = new ZipArchive();
        $zip->open($excel->getFile()->getPathname());
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('CALIFICACION GLOBAL DE LA DUPLA', $hoja);
        $this->assertStringContainsString('Bruno Dinamizador · CC 2002', $hoja);
        $this->assertStringContainsString('<drawing r:id="rId1"/>', $hoja);
    }

    public function test_ca09_lectura_y_otra_dependencia_no_entran(): void
    {
        $this->registrar();
        $s = Supervision::withoutGlobalScopes()->sole();

        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $this->actingAs($lector)->get(route('admin.turnos.supervisiones.index'))->assertForbidden();
        $this->actingAs($lector)->get(route('admin.turnos.supervisiones.exportar'))->assertForbidden();

        $ajeno = $this->usuarioCon(RolDependencia::Administracion);
        // Negado por la política (403) o por el aislamiento (404): en ningún caso ve el registro.
        $respuesta = $this->actingAs($ajeno)->get(route('admin.turnos.supervisiones.show', $s));
        $this->assertContains($respuesta->status(), [403, 404]);
        $respuesta->assertDontSee('Felipe Apoyo');
    }

    public function test_ca10_el_enlace_se_crea_con_el_tipo_supervision(): void
    {
        $this->actingAs($this->lider)->post(route('admin.turnos.enlaces.store'), [
            'tipo' => 'supervision', 'componente_id' => $this->calle->id,
        ])->assertSessionHasNoErrors();

        $enlace = EnlaceTurno::latest('id')->first();
        $this->assertTrue($enlace->esSupervision());
        $this->assertStringContainsString('/s/', $enlace->url());

        // Un enlace de supervisión no abre el de turnos ni el de derivaciones, y al revés.
        $this->get(route('turno.enlace', ['token' => $this->token]))->assertNotFound();
        $this->get(route('derivacion.enlace', ['token' => $this->token]))->assertNotFound();
    }

    public function test_doc13_ca01_quien_supervisa_tiene_que_estar_en_turno(): void
    {
        $sinTurno = Colaborador::factory()->en($this->calle)->create(['documento' => '1002', 'cargo' => 'Apoyo al control territorial']);
        $aviso = 'Para registrar la supervisión tienes que estar en turno: marca tu entrada primero.';

        $this->postJson(route('supervision.cedula', ['token' => $this->token]), ['cedula' => '1002'])->assertJson(['existe' => false, 'aviso' => $aviso]);
        $this->registrar(['cedula' => '1002'])->assertSessionHasErrors(['cedula' => $aviso]);

        // Marcó salida: ya no está en turno.
        \App\Models\Marcacion::factory()->de(Colaborador::withoutGlobalScopes()->where('documento', '1001')->sole())->salida()->create(['marcada_at' => now()->subMinute()]);
        $this->registrar()->assertSessionHasErrors(['cedula' => $aviso]);
        $this->assertSame(0, Supervision::withoutGlobalScopes()->count());
    }

    public function test_doc13_ca02_el_dinamizador_tiene_que_estar_en_turno(): void
    {
        Colaborador::factory()->en($this->calle)->create(['documento' => '2003', 'cargo' => 'Educador']);
        $aviso = 'Esta persona no está en turno: no ha marcado entrada.';

        $this->postJson(route('supervision.dinamizador', ['token' => $this->token]), ['cedula' => '2003'])->assertJson(['existe' => false, 'aviso' => $aviso]);
        $this->registrar(['dinamizador2' => '2003'])->assertSessionHasErrors(['dinamizador2' => $aviso]);
        $this->assertSame(0, Supervision::withoutGlobalScopes()->count());
    }

    public function test_doc13_ca06_ca07_avisa_solo_lo_importante_en_su_pestana(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        // Nada importante: no avisa.
        $this->registrar(['incidencia' => 'leve'])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Notification::assertNothingSent();

        // Crítica, con caso escalado y global 2: avisa a Edición y Coordinación, no a Lectura.
        $this->registrar(['incidencia' => 'critica', 'casos_escalados' => 'si', 'casos_escalados_cual' => 'Menor en riesgo', 'calificacion_global' => 2, 'hora_visita' => '10:30'])
            ->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Notification::assertSentTo([$this->lider, $coordinador], \App\Notifications\SupervisionImportante::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($lector, \App\Notifications\SupervisionImportante::class);

        $aviso = \Illuminate\Support\Facades\Notification::sent($this->lider, \App\Notifications\SupervisionImportante::class)->first();
        $this->assertSame(
            'Supervisión #'.Supervision::withoutGlobalScopes()->latest('id')->value('id').' en Parque Bolívar: incidencia crítica, casos escalados, calificación global 2.',
            $aviso->toArray($this->lider)['mensaje'],
        );
        $this->assertSame('supervision', \App\Http\Controllers\NotificacionController::tipoDe(\App\Notifications\SupervisionImportante::class));
    }

    public function test_doc14_la_analitica_resume_por_criterio_dinamizador_supervisor_y_nodo(): void
    {
        // Visita 1: todo 5 salvo presentación 3 (promedio 4,78), global 4, Ana y Bruno.
        $this->registrar()->assertSessionHasNoErrors();
        // Visita 2: todo 2 (promedio 2), global 1, crítica y escalada, solo Ana.
        $this->registrar([
            ...array_fill_keys(array_keys(Supervision::CRITERIOS), 2), 'calificacion_global' => 1,
            'incidencia' => 'critica', 'casos_escalados' => 'si', 'casos_escalados_cual' => 'Riña', 'dinamizador2' => null,
            'hora_visita' => '10:40', 'lat' => null, 'lng' => null,
        ])->assertSessionHasNoErrors();

        $a = $this->actingAs($this->lider)->get(route('admin.turnos.supervisiones.index'))->assertOk()
            ->assertSee('Por criterio')->viewData('analitica');

        // CA-01
        $this->assertSame([2, 1, 1, 1, 1], [$a['total'], $a['relevantes'], $a['criticas'], $a['escalados'], $a['sin_ubicacion']]);
        $this->assertEqualsWithDelta((43 / 9 + 2) / 2, $a['promedio'], 0.01);
        $this->assertEqualsWithDelta(2.5, $a['global'], 0.01);
        // CA-02: del más bajo al más alto; presentación (3 y 2) es el más bajo.
        $this->assertSame('Presentación personal', $a['criterios']->first()['criterio']);
        $this->assertEqualsWithDelta(2.5, $a['criterios']->first()['promedio'], 0.01);
        // CA-03: Ana estuvo en las dos visitas, Bruno en una; Ana tiene el promedio más bajo.
        $ana = $a['dinamizadores']->firstWhere('documento', '2001');
        $this->assertSame(2, $ana['visitas']);
        $this->assertSame('2001', $a['dinamizadores']->first()['documento']);
        $this->assertSame(1, $a['dinamizadores']->firstWhere('documento', '2002')['visitas']);
        // CA-04
        $this->assertSame('Felipe Apoyo', $a['supervisores']->sole()->nombre);
        $this->assertSame(['Nodo 3', 2, 1], [$a['nodos']->sole()['nodo'], $a['nodos']->sole()['visitas'], $a['nodos']->sole()['relevantes']]);

        // CA-05: con el filtro de Bruno, solo su visita.
        $filtrada = $this->actingAs($this->lider)->get(route('admin.turnos.supervisiones.index', ['dinamizador' => '2002']))->viewData('analitica');
        $this->assertSame(1, $filtrada['total']);

        // CA-06: el Excel trae las hojas de la analítica.
        $excel = $this->actingAs($this->lider)->get(route('admin.turnos.supervisiones.exportar'))->assertOk();
        $zip = new ZipArchive();
        $zip->open($excel->getFile()->getPathname());
        $libro = $zip->getFromName('xl/workbook.xml');
        $porDinamizador = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();
        foreach (['Supervisiones', 'Por dinamizador', 'Por criterio', 'Por supervisor', 'Por nodo'] as $hoja) {
            $this->assertStringContainsString('name="'.$hoja.'"', $libro);
        }
        $this->assertStringContainsString('Ana Dinamizadora', $porDinamizador);
    }

    public function test_casos_limite_hora_futura_punto_otro_y_doble_envio(): void
    {
        $this->registrar(['hora_visita' => '12:00'])
            ->assertSessionHasErrors(['hora_visita' => 'La hora de la visita no puede ser posterior a la hora actual.']);

        $this->registrar(['punto_critico_id' => null, 'punto_critico_otro' => 'Bajos del puente de la 80'])->assertSessionHasNoErrors();
        $this->assertSame('Otro: Bajos del puente de la 80', Supervision::withoutGlobalScopes()->sole()->puntoNombre());

        // El mismo envío dentro del minuto no duplica.
        $this->registrar(['punto_critico_id' => null, 'punto_critico_otro' => 'Bajos del puente de la 80'])->assertSessionHasNoErrors();
        $this->assertSame(1, Supervision::withoutGlobalScopes()->count());
    }
}
