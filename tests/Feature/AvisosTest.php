<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Aviso;
use App\Models\Dependencia;
use App\Notifications\AvisoDelSistema;
use App\Services\Avisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Avisos por la campana: los de Administración y los de cada versión. */
class AvisosTest extends TestCase
{
    use RefreshDatabase;

    private const CHANGELOG = <<<'MD'
        # Cambios

        ## v2.4

        - Turnos con QR.
        - Reporte para Excel.

        ## v2.3

        - Algo anterior.
        MD;

    public function test_administracion_envia_un_aviso_a_ciertos_roles(): void
    {
        Notification::fake();

        $dependencia = Dependencia::factory()->create();
        $admin = $this->usuarioCon(RolDependencia::Administracion, $dependencia);
        $editor = $this->usuarioCon(RolDependencia::Edicion, $dependencia);
        $lector = $this->usuarioCon(RolDependencia::Lectura, $dependencia);

        $this->actingAs($admin)
            ->post(route('admin.avisos.store'), [
                'titulo' => 'Mantenimiento', 'mensaje' => 'El sábado no habrá servicio.', 'roles' => ['edicion'],
            ])
            ->assertSessionHas('exito', 'Aviso enviado a 1 persona.');

        Notification::assertSentTo($editor, AvisoDelSistema::class);
        Notification::assertNotSentTo($lector, AvisoDelSistema::class);
        $this->assertSame($admin->id, Aviso::sole()->enviado_por);
    }

    public function test_sin_roles_va_para_todos_los_de_la_dependencia_y_no_para_otra(): void
    {
        Notification::fake();

        $dependencia = Dependencia::factory()->create();
        $admin = $this->usuarioCon(RolDependencia::Administracion, $dependencia);
        $lector = $this->usuarioCon(RolDependencia::Lectura, $dependencia);
        $ajeno = $this->usuarioCon(RolDependencia::Lectura, Dependencia::factory()->create());

        $this->actingAs($admin)->post(route('admin.avisos.store'), ['titulo' => 'Hola', 'mensaje' => 'Para todos']);

        Notification::assertSentTo([$admin, $lector], AvisoDelSistema::class);
        Notification::assertNotSentTo($ajeno, AvisoDelSistema::class);
    }

    public function test_coordinacion_no_envia_avisos(): void
    {
        $dependencia = Dependencia::factory()->create();
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $dependencia);

        $this->actingAs($coordinador)->get(route('admin.avisos.index'))->assertForbidden();
        $this->actingAs($coordinador)->post(route('admin.avisos.store'), ['titulo' => 'x', 'mensaje' => 'y'])->assertForbidden();
    }

    public function test_la_seccion_de_la_version_se_lee_del_changelog(): void
    {
        $avisos = app(Avisos::class);

        $this->assertSame('Turnos con QR. · Reporte para Excel.', $avisos->seccionDe('v2.4', self::CHANGELOG));
        $this->assertNull($avisos->seccionDe('v2.9', self::CHANGELOG));
        $this->assertNull($avisos->seccionDe('v2.', self::CHANGELOG), 'No confunde v2. con v2.4.');
    }

    public function test_cada_version_se_anuncia_una_sola_vez_y_las_beta_no(): void
    {
        Notification::fake();
        $usuario = $this->usuarioCon(RolDependencia::Lectura, Dependencia::factory()->create());
        $avisos = app(Avisos::class);

        $this->assertNull($avisos->anunciarVersion('v2.4-beta.3', self::CHANGELOG));
        $this->assertNull($avisos->anunciarVersion('v2.x-dev', self::CHANGELOG));

        $aviso = $avisos->anunciarVersion('v2.4', self::CHANGELOG);
        $this->assertSame('Documenta se actualizó a v2.4', $aviso->titulo);
        Notification::assertSentTo($usuario, AvisoDelSistema::class);

        $this->assertNull($avisos->anunciarVersion('v2.4', self::CHANGELOG), 'Desplegar dos veces no avisa dos veces.');
    }

    public function test_el_menu_muestra_la_version(): void
    {
        config(['app.version' => 'v2.7']);
        $usuario = $this->usuarioCon(RolDependencia::Lectura, Dependencia::factory()->create());

        $this->actingAs($usuario)->get(route('documentos.index'))->assertSee('v2.7');
    }
}
