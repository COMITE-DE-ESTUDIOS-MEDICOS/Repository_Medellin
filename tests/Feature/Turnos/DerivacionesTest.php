<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\Nodo;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * DERIVACIONES Y RESPUESTA TERRITORIAL: los registros anteriores a DOC-20
 * (numero = null) en el panel, la corrección, los permisos y el reporte. Lo
 * de los casos DC está en CasosDerivacion*Test.
 */
class DerivacionesTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private Colaborador $jose;

    private string $token;

    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        // El requerimiento de las pruebas es a las 10:00: «ahora» son las 11:00.
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 2']);
        $this->jose = Colaborador::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'documento' => '1017000111',
            'nombre' => 'José Amaya Pérez', 'cargo' => 'Monitor de ruta', 'componente_id' => $this->calle->id,
        ]);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create(['tipo' => 'derivacion']);
        $this->lider = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    /** Un registro anterior, como lo dejaba el formulario viejo: pendiente a las 10:00. */
    private function anterior(array $datos = []): Derivacion
    {
        return Derivacion::create($datos + [
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->calle->id,
            'colaborador_id' => $this->jose->id, 'cedula' => '1017000111', 'nombre' => 'José Amaya Pérez',
            'cargo' => 'Monitor de ruta', 'nodo_id' => $this->nodo->id,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
            'entidad' => 'Policía Nacional', 'descripcion' => 'Habitante de calle requiere traslado.',
            'ubicacion' => 'Cra. 65 #97-121, Castilla', 'estado' => Derivacion::PENDIENTE,
        ]);
    }

    private function documento(Derivacion $d, string $etiqueta, UploadedFile $archivo): int
    {
        $carpeta = Carpeta::withoutGlobalScopes()->firstOrCreate(['dependencia_id' => $d->dependencia_id, 'carpeta_id' => null, 'nombre' => 'Derivaciones · Calle']);
        $documento = Documento::create(['dependencia_id' => $d->dependencia_id, 'carpeta_id' => $carpeta->id, 'nombre' => "{$d->codigo()} · {$etiqueta}", 'fecha_documento' => now()]);
        app(AlmacenamientoDocumentos::class)->guardarVersion($documento, $archivo);

        return $documento->id;
    }

    public function test_la_trazabilidad_dice_que_nodo_tiene_casos_sin_respuesta(): void
    {
        $this->anterior(['estado' => Derivacion::SOLUCIONADA, 'hora_respuesta' => '12:00', 'tiempo_respuesta_min' => 120, 'respondida_por' => $this->lider->id]);
        $this->anterior(['entidad' => 'ICBF']);

        // La otra sigue sin respuesta y a las 14:00 ya venció.
        Carbon::setTestNow(Carbon::today()->setTime(14, 0));
        $traza = $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.index'))->assertOk()
            ->assertSee('registro anterior')->viewData('traza');

        $this->assertSame([1, 1, 1, 1], [$traza['pendientes'], $traza['vencidas'], $traza['solucionadas'], $traza['a_tiempo']]);
        $this->assertSame(['Nodo 2'], $traza['sin_respuesta']->keys()->all());
        $this->assertSame('2 h 0 min', $traza['promedio']);
        // «Casos atendidos por persona de campo» es de los casos DC: los anteriores los respondía un líder.
        $this->assertTrue($traza['personas']->isEmpty());
    }

    public function test_un_enlace_de_turno_no_abre_el_formulario_ni_al_reves(): void
    {
        $turno = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($turno)->create();

        $this->get(route('derivacion.enlace', ['token' => $turno]))->assertNotFound();
        $this->get(route('turno.enlace', ['token' => $this->token]))->assertNotFound();
    }

    public function test_edicion_consulta_corrige_textos_y_coordinacion_elimina(): void
    {
        $d = $this->anterior(['estado' => Derivacion::SOLUCIONADA, 'hora_respuesta' => '10:30', 'tiempo_respuesta_min' => 30, 'respuesta' => 'Atendido.']);

        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.index'))->assertOk()->assertSee('José Amaya Pérez');
        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.show', $d))->assertOk()->assertSee('Policía Nacional')->assertDontSee('Responder');
        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.edit', $d))->assertOk()->assertDontSee('Hora de respuesta');

        // Edición corrige los textos; la hora que mande se ignora (DOC-20 CA-12).
        $this->actingAs($this->lider)->put(route('admin.turnos.derivaciones.update', $d), [
            'nodo_id' => $this->nodo->id, 'entidad' => 'ICBF', 'descripcion' => 'Corregida', 'ubicacion' => 'Castilla',
            'respuesta' => 'Atendido.', 'hora_requerimiento' => '09:00', 'hora_respuesta' => '10:20',
        ])->assertSessionHasNoErrors();
        $d->refresh();
        $this->assertSame(['ICBF', 'Corregida', '10:00', '10:30', 30], [$d->entidad, $d->descripcion, substr($d->hora_requerimiento, 0, 5), substr($d->hora_respuesta, 0, 5), $d->tiempo_respuesta_min]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'derivacion.actualizada', 'auditable_id' => $d->id]);

        $this->actingAs($this->lider)->delete(route('admin.turnos.derivaciones.destroy', $d))->assertForbidden();

        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $this->actingAs($coordinador)->delete(route('admin.turnos.derivaciones.destroy', $d))->assertRedirect();
        $this->assertSoftDeleted($d);
    }

    public function test_lectura_no_ve_el_modulo(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)->get(route('admin.turnos.derivaciones.index'))->assertForbidden();
        $this->actingAs($lector)->get(route('admin.turnos.derivaciones.exportar'))->assertForbidden();
    }

    public function test_el_excel_trae_los_encabezados_negros_y_el_zip_las_fotos(): void
    {
        $d = $this->anterior();
        $d->update([
            'soporte_documento_id' => $this->documento($d, 'Soporte', $this->archivoPdf('oficio.pdf')),
            'evidencia1_documento_id' => $this->documento($d, 'Evidencia 1', $this->archivoJpg('uno.jpg')),
            'evidencia2_documento_id' => $this->documento($d, 'Evidencia 2', $this->archivoJpg('dos.jpg')),
            'evidencia3_documento_id' => $this->documento($d, 'Evidencia 3', $this->archivoJpg('tres.jpg')),
        ]);

        $excel = $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.exportar'))->assertOk();
        $zip = new ZipArchive();
        $zip->open($excel->getFile()->getPathname());
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('CC DE QUIEN ESCALA LA NOVEDAD', $hoja);
        $this->assertStringContainsString('JUSTIFICACION EN CASO DE NO CUMPLIR', $hoja);
        $this->assertStringContainsString('Pendiente', $hoja);
        $this->assertStringContainsString('Registro anterior', $hoja);
        $this->assertStringContainsString('José Amaya Pérez', $hoja);
        $this->assertStringContainsString('FF000000', $zip->getFromName('xl/styles.xml'));

        // Las tres evidencias van dentro del Excel; el soporte (PDF) como enlace firmado.
        $this->assertStringContainsString('<drawing r:id="rId1"/>', $hoja);
        $this->assertNotFalse($zip->getFromName('xl/media/hoja1-image3.jpeg'));
        $this->assertFalse($zip->getFromName('xl/media/hoja1-image4.jpeg'));
        preg_match('/HYPERLINK\(&quot;([^&]+(?:&amp;[^&]+)*)&quot;/', $hoja, $enlace);
        $zip->close();

        // El enlace abre sin sesión; alterado, no.
        $url = html_entity_decode($enlace[1]);
        auth()->logout();
        $this->get($url)->assertOk();
        $this->get($url.'x')->assertForbidden();

        $fotos = $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.fotos'))->assertOk();
        $zip->open($fotos->getFile()->getPathname());
        $nombres = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $zip->close();

        $this->assertTrue($nombres->contains('derivaciones.xlsx'));
        $this->assertTrue($nombres->contains(fn ($n) => str_ends_with($n, '/Evidencia 1.jpg')));
        $this->assertTrue($nombres->contains(fn ($n) => str_ends_with($n, '/Soporte.pdf')));
    }

    public function test_el_enlace_se_crea_con_el_tipo_derivacion(): void
    {
        $this->actingAs($this->lider)->post(route('admin.turnos.enlaces.store'), [
            'tipo' => 'derivacion', 'componente_id' => $this->calle->id,
        ])->assertSessionHasNoErrors();

        $enlace = EnlaceTurno::latest('id')->first();
        $this->assertTrue($enlace->esDerivacion());
        $this->assertStringContainsString('/d/', $enlace->url());
    }
}
