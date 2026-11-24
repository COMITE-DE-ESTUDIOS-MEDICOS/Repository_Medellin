<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Nodo;
use App\Models\Supervision;
use App\Models\TipoTurno;
use App\Models\User;
use App\Notifications\DerivacionPendiente;
use App\Notifications\NovedadTurnoDetectada;
use App\Notifications\SupervisionImportante;
use App\Services\Turnos\Novedades;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * DOC-25 (ACC-10, CA-12): los avisos automáticos de la campana (RN-23, RN-25,
 * RN-33, RN-45) van a los usuarios del componente del registro y a
 * Administración (el coordinador líder); nadie de otro componente los recibe.
 * Se disparan desde el cron o la vía pública: el componente sale del dato.
 */
class AvisosCampanaPorComponenteTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    /** @var array<string, User> */
    private array $u;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);

        $this->u = [
            'admin' => $this->usuarioCon(RolDependencia::Administracion, $this->dependencia),
            'edicionCalle' => $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle),
            'coordCalle' => $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle),
            'lecturaCalle' => $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->calle),
            'edicionBasica' => $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica),
            'coordBasica' => $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica),
            'apruebaBasica' => $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica),
        ];

        // Quien aprueba novedades en Básica no aprueba las de Calle.
        $this->dependencia->usuarios()->updateExistingPivot($this->u['apruebaBasica']->id, ['aprueba_novedades' => true]);
    }

    private function assertSoloA(string $aviso, array $quienes): void
    {
        foreach ($this->u as $nombre => $usuario) {
            in_array($nombre, $quienes, true)
                ? Notification::assertSentTo($usuario, $aviso)
                : Notification::assertNotSentTo($usuario, $aviso, "No debía avisar a {$nombre}.");
        }
    }

    public function test_doc25_ca12_no_registro_entrada_avisa_solo_al_componente_y_a_administracion(): void
    {
        $persona = Colaborador::factory()->en($this->calle)->create();
        $tipo = TipoTurno::create(['componente_id' => $this->calle->id, 'codigo' => 'N1', 'nombre' => 'Mañana', 'hora_inicio' => '05:00', 'hora_fin' => '13:00']);

        $this->assertNotNull(app(Novedades::class)->sinEntrada($persona, $tipo, Carbon::parse('2026-10-20 05:00')));

        $this->assertSoloA(NovedadTurnoDetectada::class, ['admin', 'edicionCalle', 'coordCalle']);
    }

    public function test_doc25_ca12_las_novedades_de_basica_van_a_quien_aprueba_en_basica(): void
    {
        $persona = Colaborador::factory()->en($this->basica)->create();
        $tipo = TipoTurno::create(['componente_id' => $this->basica->id, 'codigo' => 'N1', 'nombre' => 'Mañana', 'hora_inicio' => '05:00', 'hora_fin' => '13:00']);

        app(Novedades::class)->sinEntrada($persona, $tipo, Carbon::parse('2026-10-20 05:00'));

        $this->assertSoloA(NovedadTurnoDetectada::class, ['admin', 'edicionBasica', 'coordBasica', 'apruebaBasica']);
    }

    public function test_doc25_ca12_derivacion_vencida_avisa_solo_al_componente_y_a_administracion(): void
    {
        $persona = Colaborador::factory()->en($this->calle)->create();
        $caso = Derivacion::create([
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->calle->id, 'colaborador_id' => $persona->id,
            'cedula' => $persona->documento, 'nombre' => $persona->nombre, 'nodo_id' => Nodo::factory()->create(['componente_id' => $this->calle->id])->id,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '07:55', 'entidad' => 'Policía Nacional',
            'descripcion' => 'Traslado', 'ubicacion' => 'Castilla', 'estado' => Derivacion::PENDIENTE,
        ]);

        DerivacionPendiente::enviar($caso);

        $this->assertSoloA(DerivacionPendiente::class, ['admin', 'edicionCalle', 'coordCalle']);
    }

    public function test_doc25_ca12_supervision_importante_avisa_solo_al_componente_y_a_administracion(): void
    {
        $supervision = (new Supervision)->forceFill([
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->basica->id,
            'incidencia' => 'critica', 'casos_escalados' => false, 'calificacion_global' => 4,
        ]);

        SupervisionImportante::enviarSiHace($supervision);

        $this->assertSoloA(SupervisionImportante::class, ['admin', 'edicionBasica', 'coordBasica']);
    }
}
