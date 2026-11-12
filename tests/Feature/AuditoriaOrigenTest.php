<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Auditoria;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * «Yo sí lo subí» / «lo subí en otra carpeta»: la auditoría dice por dónde
 * entró cada archivo y a qué carpeta fue a parar.
 */
class AuditoriaOrigenTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $coordinador;

    private Carpeta $octubre;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $calle = Carpeta::create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->octubre = Carpeta::create(['dependencia_id' => $this->dependencia->id, 'carpeta_id' => $calle->id, 'nombre' => 'Octubre']);
    }

    public function test_una_carga_en_la_aplicacion_queda_con_origen_y_carpeta_y_se_ve_en_la_tabla(): void
    {
        $this->actingAs($this->coordinador)->post(route('documentos.store'), [
            'nombre' => 'Acta de ruta', 'carpeta_id' => $this->octubre->id, 'archivo' => $this->archivoPdf(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['origen' => 'Aplicación', 'carpeta' => 'Calle / Octubre'],
            Auditoria::where('accion', AccionAuditoria::DocumentoCreado->value)->sole()->datos,
        );

        $this->actingAs($this->coordinador)->get(route('auditoria.index', ['q' => 'Acta de ruta']))
            ->assertOk()->assertSee('Calle / Octubre')->assertSee('Aplicación');

        $this->actingAs($this->coordinador)->get(route('auditoria.index', ['q' => 'otra cosa']))
            ->assertDontSee('Acta de ruta');
    }

    public function test_un_envio_por_enlace_queda_con_origen_y_carpeta(): void
    {
        $token = EnlaceCarga::generarToken();
        EnlaceCarga::factory()->conToken($token)->hacia($this->octubre)->create();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'remitente_nombre' => 'Ana Ramírez', 'remitente_email' => 'ana@externa.co',
            'remitente_entidad' => 'Contratista', 'nodo' => 3, 'archivo' => [$this->archivoPdf()],
        ])->assertRedirect(route('envio.confirmacion'));

        $datos = Auditoria::where('accion', AccionAuditoria::RecepcionRecibida->value)->sole()->datos;
        $this->assertSame('Enlace de carga', $datos['origen']);
        $this->assertSame('Calle / Octubre', $datos['carpeta']);
    }

    public function test_los_registros_de_antes_se_completan_y_avisan_si_el_documento_se_movio(): void
    {
        $this->actingAs($this->coordinador)->post(route('documentos.store'), [
            'nombre' => 'Viejo', 'carpeta_id' => $this->octubre->id, 'archivo' => $this->archivoPdf(),
        ]);
        $documento = Documento::sole();

        // Como quedaba antes: sin datos. Y luego se movió.
        Auditoria::query()->update(['datos' => null]);
        $this->actingAs($this->coordinador)->put(route('documentos.update', $documento), [
            'nombre' => 'Viejo', 'carpeta_id' => $this->octubre->carpeta_id,
        ]);

        (require database_path('migrations/2026_10_15_000001_origen_en_auditoria_de_cargas.php'))->up();

        $this->assertSame(
            ['origen' => 'Aplicación', 'carpeta' => 'Calle (carpeta actual; se movió después)'],
            Auditoria::where('accion', AccionAuditoria::DocumentoCreado->value)->sole()->datos,
        );
    }
}
