<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La otra garantía crítica: una dependencia no ve a la otra. Se prueba
 * siempre con un administrador, porque el rol alto es justo el que no debe
 * servir de llave maestra fuera de su propia dependencia.
 */
class AislamientoTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $alfa;

    private Dependencia $beta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->alfa = Dependencia::factory()->create(['nombre' => 'Alfa', 'slug' => 'alfa']);
        $this->beta = Dependencia::factory()->create(['nombre' => 'Beta', 'slug' => 'beta']);
    }

    private function documentoDe(Dependencia $dependencia, string $nombre): Documento
    {
        $documento = Documento::factory()->create([
            'dependencia_id' => $dependencia->id,
            'nombre' => $nombre,
        ]);

        DocumentoVersion::factory()
            ->conArchivoEnDisco('contenido de '.$nombre)
            ->create(['documento_id' => $documento->id]);

        return $documento;
    }

    public function test_el_listado_solo_muestra_documentos_de_la_dependencia_activa(): void
    {
        $this->documentoDe($this->alfa, 'Papel de Alfa');
        $this->documentoDe($this->beta, 'Papel de Beta');

        $this->actingAs($this->usuarioCon(RolDependencia::Administracion, $this->alfa))
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Papel de Alfa')
            ->assertDontSee('Papel de Beta');
    }

    public function test_el_buscador_tampoco_alcanza_la_otra_dependencia(): void
    {
        $this->documentoDe($this->beta, 'Papel de Beta');

        $this->actingAs($this->usuarioCon(RolDependencia::Administracion, $this->alfa))
            ->get(route('documentos.index', ['q' => 'Papel']))
            ->assertOk()
            ->assertDontSee('Papel de Beta');
    }

    public function test_ni_administrando_se_abre_un_documento_de_otra_dependencia(): void
    {
        $secreto = $this->documentoDe($this->beta, 'Historia reservada de Beta');
        $version = $secreto->versiones()->firstOrFail();

        $adminAlfa = $this->usuarioCon(RolDependencia::Administracion, $this->alfa);
        $prohibido = 'Historia reservada de Beta';

        $this->assertNoDejaVer(
            $this->actingAs($adminAlfa)->get(route('documentos.show', $secreto)),
            $prohibido,
            'Abrir la ficha',
        );

        $this->assertNoDejaVer(
            $this->actingAs($adminAlfa)->get(route('documentos.descargar', $secreto)),
            $prohibido,
            'Descargar',
        );

        $this->assertNoDejaVer(
            $this->actingAs($adminAlfa)->get(route('documentos.versiones.descargar', [$secreto, $version])),
            $prohibido,
            'Descargar una versión concreta',
        );

        $this->assertNoDejaVer(
            $this->actingAs($adminAlfa)->get(route('documentos.previsualizar', $secreto)),
            $prohibido,
            'Previsualizar',
        );

        $this->assertNoDejaVer(
            $this->actingAs($adminAlfa)->get(route('documentos.edit', $secreto)),
            $prohibido,
            'Editar',
        );

        $this->assertNoDejaVer(
            $this->actingAs($adminAlfa)->patch(route('documentos.inactivar', $secreto), ['motivo' => 'porque puedo']),
            $prohibido,
            'Inactivar',
        );

        $this->assertDatabaseHas('documentos', ['id' => $secreto->id, 'activo' => true]);
    }

    public function test_las_carpetas_de_otra_dependencia_no_aparecen_al_subir_un_documento(): void
    {
        Carpeta::factory()->create(['dependencia_id' => $this->alfa->id, 'nombre' => 'Actas de Alfa']);
        $carpetaBeta = Carpeta::factory()->create(['dependencia_id' => $this->beta->id, 'nombre' => 'Actas de Beta']);

        $editorAlfa = $this->usuarioCon(RolDependencia::Edicion, $this->alfa);

        $this->actingAs($editorAlfa)
            ->get(route('documentos.create'))
            ->assertOk()
            ->assertSee('Actas de Alfa')
            ->assertDontSee('Actas de Beta');

        $this->actingAs($editorAlfa)
            ->get(route('carpetas.create'))
            ->assertOk()
            ->assertSee('Actas de Alfa')
            ->assertDontSee('Actas de Beta');

        // Y si alguien manda el id a mano, la validación tampoco lo acepta.
        $this->actingAs($editorAlfa)
            ->post(route('documentos.store'), [
                'nombre' => 'Intruso',
                'carpeta_id' => $carpetaBeta->id,
                'archivo' => $this->archivoPdf(),
            ])
            ->assertSessionHasErrors('carpeta_id');

        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_un_administrador_no_ve_a_los_usuarios_de_la_otra_dependencia(): void
    {
        $this->usuarioCon(RolDependencia::Lectura, $this->alfa);
        $deBeta = $this->usuarioCon(RolDependencia::Lectura, $this->beta);

        $this->actingAs($this->usuarioCon(RolDependencia::Administracion, $this->alfa))
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertDontSee($deBeta->email);
    }

    public function test_quien_tiene_dos_dependencias_ve_una_u_otra_segun_la_activa(): void
    {
        $this->documentoDe($this->alfa, 'Papel de Alfa');
        $this->documentoDe($this->beta, 'Papel de Beta');

        $usuario = $this->usuarioCon(RolDependencia::Lectura, $this->alfa);
        $this->darRol($usuario, RolDependencia::Edicion, $this->beta);

        // Arranca en Alfa: es la primera por nombre.
        $this->actingAs($usuario)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Papel de Alfa')
            ->assertDontSee('Papel de Beta');

        $this->actingAs($usuario)
            ->put(route('dependencia.cambiar', $this->beta))
            ->assertRedirect(route('inicio'));

        $this->actingAs($usuario)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Papel de Beta')
            ->assertDontSee('Papel de Alfa');
    }

    public function test_la_misma_persona_puede_tener_roles_distintos_en_cada_dependencia(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura, $this->alfa);
        $this->darRol($usuario, RolDependencia::Edicion, $this->beta);

        // En Alfa solo lee: no puede crear carpetas.
        $this->actingAs($usuario)
            ->post(route('carpetas.store'), ['nombre' => 'No debería crearse'])
            ->assertForbidden();

        $this->actingAs($usuario)->put(route('dependencia.cambiar', $this->beta));

        // En Beta edita: la misma acción sí pasa.
        $this->actingAs($usuario)
            ->post(route('carpetas.store'), ['nombre' => 'Carpeta de Beta'])
            ->assertRedirect();

        $this->assertDatabaseHas('carpetas', [
            'nombre' => 'Carpeta de Beta',
            'dependencia_id' => $this->beta->id,
        ]);

        $this->assertDatabaseMissing('carpetas', ['nombre' => 'No debería crearse']);
    }

    public function test_no_se_puede_cambiar_a_una_dependencia_no_asignada(): void
    {
        $soloAlfa = $this->usuarioCon(RolDependencia::Administracion, $this->alfa);
        $this->documentoDe($this->beta, 'Papel de Beta');

        $this->actingAs($soloAlfa)
            ->put(route('dependencia.cambiar', $this->beta))
            ->assertForbidden();

        // Y sigue viendo lo suyo, no lo de Beta.
        $this->actingAs($soloAlfa)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertDontSee('Papel de Beta');
    }
}
