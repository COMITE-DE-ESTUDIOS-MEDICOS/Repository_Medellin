<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** Nombres sin tope, copiar a otra carpeta y bajar una carpeta entera. */
class CarpetasYCopiasTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function carpeta(string $nombre, ?Carpeta $padre = null): Carpeta
    {
        return Carpeta::create([
            'dependencia_id' => $this->dependencia->id,
            'carpeta_id' => $padre?->id,
            'nombre' => $nombre,
        ]);
    }

    private function subir(string $nombre, ?Carpeta $carpeta = null, string $contenido = 'Acta'): Documento
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'nombre' => $nombre,
                'carpeta_id' => $carpeta?->id,
                'archivo' => $this->archivoPdf('acta.pdf', $contenido),
            ])
            ->assertSessionHasNoErrors();

        return Documento::latest('id')->firstOrFail();
    }

    public function test_un_nombre_de_mas_de_255_caracteres_se_guarda_entero(): void
    {
        $largo = str_repeat('Informe de seguimiento ', 40);

        $documento = $this->subir($largo);

        $this->assertSame(trim($largo), $documento->nombre);
    }

    public function test_copiar_crea_otro_documento_con_su_propio_archivo(): void
    {
        $origen = $this->carpeta('Carpeta 1');
        $destino = $this->carpeta('Carpeta 2');
        $documento = $this->subir('Acta de comité', $origen, 'CONTENIDO ORIGINAL');

        $this->actingAs($this->editor)
            ->post(route('documentos.copiar', $documento), ['carpeta_id' => $destino->id])
            ->assertSessionHasNoErrors();

        $copia = Documento::where('carpeta_id', $destino->id)->with('versionActual')->sole();
        $original = $documento->fresh('versionActual');

        $this->assertSame('Acta de comité', $copia->nombre);
        $this->assertSame($origen->id, $original->carpeta_id);
        $this->assertNotSame($original->versionActual->ruta, $copia->versionActual->ruta);
        $this->assertSame($original->versionActual->hash, $copia->versionActual->hash);
        Storage::disk(config('repositorio.disco'))->assertExists($copia->versionActual->ruta);
    }

    public function test_no_se_copia_a_una_carpeta_de_otra_dependencia(): void
    {
        $documento = $this->subir('Acta');
        $ajena = Carpeta::withoutGlobalScopes()->create([
            'dependencia_id' => Dependencia::factory()->create()->id,
            'nombre' => 'Ajena',
        ]);

        $this->actingAs($this->editor)
            ->post(route('documentos.copiar', $documento), ['carpeta_id' => $ajena->id])
            ->assertSessionHasErrors('carpeta_id');

        $this->assertSame(1, Documento::withoutGlobalScopes()->count());
    }

    public function test_un_lector_no_puede_copiar(): void
    {
        $documento = $this->subir('Acta');
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)
            ->post(route('documentos.copiar', $documento), ['carpeta_id' => null])
            ->assertForbidden();
    }

    public function test_edicion_copia_una_carpeta_con_todo_su_arbol(): void
    {
        $origen = $this->carpeta('Evidencias');
        $sub = $this->carpeta('Octubre', $origen);
        $destino = $this->carpeta('Archivo 2026');

        $this->subir('Acta', $origen);
        $this->subir('Foto', $sub);
        $this->subir('Retirado', $origen)->update(['activo' => false]);

        $this->actingAs($this->editor)
            ->post(route('carpetas.copiar', $origen), ['carpeta_id' => $destino->id])
            ->assertSessionHasNoErrors();

        $copia = Carpeta::where('carpeta_id', $destino->id)->sole();
        $subCopia = Carpeta::where('carpeta_id', $copia->id)->sole();

        $this->assertSame('Evidencias', $copia->nombre);
        $this->assertSame('Octubre', $subCopia->nombre);
        $this->assertSame(['Acta'], Documento::where('carpeta_id', $copia->id)->pluck('nombre')->all());
        $this->assertSame(['Foto'], Documento::where('carpeta_id', $subCopia->id)->pluck('nombre')->all());
        $this->assertSame(2, Documento::where('carpeta_id', $origen->id)->count());
    }

    public function test_no_se_copia_una_carpeta_dentro_de_si_misma(): void
    {
        $origen = $this->carpeta('Evidencias');
        $sub = $this->carpeta('Octubre', $origen);

        $this->actingAs($this->editor)
            ->post(route('carpetas.copiar', $origen), ['carpeta_id' => $sub->id])
            ->assertSessionHasErrors('carpeta_id');

        $this->assertSame(2, Carpeta::count());
    }

    public function test_edicion_mueve_una_carpeta(): void
    {
        $carpeta = $this->carpeta('Evidencias');
        $destino = $this->carpeta('Archivo');

        $this->actingAs($this->editor)
            ->put(route('carpetas.update', $carpeta), ['nombre' => 'Evidencias', 'carpeta_id' => $destino->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($destino->id, $carpeta->fresh()->carpeta_id);
    }

    public function test_edicion_genera_enlaces_y_revoca_solo_los_suyos(): void
    {
        $carpeta = $this->carpeta('Evidencias');
        $otroEditor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->actingAs($this->editor)
            ->post(route('admin.enlaces.store'), ['carpeta_id' => $carpeta->id])
            ->assertSessionHasNoErrors();

        $enlace = \App\Models\EnlaceCarga::sole();

        $this->assertFalse($otroEditor->can('revocar', $enlace));
        $this->assertTrue($this->editor->can('revocar', $enlace));
    }

    public function test_lo_que_cuelga_de_una_carpeta_inactiva_no_sale_ni_buscandolo(): void
    {
        $raiz = $this->carpeta('Evidencias');
        $sub = $this->carpeta('Octubre', $raiz);
        $documento = $this->subir('Acta secreta', $sub);
        $raiz->update(['activa' => false]);

        $this->actingAs($this->editor)
            ->get(route('documentos.index', ['q' => 'secreta']))
            ->assertOk()
            ->assertDontSee('Acta secreta');

        $this->actingAs($this->editor)->get(route('documentos.show', $documento))->assertForbidden();
        $this->actingAs($this->editor)->get(route('documentos.index', ['carpeta' => $sub->uuid]))->assertForbidden();

        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $this->actingAs($coordinador)
            ->get(route('documentos.index', ['q' => 'secreta']))
            ->assertSee('Acta secreta');
    }

    public function test_edicion_reemplaza_el_archivo_sin_ver_el_historial(): void
    {
        $documento = $this->subir('Foto visita', null, 'ORIGINAL');

        $this->actingAs($this->editor)
            ->post(route('documentos.versiones.store', $documento), [
                'archivo' => $this->archivoPdf('nueva.pdf', 'NUEVA'),
            ])
            ->assertSessionHas('exito', 'Archivo reemplazado.');

        $ficha = $this->actingAs($this->editor)->get(route('documentos.show', $documento));
        $ficha->assertSee('Reemplazar archivo')->assertDontSee('Historial de archivos');

        $descarga = $this->actingAs($this->editor)->get(route('documentos.descargar', $documento));
        $this->assertStringContainsString('NUEVA', $descarga->streamedContent());

        // Lo anterior sigue ahí, para quien audita.
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $this->actingAs($coordinador)->get(route('documentos.show', $documento))->assertSee('Historial de archivos (2)');
    }

    public function test_la_carpeta_se_descarga_en_zip_con_subcarpetas_y_sin_lo_inactivo(): void
    {
        $raiz = $this->carpeta('Evidencias');
        $sub = $this->carpeta('Octubre', $raiz);

        $this->subir('Acta', $raiz);
        $this->subir('Acta', $raiz);
        $this->subir('Foto visita', $sub);
        $this->subir('Retirado', $raiz)->update(['activo' => false]);

        $respuesta = $this->actingAs($this->editor)->get(route('carpetas.descargar', $raiz));
        $respuesta->assertOk();

        $zip = new ZipArchive();
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $entradas = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entradas[] = $zip->getNameIndex($i);
        }

        $zip->close();

        $this->assertContains('Evidencias/Acta.pdf', $entradas);
        $this->assertContains('Evidencias/Acta (2).pdf', $entradas);
        $this->assertContains('Evidencias/Octubre/Foto visita.pdf', $entradas);
        $this->assertNotContains('Evidencias/Retirado.pdf', $entradas);
    }

    /** Ni siquiera Administración, que sí ve lo inactivo en pantalla, se lo lleva en el ZIP. */
    public function test_el_zip_no_trae_documentos_ni_carpetas_inactivas_aunque_descargue_administracion(): void
    {
        $raiz = $this->carpeta('Evidencias');
        $inactiva = $this->carpeta('Vieja', $raiz);
        $dentro = $this->carpeta('Dentro', $inactiva);
        $this->subir('Vigente', $raiz);
        $this->subir('Retirado', $raiz)->update(['activo' => false]);
        $this->subir('De la vieja', $inactiva);
        $this->subir('Mas adentro', $dentro);
        $inactiva->update(['activa' => false]);

        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $respuesta = $this->actingAs($admin)->get(route('carpetas.descargar', $raiz))->assertOk();

        $zip = new ZipArchive();
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $entradas = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entradas[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertContains('Evidencias/Vigente.pdf', $entradas);
        $this->assertSame([], array_values(array_filter($entradas, fn ($e) => str_contains($e, 'Retirado') || str_contains($e, 'Vieja'))));
    }
}
