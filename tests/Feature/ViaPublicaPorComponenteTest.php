<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Enums\TipoMarcacion;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\PuntoCritico;
use App\Models\Supervision;
use App\Models\TipoTurno;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * DOC-25 (ACC-12, CA-14): los enlaces por cédula /t/, /d/ y /s/ funcionan igual
 * que antes con Calle y Básica en la misma dependencia, también con una sesión
 * de Básica abierta en el mismo navegador, y con las mismas direcciones.
 */
class ViaPublicaPorComponenteTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    private Nodo $nodoCalle;

    private Colaborador $jose;

    private User $edicionBasica;

    /** @var array<string, string> token por tipo de enlace de Calle */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Notification::fake();
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle',
            // Sin foto ni descanso: aquí se prueba el componente, no la mecánica de marcar.
            'config' => [
                'jornada_horas' => 8, 'tolerancia_min' => 15, 'horas_max_turno' => 14, 'foto_obligatoria' => false,
                'descanso_min' => 0, 'descanso_parcial_min' => 0,
                'cargos' => ['Educador', 'Apoyo al control territorial'], 'cargos_supervision' => ['Apoyo al control territorial'],
            ],
        ]);
        $this->basica = Componente::factory()->sinModulos()->create([
            'dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica',
        ]);

        $this->nodoCalle = Nodo::factory()->conZona()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 1']);
        $nodoBasica = Nodo::factory()->create(['componente_id' => $this->basica->id, 'nombre' => 'Nodo Básica']);

        $this->jose = Colaborador::factory()->en($this->calle, $this->nodoCalle)->create([
            'documento' => '1017000111', 'nombre' => 'José Amaya Pérez', 'cargo' => 'Educador',
        ]);
        Colaborador::factory()->en($this->basica, $nodoBasica)->create(['documento' => '1017000999', 'nombre' => 'Beatriz Básica']);

        foreach (['turno', 'derivacion', 'supervision'] as $tipo) {
            $this->tokens[$tipo] = EnlaceTurno::generarToken();
            EnlaceTurno::factory()->de($this->calle)->conToken($this->tokens[$tipo])->create(['tipo' => $tipo]);
        }

        // Usuarios de los dos componentes.
        $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $this->edicionBasica = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);
    }

    /**
     * Abre en el navegador de la prueba una sesión de verdad parada en Básica.
     * Con Administración, Básica se elige con el selector (componente en la sesión).
     *
     * Después olvida el ContextoDependencia: en producción (php-fpm) cada petición
     * empieza con uno nuevo, y en la prueba el singleton seguiría con el Básica
     * de esta petición. La sesión y el usuario autenticado sí siguen.
     */
    private function abrirSesionEnBasica(bool $administracion = false): User
    {
        $usuario = $administracion
            ? $this->usuarioCon(RolDependencia::Administracion, $this->dependencia)
            : $this->edicionBasica;

        $this->actingAs($usuario);

        if ($administracion) {
            $this->put(route('componente.cambiar', $this->basica->id))->assertRedirect(route('inicio'));
            $this->assertSame($this->basica->id, session('componente_id'));
        }

        $this->get(route('inicio'))->assertOk();
        $this->assertSame($this->basica->id, app(ContextoDependencia::class)->componenteId(), 'La sesión quedó en Básica.');

        $this->app->forgetInstance(ContextoDependencia::class);

        return $usuario;
    }

    private function marcar(array $datos = [])
    {
        return $this->post(route('turno.marcar', ['token' => $this->tokens['turno']]), $datos + [
            'cedula' => '1.017.000.111',
            'lat' => 6.2476,
            'lng' => -75.5658,
            'precision_m' => 15,
            'municipio' => 'Medellín, Antioquia',
            'epp' => 'si',
        ]);
    }

    /** Entrada y, ocho horas después, salida de José; las dos en Calle. */
    private function marcarEntradaYSalida(): void
    {
        $this->marcar()->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('turno.marcado.tipo', 'Entrada');
        $this->travel(8)->hours();
        $this->marcar()->assertSessionHasNoErrors()->assertSessionHas('turno.marcado.tipo', 'Salida');

        $marcas = Marcacion::withoutGlobalScopes()->where('colaborador_id', $this->jose->id)->orderBy('marcada_at')->get();
        $this->assertSame([TipoMarcacion::Entrada, TipoMarcacion::Salida], $marcas->pluck('tipo')->all());
        $this->assertSame([$this->calle->id, $this->calle->id], $marcas->pluck('componente_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, Marcacion::withoutGlobalScopes()->where('componente_id', $this->basica->id)->count());
    }

    private function enTurno(Colaborador $persona): void
    {
        Marcacion::factory()->de($persona)->create(['marcada_at' => now()->subHours(2)]);
    }

    private function caso(Componente $componente, int $numero): Derivacion
    {
        return Derivacion::withoutGlobalScopes()->create([
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $componente->id, 'numero' => $numero,
            'remitente' => 'alcaldia', 'entidad' => 'Alcaldía', 'descripcion' => 'Ciudadano en riesgo en el parque.',
            'ubicacion' => 'Parque de Castilla', 'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
            'estado' => Derivacion::PENDIENTE, 'creado_por' => $this->edicionBasica->id,
        ]);
    }

    public function test_doc25_ca14_sin_sesion_la_persona_de_calle_marca_entrada_y_salida_en_calle(): void
    {
        $this->get(route('turno.enlace', ['token' => $this->tokens['turno']]))->assertOk()->assertSee('Calle');

        $this->marcarEntradaYSalida();
    }

    public function test_doc25_ca14_con_sesion_de_basica_abierta_la_marcacion_queda_en_calle(): void
    {
        $this->abrirSesionEnBasica();

        $this->get(route('turno.enlace', ['token' => $this->tokens['turno']]))->assertOk()->assertSee('Marca tu turno');
        $this->postJson(route('turno.cedula', ['token' => $this->tokens['turno']]), ['cedula' => '1017000111'])
            ->assertExactJson(['existe' => true, 'saludo' => 'Jos…', 'accion' => 'entrada']);

        $this->marcarEntradaYSalida();

        // El autorregistro también queda en Calle, aunque la sesión sea de Básica.
        $this->marcar(['cedula' => '1017000222', 'colaborador' => [
            'nombre' => 'Nueva Persona Calle', 'nodo_id' => $this->nodoCalle->id, 'cargo' => 'Educador',
        ]])->assertSessionHasNoErrors()->assertSessionHas('turno.marcado.tipo', 'Entrada');

        $nueva = Colaborador::withoutGlobalScopes()->where('documento', '1017000222')->sole();
        $this->assertSame($this->calle->id, (int) $nueva->componente_id);
        $this->assertSame($this->calle->id, (int) Marcacion::withoutGlobalScopes()->where('colaborador_id', $nueva->id)->sole()->componente_id);

        // La sesión de Básica sigue igual después de usar el enlace.
        $this->get(route('inicio'))->assertOk();
        $this->assertSame($this->basica->id, app(ContextoDependencia::class)->componenteId());
    }

    public function test_doc25_ca14_con_sesion_de_basica_abierta_se_consulta_y_atiende_un_caso_de_calle(): void
    {
        $this->enTurno($this->jose);
        $casoCalle = $this->caso($this->calle, 1);
        $this->caso($this->basica, 2);
        $token = $this->tokens['derivacion'];

        $this->abrirSesionEnBasica();

        $this->get(route('derivacion.enlace', ['token' => $token]))->assertOk()->assertSee('Atender caso');
        $this->postJson(route('derivacion.cedula', ['token' => $token]), ['cedula' => '1017000111'])
            ->assertExactJson(['existe' => true, 'saludo' => 'Jos…']);

        $this->postJson(route('derivacion.caso', ['token' => $token]), ['cedula' => '1017000111', 'numero' => '1'])
            ->assertOk()->assertJsonPath('codigo', 'DC-001');
        // El caso de Básica no se ve por el enlace de Calle, con sesión de Básica o sin ella.
        $this->postJson(route('derivacion.caso', ['token' => $token]), ['cedula' => '1017000111', 'numero' => '2'])
            ->assertStatus(422);

        $this->post(route('derivacion.atender', ['token' => $token]), [
            'cedula' => '1.017.000.111',
            'numero' => '1',
            'nodo_id' => $this->nodoCalle->id,
            'gestion_realizada' => 'Se habló con el ciudadano y se trasladó al hogar de paso.',
            'cumplio_requerimiento' => '1',
            'evidencia1' => $this->archivoJpg('uno.jpg'),
            'evidencia2' => $this->archivoJpg('dos.jpg'),
            'evidencia3' => $this->archivoJpg('tres.jpg'),
        ])->assertRedirect(route('derivacion.enlace', ['token' => $token]))->assertSessionHasNoErrors();

        $caso = Derivacion::withoutGlobalScopes()->findOrFail($casoCalle->id);
        $this->assertSame(Derivacion::SOLUCIONADA, $caso->estado);
        $this->assertSame([$this->calle->id, $this->jose->id], [(int) $caso->componente_id, (int) $caso->colaborador_id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'caso.atendido', 'auditable_id' => $caso->id]);
    }

    public function test_doc25_ca14_con_sesion_de_administracion_en_basica_se_registra_una_supervision_de_calle(): void
    {
        $punto = PuntoCritico::create(['componente_id' => $this->calle->id, 'nombre' => 'Parque Bolívar', 'activo' => true]);
        $turno = TipoTurno::create([
            'componente_id' => $this->calle->id, 'codigo' => 'N1', 'nombre' => 'Mañana', 'es_turno' => true,
            'hora_inicio' => '05:00', 'hora_fin' => '12:30', 'horas_nomina' => 7, 'activo' => true,
        ]);
        $supervisor = Colaborador::factory()->en($this->calle, $this->nodoCalle)->create([
            'documento' => '1001', 'nombre' => 'Felipe Apoyo', 'cargo' => 'Apoyo al control territorial',
        ]);
        $this->enTurno($supervisor);
        $this->enTurno($this->jose);
        $token = $this->tokens['supervision'];

        $this->abrirSesionEnBasica(administracion: true);

        $this->get(route('supervision.enlace', ['token' => $token]))->assertOk()->assertSee('Parque Bolívar');
        $this->postJson(route('supervision.cedula', ['token' => $token]), ['cedula' => '1001'])
            ->assertExactJson(['existe' => true, 'saludo' => 'Fel…']);
        $this->postJson(route('supervision.dinamizador', ['token' => $token]), ['cedula' => '1017000111'])
            ->assertExactJson(['existe' => true, 'saludo' => 'Jos…']);

        $this->post(route('supervision.registrar', ['token' => $token]), [
            'cedula' => '1001',
            'punto_critico_id' => $punto->id,
            'nodo_id' => $this->nodoCalle->id,
            'fecha_visita' => now()->toDateString(),
            'hora_visita' => '10:15',
            'dinamizador1' => '1017000111',
            'tipo_turno_id' => $turno->id,
            'descripcion_punto' => 'Punto con 12 personas, consumo activo.',
            ...array_fill_keys(array_keys(Supervision::CRITERIOS), 5),
            'calificacion_global' => 4,
            'incidencia' => 'leve',
            'casos_escalados' => 'no',
            'recomendaciones' => 'Reforzar la ruta de la tarde.',
            'evidencia1' => $this->archivoJpg('uno.jpg'),
        ])->assertRedirect(route('supervision.enlace', ['token' => $token]))
            ->assertSessionHasNoErrors()->assertSessionHas('supervision.registrada');

        $supervision = Supervision::withoutGlobalScopes()->sole();
        $this->assertSame($this->calle->id, (int) $supervision->componente_id);
        $this->assertSame([$supervisor->id, $this->jose->id], [(int) $supervision->supervisor_id, (int) $supervision->dinamizador1_id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'supervision.registrada', 'auditable_id' => $supervision->id]);
    }

    public function test_doc25_ca14_las_direcciones_publicas_no_cambian_y_no_piden_sesion_ni_modulo(): void
    {
        $t = 'abc123';
        $esperadas = [
            'turno.enlace' => "/t/{$t}", 'turno.cedula' => "/t/{$t}/cedula", 'turno.marcar' => "/t/{$t}/marcar",
            'derivacion.enlace' => "/d/{$t}", 'derivacion.cedula' => "/d/{$t}/cedula",
            'derivacion.caso' => "/d/{$t}/caso", 'derivacion.atender' => "/d/{$t}",
            'supervision.enlace' => "/s/{$t}", 'supervision.cedula' => "/s/{$t}/cedula",
            'supervision.dinamizador' => "/s/{$t}/dinamizador", 'supervision.registrar' => "/s/{$t}",
        ];

        foreach ($esperadas as $nombre => $ruta) {
            $this->assertSame(url($ruta), route($nombre, ['token' => $t]), $nombre);

            $middlewares = Route::getRoutes()->getByName($nombre)->gatherMiddleware();
            foreach ($middlewares as $m) {
                $this->assertFalse(
                    is_string($m) && ($m === 'auth' || $m === 'dependencia' || $m === 'usuario.activo' || str_starts_with($m, 'can:')),
                    "{$nombre} no debe pedir sesión, dependencia ni módulo ({$m}).",
                );
            }
        }

        // Los QR ya impresos: la URL del enlace es la de siempre, para cada tipo.
        foreach (['turno' => 't', 'derivacion' => 'd', 'supervision' => 's'] as $tipo => $letra) {
            $enlace = EnlaceTurno::porToken($this->tokens[$tipo]);
            $this->assertSame(url("/{$letra}/{$this->tokens[$tipo]}"), $enlace->url());
        }
    }

    public function test_doc25_ca14_la_respuesta_publica_solo_trae_el_saludo_con_sesion_o_sin_ella(): void
    {
        $token = $this->tokens['turno'];
        $comprobar = function () use ($token) {
            foreach ([
                $this->get(route('turno.enlace', ['token' => $token]))->assertOk(),
                $this->postJson(route('turno.cedula', ['token' => $token]), ['cedula' => '1017000111'])->assertOk()->assertJsonPath('saludo', 'Jos…'),
            ] as $respuesta) {
                foreach (['José Amaya Pérez', 'Amaya', '1017000111', 'Beatriz Básica', '1017000999'] as $dato) {
                    $this->assertStringNotContainsString($dato, $respuesta->getContent());
                }
            }
        };

        $comprobar();

        $usuario = $this->abrirSesionEnBasica();
        $comprobar();

        // La pantalla después de marcar: el saludo recortado y nada más.
        $pantalla = $this->followingRedirects()->post(route('turno.marcar', ['token' => $token]), [
            'cedula' => '1017000111', 'lat' => 6.2476, 'lng' => -75.5658, 'precision_m' => 15, 'epp' => 'si',
        ])->assertOk()->assertSee('Jos…');

        foreach (['José Amaya Pérez', 'Amaya', '1017000111', $usuario->name] as $dato) {
            $this->assertStringNotContainsString($dato, $pantalla->getContent());
        }
    }
}
