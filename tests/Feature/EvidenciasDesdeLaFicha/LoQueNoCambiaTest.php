<?php

namespace Tests\Feature\EvidenciasDesdeLaFicha;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

/** DOC-34 (EVF-03): lo que la carga desde la ficha no cambia: el cierre a mano (CA-12) y la versión vigente (CA-11). */
class LoQueNoCambiaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
    }

    private function pendiente(): Derivacion
    {
        return Derivacion::create([
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->calle->id, 'numero' => 1,
            'remitente' => 'alcaldia', 'entidad' => 'Alcaldía', 'descripcion' => 'Requiere traslado.',
            'ubicacion' => 'Cra. 65 #97-121', 'estado' => Derivacion::PENDIENTE,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
        ]);
    }

    /** PNG de un solo color y tamaño propio: cada imagen se distingue por sus bytes. */
    private function png(int $lado): string
    {
        ob_start();
        imagepng(imagecreatetruecolor($lado, $lado));

        return ob_get_clean();
    }

    /** Un documento de evidencia con la versión 1 (60 px) y la 2 (120 px), subidas por `guardarVersion`. */
    private function documentoConDosVersiones(User $usuario, string $nombre): Documento
    {
        $this->actingAs($usuario);
        $servicio = app(AlmacenamientoDocumentos::class);
        $documento = Documento::create(['dependencia_id' => $this->dependencia->id, 'nombre' => $nombre, 'fecha_documento' => now()]);
        $servicio->guardarVersion($documento, UploadedFile::fake()->createWithContent('uno.png', $this->png(60)));
        $servicio->guardarVersion($documento, UploadedFile::fake()->createWithContent('dos.png', $this->png(120)), 'Cambiada');

        return $documento;
    }

    private function miniatura(int $lado): string
    {
        return Xlsx::miniatura($this->png($lado))['imagen'];
    }

    public function test_doc34_ca12_solo_el_superadministrador_cierra_a_mano_por_contingencia(): void
    {
        $caso = $this->pendiente();
        $cierre = ['nodo_id' => null, 'atendida_at' => now()->format('Y-m-d').'T10:40', 'cumplio_requerimiento' => '1', 'motivo' => 'Sin batería'];

        foreach ([RolDependencia::Edicion, RolDependencia::Coordinacion, RolDependencia::Administracion] as $rol) {
            $usuario = $this->usuarioCon($rol, $this->dependencia);
            $this->actingAs($usuario)->get(route('admin.turnos.derivaciones.show', $caso))->assertOk()->assertDontSee('Cerrar a mano');
            $this->actingAs($usuario)->patch(route('admin.turnos.derivaciones.contingencia', $caso), $cierre)->assertForbidden();
        }

        $caso->refresh();
        $this->assertTrue($caso->estaPendiente());
        $this->assertNull($caso->atendida_at);
        $this->assertDatabaseMissing('auditorias', ['accion' => 'caso.contingencia']);

        $super = User::factory()->superadmin()->create();
        $this->actingAs($super)->withSession(['dependencia_id' => $this->dependencia->id])
            ->get(route('admin.turnos.derivaciones.show', $caso))->assertOk()->assertSee('Cerrar a mano (contingencia)');
    }

    public function test_doc34_ca11_la_derivacion_entrega_la_version_vigente_en_la_ficha_el_excel_y_el_zip(): void
    {
        $coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $caso = $this->pendiente();
        $documento = $this->documentoConDosVersiones($coordinacion, 'DC-001 · Evidencia 1');
        $caso->update(['evidencia1_documento_id' => $documento->id]);
        $antes = $caso->fresh()->only(['estado', 'hora_requerimiento', 'atendida_at', 'tiempo_respuesta_min']);

        // Ficha: la tarjeta abre el documento y el enlace firmado sirve la versión 2.
        $this->actingAs($coordinacion)->get(route('admin.turnos.derivaciones.show', $caso))->assertOk()->assertSee($documento->nombre);
        $enlace = URL::temporarySignedRoute('derivaciones.archivo', now()->addDay(), ['documento' => $documento->uuid]);
        $this->assertSame($this->png(120), $this->get($enlace)->assertOk()->streamedContent());

        // Excel: la foto de la celda es la miniatura de la versión 2.
        $excel = $this->actingAs($coordinacion)->get(route('admin.turnos.derivaciones.exportar'))->assertOk();
        $zip = new ZipArchive;
        $zip->open($excel->getFile()->getPathname());
        $this->assertSame($this->miniatura(120), $zip->getFromName('xl/media/hoja1-image1.jpeg'));
        $this->assertNotSame($this->miniatura(60), $zip->getFromName('xl/media/hoja1-image1.jpeg'));
        $zip->close();

        // ZIP: la carpeta del caso trae la versión 2.
        $fotos = $this->actingAs($coordinacion)->get(route('admin.turnos.derivaciones.fotos'))->assertOk();
        $zip->open($fotos->getFile()->getPathname());
        $nombre = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->first(fn ($n) => str_ends_with($n, '/Evidencia 1.png'));
        $this->assertNotNull($nombre);
        $this->assertSame($this->png(120), $zip->getFromName($nombre));
        $zip->close();

        // La 1 sigue en el historial y el caso no cambió.
        $this->assertSame([1, 2], $documento->versiones()->reorder('numero')->pluck('numero')->all());
        $this->assertEquals($antes, $caso->fresh()->only(['estado', 'hora_requerimiento', 'atendida_at', 'tiempo_respuesta_min']));
    }
}
