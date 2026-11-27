<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Inactivar no puede ser un viaje de ida. Si administración esconde una
 * carpeta o un documento y luego no encuentra por dónde devolverlos, la
 * inactivación se comporta como un borrado, que es justo lo que el proyecto
 * se propuso evitar.
 */
class ReactivacionTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
    }

    private function carpetaInactiva(string $nombre = 'Actas de 2025'): Carpeta
    {
        return Carpeta::factory()->inactiva()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => $nombre,
        ]);
    }

    private function documentoInactivo(string $nombre = 'Acta guardada'): Documento
    {
        $documento = Documento::factory()->inactivo()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => $nombre,
        ]);

        DocumentoVersion::factory()
            ->conArchivoEnDisco('contenido')
            ->create(['documento_id' => $documento->id]);

        return $documento;
    }

    /*
    |--------------------------------------------------------------------------
    | Carpetas
    |--------------------------------------------------------------------------
    */

    public function test_administracion_reactiva_una_carpeta_inactiva(): void
    {
        $carpeta = $this->carpetaInactiva();

        $this->actingAs($this->admin)
            ->patch(route('carpetas.reactivar', $carpeta))
            ->assertRedirect(route('documentos.index', ['carpeta' => $carpeta->uuid]));

        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'activa' => true]);
    }

    public function test_ni_lectura_ni_edicion_pueden_reactivar_una_carpeta(): void
    {
        $carpeta = $this->carpetaInactiva();

        foreach ([RolDependencia::Lectura, RolDependencia::Edicion] as $rol) {
            $this->actingAs($this->usuarioCon($rol, $this->dependencia))
                ->patch(route('carpetas.reactivar', $carpeta))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'activa' => false]);
    }

    public function test_no_se_puede_reactivar_una_carpeta_de_otra_dependencia(): void
    {
        $otra = Dependencia::factory()->create();
        $ajena = Carpeta::factory()->inactiva()->create(['dependencia_id' => $otra->id]);

        $this->actingAs($this->admin)
            ->patch(route('carpetas.reactivar', $ajena))
            ->assertNotFound();

        $this->assertDatabaseHas('carpetas', ['id' => $ajena->id, 'activa' => false]);
    }

    public function test_reactivar_una_carpeta_queda_en_la_auditoria(): void
    {
        $carpeta = $this->carpetaInactiva();

        $this->actingAs($this->admin)->patch(route('carpetas.reactivar', $carpeta));

        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::CarpetaReactivada->value,
            'user_id' => $this->admin->id,
            'dependencia_id' => $this->dependencia->id,
            'auditable_type' => Carpeta::class,
            'auditable_id' => $carpeta->id,
            'ip' => '127.0.0.1',
        ]);
    }

    /** La garantía de fondo: al reactivarla, los demás vuelven a verla. */
    public function test_al_reactivar_la_carpeta_vuelve_a_verse_para_lectura_y_edicion(): void
    {
        $carpeta = $this->carpetaInactiva('Actas de 2025');
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertDontSee('Actas de 2025');

        $this->actingAs($this->admin)->patch(route('carpetas.reactivar', $carpeta));

        $this->actingAs($lector)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Actas de 2025');
    }

    /*
    |--------------------------------------------------------------------------
    | El camino completo: inactivar y volver, sin callejones sin salida
    |--------------------------------------------------------------------------
    */

    public function test_tras_inactivar_una_carpeta_administracion_encuentra_como_devolverla(): void
    {
        $carpeta = Carpeta::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Actas de 2025',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('carpetas.inactivar', $carpeta))
            ->assertRedirect();

        $reactivar = route('carpetas.reactivar', $carpeta);

        // El botón está donde te deja la inactivación...
        $this->actingAs($this->admin)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Actas de 2025')
            ->assertSee($reactivar, false);

        // ...y también en la pantalla de editar la carpeta.
        $this->actingAs($this->admin)
            ->get(route('carpetas.edit', $carpeta))
            ->assertOk()
            ->assertSee($reactivar, false)
            ->assertSee('Reactivar carpeta');
    }

    public function test_tras_inactivar_un_documento_el_boton_esta_en_el_listado_y_en_la_ficha(): void
    {
        $documento = $this->documentoInactivo('Acta guardada');
        $reactivar = route('documentos.reactivar', $documento);

        $this->actingAs($this->admin)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Acta guardada')
            ->assertSee($reactivar, false);

        $this->actingAs($this->admin)
            ->get(route('documentos.show', $documento))
            ->assertOk()
            ->assertSee($reactivar, false)
            ->assertSee('Reactivar documento');
    }

    /** Un documento activo no ofrece reactivar: no habría nada que devolver. */
    public function test_lo_que_esta_activo_no_ofrece_el_boton_de_reactivar(): void
    {
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);

        $documento = Documento::factory()->create(['dependencia_id' => $this->dependencia->id]);
        DocumentoVersion::factory()->conArchivoEnDisco()->create(['documento_id' => $documento->id]);

        $this->actingAs($this->admin)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertDontSee(route('carpetas.reactivar', $carpeta), false)
            ->assertDontSee(route('documentos.reactivar', $documento), false);
    }
}
