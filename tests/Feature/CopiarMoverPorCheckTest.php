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

/** Copiar y mover varios a la vez, marcando con el check del explorador. */
class CopiarMoverPorCheckTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $editor;

    private Carpeta $origen;

    private Carpeta $destino;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->origen = $this->carpeta('Carpeta 1');
        $this->destino = $this->carpeta('Carpeta 2');
    }

    private function carpeta(string $nombre, ?Carpeta $padre = null): Carpeta
    {
        return Carpeta::create(['dependencia_id' => $this->dependencia->id, 'carpeta_id' => $padre?->id, 'nombre' => $nombre]);
    }

    private function subir(string $nombre, Carpeta $carpeta): Documento
    {
        $this->actingAs($this->editor)->post(route('documentos.store'), [
            'nombre' => $nombre, 'carpeta_id' => $carpeta->id, 'archivo' => $this->archivoPdf("{$nombre}.pdf", $nombre),
        ])->assertSessionHasNoErrors();

        return Documento::latest('id')->firstOrFail();
    }

    private function lote(string $accion, array $documentos = [], array $carpetas = [], ?Carpeta $destino = null)
    {
        return $this->actingAs($this->editor)->post(route('documentos.lote'), [
            'accion' => $accion,
            'documentos' => array_map(fn ($d) => $d->uuid, $documentos),
            'carpetas' => array_map(fn ($c) => $c->uuid, $carpetas),
            'destino' => ($destino ?? $this->destino)->id,
        ]);
    }

    public function test_copiar_varios_documentos_y_una_carpeta_de_una_vez(): void
    {
        $a = $this->subir('Acta A', $this->origen);
        $b = $this->subir('Acta B', $this->origen);
        $sub = $this->carpeta('Octubre', $this->origen);
        $this->subir('Foto', $sub);

        $this->lote('copiar', [$a, $b], [$sub])
            ->assertSessionHas('exito', 'Se copiaron 2 documentos y 1 carpeta a «Carpeta 2».');

        $this->assertSame(['Acta A', 'Acta B'], Documento::where('carpeta_id', $this->destino->id)->orderBy('nombre')->pluck('nombre')->all());
        $copiaSub = Carpeta::where('carpeta_id', $this->destino->id)->where('nombre', 'Octubre')->sole();
        $this->assertSame(1, $copiaSub->documentos()->count());
        $this->assertSame(2, Documento::where('carpeta_id', $this->origen->id)->count(), 'Los originales siguen donde estaban.');
    }

    public function test_mover_cambia_la_carpeta_sin_duplicar(): void
    {
        $a = $this->subir('Acta A', $this->origen);
        $sub = $this->carpeta('Octubre', $this->origen);

        $this->lote('mover', [$a], [$sub])
            ->assertSessionHas('exito', 'Se movieron 1 documento y 1 carpeta a «Carpeta 2».');

        $this->assertSame($this->destino->id, $a->fresh()->carpeta_id);
        $this->assertSame($this->destino->id, $sub->fresh()->carpeta_id);
        $this->assertSame(1, Documento::count());
        $this->assertDatabaseHas('auditorias', ['accion' => 'documento.actualizado', 'auditable_id' => $a->id]);
    }

    public function test_mover_a_donde_ya_esta_no_hace_nada(): void
    {
        $a = $this->subir('Acta A', $this->destino);

        $this->lote('mover', [$a])->assertSessionHas('exito', 'Nada que mover: ya estaba en «Carpeta 2».');
    }

    public function test_una_carpeta_no_se_lleva_dentro_de_si_misma(): void
    {
        $sub = $this->carpeta('Octubre', $this->origen);

        $this->lote('mover', [], [$this->origen], $sub)->assertSessionHasErrors('destino');
        $this->assertNull($this->origen->fresh()->carpeta_id);
    }

    public function test_sin_marcar_nada_avisa(): void
    {
        $this->lote('copiar')->assertSessionHasErrors('documentos');
    }

    /**
     * Las carpetas ya no tienen check a la vista: se marcan con «Seleccionar»
     * en su menú ⋮. Se sigue el marcado tal cual: la opción apunta a un check
     * escondido, y ese check (con el de un documento) es lo que envía el lote.
     */
    public function test_lo_que_marca_el_menu_de_la_tarjeta_es_lo_que_envia_el_lote(): void
    {
        $a = $this->subir('Acta A', $this->origen);
        $sub = $this->carpeta('Octubre', $this->origen);
        $this->subir('Foto', $sub);

        $html = $this->actingAs($this->editor)
            ->get(route('documentos.index', ['carpeta' => $this->origen->uuid]))
            ->assertOk()
            ->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $opcion = $xpath->query('//details[@data-menu-opciones]//button[@data-lote-alternar]')->item(0);
        $checkCarpeta = $dom->getElementById($opcion->getAttribute('data-lote-alternar'));
        $checkDocumento = $xpath->query('//input[@data-lote-documento]')->item(0);
        $formulario = $dom->getElementById('form-lote');

        $this->assertSame('form-lote', $checkCarpeta->getAttribute('form'));
        $this->assertSame('form-lote', $checkDocumento->getAttribute('form'));

        $this->actingAs($this->editor)->post($formulario->getAttribute('action'), [
            'accion' => 'copiar',
            str_replace('[]', '', $checkCarpeta->getAttribute('name')) => [$checkCarpeta->getAttribute('value')],
            str_replace('[]', '', $checkDocumento->getAttribute('name')) => [$checkDocumento->getAttribute('value')],
            'destino' => $this->destino->id,
        ])->assertSessionHas('exito', 'Se copiaron 1 documento y 1 carpeta a «Carpeta 2».');

        $this->assertSame(['Acta A'], Documento::where('carpeta_id', $this->destino->id)->pluck('nombre')->all());
        $this->assertSame(1, Carpeta::where('carpeta_id', $this->destino->id)->where('nombre', 'Octubre')->sole()->documentos()->count());
        $this->assertSame($this->origen->id, $a->fresh()->carpeta_id, 'Copiar no mueve el original.');
    }

    public function test_lectura_no_copia_ni_mueve_y_no_ve_los_checks(): void
    {
        $a = $this->subir('Acta A', $this->origen);
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)->post(route('documentos.lote'), [
            'accion' => 'mover', 'documentos' => [$a->uuid], 'destino' => $this->destino->id,
        ])->assertForbidden();

        $this->actingAs($lector)->get(route('documentos.index', ['carpeta' => $this->origen->uuid]))
            ->assertDontSee('data-lote-item', false);
        $this->actingAs($this->editor)->get(route('documentos.index', ['carpeta' => $this->origen->uuid]))
            ->assertSee('data-lote-item', false);
    }

    public function test_un_documento_de_otra_dependencia_no_se_cuela(): void
    {
        $ajena = Dependencia::factory()->create();
        $carpetaAjena = Carpeta::create(['dependencia_id' => $ajena->id, 'nombre' => 'Ajena']);
        $docAjeno = Documento::factory()->en($carpetaAjena)->create(['dependencia_id' => $ajena->id]);

        $this->lote('mover', [$docAjeno])->assertSessionHasErrors('documentos');
        $this->assertSame($carpetaAjena->id, Documento::withoutGlobalScopes()->find($docAjeno->id)->carpeta_id);
    }

    public function test_los_destinos_muestran_la_ruta_completa(): void
    {
        $this->carpeta('Octubre', $this->origen);
        $this->carpeta('Octubre', $this->destino);

        $this->actingAs($this->editor)->get(route('documentos.index'))
            ->assertSee('Carpeta 1 / Octubre')
            ->assertSee('Carpeta 2 / Octubre');
    }
}
