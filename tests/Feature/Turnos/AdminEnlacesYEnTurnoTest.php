<?php

namespace Tests\Feature\Turnos;

use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 2b: enlaces de turno (link + QR) y «En turno ahora». */
class AdminEnlacesYEnTurnoTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    public function test_crear_un_enlace_de_un_nodo_muestra_su_link_y_su_qr(): void
    {
        $nodo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 3']);

        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.enlaces.store'), [
                'componente_id' => $this->calle->id,
                'nodo_id' => $nodo->id,
                'nombre' => 'Microbús ABC123',
                'expira_at' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $enlace = EnlaceTurno::sole();
        $this->assertSame($nodo->id, $enlace->nodo_id);
        $this->assertTrue($enlace->expira_at->isSameSecond(now()->endOfDay()), 'Vence al final del día elegido.');

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.enlaces.show', $enlace))
            ->assertOk()
            ->assertSee('Calle · Nodo 3')
            ->assertSee($enlace->url(), false)
            ->assertSee('data-qr="'.$enlace->url().'"', false);

        $this->assertDatabaseHas('auditorias', ['accion' => 'enlace_turno.creado', 'auditable_id' => $enlace->id]);
    }

    public function test_el_link_apunta_a_t_token_aunque_la_pantalla_publica_aun_no_exista(): void
    {
        $enlace = EnlaceTurno::factory()->de($this->calle)->create();

        $this->assertSame(url('t/'.$enlace->token()), $enlace->url());
    }

    public function test_la_hoja_de_impresion_trae_el_qr_y_las_instrucciones(): void
    {
        $enlace = EnlaceTurno::factory()->de($this->calle)->create(['nombre' => 'Base norte']);

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.enlaces.imprimir', $enlace))
            ->assertOk()
            ->assertSee('Marca tu turno aquí')
            ->assertSee('Base norte')
            ->assertSee('data-qr="'.$enlace->url().'"', false);
    }

    public function test_no_acepta_un_nodo_de_otro_componente(): void
    {
        $otro = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $nodoAjeno = Nodo::factory()->create(['componente_id' => $otro->id]);

        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.enlaces.store'), ['componente_id' => $this->calle->id, 'nodo_id' => $nodoAjeno->id])
            ->assertSessionHasErrors('nodo_id');
    }

    public function test_revocar_apaga_el_enlace_y_queda_en_la_auditoria(): void
    {
        $enlace = EnlaceTurno::factory()->de($this->calle)->create();

        $this->actingAs($this->coordinador)->patch(route('admin.turnos.enlaces.revocar', $enlace));

        $this->assertFalse($enlace->fresh()->estaVigente());
        $this->assertDatabaseHas('auditorias', ['accion' => 'enlace_turno.revocado', 'auditable_id' => $enlace->id]);
    }

    public function test_en_turno_muestra_solo_a_quien_tiene_la_entrada_abierta(): void
    {
        $adentro = Colaborador::factory()->en($this->calle)->create(['nombre' => 'Sigue Adentro']);
        $salio = Colaborador::factory()->en($this->calle)->create(['nombre' => 'Ya Salio']);

        Marcacion::factory()->de($adentro)->create(['marcada_at' => now()->subHours(2)]);
        Marcacion::factory()->de($salio)->create(['marcada_at' => now()->subHours(8)]);
        Marcacion::factory()->de($salio)->salida()->create(['marcada_at' => now()->subHour()]);

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.en-turno'))
            ->assertOk()
            ->assertSee('Sigue Adentro')
            ->assertSee('2 h 0 min')
            ->assertDontSee('Ya Salio');
    }

    public function test_una_correccion_con_hora_anterior_no_cambia_quien_sigue_en_turno(): void
    {
        $persona = Colaborador::factory()->en($this->calle)->create();

        // Entró a las 8 h de hoy; luego Coordinación agrega la salida de AYER,
        // que se inserta después (id mayor) pero es de una hora anterior.
        $entrada = Marcacion::factory()->de($persona)->create(['marcada_at' => now()->subHours(3)]);
        Marcacion::factory()->de($persona)->salida()->create([
            'marcada_at' => now()->subDay(),
            'origen' => OrigenMarcacion::Manual,
            'motivo' => 'Olvidó marcar la salida de ayer',
        ]);

        $abiertas = Marcacion::entradasAbiertas()->pluck('id')->all();

        $this->assertSame([$entrada->id], $abiertas);
    }

    public function test_pasado_el_maximo_del_turno_se_resalta(): void
    {
        $this->calle->update(['config' => ['horas_max_turno' => 8]]);
        $persona = Colaborador::factory()->en($this->calle)->create();
        Marcacion::factory()->de($persona)->create(['marcada_at' => now()->subHours(9)]);

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.en-turno'))
            ->assertSee('Pasó del máximo del turno');
    }

    public function test_edicion_no_entra_y_otra_dependencia_no_se_ve(): void
    {
        // Edición (los líderes) maneja los enlaces y ve quién está en turno; Lectura nada.
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->actingAs($editor)->get(route('admin.turnos.enlaces.index'))->assertOk();
        $this->actingAs($editor)->get(route('admin.turnos.en-turno'))->assertOk();
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $this->actingAs($lector)->get(route('admin.turnos.enlaces.index'))->assertForbidden();
        $this->actingAs($lector)->get(route('admin.turnos.en-turno'))->assertForbidden();

        $ajeno = EnlaceTurno::factory()->create(['nombre' => 'Enlace de Beta']);
        $this->assertNoDejaVer(
            $this->actingAs($this->coordinador)->get(route('admin.turnos.enlaces.show', $ajeno)),
            $ajeno->token(),
            'Ver el link de otra dependencia',
        );

        $deBeta = Colaborador::factory()->en(Componente::factory()->create())->create(['nombre' => 'Persona Beta']);
        Marcacion::factory()->de($deBeta)->create();
        $this->actingAs($this->coordinador)->get(route('admin.turnos.en-turno'))->assertDontSee('Persona Beta');
    }
}
