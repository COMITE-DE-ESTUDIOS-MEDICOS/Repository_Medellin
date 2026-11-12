<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\GrupoCustodia;
use App\Models\User;
use App\Notifications\ComentarioEnDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pasar de un archivo a otro con las flechas, y comentar un archivo avisando a quien lo subió. */
class ComentariosYNavegacionTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Carpeta $carpeta;

    private User $editor;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        $this->dependencia = Dependencia::factory()->create();
        $this->carpeta = Carpeta::create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Fotos de octubre']);
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    private function subir(string $nombre): Documento
    {
        $this->actingAs($this->editor)->post(route('documentos.store'), [
            'nombre' => $nombre, 'carpeta_id' => $this->carpeta->id, 'archivo' => $this->archivoJpg(),
        ])->assertSessionHasNoErrors();
        $this->travel(1)->minutes();

        return Documento::where('nombre', $nombre)->sole();
    }

    public function test_las_flechas_recorren_la_carpeta_en_el_orden_del_explorador(): void
    {
        $primera = $this->subir('Foto 1');
        $segunda = $this->subir('Foto 2');
        $tercera = $this->subir('Foto 3');

        // El explorador pone lo más nuevo primero: Foto 3, Foto 2, Foto 1.
        $this->actingAs($this->editor)->get(route('documentos.show', $segunda))
            ->assertOk()
            ->assertSee('2 de 3')
            ->assertSee('href="'.route('documentos.show', $tercera).'" data-navegar="anterior"', false)
            ->assertSee('href="'.route('documentos.show', $primera).'" data-navegar="siguiente"', false);

        $this->actingAs($this->editor)->get(route('documentos.show', $tercera))
            ->assertSee('1 de 3')
            ->assertDontSee('data-navegar="anterior"', false);
    }

    public function test_las_flechas_no_llevan_a_lo_que_no_se_puede_ver(): void
    {
        $visible = $this->subir('Visible');
        $retirada = $this->subir('Retirada');
        $retirada->update(['activo' => false]);

        $this->actingAs($this->editor)->get(route('documentos.show', $visible))
            ->assertDontSee(route('documentos.show', $retirada), false)
            ->assertDontSee('data-navegar', false);
    }

    public function test_el_coordinador_comenta_y_a_quien_subio_la_foto_le_llega_con_enlace_directo(): void
    {
        Notification::fake();
        $foto = $this->subir('Foto de la ruta');

        $this->actingAs($this->coordinador)->post(route('documentos.comentarios.store', $foto), [
            'texto' => 'La foto está desenfocada: tómala de nuevo.',
        ])->assertRedirect();

        $comentario = $foto->comentarios()->sole();
        $this->assertSame($this->coordinador->id, $comentario->user_id);
        $this->assertDatabaseHas('auditorias', ['accion' => 'documento.comentado', 'auditable_id' => $foto->id]);

        Notification::assertSentTo($this->editor, ComentarioEnDocumento::class, function ($n) use ($foto, $comentario) {
            $datos = $n->toArray($this->editor);

            return str_contains($datos['mensaje'], $this->coordinador->name.' comentó en «Foto de la ruta»')
                && $datos['url'] === route('documentos.show', $foto, false).'#comentario-'.$comentario->id;
        });
        Notification::assertNotSentTo($this->coordinador, ComentarioEnDocumento::class);

        $this->actingAs($this->editor)->get(route('documentos.show', $foto))
            ->assertSee('La foto está desenfocada: tómala de nuevo.');
    }

    public function test_la_respuesta_le_llega_a_quien_comento_antes(): void
    {
        Notification::fake();
        $foto = $this->subir('Foto');

        $this->actingAs($this->coordinador)->post(route('documentos.comentarios.store', $foto), ['texto' => 'Está movida.']);
        $this->actingAs($this->editor)->post(route('documentos.comentarios.store', $foto), ['texto' => 'Ya la cambio.']);

        Notification::assertSentToTimes($this->coordinador, ComentarioEnDocumento::class, 1);
        Notification::assertSentToTimes($this->editor, ComentarioEnDocumento::class, 1);
    }

    public function test_no_comenta_ni_se_entera_quien_no_ve_el_archivo(): void
    {
        Notification::fake();
        $foto = $this->subir('Foto custodiada');
        $grupo = GrupoCustodia::create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Solo coordinación']);
        $grupo->usuarios()->attach($this->coordinador);
        $this->carpeta->update(['grupo_custodia_id' => $grupo->id]);

        $ajeno = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->actingAs($ajeno)->post(route('documentos.comentarios.store', $foto), ['texto' => 'Hola'])->assertForbidden();

        // El editor que la subió ya no la ve (está custodiada): no recibe nada.
        $this->actingAs($this->coordinador)->post(route('documentos.comentarios.store', $foto), ['texto' => 'Revisada']);
        Notification::assertNothingSentTo($this->editor);
    }

    public function test_solo_el_autor_o_coordinacion_borran_un_comentario(): void
    {
        $foto = $this->subir('Foto');
        $this->actingAs($this->editor)->post(route('documentos.comentarios.store', $foto), ['texto' => 'Mío']);
        $comentario = $foto->comentarios()->sole();

        $otro = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->actingAs($otro)->delete(route('documentos.comentarios.destroy', [$foto, $comentario]))->assertForbidden();

        $this->actingAs($this->coordinador)->delete(route('documentos.comentarios.destroy', [$foto, $comentario]))->assertRedirect();
        $this->assertSame(0, $foto->comentarios()->count());
    }
}
