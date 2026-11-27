<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Auditoria;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DOC-33 (CCTV-04): la ficha, cerrar, anular y abrir imágenes. */
class FichaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
    }

    private function usuario(RolDependencia $rol, bool $casilla = false): User
    {
        $usuario = $this->usuarioCon($rol, $this->dependencia, $this->calle);
        $usuario->dependencias()->updateExistingPivot($this->dependencia->id, ['responsable_cctv' => $casilla]);

        return $usuario->unsetRelation('dependencias');
    }

    private function conSoporte(CasoCctv $caso): CasoCctv
    {
        $documento = Documento::factory()->create(['dependencia_id' => $this->dependencia->id]);
        DocumentoVersion::factory()->conArchivoEnDisco('imagen')->create(['documento_id' => $documento->id]);
        $caso->update(['soporte_documento_id' => $documento->id]);

        return $caso;
    }

    private function auditados(AccionAuditoria $accion): int
    {
        return Auditoria::where('accion', $accion->value)->count();
    }

    public function test_doc33_ca01_ca02_la_ficha_exige_modulo_y_componente(): void
    {
        $caso = CasoCctv::factory()->de($this->calle)->create();
        $basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
        $otro = CasoCctv::factory()->de($basica)->create();
        $admin = $this->usuario(RolDependencia::Administracion);

        $this->actingAs($admin)->get(route('admin.turnos.cctv.show', $caso))->assertOk()->assertSee($caso->codigo());
        $this->actingAs($admin)->get(route('admin.turnos.cctv.show', $otro))->assertNotFound();

        $this->actingAs($this->usuario(RolDependencia::Lectura))->get(route('admin.turnos.cctv.show', $caso))->assertForbidden();

        // Parado en un componente sin el módulo: «No tienes permiso» o «No encontrado», nunca la ficha.
        $estado = $this->withSession(['componente_id' => $basica->id])
            ->actingAs($admin)->get(route('admin.turnos.cctv.show', $otro))->getStatusCode();
        $this->assertContains($estado, [403, 404]);
    }

    public function test_doc33_ca10_la_ficha_muestra_el_tiempo_sin_vencido(): void
    {
        $caso = CasoCctv::factory()->de($this->calle)->atendido(85)->create(['nombre' => 'Ana Campo', 'cedula' => '123']);

        $this->actingAs($this->usuario(RolDependencia::Edicion))
            ->get(route('admin.turnos.cctv.show', $caso))
            ->assertOk()
            ->assertSee('1 h 25 min')
            ->assertSee('Ana Campo')
            ->assertSee($caso->asignado->nombre)
            ->assertDontSee('vencido');
    }

    public function test_doc33_ca12_ca15_cerrar_solo_atendido_y_solo_responsables(): void
    {
        $atendido = CasoCctv::factory()->de($this->calle)->atendido()->create();
        $abierto = CasoCctv::factory()->de($this->calle)->create();
        $conCasilla = $this->usuario(RolDependencia::Edicion, true);

        $this->actingAs($this->usuario(RolDependencia::Edicion))->patch(route('admin.turnos.cctv.cerrar', $atendido))->assertForbidden();
        $this->actingAs($conCasilla)->patch(route('admin.turnos.cctv.cerrar', $abierto))->assertForbidden();

        $this->actingAs($conCasilla)->patch(route('admin.turnos.cctv.cerrar', $atendido))->assertRedirect();

        $atendido->refresh();
        $this->assertSame(CasoCctv::CERRADO, $atendido->estado);
        $this->assertSame($conCasilla->id, $atendido->cerrado_por);
        $this->assertNotNull($atendido->cerrado_at);
        $this->assertSame(1, $this->auditados(AccionAuditoria::CasoCctvCerrado));
    }

    public function test_doc33_ca14_ca15_anular_exige_motivo_y_coordinacion_y_no_borra(): void
    {
        $caso = CasoCctv::factory()->de($this->calle)->create();

        $this->actingAs($this->usuario(RolDependencia::Edicion, true))
            ->patch(route('admin.turnos.cctv.anular', $caso), ['motivo' => 'x'])->assertForbidden();

        $coordinacion = $this->usuario(RolDependencia::Coordinacion);
        $this->actingAs($coordinacion)->patch(route('admin.turnos.cctv.anular', $caso))->assertSessionHasErrors('motivo');
        $this->assertSame(CasoCctv::ABIERTO, $caso->fresh()->estado);

        $this->actingAs($coordinacion)->patch(route('admin.turnos.cctv.anular', $caso), ['motivo' => 'Duplicado'])->assertRedirect();

        $caso->refresh();
        $this->assertSame(CasoCctv::ANULADO, $caso->estado);
        $this->assertSame('Duplicado', $caso->motivo_anulacion);
        $this->assertSame($coordinacion->id, $caso->anulado_por);
        $this->assertSame(1, $this->auditados(AccionAuditoria::CasoCctvAnulado));
        $this->actingAs($coordinacion)->patch(route('admin.turnos.cctv.anular', $caso), ['motivo' => 'otra vez'])->assertForbidden();
    }

    public function test_doc33_ca15_ca19_el_archivo_lo_abren_solo_responsables_y_queda_auditado(): void
    {
        $caso = $this->conSoporte(CasoCctv::factory()->de($this->calle)->create());
        $ruta = route('admin.turnos.cctv.archivo', [$caso, 'soporte']);

        $edicion = $this->usuario(RolDependencia::Edicion);
        $this->actingAs($edicion)->get(route('admin.turnos.cctv.show', $caso))->assertOk()->assertDontSee($ruta);
        $this->actingAs($edicion)->get($ruta)->assertForbidden();
        $this->assertSame(0, $this->auditados(AccionAuditoria::CasoCctvArchivoAbierto));

        $conCasilla = $this->usuario(RolDependencia::Edicion, true);
        $this->actingAs($conCasilla)->get(route('admin.turnos.cctv.show', $caso))->assertSee($ruta, false);
        $respuesta = $this->actingAs($conCasilla)->get($ruta)->assertOk();
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertSame(1, $this->auditados(AccionAuditoria::CasoCctvArchivoAbierto));

        $this->actingAs($conCasilla)->get(route('admin.turnos.cctv.archivo', [$caso, 'evidencia1']))->assertNotFound();
        $this->actingAs($conCasilla)->get(route('admin.turnos.cctv.archivo', [$caso, 'otro']))->assertNotFound();
    }
}
