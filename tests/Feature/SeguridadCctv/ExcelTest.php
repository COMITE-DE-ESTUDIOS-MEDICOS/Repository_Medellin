<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\RolDependencia;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** DOC-33 (CCTV-12): el Excel con las hojas «Casos» y «Resumen». */
class ExcelTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
    }

    private function usuario(RolDependencia $rol, Componente $componente, bool $casilla = false): User
    {
        $usuario = $this->usuarioCon($rol, $this->dependencia, $componente);
        $usuario->dependencias()->updateExistingPivot($this->dependencia->id, ['responsable_cctv' => $casilla]);

        return $usuario->unsetRelation('dependencias');
    }

    private function conFoto(CasoCctv $caso): CasoCctv
    {
        ob_start();
        imagepng(imagecreatetruecolor(8, 8));
        $png = ob_get_clean();

        $documento = Documento::factory()->create(['dependencia_id' => $this->dependencia->id]);
        DocumentoVersion::factory()->conArchivoEnDisco($png)->create(['documento_id' => $documento->id]);
        $caso->update(['soporte_documento_id' => $documento->id]);

        return $caso;
    }

    /** @return array{libro: string, zip: ZipArchive} */
    private function descargar(User $usuario, array $consulta = []): array
    {
        $respuesta = $this->actingAs($usuario)->get(route('admin.turnos.cctv.exportar', $consulta))->assertOk();
        $ruta = $respuesta->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($ruta);

        return ['libro' => $zip->getFromName('xl/workbook.xml').$zip->getFromName('xl/worksheets/sheet1.xml').$zip->getFromName('xl/worksheets/sheet2.xml'), 'zip' => $zip];
    }

    private function fotos(ZipArchive $zip): int
    {
        return count(array_filter(array_map(fn (int $i): string => $zip->getNameIndex($i), range(0, $zip->numFiles - 1)), fn (string $n): bool => str_starts_with($n, 'xl/media/')));
    }

    public function test_doc33_ca17_ca14_el_excel_trae_las_hojas_casos_y_resumen_sin_anulados(): void
    {
        $this->conFoto(CasoCctv::factory()->de($this->calle)->atendido(30)->create(['numero' => 7]));
        CasoCctv::factory()->de($this->calle)->create(['numero' => 8, 'estado' => CasoCctv::ANULADO]);

        $excel = $this->descargar($this->usuario(RolDependencia::Coordinacion, $this->calle));

        $this->assertStringContainsString('name="Casos"', $excel['libro']);
        $this->assertStringContainsString('name="Resumen"', $excel['libro']);
        $this->assertStringContainsString('CCTV-007', $excel['libro']);
        $this->assertStringNotContainsString('CCTV-008', $excel['libro']);
        $this->assertStringContainsString('Casos del rango', $excel['libro']);
        $this->assertStringContainsString('Mediana', $excel['libro']);
        $this->assertSame(1, $this->fotos($excel['zip']));
    }

    public function test_doc33_ca19_edicion_sin_casilla_recibe_el_excel_sin_fotos_y_con_casilla_con_fotos(): void
    {
        $this->conFoto(CasoCctv::factory()->de($this->calle)->atendido()->create(['numero' => 7]));

        $sin = $this->descargar($this->usuario(RolDependencia::Edicion, $this->calle));
        $this->assertStringContainsString('CCTV-007', $sin['libro']);
        $this->assertSame(0, $this->fotos($sin['zip']));

        $con = $this->descargar($this->usuario(RolDependencia::Edicion, $this->calle, true));
        $this->assertSame(1, $this->fotos($con['zip']));
    }

    public function test_doc33_ca01_ca02_sin_modulo_da_prohibido_y_lo_de_otro_componente_no_entra(): void
    {
        CasoCctv::factory()->de($this->calle)->create(['numero' => 7]);
        CasoCctv::factory()->de($this->basica)->create(['numero' => 9]);

        $this->actingAs($this->usuario(RolDependencia::Coordinacion, $this->basica))->get(route('admin.turnos.cctv.exportar'))->assertForbidden();
        $this->actingAs($this->usuario(RolDependencia::Lectura, $this->calle))->get(route('admin.turnos.cctv.exportar'))->assertForbidden();

        $excel = $this->descargar($this->usuario(RolDependencia::Edicion, $this->calle));
        $this->assertStringContainsString('CCTV-007', $excel['libro']);
        $this->assertStringNotContainsString('CCTV-009', $excel['libro']);
    }

    public function test_doc33_ca17_el_excel_respeta_el_rango(): void
    {
        CasoCctv::factory()->de($this->calle)->create(['numero' => 7, 'requerido_at' => now()->subMonths(3)]);

        $usuario = $this->usuario(RolDependencia::Edicion, $this->calle);
        $this->assertStringNotContainsString('CCTV-007', $this->descargar($usuario)['libro']);

        $desde = now()->subMonths(3)->toDateString();
        $this->assertStringContainsString('CCTV-007', $this->descargar($usuario, ['desde' => $desde, 'hasta' => $desde])['libro']);
    }
}
