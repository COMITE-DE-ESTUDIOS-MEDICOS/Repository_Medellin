<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\User;
use App\Notifications\CasoAtendido;
use App\Services\AlmacenamientoDocumentos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** DOC-20 (DCA-03): «Atender caso» desde el enlace público /d/{token}. */
class CasosDerivacionAtenderTest extends TestCase
{
    use RefreshDatabase;

    private const SIN_CASO = ['message' => 'No hay un caso abierto con ese número.'];

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private Nodo $nodoHabitual;

    private string $token;

    private User $creador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        // Los casos se crean a las 10:00: «ahora» son las 11:00.
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 2']);
        $this->nodoHabitual = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 5']);
        $jose = Colaborador::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'documento' => '1017000111', 'nodo_id' => $this->nodoHabitual->id,
            'nombre' => 'José Amaya Pérez', 'cargo' => 'Monitor de ruta', 'componente_id' => $this->calle->id,
        ]);
        Marcacion::factory()->de($jose)->create(['marcada_at' => now()->subHours(3)]);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create(['tipo' => 'derivacion']);
        $this->creador = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function caso(int $numero, array $datos = [], bool $conSoporte = true): Derivacion
    {
        $caso = Derivacion::withoutGlobalScopes()->create($datos + [
            'dependencia_id' => $this->dependencia->id,
            'componente_id' => $this->calle->id,
            'numero' => $numero,
            'remitente' => 'alcaldia',
            'entidad' => 'Alcaldía',
            'descripcion' => 'Ciudadano en riesgo en el parque.',
            'ubicacion' => 'Parque de Castilla',
            'fecha_requerimiento' => now()->toDateString(),
            'hora_requerimiento' => '10:00',
            'estado' => Derivacion::PENDIENTE,
            'creado_por' => $this->creador->id,
        ]);

        if ($conSoporte) {
            $documento = Documento::withoutGlobalScopes()->create([
                'dependencia_id' => $caso->dependencia_id, 'nombre' => "Soporte {$numero}",
            ]);
            app(AlmacenamientoDocumentos::class)->guardarVersion($documento, $this->archivoJpg('oficio.jpg'));
            $caso->update(['soporte_documento_id' => $documento->id]);
        }

        return $caso;
    }

    private function consultar(string|int $numero, string $cedula = '1017000111', ?string $token = null)
    {
        return $this->postJson(route('derivacion.caso', ['token' => $token ?? $this->token]), ['cedula' => $cedula, 'numero' => (string) $numero]);
    }

    private function atender(array $datos = [])
    {
        return $this->post(route('derivacion.atender', ['token' => $this->token]), $datos + [
            'cedula' => '1.017.000.111',
            'numero' => '1',
            'nodo_id' => $this->nodo->id,
            'cumplio_requerimiento' => '1',
            'evidencia1' => $this->archivoJpg('uno.jpg'),
            'evidencia2' => $this->archivoJpg('dos.jpg'),
            'evidencia3' => $this->archivoJpg('tres.jpg'),
        ]);
    }

    private function archivosEnDisco(): int
    {
        return count(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_la_pantalla_dice_atender_caso(): void
    {
        $this->get(route('derivacion.enlace', ['token' => $this->token]))
            ->assertOk()->assertSee('Atender caso')->assertSee('DC-')->assertSee('Enviar atención')
            ->assertDontSee('Registrar derivación');
    }

    public function test_doc20_ca03_sin_cedula_en_turno_no_se_consulta_ningun_caso(): void
    {
        $this->caso(1);
        Colaborador::factory()->en($this->calle)->create(['documento' => '1017000222']); // sin entrada
        Colaborador::factory()->en($this->calle)->create(['documento' => '1017000333', 'activo' => false]);

        foreach (['1017000222', '1017000333', '99999999', '', 'x;drop'] as $cedula) {
            $this->consultar(1, $cedula)->assertStatus(422)->assertExactJson(self::SIN_CASO);
        }

        $this->consultar(1)->assertOk()->assertJsonPath('codigo', 'DC-001');
    }

    public function test_doc20_ca04_inexistente_atendido_y_de_otro_componente_dan_el_mismo_mensaje(): void
    {
        $this->caso(1);
        $this->caso(2, ['estado' => Derivacion::SOLUCIONADA, 'atendida_at' => now()]);
        $otro = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $this->caso(3, ['componente_id' => $otro->id]);
        $this->caso(4)->delete();
        // Mismo número en otra dependencia.
        $ajena = Dependencia::factory()->create();
        $this->caso(5, ['dependencia_id' => $ajena->id, 'componente_id' => Componente::factory()->create(['dependencia_id' => $ajena->id])->id]);

        $respuestas = collect([99, 2, 3, 4, 5, 'abc', '1 OR 1=1'])->map(fn ($n) => $this->consultar($n));

        foreach ($respuestas as $r) {
            $r->assertStatus(422);
        }
        $this->assertCount(1, $respuestas->map(fn ($r) => $r->status().'|'.$r->getContent())->unique());
        $this->assertSame(json_encode(self::SIN_CASO), $respuestas->first()->getContent());

        $this->consultar(1)->assertOk();
    }

    public function test_doc20_ca04_cada_consulta_se_audita_y_hay_limite(): void
    {
        $caso = $this->caso(1);

        $this->consultar(1)->assertOk();
        $this->consultar(77)->assertStatus(422);

        $this->assertDatabaseHas('auditorias', [
            'accion' => 'caso.consultado', 'auditable_id' => $caso->id, 'ip' => '127.0.0.1', 'dependencia_id' => $this->dependencia->id,
        ]);
        $auditorias = \App\Models\Auditoria::withoutGlobalScopes()->where('accion', 'caso.consultado')->orderBy('id')->get();
        $this->assertCount(2, $auditorias);
        $this->assertSame(['1017000111', '1', true], [$auditorias[0]->datos['cedula'], $auditorias[0]->datos['numero'], $auditorias[0]->datos['encontrado']]);
        $this->assertSame(['77', false], [$auditorias[1]->datos['numero'], $auditorias[1]->datos['encontrado']]);

        foreach (range(3, 10) as $_) {
            $this->consultar(77)->assertStatus(422);
        }
        $this->consultar(1)->assertStatus(429);

        // Pasados los 10 minutos, vuelve a poder.
        Carbon::setTestNow(now()->addMinutes(11));
        $this->consultar(1)->assertOk();
    }

    public function test_un_enlace_de_turno_o_vencido_no_consulta_casos(): void
    {
        $this->caso(1);
        $turno = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($turno)->create(['tipo' => 'turno']);

        $this->consultar(1, token: $turno)->assertNotFound();
        $this->consultar(1, token: 'no-existe')->assertNotFound();

        EnlaceTurno::porToken($this->token)->update(['activo' => false]);
        $this->consultar(1)->assertNotFound();
    }

    public function test_la_respuesta_del_caso_no_trae_datos_de_mas(): void
    {
        $this->caso(7, ['entidad' => 'Policía', 'remitente' => 'otra']);

        $r = $this->consultar(7)->assertOk();

        $this->assertSame(['codigo', 'mensaje', 'entidad', 'ubicacion', 'soporte', 'nodo_id', 'vencido'], array_keys($r->json()));
        $this->assertSame(['url', 'tipo'], array_keys($r->json('soporte')));
        $r->assertJson([
            'codigo' => 'DC-007', 'mensaje' => 'Ciudadano en riesgo en el parque.', 'entidad' => 'Policía',
            'ubicacion' => 'Parque de Castilla', 'soporte' => ['tipo' => 'imagen'], 'nodo_id' => $this->nodoHabitual->id, 'vencido' => false,
        ]);
        $this->assertStringNotContainsString($this->creador->name, $r->getContent());
        $this->assertStringNotContainsString('José', $r->getContent());

        $this->caso(8, conSoporte: false);
        $this->consultar(8)->assertOk()->assertJsonPath('soporte', null);
    }

    public function test_doc20_ca05_el_soporte_solo_por_url_firmada_y_vencible(): void
    {
        $caso = $this->caso(1);
        $url = $this->consultar(1)->json('soporte.url');

        $this->get(route('derivacion.soporte', ['token' => $this->token, 'derivacion' => $caso->id]))->assertForbidden();
        $this->get($url.'x')->assertForbidden();

        $this->get($url)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeaderContains('Cache-Control', 'no-store');

        // Firmada para otro caso: la firma no cubre el cambio.
        $otro = $this->caso(2);
        $this->get(str_replace("/soporte/{$caso->id}", "/soporte/{$otro->id}", $url))->assertForbidden();

        // Firmada de verdad pero con un enlace de turno: no.
        $turno = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($turno)->create(['tipo' => 'turno']);
        $this->get(URL::temporarySignedRoute('derivacion.soporte', now()->addMinutes(10), ['token' => $turno, 'derivacion' => $caso->id]))->assertNotFound();

        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        // Ya atendido, la URL todavía firmada no sirve.
        $url = $this->consultar(1)->json('soporte.url');
        $caso->update(['estado' => Derivacion::SOLUCIONADA]);
        $this->get($url)->assertNotFound();
    }

    public function test_doc20_ca06_atender_exige_nodo_evidencias_y_si_no_y_cierra_a_hora_del_servidor(): void
    {
        Notification::fake();
        $caso = $this->caso(1);

        $this->atender(['nodo_id' => ''])->assertSessionHasErrors('nodo_id');
        $this->atender(['evidencia2' => null])->assertSessionHasErrors('evidencia2');
        $this->atender(['evidencia3' => $this->archivoPdf()])->assertSessionHasErrors('evidencia3');
        $this->atender(['cumplio_requerimiento' => null])->assertSessionHasErrors('cumplio_requerimiento');
        $this->atender(['cumplio_requerimiento' => '0'])->assertSessionHasErrors('observacion');
        $this->atender(['nodo_id' => Nodo::factory()->create()->id])->assertSessionHasErrors('nodo_id');
        $this->assertTrue($caso->fresh()->estaPendiente());
        $this->assertSame(1, $this->archivosEnDisco(), 'Solo el soporte: los envíos con errores no dejan archivos.');

        // A las 11:42, hora del servidor: 1 h 42 min.
        Carbon::setTestNow(Carbon::today()->setTime(11, 42));
        $this->atender(['cumplio_requerimiento' => '0', 'observacion' => 'La persona no quiso el traslado.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('derivacion.enlace', ['token' => $this->token]))
            ->assertSessionHas('derivacion.atendida', ['codigo' => 'DC-001', 'tiempo' => '1 h 42 min', 'saludo' => 'Jos…']);

        $caso->refresh();
        $this->assertSame(
            ['solucionada', '1017000111', 'José Amaya Pérez', 'Monitor de ruta', $this->nodo->id, false, 'La persona no quiso el traslado.', 102, '11:42'],
            [$caso->estado, $caso->cedula, $caso->nombre, $caso->cargo, $caso->nodo_id, $caso->cumplio_requerimiento, $caso->observacion, $caso->tiempo_respuesta_min, substr($caso->hora_respuesta, 0, 5)],
        );
        $this->assertTrue($caso->atendida_at->equalTo(now()));
        $this->assertNotNull($caso->colaborador_id);
        $this->assertNotNull($caso->enlace_turno_id);
        $this->assertSame('Caso DC-001 · Evidencia 2 · José Amaya Pérez', Documento::withoutGlobalScopes()->find($caso->evidencia2_documento_id)->nombre);
        $this->assertSame(4, $this->archivosEnDisco());
        $this->assertDatabaseHas('auditorias', ['accion' => 'caso.atendido', 'auditable_id' => $caso->id]);
        Notification::assertSentTo($this->creador, CasoAtendido::class);

        $this->get(route('derivacion.enlace', ['token' => $this->token]))->assertSee('Caso DC-001 atendido');
    }

    public function test_doc20_ca07_pasadas_3h_exige_justificacion(): void
    {
        $caso = $this->caso(1);
        Carbon::setTestNow(Carbon::today()->setTime(13, 1));

        $this->consultar(1)->assertJsonPath('vencido', true);
        $this->atender()->assertSessionHasErrors('justificacion');
        $this->assertTrue($caso->fresh()->estaPendiente());

        $this->atender(['justificacion' => 'No había móvil disponible.'])->assertSessionHasNoErrors();
        $this->assertSame([181, 'No había móvil disponible.'], [$caso->fresh()->tiempo_respuesta_min, $caso->fresh()->justificacion]);
    }

    public function test_doc20_ca08_no_se_atiende_dos_veces_y_no_deja_archivos(): void
    {
        $caso = $this->caso(1);

        $this->atender()->assertSessionHasNoErrors();
        $archivos = $this->archivosEnDisco();
        $documentos = Documento::withoutGlobalScopes()->count();
        $atendida = $caso->fresh()->atendida_at;

        Carbon::setTestNow(now()->addMinutes(5));
        $this->atender(['cumplio_requerimiento' => '0', 'observacion' => 'Otra'])->assertSessionHasErrors(['numero' => 'Este caso ya fue atendido.']);

        // A otra persona en turno no se le dice que existe: el mensaje único.
        $otra = Colaborador::factory()->en($this->calle)->create(['documento' => '1017000444']);
        Marcacion::factory()->de($otra)->create(['marcada_at' => now()->subHour()]);
        $this->atender(['cedula' => '1017000444'])->assertSessionHasErrors(['numero' => 'No hay un caso abierto con ese número.']);

        $this->assertSame($archivos, $this->archivosEnDisco());
        $this->assertSame($documentos, Documento::withoutGlobalScopes()->count());
        $this->assertTrue($caso->fresh()->atendida_at->equalTo($atendida));
        $this->assertTrue($caso->fresh()->cumplio_requerimiento);
    }

    public function test_doc20_ca08_si_falla_a_mitad_del_envio_no_quedan_archivos_ni_documentos(): void
    {
        $caso = $this->caso(1);
        $creados = 0;
        // La tercera evidencia falla: las dos primeras ya estaban en disco.
        Documento::created(function () use (&$creados) {
            if (++$creados === 3) {
                throw new \RuntimeException('Disco lleno');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->atender();
            $this->fail('Debió fallar.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Disco lleno', $e->getMessage());
        }

        $this->assertTrue($caso->fresh()->estaPendiente());
        $this->assertSame(1, Documento::withoutGlobalScopes()->count(), 'Solo el soporte.');
        $this->assertSame(1, $this->archivosEnDisco(), 'Solo el soporte.');
    }

    public function test_al_enviar_se_revalida_la_cedula_en_turno(): void
    {
        $this->caso(1);
        Colaborador::factory()->en($this->calle)->create(['documento' => '1017000222']);

        $this->atender(['cedula' => '1017000222'])->assertSessionHasErrors('cedula');
        $this->atender(['cedula' => '99999999'])->assertSessionHasErrors('cedula');
        $this->assertSame(1, $this->archivosEnDisco());
    }
}
