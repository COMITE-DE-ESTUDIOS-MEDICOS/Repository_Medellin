<?php

namespace Tests\Feature\Turnos;

use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\Nodo;
use App\Models\User;
use App\Notifications\NovedadTurnoDetectada;
use App\Services\Turnos\Marcador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Marcar turno por el enlace: cédula, registro una vez, entrada y salida con el mismo link. */
class PublicoMarcarTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private string $token;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Notification::fake();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Calle',
            // Sin descanso: aquí se prueba la mecánica de marcar; el descanso
            // tiene su propia prueba (DescansoTest).
            'config' => ['jornada_horas' => 8, 'tolerancia_min' => 15, 'horas_max_turno' => 14, 'foto_obligatoria' => false, 'descanso_min' => 0, 'descanso_parcial_min' => 0],
        ]);
        $this->nodo = Nodo::factory()->conZona()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 1']);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create();
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    /** Lo que manda el teléfono, con ubicación en el centro del nodo salvo que se diga otra cosa. */
    private function marcar(array $datos = [])
    {
        return $this->post(route('turno.marcar', ['token' => $this->token]), $datos + [
            'cedula' => '1.017.000.111',
            'lat' => 6.2476,
            'lng' => -75.5658,
            'precision_m' => 15,
            'municipio' => 'Medellín, Antioquia',
            'epp' => 'si',
        ]);
    }

    private function registro(): array
    {
        return ['colaborador' => ['nombre' => 'José Amaya Pérez', 'nodo_id' => $this->nodo->id, 'cargo' => 'Monitor de ruta']];
    }

    public function test_la_pantalla_abre_con_el_enlace_y_un_enlace_revocado_no(): void
    {
        $this->get(route('turno.enlace', ['token' => $this->token]))
            ->assertOk()->assertSee('Marca tu turno')->assertSee('Calle');

        EnlaceTurno::withoutGlobalScopes()->first()->revocar();

        $this->get(route('turno.enlace', ['token' => $this->token]))->assertNotFound();
        $this->marcar($this->registro())->assertNotFound();
        $this->assertSame(0, Marcacion::withoutGlobalScopes()->count());
    }

    public function test_la_primera_vez_se_registra_y_marca_entrada_la_segunda_sale_con_solo_la_cedula(): void
    {
        $this->marcar($this->registro())->assertRedirect()->assertSessionHas('turno.marcado.tipo', 'Entrada');

        $persona = Colaborador::withoutGlobalScopes()->where('documento', '1017000111')->sole();
        $this->assertFalse($persona->estaVerificado(), 'Lo autorregistrado espera verificación.');
        $this->assertSame($this->nodo->id, $persona->nodo_id);

        $this->travel(8)->hours();

        $this->marcar()->assertSessionHas('turno.marcado.tipo', 'Salida');

        $tipos = Marcacion::withoutGlobalScopes()->orderBy('marcada_at')->pluck('tipo')->all();
        $this->assertSame([TipoMarcacion::Entrada, TipoMarcacion::Salida], $tipos);
        $this->assertSame(1, Colaborador::withoutGlobalScopes()->count(), 'La segunda vez no registra a nadie.');
    }

    public function test_un_doble_toque_no_crea_dos_entradas(): void
    {
        $this->marcar($this->registro());
        $this->travel(30)->seconds();
        $this->marcar()->assertSessionHas('turno.marcado.repetida', true);

        $this->assertSame(1, Marcacion::withoutGlobalScopes()->count());
    }

    /** DOC-17: la entrada llegó, el teléfono no se enteró y la persona lo intenta otra vez. */
    public function test_ca01_reintentar_minutos_despues_de_la_entrada_no_crea_una_salida(): void
    {
        $this->marcar($this->registro());
        $this->travel(29)->minutes();

        $this->marcar()
            ->assertSessionHas('turno.marcado.repetida', true)
            ->assertSessionHas('turno.marcado.tipo', 'Entrada');

        $this->assertSame(1, Marcacion::withoutGlobalScopes()->count());
        $this->postJson(route('turno.cedula', ['token' => $this->token]), ['cedula' => '1017000111'])
            ->assertJsonPath('accion', 'reciente');
    }

    public function test_ca02_pasado_el_margen_la_marca_es_salida(): void
    {
        $this->marcar($this->registro());
        $this->travel(30)->minutes();

        $this->marcar()->assertSessionHas('turno.marcado.tipo', 'Salida');
        $this->assertSame(2, Marcacion::withoutGlobalScopes()->count());
    }

    public function test_ca03_el_margen_es_regla_del_componente(): void
    {
        $this->calle->update(['config' => [...$this->calle->config, 'salida_minima_min' => 60]]);
        $this->marcar($this->registro());
        $this->travel(45)->minutes();

        $this->marcar()->assertSessionHas('turno.marcado.repetida', true);
        $this->assertSame(1, Marcacion::withoutGlobalScopes()->count());
    }

    public function test_un_turno_de_noche_cierra_con_la_salida_de_la_madrugada(): void
    {
        $this->travelTo(now()->setTime(22, 0));
        $this->marcar($this->registro());

        $this->travelTo(now()->addDay()->setTime(6, 0));
        $this->marcar();

        $salida = Marcacion::withoutGlobalScopes()->where('tipo', 'salida')->sole();
        $this->assertSame('06:00', $salida->marcada_at->format('H:i'));
        $this->assertSame(0, NovedadTurno::withoutGlobalScopes()->count(), 'Ocho horas justas: sin novedad.');
    }

    public function test_la_consulta_de_cedula_no_revela_datos_personales(): void
    {
        $this->marcar($this->registro());
        $this->travel(1)->hours();

        $respuesta = $this->postJson(route('turno.cedula', ['token' => $this->token]), ['cedula' => '1017000111']);

        $respuesta->assertOk()->assertExactJson(['existe' => true, 'saludo' => 'Jos…', 'accion' => 'salida']);
        $this->assertStringNotContainsString('Amaya', $respuesta->getContent());

        $this->postJson(route('turno.cedula', ['token' => $this->token]), ['cedula' => '999999999'])
            ->assertExactJson(['existe' => false, 'saludo' => '', 'accion' => 'entrada']);
    }

    public function test_la_foto_queda_como_documento_en_la_carpeta_del_componente(): void
    {
        $this->marcar($this->registro() + ['foto' => $this->archivoJpg('selfie.jpg')]);

        $marca = Marcacion::withoutGlobalScopes()->with('foto.carpeta')->sole();
        $this->assertNotNull($marca->foto);
        $this->assertSame('Turnos · Calle', $marca->foto->carpeta->nombre);
    }

    public function test_si_el_componente_exige_foto_no_marca_sin_ella(): void
    {
        $this->calle->update(['config' => ['foto_obligatoria' => true]]);

        $this->marcar($this->registro())->assertSessionHasErrors('foto');
        $this->assertSame(0, Marcacion::withoutGlobalScopes()->count());
    }

    public function test_sin_ubicacion_marca_igual_y_avisa_a_coordinacion(): void
    {
        $this->marcar($this->registro() + ['lat' => null, 'lng' => null]);

        $this->assertTrue(Marcacion::withoutGlobalScopes()->sole()->sin_ubicacion);
        $this->assertSame(TipoNovedad::SinUbicacion, NovedadTurno::withoutGlobalScopes()->sole()->tipo);
        Notification::assertSentTo($this->coordinador, NovedadTurnoDetectada::class);
    }

    public function test_lejos_del_nodo_queda_fuera_de_zona(): void
    {
        // Envigado, a varios kilómetros del centro de Medellín.
        $this->marcar($this->registro() + ['lat' => 6.1719, 'lng' => -75.5873]);

        $this->assertTrue(Marcacion::withoutGlobalScopes()->sole()->fuera_de_zona);
        $this->assertSame(TipoNovedad::FueraDeZona, NovedadTurno::withoutGlobalScopes()->sole()->tipo);
    }

    public function test_horas_extra_y_turno_corto_salen_como_novedad(): void
    {
        // A horas fijas: con «un turno por día», el segundo va al día siguiente.
        $this->travelTo(now()->setTime(7, 0));
        $this->marcar($this->registro());
        $this->travel(10)->hours();
        $this->marcar();

        $extra = NovedadTurno::withoutGlobalScopes()->sole();
        $this->assertSame(TipoNovedad::HorasExtra, $extra->tipo);
        $this->assertSame(600, $extra->minutos);
        $this->assertStringContainsString('2 h más', $extra->detalle);

        $this->travelTo(now()->addDay()->setTime(7, 0));
        $this->marcar();
        $this->travel(3)->hours();
        $this->marcar();

        $this->assertSame(TipoNovedad::TurnoCorto, NovedadTurno::withoutGlobalScopes()->latest('id')->first()->tipo);
    }

    public function test_el_cierre_automatico_cierra_lo_que_paso_del_maximo(): void
    {
        $this->marcar($this->registro());
        $this->travel(15)->hours();

        $this->assertSame(1, app(Marcador::class)->cerrarAbiertas());

        $salida = Marcacion::withoutGlobalScopes()->where('tipo', 'salida')->sole();
        $this->assertSame(OrigenMarcacion::CierreAutomatico, $salida->origen);
        $this->assertSame(TipoNovedad::SinSalida, NovedadTurno::withoutGlobalScopes()->sole()->tipo);
        $this->assertSame(0, app(Marcador::class)->cerrarAbiertas(), 'Lo ya cerrado no se cierra dos veces.');
    }

    public function test_una_persona_desactivada_no_marca(): void
    {
        Colaborador::factory()->en($this->calle)->inactivo()->create(['documento' => '1017000111']);

        $this->marcar()->assertSessionHasErrors('cedula');
        $this->assertSame(0, Marcacion::withoutGlobalScopes()->count());
    }

    public function test_sin_autorregistro_una_cedula_nueva_no_entra(): void
    {
        $this->calle->update(['config' => ['autoregistro' => false]]);

        $this->marcar($this->registro())->assertSessionHasErrors('cedula');
        $this->assertSame(0, Colaborador::withoutGlobalScopes()->count());
    }

    public function test_el_cargo_del_registro_tiene_que_ser_del_componente(): void
    {
        $this->calle->update(['config' => ['cargos' => ['Monitor de ruta']]]);

        $this->marcar(['colaborador' => ['nombre' => 'X Y', 'nodo_id' => $this->nodo->id, 'cargo' => 'Astronauta']])
            ->assertSessionHasErrors('colaborador.cargo');
    }
}
