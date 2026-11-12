<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El menú ⋮ de las tarjetas de carpeta del explorador: «Editar» y
 * «Seleccionar», en lugar del check que la tarjeta llevaba a la vista.
 * Lo que hace el menú en el navegador lo llevan menu-opciones.js y
 * lote-explorador.js; aquí se comprueba el marcado del que dependen.
 */
class MenuTarjetaCarpetaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $editor;

    private Carpeta $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->carpeta = Carpeta::create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Informes']);
    }

    private function explorador(User $usuario): DOMXPath
    {
        $html = $this->actingAs($usuario)->get(route('documentos.index'))->assertOk()->getContent();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function unico(DOMXPath $xpath, string $consulta): DOMElement
    {
        $nodos = $xpath->query($consulta);
        $this->assertSame(1, $nodos->length, "Se esperaba un solo resultado para {$consulta}");

        return $nodos->item(0);
    }

    public function test_cada_carpeta_tiene_su_boton_de_opciones_con_editar_y_seleccionar(): void
    {
        $xpath = $this->explorador($this->editor);
        $lote = "lote-carpeta-{$this->carpeta->uuid}";

        $boton = $this->unico($xpath, '//details[@data-menu-opciones]/summary');
        $this->assertSame('Opciones de la carpeta Informes', $boton->getAttribute('aria-label'));
        $this->assertSame('menu', $boton->getAttribute('aria-haspopup'));
        $this->assertSame('false', $boton->getAttribute('aria-expanded'));

        $editar = $this->unico($xpath, '//details[@data-menu-opciones]//*[@role="menu"]/a[@role="menuitem"]');
        $this->assertSame(route('carpetas.edit', $this->carpeta), $editar->getAttribute('href'));
        $this->assertStringContainsString('Editar', $editar->textContent);

        // Las dos etiquetas están en el HTML; el CSS enseña la que toca según el check.
        $seleccionar = $this->unico($xpath, '//details[@data-menu-opciones]//button[@role="menuitem"]');
        $this->assertSame($lote, $seleccionar->getAttribute('data-lote-alternar'));
        $this->assertSame('button', $seleccionar->getAttribute('type'));
        $this->assertStringContainsString('Seleccionar', $seleccionar->textContent);
        $this->assertStringContainsString('Quitar selección', $seleccionar->textContent);
    }

    public function test_la_tarjeta_ya_no_ensena_el_check_pero_lo_sigue_enviando_al_lote(): void
    {
        $xpath = $this->explorador($this->editor);
        $lote = "lote-carpeta-{$this->carpeta->uuid}";

        $check = $this->unico($xpath, "//input[@id='{$lote}']");
        $this->assertSame('checkbox', $check->getAttribute('type'));
        $this->assertTrue($check->hasAttribute('hidden'), 'El check de la carpeta no debe verse.');
        $this->assertSame('carpetas[]', $check->getAttribute('name'));
        $this->assertSame($this->carpeta->uuid, $check->getAttribute('value'));
        $this->assertSame('form-lote', $check->getAttribute('form'));
        $this->assertTrue($check->hasAttribute('data-lote-item'));

        // El estado para el lector de pantalla describe al botón ⋮.
        $estado = $this->unico($xpath, "//*[@data-lote-estado='{$lote}']");
        $this->assertSame($estado->getAttribute('id'), $this->unico($xpath, '//details[@data-menu-opciones]/summary')->getAttribute('aria-describedby'));

        $this->assertSame(0, $xpath->query('//input[@type="checkbox" and @name="carpetas[]" and not(@hidden)]')->length);
    }

    /** El ⋮ va al lado del enlace de la tarjeta, nunca dentro: pulsarlo no navega. */
    public function test_el_menu_no_esta_dentro_del_enlace_que_abre_la_carpeta(): void
    {
        $xpath = $this->explorador($this->editor);

        $this->unico($xpath, '//details[@data-menu-opciones]');
        $this->assertSame(0, $xpath->query('//a//details[@data-menu-opciones]')->length);
        $this->assertSame(0, $xpath->query('//a//input[@data-lote-item]')->length);
    }

    public function test_lectura_no_ve_el_menu(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $xpath = $this->explorador($lector);

        $this->assertSame(0, $xpath->query('//details[@data-menu-opciones]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-lote-alternar]')->length);
    }

    /** Como antes: una carpeta inactiva se puede editar, pero no marcar para el lote. */
    public function test_una_carpeta_inactiva_se_edita_pero_no_se_selecciona(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->carpeta->update(['activa' => false]);

        $xpath = $this->explorador($admin);

        $this->unico($xpath, '//details[@data-menu-opciones]//a[@role="menuitem"]');
        $this->assertSame(0, $xpath->query('//*[@data-lote-alternar]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="carpetas[]"]')->length);
    }

    /** El cambio es solo de las tarjetas: la tabla de documentos sigue con sus checks. */
    public function test_la_tabla_de_documentos_conserva_sus_checks(): void
    {
        $this->actingAs($this->editor)->post(route('documentos.store'), [
            'nombre' => 'Acta A', 'carpeta_id' => null, 'archivo' => $this->archivoPdf('acta-a.pdf'),
        ])->assertSessionHasNoErrors();

        $xpath = $this->explorador($this->editor);

        $this->unico($xpath, '//thead//input[@type="checkbox" and @data-lote-todos]');
        $check = $this->unico($xpath, '//tbody//input[@type="checkbox" and @data-lote-documento]');
        $this->assertFalse($check->hasAttribute('hidden'));
        $this->assertSame('Marcar Acta A', $check->getAttribute('aria-label'));
    }
}
