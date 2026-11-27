<?php

namespace Tests\Feature\UsuariosHc;

use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Models\EnlaceTurno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UHC-10 (cámara sin sello), casos de borde de QA: bordes del valor por defecto de `sello` en
 * la vista y las páginas reales que comparten el campo.
 */
class UsuariosHcFotoCampoCasosTest extends TestCase
{
    use RefreshDatabase;

    /** La etiqueta de apertura del campo número $n (0, 1, …) de la salida. */
    private function apertura(string $html, int $n = 0): string
    {
        preg_match_all('/<div data-campo-archivo[^>]*>/s', $html, $m);

        $this->assertArrayHasKey($n, $m[0], "No hay campo de archivo número {$n} en la salida.");

        return preg_replace('/\s+/', ' ', $m[0][$n]);
    }

    public function test_doc26_uhc10_varios_campos_en_la_misma_vista_no_se_contagian_el_sello(): void
    {
        $html = (string) $this->blade(
            '<x-campo-archivo nombre="a" camara :sello="null" />'
            .'<x-campo-archivo nombre="b" camara />'
            .'<x-campo-archivo nombre="c" camara sello="Turno" />'
            .'<x-campo-archivo nombre="d" camara :sello="null" />'
            .'<x-campo-archivo nombre="e" camara />'
        );

        $this->assertStringNotContainsString('data-sello', $this->apertura($html, 0));
        $this->assertStringContainsString('data-sello="Evidencia"', $this->apertura($html, 1));
        $this->assertStringContainsString('data-sello="Turno"', $this->apertura($html, 2));
        $this->assertStringNotContainsString('data-sello', $this->apertura($html, 3));
        $this->assertStringContainsString('data-sello="Evidencia"', $this->apertura($html, 4));
    }

    public function test_doc26_uhc10_una_variable_sello_de_la_vista_padre_no_entra_al_campo(): void
    {
        // La vista que usa el campo tiene su propio $sello en null: el campo,
        // sin la prop, sigue sellando.
        $html = (string) $this->blade('<x-campo-archivo camara />', ['sello' => null]);
        $this->assertStringContainsString('data-sello="Evidencia"', $this->apertura($html));

        $html = (string) $this->blade('@php($sello = null)<x-campo-archivo camara />');
        $this->assertStringContainsString('data-sello="Evidencia"', $this->apertura($html));
    }

    public function test_doc26_uhc10_sello_no_se_cuela_como_atributo_html(): void
    {
        $html = (string) $this->blade('<x-campo-archivo camara :sello="null" />');
        $this->assertDoesNotMatchRegularExpression('/[\s"]sello=/', $html);

        $html = (string) $this->blade('<x-campo-archivo camara sello="Turno" />');
        $this->assertDoesNotMatchRegularExpression('/[\s"]sello=/', $html);
    }

    public function test_doc26_uhc10_bordes_de_la_prop(): void
    {
        // false no es null: sigue sellando («Evidencia» lo pone el script).
        $this->assertStringContainsString('data-sello=""', $this->apertura((string) $this->blade('<x-campo-archivo camara :sello="false" />')));

        // Sin cámara, :sello="null" no cambia nada.
        $this->assertStringNotContainsString('data-sello', $this->apertura((string) $this->blade('<x-campo-archivo :sello="null" />')));
        $this->assertStringNotContainsString('data-lado-maximo', $this->apertura((string) $this->blade('<x-campo-archivo :sello="null" />')));

        // El texto del sello se escapa.
        $this->assertStringContainsString('data-sello="&lt;b&gt;&quot;x"', $this->apertura((string) $this->blade('<x-campo-archivo camara sello=\'<b>"x\' />')));
    }

    public function test_doc26_uhc10_el_enlace_de_carga_real_sigue_sellando_con_evidencia(): void
    {
        $dependencia = Dependencia::factory()->create();
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $dependencia->id]);
        $token = EnlaceCarga::generarToken();
        EnlaceCarga::factory()->conToken($token)->hacia($carpeta)->create();

        $html = $this->get(route('envio.formulario', ['token' => $token]))->assertOk()->getContent();

        $this->assertStringContainsString('data-sello="Evidencia"', $this->apertura($html));
    }

    public function test_doc26_uhc10_el_turno_real_sigue_sellando_con_turno(): void
    {
        $token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->conToken($token)->create();

        $html = $this->get('/t/'.$token)->assertOk()->getContent();

        $this->assertStringContainsString('data-sello="Turno"', $this->apertura($html));
    }
}
