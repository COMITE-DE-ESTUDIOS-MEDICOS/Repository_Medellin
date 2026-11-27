<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;
use App\Models\CasoCctv;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Services\AlmacenamientoDocumentos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** DOC-33 (CCTV-06): el enlace público /c/{token}. */
class EnlaceTest extends TestCase
{
    use RefreshDatabase;

    private const SIN_CASO = ['message' => 'No hay un caso abierto con ese número.'];

    private Dependencia $dependencia;

    private Componente $calle;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $jose = Colaborador::factory()->en($this->calle)->create(['documento' => '1017000111', 'nombre' => 'José Amaya Pérez']);
        Marcacion::factory()->de($jose)->create(['marcada_at' => now()->subHours(3)]);

        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create(['tipo' => 'seguridad_cctv']);
    }

    private function caso(int $numero, array $datos = [], bool $conSoporte = true): CasoCctv
    {
        $caso = CasoCctv::factory()->de($this->calle)->create($datos + [
            'numero' => $numero,
            'descripcion' => 'Persona sospechosa frente al parque.',
            'ubicacion' => 'Parque de Castilla',
            'requerido_at' => now()->subMinutes(85),
        ]);

        if ($conSoporte) {
            $documento = Documento::withoutGlobalScopes()->create(['dependencia_id' => $caso->dependencia_id, 'nombre' => "Soporte {$numero}"]);
            app(AlmacenamientoDocumentos::class)->guardarVersion($documento, $this->archivoJpg('oficio.jpg'));
            $caso->update(['soporte_documento_id' => $documento->id]);
        }

        return $caso;
    }

    private function consultar(string|int $numero, string $cedula = '1017000111', ?string $token = null)
    {
        return $this->postJson(route('cctv.caso', ['token' => $token ?? $this->token]), ['cedula' => $cedula, 'numero' => (string) $numero]);
    }

    private function atender(array $datos = [])
    {
        return $this->post(route('cctv.atender', ['token' => $this->token]), $datos + [
            'cedula' => '1.017.000.111',
            'numero' => '1',
            'evidencia1' => $this->archivoJpg('uno.jpg'),
        ]);
    }

    private function archivosEnDisco(): int
    {
        return count(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_doc33_ca01_el_enlace_de_un_componente_sin_el_modulo_no_abre(): void
    {
        $this->get(route('cctv.enlace', ['token' => $this->token]))->assertOk()->assertSee('CCTV-');

        $this->calle->forceFill(['modulos' => ['derivaciones']])->save();

        $this->get(route('cctv.enlace', ['token' => $this->token]))->assertNotFound();
        $this->postJson(route('cctv.cedula', ['token' => $this->token]), ['cedula' => '1017000111'])->assertNotFound();
        $this->consultar(1)->assertNotFound();
        $this->atender()->assertNotFound();
    }

    public function test_doc33_ca07_solo_una_cedula_en_turno_ve_el_caso_y_solo_lo_permitido(): void
    {
        $this->caso(1);
        Colaborador::factory()->en($this->calle)->create(['documento' => '1017000222']); // sin entrada
        Colaborador::factory()->en($this->calle)->create(['documento' => '1017000333', 'activo' => false]);

        foreach (['1017000222', '1017000333', '99999999', '', 'x;drop'] as $cedula) {
            $this->consultar(1, $cedula)->assertStatus(422)->assertExactJson(self::SIN_CASO);
        }

        $this->postJson(route('cctv.cedula', ['token' => $this->token]), ['cedula' => '1017000111'])
            ->assertOk()->assertJsonPath('existe', true)->assertJsonMissingPath('nombre');
        $this->postJson(route('cctv.cedula', ['token' => $this->token]), ['cedula' => '1017000222'])
            ->assertOk()->assertJsonPath('existe', false)->assertJsonPath('aviso', 'Para atender un caso tienes que estar en turno: marca tu entrada primero.');

        $respuesta = $this->consultar(1, '1.017.000.111')->assertOk();
        $respuesta->assertJsonPath('codigo', 'CCTV-001')
            ->assertJsonPath('descripcion', 'Persona sospechosa frente al parque.')
            ->assertJsonPath('ubicacion', 'Parque de Castilla')
            ->assertJsonPath('comuna', '10 · '.config('repositorio.comunas')[10])
            ->assertJsonPath('soporte.tipo', 'imagen');
        $this->assertSame(['codigo', 'comuna', 'descripcion', 'soporte', 'ubicacion'], collect($respuesta->json())->keys()->sort()->values()->all());
        $this->assertStringNotContainsString('José Amaya', $respuesta->getContent());
    }

    public function test_doc33_ca07_solo_se_consulta_por_post(): void
    {
        $this->caso(1);

        $this->get(route('cctv.caso', ['token' => $this->token]).'?cedula=1017000111&numero=1')->assertStatus(405);
    }

    public function test_doc33_ca08_todo_lo_que_no_es_un_caso_abierto_recibe_el_mismo_mensaje(): void
    {
        $this->caso(1, ['estado' => CasoCctv::ATENDIDO]);
        $this->caso(2, ['estado' => CasoCctv::CERRADO]);
        $this->caso(3, ['estado' => CasoCctv::ANULADO]);
        $otro = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);
        CasoCctv::factory()->de($otro)->create(['numero' => 4]);

        foreach ([1, 2, 3, 4, 999, 'abc', '1 OR 1=1'] as $numero) {
            $this->consultar($numero)->assertStatus(422)->assertExactJson(self::SIN_CASO);
        }

        $this->assertSame(7, Auditoria::where('accion', AccionAuditoria::CasoCctvConsultado->value)->count());
    }

    public function test_doc33_ca08_diez_intentos_cada_diez_minutos_y_cada_consulta_queda_auditada(): void
    {
        $caso = $this->caso(1);

        $this->consultar(1)->assertOk();
        $registro = Auditoria::where('accion', AccionAuditoria::CasoCctvConsultado->value)->firstOrFail();
        $this->assertSame('1017000111', $registro->datos['cedula']);
        $this->assertTrue($registro->datos['encontrado']);
        $this->assertArrayHasKey('ip', $registro->datos);
        $this->assertSame($caso->id, (int) $registro->auditable_id);

        for ($i = 0; $i < 9; $i++) {
            $this->consultar(999)->assertStatus(422);
        }
        $this->consultar(999)->assertStatus(429);
    }

    public function test_doc33_ca18_el_soporte_solo_abre_firmado_vigente_y_del_caso_consultado(): void
    {
        $uno = $this->caso(1);
        $dos = $this->caso(2, conSoporte: false);

        $url = $this->consultar(1)->json('soporte.url');
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');

        $sinFirma = route('cctv.soporte', ['token' => $this->token, 'caso' => $uno->id]);
        $this->get($sinFirma)->assertForbidden();

        Carbon::setTestNow(now()->addMinutes(11));
        $this->get($url)->assertForbidden();
        Carbon::setTestNow(now()->subMinutes(11));

        // Otro caso (firmado, pero sin soporte), y el mismo caso ya atendido.
        $this->get(URL::temporarySignedRoute('cctv.soporte', now()->addMinutes(10), ['token' => $this->token, 'caso' => $dos->id]))->assertNotFound();
        $uno->update(['estado' => CasoCctv::ATENDIDO]);
        $this->get($url)->assertNotFound();
    }

    public function test_doc33_ca09_atender_exige_la_evidencia_1_y_deja_las_otras_opcionales(): void
    {
        $caso = $this->caso(1);

        $this->atender(['evidencia1' => null])->assertSessionHasErrors('evidencia1');
        $this->assertSame(CasoCctv::ABIERTO, $caso->fresh()->estado);

        $this->atender()->assertRedirect(route('cctv.enlace', ['token' => $this->token]))->assertSessionHas('cctv.atendido');

        $caso->refresh();
        $this->assertSame(CasoCctv::ATENDIDO, $caso->estado);
        $this->assertNotNull($caso->evidencia1_documento_id);
        $this->assertNull($caso->evidencia2_documento_id);
        $this->assertNull($caso->evidencia3_documento_id);
        $this->assertNull($caso->justificacion);
        $this->assertSame('José Amaya Pérez', $caso->nombre);
        $this->assertSame('1017000111', $caso->cedula);
        $this->assertSame(now()->toDateTimeString(), $caso->atendido_at->toDateTimeString());
        $this->assertSame(CasoCctv::carpetaPara($this->calle)->id, Documento::withoutGlobalScopes()->find($caso->evidencia1_documento_id)->carpeta_id);
    }

    public function test_doc33_ca09_con_las_tres_evidencias_y_la_justificacion(): void
    {
        $caso = $this->caso(1);

        $this->atender([
            'numero' => '1', 'justificacion' => 'No llegó la patrulla.',
            'evidencia2' => $this->archivoJpg('dos.jpg'), 'evidencia3' => $this->archivoJpg('tres.jpg'),
        ])->assertSessionHasNoErrors();

        $caso->refresh();
        $this->assertNotNull($caso->evidencia2_documento_id);
        $this->assertNotNull($caso->evidencia3_documento_id);
        $this->assertSame('No llegó la patrulla.', $caso->justificacion);
    }

    public function test_doc33_ca09_la_cedula_y_el_turno_se_revalidan_al_enviar(): void
    {
        $caso = $this->caso(1);
        Colaborador::factory()->en($this->calle)->create(['documento' => '1017000222']);

        $this->atender(['cedula' => '1017000222'])->assertSessionHasErrors('cedula');
        $this->atender(['cedula' => '99999999'])->assertSessionHasErrors('cedula');
        $this->atender(['numero' => '777'])->assertSessionHasErrors(['numero' => self::SIN_CASO['message']]);

        $this->assertSame(CasoCctv::ABIERTO, $caso->fresh()->estado);
        $this->assertSame(0, $this->archivosEnDisco() - 1); // solo el soporte
    }

    public function test_doc33_ca10_el_tiempo_es_la_hora_de_respuesta_menos_la_del_requerimiento_sin_meta(): void
    {
        $caso = $this->caso(1, ['requerido_at' => now()->subHours(30)->subMinutes(5)]);

        // Más de 24 h y sin justificación: se atiende igual.
        $this->atender()->assertSessionHasNoErrors();

        $caso->refresh();
        $this->assertSame(30 * 60 + 5, $caso->tiempo_respuesta_min);
        $this->assertSame('30 h 5 min', $caso->tiempoLegible());
    }

    public function test_doc33_ca11_el_segundo_envio_dice_ya_atendido_y_no_deja_archivos(): void
    {
        $this->caso(1);

        $this->atender()->assertSessionHasNoErrors();
        $antes = $this->archivosEnDisco();

        $this->atender()->assertSessionHasErrors(['numero' => 'Este caso ya fue atendido.']);
        $this->assertSame($antes, $this->archivosEnDisco());
        $this->assertSame(1, Documento::withoutGlobalScopes()->where('nombre', 'like', 'Caso CCTV-001 · Evidencia 1%')->count());
    }

    public function test_doc33_ca11_atender_bloquea_el_caso_para_que_dos_envios_juntos_no_lo_atiendan_dos_veces(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('SQLite no tiene bloqueo de filas: esta prueba corre contra MySQL.');
        }

        $this->caso(1);
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            $consultas[] = $consulta->sql;
        });

        $this->atender()->assertSessionHasNoErrors();

        $bloqueo = collect($consultas)->filter(fn (string $sql): bool => str_contains($sql, 'casos_cctv') && str_contains($sql, 'for update'));
        $this->assertCount(1, $bloqueo);
    }

    public function test_doc33_ca15_consultar_y_atender_dejan_auditoria(): void
    {
        $caso = $this->caso(1);

        $this->consultar(1)->assertOk();
        $this->atender()->assertSessionHasNoErrors();

        $atendido = Auditoria::where('accion', AccionAuditoria::CasoCctvAtendido->value)->firstOrFail();
        $this->assertSame($caso->id, (int) $atendido->auditable_id);
        $this->assertSame('Enlace por cédula', $atendido->datos['origen']);
        $this->assertSame(1, Auditoria::where('accion', AccionAuditoria::CasoCctvConsultado->value)->count());
    }

    public function test_doc33_la_pantalla_muestra_el_cctv_fijo_y_la_evidencia_1_obligatoria(): void
    {
        $this->get(route('cctv.enlace', ['token' => $this->token]))
            ->assertOk()->assertSee('Atender caso')->assertSee('CCTV-')->assertSee('Evidencia #1')->assertSee('Evidencia #3')
            ->assertSee('Seguridad CCTV');
    }

    public function test_doc33_ca08_un_enlace_de_otro_tipo_o_revocado_no_abre_este_flujo(): void
    {
        $derivacion = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($derivacion)->create(['tipo' => 'derivacion']);

        $this->get(route('cctv.enlace', ['token' => $derivacion]))->assertNotFound();
        $this->consultar(1, token: $derivacion)->assertNotFound();
        $this->get(route('cctv.enlace', ['token' => 'no-existe']))->assertNotFound();
    }
}
