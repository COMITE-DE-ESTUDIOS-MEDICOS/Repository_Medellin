<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\EnlaceTurno;
use App\Models\Nodo;
use App\Models\User;
use App\Notifications\DerivacionPendiente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** DERIVACIONES Y RESPUESTA TERRITORIAL: formulario por cédula, CRUD y reporte. */
class DerivacionesTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private string $token;

    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        // El requerimiento de las pruebas es a las 10:00: «ahora» son las 11:00.
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 2']);
        $jose = Colaborador::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'documento' => '1017000111',
            'nombre' => 'José Amaya Pérez', 'cargo' => 'Monitor de ruta', 'componente_id' => $this->calle->id,
        ]);
        // DOC-13: para reportar hay que estar en turno.
        \App\Models\Marcacion::factory()->de($jose)->create(['marcada_at' => now()->subHours(3)]);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create(['tipo' => 'derivacion']);
        $this->lider = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function registrar(array $datos = [])
    {
        return $this->post(route('derivacion.registrar', ['token' => $this->token]), $datos + [
            'cedula' => '1.017.000.111',
            'nodo_id' => $this->nodo->id,
            'fecha_requerimiento' => now()->toDateString(),
            'hora_requerimiento' => '10:00',
            'entidad' => 'Policía Nacional',
            'descripcion' => 'Habitante de calle requiere traslado.',
            'ubicacion' => 'Cra. 65 #97-121, Castilla',
            'soporte' => $this->archivoPdf('oficio.pdf'),
            'evidencia1' => $this->archivoJpg('uno.jpg'),
            'evidencia2' => $this->archivoJpg('dos.jpg'),
            'evidencia3' => $this->archivoJpg('tres.jpg'),
        ]);
    }

    public function test_una_persona_registrada_reporta_y_queda_con_sus_datos_y_archivos(): void
    {
        $this->get(route('derivacion.enlace', ['token' => $this->token]))->assertOk()->assertSee('Derivaciones y respuesta territorial');

        $this->registrar()->assertRedirect(route('derivacion.enlace', ['token' => $this->token]))->assertSessionHasNoErrors();

        $d = Derivacion::sole();
        $this->assertSame(['1017000111', 'José Amaya Pérez', 'Monitor de ruta', 'pendiente', null], [$d->cedula, $d->nombre, $d->cargo, $d->estado, $d->tiempo_respuesta_min]);
        $this->assertSame(60, $d->minutos(), 'Corre desde las 10:00.');
        $this->assertNotNull($d->soporte_documento_id);
        $this->assertNotNull($d->evidencia2_documento_id);
        $this->assertNotNull($d->evidencia3_documento_id);

        $this->registrar(['evidencia3' => null])->assertSessionHasErrors('evidencia3');
        $this->assertSame('Derivaciones · Calle', $d->evidencia1->carpeta->nombre);
        $this->assertDatabaseHas('auditorias', ['accion' => 'derivacion.registrada', 'auditable_id' => $d->id]);
    }

    public function test_doc13_ca03_quien_no_esta_en_turno_no_reporta(): void
    {
        $sinTurno = Colaborador::factory()->en($this->calle)->create(['documento' => '1017000222']);

        $this->postJson(route('derivacion.cedula', ['token' => $this->token]), ['cedula' => '1017000222'])
            ->assertJson(['existe' => false, 'aviso' => 'Para reportar una derivación tienes que estar en turno: marca tu entrada primero.']);
        $this->registrar(['cedula' => '1017000222'])
            ->assertSessionHasErrors(['cedula' => 'Para reportar una derivación tienes que estar en turno: marca tu entrada primero.']);
        $this->assertSame(0, Derivacion::count());

        // Con entrada marcada, sí (DOC-13 CA-05).
        \App\Models\Marcacion::factory()->de($sinTurno)->create(['marcada_at' => now()->subHour()]);
        $this->registrar(['cedula' => '1017000222'])->assertSessionHasNoErrors();
    }

    public function test_una_cedula_no_registrada_no_entra(): void
    {
        $this->registrar(['cedula' => '99999999'])->assertSessionHasErrors('cedula');
        $this->assertSame(0, Derivacion::count());
    }

    public function test_el_requerimiento_no_puede_ser_de_una_hora_que_no_ha_llegado(): void
    {
        $this->registrar(['hora_requerimiento' => '12:00'])->assertSessionHasErrors('hora_requerimiento');
        $this->assertSame(0, Derivacion::count());
    }

    public function test_el_lider_responde_y_se_detiene_el_tiempo(): void
    {
        $this->registrar();
        $d = Derivacion::sole();

        // A las 12:30: 2 h 30 min, dentro de la meta; no pide justificación.
        Carbon::setTestNow(Carbon::today()->setTime(12, 30));
        $this->actingAs($this->lider)->patch(route('admin.turnos.derivaciones.responder', $d), ['respuesta' => 'Se trasladó al hogar de paso.'])
            ->assertSessionHasNoErrors();

        $d->refresh();
        $this->assertSame(['solucionada', 150, '12:30', $this->lider->id], [$d->estado, $d->tiempo_respuesta_min, substr($d->hora_respuesta, 0, 5), $d->respondida_por]);

        // El tiempo ya no corre, y no se responde dos veces.
        Carbon::setTestNow(Carbon::today()->setTime(18, 0));
        $this->assertSame(150, $d->fresh()->minutos());
        $this->actingAs($this->lider)->patch(route('admin.turnos.derivaciones.responder', $d), ['respuesta' => 'Otra'])->assertStatus(422);
        $this->assertDatabaseHas('auditorias', ['accion' => 'derivacion.respondida', 'auditable_id' => $d->id]);
    }

    public function test_pasada_la_meta_la_respuesta_exige_justificacion(): void
    {
        $this->registrar();
        $d = Derivacion::sole();
        Carbon::setTestNow(Carbon::today()->setTime(13, 30));

        $this->actingAs($this->lider)->patch(route('admin.turnos.derivaciones.responder', $d), ['respuesta' => 'Atendido.'])
            ->assertSessionHasErrors('justificacion');
        $this->assertTrue($d->fresh()->estaPendiente());

        $this->actingAs($this->lider)->patch(route('admin.turnos.derivaciones.responder', $d), [
            'respuesta' => 'Atendido.', 'justificacion' => 'No había móvil disponible.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(210, $d->fresh()->tiempo_respuesta_min);
    }

    public function test_avisa_a_los_lideres_al_llegar_y_cada_30_minutos_hasta_la_respuesta(): void
    {
        Notification::fake();
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);

        $this->registrar();
        $d = Derivacion::sole();
        Notification::assertSentToTimes($this->lider, DerivacionPendiente::class, 1);
        Notification::assertNotSentTo($coordinador, DerivacionPendiente::class);
        $this->assertStringContainsString('Nueva derivación', $this->ultimoMensaje());

        // A los 29 minutos todavía no; a los 30, sí.
        Carbon::setTestNow(now()->addMinutes(29));
        $this->artisan('derivaciones:recordar');
        Notification::assertSentToTimes($this->lider, DerivacionPendiente::class, 1);
        Carbon::setTestNow(now()->addMinute());
        $this->artisan('derivaciones:recordar');
        Notification::assertSentToTimes($this->lider, DerivacionPendiente::class, 2);

        // Pasadas las 3 horas desde las 10:00, el aviso dice que está vencida.
        Carbon::setTestNow(Carbon::today()->setTime(13, 5));
        $this->artisan('derivaciones:recordar');
        $this->assertStringContainsString('VENCIDA', $this->ultimoMensaje());

        // Respondida, no se avisa más.
        $this->actingAs($this->lider)->patch(route('admin.turnos.derivaciones.responder', $d), ['respuesta' => 'Listo.', 'justificacion' => 'Tarde.']);
        Carbon::setTestNow(now()->addHour());
        $this->artisan('derivaciones:recordar');
        Notification::assertSentToTimes($this->lider, DerivacionPendiente::class, 3);
    }

    public function test_cada_aviso_reemplaza_al_anterior_y_responder_los_retira(): void
    {
        $this->registrar();
        $d = Derivacion::sole();
        $this->assertSame(1, $this->lider->notifications()->count());

        Carbon::setTestNow(now()->addMinutes(30));
        $this->artisan('derivaciones:recordar');
        $this->assertSame(1, $this->lider->notifications()->count(), 'El recordatorio reemplaza al aviso anterior del mismo caso.');
        $this->assertStringContainsString('sin respuesta', $this->lider->notifications()->first()->data['mensaje']);

        $this->actingAs($this->lider)->patch(route('admin.turnos.derivaciones.responder', $d), ['respuesta' => 'Listo.']);
        $this->assertSame(0, $this->lider->notifications()->count());
    }

    public function test_la_trazabilidad_dice_quien_respondio_y_que_nodo_tiene_casos_sin_respuesta(): void
    {
        $this->registrar();
        $this->registrar(['entidad' => 'ICBF']);
        [$primera] = Derivacion::orderBy('id')->get();

        Carbon::setTestNow(Carbon::today()->setTime(12, 0));
        $this->actingAs($this->lider)->patch(route('admin.turnos.derivaciones.responder', $primera), ['respuesta' => 'Atendida.']);

        // La otra sigue sin respuesta y a las 14:00 ya venció.
        Carbon::setTestNow(Carbon::today()->setTime(14, 0));
        $traza = $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.index'))->assertOk()->viewData('traza');

        $this->assertSame([1, 1, 1, 1], [$traza['pendientes'], $traza['vencidas'], $traza['solucionadas'], $traza['a_tiempo']]);
        $this->assertSame(['Nodo 2'], $traza['sin_respuesta']->keys()->all());
        $this->assertSame($this->lider->name, $traza['respondedores']->sole()->nombre);
        $this->assertSame('2 h 0 min', $traza['promedio']);
    }

    private function ultimoMensaje(): string
    {
        return Notification::sent($this->lider, DerivacionPendiente::class)->last()->toArray($this->lider)['mensaje'];
    }

    public function test_un_enlace_de_turno_no_abre_el_formulario_ni_al_reves(): void
    {
        $turno = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($turno)->create();

        $this->get(route('derivacion.enlace', ['token' => $turno]))->assertNotFound();
        $this->get(route('turno.enlace', ['token' => $this->token]))->assertNotFound();
    }

    public function test_edicion_consulta_corrige_y_coordinacion_elimina(): void
    {
        $this->registrar();
        $d = Derivacion::sole();

        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.index'))->assertOk()->assertSee('José Amaya Pérez');
        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.show', $d))->assertOk()->assertSee('Policía Nacional');

        $this->actingAs($this->lider)->put(route('admin.turnos.derivaciones.update', $d), [
            'nodo_id' => $this->nodo->id, 'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
            'entidad' => 'ICBF', 'descripcion' => 'Corregida', 'ubicacion' => 'Castilla',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['ICBF', 'pendiente'], [$d->fresh()->entidad, $d->fresh()->estado]);

        // Ya solucionada, también se corrige la hora de respuesta.
        $this->actingAs($this->lider)->patch(route('admin.turnos.derivaciones.responder', $d), ['respuesta' => 'Atendido.']);
        $this->actingAs($this->lider)->put(route('admin.turnos.derivaciones.update', $d), [
            'nodo_id' => $this->nodo->id, 'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
            'entidad' => 'ICBF', 'descripcion' => 'Corregida', 'ubicacion' => 'Castilla', 'hora_respuesta' => '10:20', 'respuesta' => 'Atendido.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(20, $d->fresh()->tiempo_respuesta_min);

        $this->actingAs($this->lider)->delete(route('admin.turnos.derivaciones.destroy', $d))->assertForbidden();

        // Coordinación corrige la hora de respuesta igual que Edición.
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $this->actingAs($coordinador)->get(route('admin.turnos.derivaciones.edit', $d))->assertOk()->assertSee('Hora de respuesta');
        $this->actingAs($coordinador)->put(route('admin.turnos.derivaciones.update', $d), [
            'nodo_id' => $this->nodo->id, 'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
            'entidad' => 'ICBF', 'descripcion' => 'Corregida', 'ubicacion' => 'Castilla', 'hora_respuesta' => '10:45', 'respuesta' => 'Atendido.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(45, $d->fresh()->tiempo_respuesta_min);
        $this->actingAs($coordinador)->delete(route('admin.turnos.derivaciones.destroy', $d))->assertRedirect();
        $this->assertSoftDeleted($d);
    }

    public function test_lectura_no_ve_el_modulo(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)->get(route('admin.turnos.derivaciones.index'))->assertForbidden();
        $this->actingAs($lector)->get(route('admin.turnos.derivaciones.exportar'))->assertForbidden();
    }

    public function test_el_excel_trae_los_encabezados_negros_y_el_zip_las_fotos(): void
    {
        $this->registrar();

        $excel = $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.exportar'))->assertOk();
        $zip = new ZipArchive();
        $zip->open($excel->getFile()->getPathname());
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('CC DE QUIEN ESCALA LA NOVEDAD', $hoja);
        $this->assertStringContainsString('JUSTIFICACION EN CASO DE NO CUMPLIR', $hoja);
        $this->assertStringContainsString('Pendiente', $hoja);
        $this->assertStringContainsString('José Amaya Pérez', $hoja);
        $this->assertStringContainsString('FF000000', $zip->getFromName('xl/styles.xml'));

        // Las tres evidencias van dentro del Excel; el soporte (PDF) como enlace firmado.
        $this->assertStringContainsString('<drawing r:id="rId1"/>', $hoja);
        $this->assertNotFalse($zip->getFromName('xl/media/hoja1-image3.jpeg'));
        $this->assertFalse($zip->getFromName('xl/media/hoja1-image4.jpeg'));
        preg_match('/HYPERLINK\(&quot;([^&]+(?:&amp;[^&]+)*)&quot;/', $hoja, $enlace);
        $zip->close();

        // El enlace abre sin sesión; alterado, no.
        $url = html_entity_decode($enlace[1]);
        auth()->logout();
        $this->get($url)->assertOk();
        $this->get($url.'x')->assertForbidden();

        $fotos = $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.fotos'))->assertOk();
        $zip->open($fotos->getFile()->getPathname());
        $nombres = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $zip->close();

        $this->assertTrue($nombres->contains('derivaciones.xlsx'));
        $this->assertTrue($nombres->contains(fn ($n) => str_ends_with($n, '/Evidencia 1.jpg')));
        $this->assertTrue($nombres->contains(fn ($n) => str_ends_with($n, '/Soporte.pdf')));
    }

    public function test_el_enlace_se_crea_con_el_tipo_derivacion(): void
    {
        $this->actingAs($this->lider)->post(route('admin.turnos.enlaces.store'), [
            'tipo' => 'derivacion', 'componente_id' => $this->calle->id,
        ])->assertSessionHasNoErrors();

        $enlace = EnlaceTurno::latest('id')->first();
        $this->assertTrue($enlace->esDerivacion());
        $this->assertStringContainsString('/d/', $enlace->url());
    }
}
