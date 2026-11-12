<?php

namespace Tests\Feature\Turnos;

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
use App\Services\Turnos\Marcador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** DOC-12: EPP al marcar entrada y foto solo con la cámara (documentacion/specs/turno-epp-y-camara). */
class EppYCamaraTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private string $token;

    private User $coordinador;

    private Colaborador $persona;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Notification::fake();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'config' => ['foto_obligatoria' => false, 'descanso_min' => 0, 'descanso_parcial_min' => 0],
        ]);
        $nodo = Nodo::factory()->create(['componente_id' => $this->calle->id]);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create();
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $this->persona = Colaborador::factory()->en($this->calle)->create(['documento' => '1017000111', 'nodo_id' => $nodo->id]);
    }

    private function marcar(array $datos = [])
    {
        return $this->post(route('turno.marcar', ['token' => $this->token]), $datos + [
            'cedula' => '1017000111', 'lat' => 6.2476, 'lng' => -75.5658,
        ]);
    }

    public function test_ca01_la_entrada_exige_responder_los_epp(): void
    {
        $this->marcar()->assertSessionHasErrors(['epp' => 'Responde si recibiste los EPP.']);
        $this->marcar(['epp' => 'tal vez'])->assertSessionHasErrors('epp');

        $this->assertSame(0, Marcacion::count());
        $this->get(route('turno.enlace', ['token' => $this->token]))
            ->assertSee('¿Recibiste los elementos de protección personal (EPP)?');
    }

    public function test_ca02_la_salida_no_pregunta_por_los_epp(): void
    {
        $this->marcar(['epp' => 'si'])->assertSessionHasNoErrors();
        $this->travel(5)->hours();

        $this->marcar()->assertSessionHasNoErrors();

        $salida = Marcacion::where('tipo', TipoMarcacion::Salida->value)->sole();
        $this->assertNull($salida->epp_recibidos);
    }

    public function test_ca03_la_respuesta_queda_en_la_entrada_y_en_el_reporte(): void
    {
        $this->marcar(['epp' => 'si'])->assertSessionHasNoErrors();
        $this->assertTrue(Marcacion::sole()->epp_recibidos);
        $this->actingAs($this->coordinador)->get(route('admin.turnos.marcaciones.index'))->assertSee('EPP ✓');

        $this->travel(8)->hours();
        $this->marcar();

        $excel = $this->actingAs($this->coordinador)->get(route('admin.turnos.marcaciones.exportar'))->assertOk();
        $zip = new ZipArchive();
        $zip->open($excel->getFile()->getPathname());
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('>EPP<', $hoja);
        $this->assertStringContainsString('>SI<', $hoja);
    }

    public function test_ca04_el_enlace_de_turnos_solo_deja_tomar_foto(): void
    {
        $pagina = $this->get(route('turno.enlace', ['token' => $this->token]))->assertOk();

        $pagina->assertSee('Tomar foto')
            ->assertDontSee('Elegir archivo')
            ->assertDontSee('data-galeria', false)
            ->assertSee('capture="environment"', false);
    }

    public function test_ca05_un_no_crea_la_novedad_sin_epp(): void
    {
        $this->marcar(['epp' => 'no'])->assertSessionHasNoErrors();

        $this->assertFalse(Marcacion::sole()->epp_recibidos);
        $novedad = NovedadTurno::where('tipo', TipoNovedad::SinEpp->value)->sole();
        $this->assertTrue($novedad->estaPendiente());

        $this->actingAs($this->coordinador)->get(route('admin.turnos.novedades.index'))->assertSee('Sin EPP');
    }

    public function test_ca06_una_entrada_manual_queda_sin_dato(): void
    {
        app(\App\Services\ContextoDependencia::class)->establecer($this->dependencia);

        $entrada = app(Marcador::class)->registrarManual(
            $this->persona, TipoMarcacion::Entrada, now()->subHour(), 'Olvidó marcar', $this->coordinador,
        );

        $this->assertNull($entrada->fresh()->epp_recibidos);
        $this->assertSame(0, NovedadTurno::where('tipo', TipoNovedad::SinEpp->value)->count());
    }
}
