<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Models\User;
use App\Services\CalendarioHabil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Registro de horarios: en qué franja trabajan los funcionarios y cuánto
 * tarda un remitente en responder desde que se le entrega el enlace.
 *
 * Lo que se prueba aquí no es la aritmética de las fechas sino las dos
 * decisiones de diseño: que el juicio de horario se congela al recibir —y no
 * se recalcula después, cuando la configuración ya cambió— y que el tiempo
 * de respuesta se deriva en vez de guardarse.
 */
class HorariosTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Carpeta $carpeta;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Storage::fake('public');

        $this->dependencia = Dependencia::factory()->create();
        $this->carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $this->administrador = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
    }

    /** @return array{0: EnlaceCarga, 1: string} */
    private function enlace(array $atributos = []): array
    {
        $token = EnlaceCarga::generarToken();

        $enlace = EnlaceCarga::factory()->conToken($token)->create([
            'dependencia_id' => $this->dependencia->id,
            'carpeta_id' => $this->carpeta->id,
            'creado_por' => $this->administrador->id,
            ...$atributos,
        ]);

        return [$enlace, $token];
    }

    private function enviar(string $token): void
    {
        $this->post(route('envio.recibir', ['token' => $token]), [
            'remitente_nombre' => 'Ana Ramírez',
            'remitente_email' => 'ana@entidad-externa.co',
            'remitente_entidad' => 'Contratista Uno',
            'nodo' => 3,
            'archivo' => [$this->archivoPdf('acta.pdf', 'CONTENIDO')],
        ])->assertRedirect(route('envio.confirmacion'));
    }

    private function recepcion(): Recepcion
    {
        return Recepcion::withoutGlobalScopes()->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | El calendario
    |--------------------------------------------------------------------------
    */

    /**
     * La trampa de este módulo. La aplicación atiende en Medellín y la base
     * puede estar en UTC: si la comparación se hiciera en la zona del
     * servidor, todo correría cinco horas y nadie lo notaría hasta que un
     * informe saliera torcido. Por eso el mismo instante se pregunta escrito
     * de dos maneras y tiene que responder lo mismo.
     */
    public function test_el_horario_se_juzga_en_la_zona_de_medellin_no_en_la_del_servidor(): void
    {
        $calendario = app(CalendarioHabil::class);

        /*
        | Los dos casos están elegidos para que la respuesta CAMBIE si se
        | juzgara en la zona equivocada. No vale cualquier hora: las 22:00 de
        | Medellín son las 03:00 UTC y las dos caen fuera de la franja, así
        | que un caso así pasaría igual con el error puesto.
        */

        // Las 14:00 de un martes en Medellín son horario de trabajo. El mismo
        // instante, escrito en UTC, son las 19:00: leído así saldría fuera.
        $enPlenaTarde = Carbon::parse('2026-09-15 14:00', 'America/Bogota');

        $this->assertSame('2026-09-15 19:00', $enPlenaTarde->copy()->setTimezone('UTC')->format('Y-m-d H:i'));
        $this->assertTrue($calendario->esHabil($enPlenaTarde));
        $this->assertTrue(
            $calendario->esHabil($enPlenaTarde->copy()->setTimezone('UTC')),
            'El mismo instante cambió de respuesta al escribirlo en UTC.',
        );

        // Y al revés: las 03:00 de un lunes en Medellín no las trabaja nadie,
        // pero en UTC son las 08:00, que sí caen dentro de la franja.
        $enLaMadrugada = Carbon::parse('2026-09-14 03:00', 'America/Bogota');

        $this->assertSame('2026-09-14 08:00', $enLaMadrugada->copy()->setTimezone('UTC')->format('Y-m-d H:i'));
        $this->assertFalse($calendario->esHabil($enLaMadrugada));
        $this->assertFalse(
            $calendario->esHabil($enLaMadrugada->copy()->setTimezone('UTC')),
            'Una madrugada pasó por horario de oficina al leerla en UTC.',
        );
    }

    public function test_un_dia_sin_franja_declarada_es_dia_no_habil(): void
    {
        $calendario = app(CalendarioHabil::class);

        // Ni sábado ni domingo aparecen en la configuración.
        $this->assertFalse($calendario->esHabil(Carbon::parse('2026-09-19 10:00', 'America/Bogota')));
        $this->assertFalse($calendario->esHabil(Carbon::parse('2026-09-20 10:00', 'America/Bogota')));
    }

    public function test_los_festivos_cargados_dejan_de_ser_habiles(): void
    {
        $calendario = app(CalendarioHabil::class);
        $martes = Carbon::parse('2026-09-15 10:00', 'America/Bogota');

        $this->assertTrue($calendario->esHabil($martes));

        config()->set('repositorio.horario.no_habiles', ['2026-09-15']);

        $this->assertFalse($calendario->esHabil($martes));
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que se congela al recibir
    |--------------------------------------------------------------------------
    */

    public function test_lo_que_llega_un_martes_a_las_diez_no_queda_fuera_de_horario(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 10:00', 'America/Bogota'));

        [, $token] = $this->enlace();
        $this->enviar($token);

        $this->assertFalse($this->recepcion()->fuera_de_horario);
    }

    public function test_lo_que_llega_un_sabado_o_de_noche_queda_fuera_de_horario(): void
    {
        // Sábado a media mañana.
        $this->travelTo(Carbon::parse('2026-09-19 10:00', 'America/Bogota'));

        [, $token] = $this->enlace();
        $this->enviar($token);

        $this->assertTrue($this->recepcion()->fuera_de_horario);

        // Y un martes, pero a las diez de la noche.
        Recepcion::withoutGlobalScopes()->delete();
        $this->travelTo(Carbon::parse('2026-09-15 22:00', 'America/Bogota'));

        [, $otro] = $this->enlace();
        $this->enviar($otro);

        $this->assertTrue($this->recepcion()->fuera_de_horario);
    }

    /**
     * La razón de que esto sea columna y no un cálculo al vuelo. Si mañana el
     * martes empieza a las 11:00, la recepción de las 10:00 de hoy no puede
     * cambiar de respuesta: lo que se guardó es el juicio de ese día.
     */
    public function test_cambiar_el_horario_no_altera_las_recepciones_ya_guardadas(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 10:00', 'America/Bogota'));

        [, $token] = $this->enlace();
        $this->enviar($token);

        $this->assertFalse($this->recepcion()->fuera_de_horario);

        // El horario se estrecha: ahora el martes abre a las 11:00.
        config()->set('repositorio.horario.dias', [2 => ['11:00', '17:00']]);

        $this->assertFalse(
            $this->recepcion()->fresh()->fuera_de_horario,
            'Reescribir la configuración reescribió el pasado.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tiempo de respuesta
    |--------------------------------------------------------------------------
    */

    public function test_el_enlace_nace_con_la_fecha_de_envio_puesta(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 09:00', 'America/Bogota'));

        [$enlace] = $this->enlace();

        $this->assertNotNull($enlace->enviado_at);
        $this->assertSame(now()->format('Y-m-d H:i'), $enlace->enviado_at->format('Y-m-d H:i'));
    }

    public function test_el_tiempo_de_respuesta_se_cuenta_desde_que_se_entrego_el_enlace(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 09:00', 'America/Bogota'));

        [, $token] = $this->enlace();

        // El remitente responde hora y media después.
        $this->travelTo(Carbon::parse('2026-09-15 10:30', 'America/Bogota'));
        $this->enviar($token);

        $this->assertSame(90, $this->recepcion()->minutosDesdeEnvio());
    }

    /** La recepción sobrevive al enlace; el tiempo de respuesta no puede. */
    public function test_sin_enlace_no_hay_tiempo_de_respuesta(): void
    {
        [$enlace, $token] = $this->enlace();
        $this->enviar($token);

        $enlace->delete();

        $recepcion = $this->recepcion()->fresh();

        $this->assertNull($recepcion->enlace_carga_id, 'La recepción se fue con el enlace.');
        $this->assertNull($recepcion->minutosDesdeEnvio());
    }

    /*
    |--------------------------------------------------------------------------
    | Corregir la fecha de envío
    |--------------------------------------------------------------------------
    */

    public function test_administracion_corrige_la_fecha_de_envio_y_queda_auditado(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 09:00', 'America/Bogota'));

        [$enlace] = $this->enlace();

        // Se generó el martes pero se entregó el miércoles.
        $this->travelTo(Carbon::parse('2026-09-16 12:00', 'America/Bogota'));

        $this->actingAs($this->administrador)
            ->from(route('admin.enlaces.index'))
            ->patch(route('admin.enlaces.fecha-envio', $enlace), [
                'enviado_at' => '2026-09-16 08:00',
            ])
            ->assertRedirect(route('admin.enlaces.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-16 08:00', $enlace->fresh()->enviado_at->format('Y-m-d H:i'));

        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::EnlaceFechaEnvioCorregida->value,
            'user_id' => $this->administrador->id,
        ]);
    }

    public function test_la_fecha_de_envio_no_puede_quedar_en_el_futuro_ni_antes_de_crearse(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 09:00', 'America/Bogota'));

        [$enlace] = $this->enlace();
        $original = $enlace->enviado_at->format('Y-m-d H:i');

        foreach (['2027-01-01 08:00', '2026-09-14 08:00'] as $imposible) {
            $this->actingAs($this->administrador)
                ->from(route('admin.enlaces.index'))
                ->patch(route('admin.enlaces.fecha-envio', $enlace), ['enviado_at' => $imposible])
                ->assertSessionHasErrors('enviado_at');
        }

        $this->assertSame($original, $enlace->fresh()->enviado_at->format('Y-m-d H:i'));
    }

    /** Ser lector no basta: esto mueve un dato del que salen informes. */
    public function test_un_lector_no_puede_corregir_la_fecha_de_envio(): void
    {
        [$enlace] = $this->enlace();

        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)
            ->patch(route('admin.enlaces.fecha-envio', $enlace), ['enviado_at' => '2026-09-16 08:00'])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que se ve
    |--------------------------------------------------------------------------
    */

    public function test_la_ficha_del_documento_muestra_el_horario_y_la_demora(): void
    {
        // Sábado: fuera de horario, y con dos horas de demora.
        $this->travelTo(Carbon::parse('2026-09-19 10:00', 'America/Bogota'));

        [, $token] = $this->enlace();

        $this->travelTo(Carbon::parse('2026-09-19 12:00', 'America/Bogota'));
        $this->enviar($token);

        $documento = Documento::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->administrador)
            ->get(route('documentos.show', $documento))
            ->assertOk()
            ->assertSee('Fuera del horario hábil')
            ->assertSee('Tardó en responder')
            ->assertSee('2 h 0 min');
    }

    public function test_la_administracion_de_enlaces_muestra_la_fecha_de_envio(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 09:30', 'America/Bogota'));

        $this->enlace();

        $this->actingAs($this->administrador)
            ->get(route('admin.enlaces.index'))
            ->assertOk()
            ->assertSee('Enviado')
            ->assertSee('15/09/2026 9:30 a. m.')
            ->assertSee('Corregir fecha de envío');
    }
}
