<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\GrupoCustodia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Carpetas bajo custodia: el líder del Nodo 2 no toca lo del Nodo 6. Ni lo
 * ve, ni lo edita, ni lo mueve, ni mete nada ahí; por ningún camino.
 */
class CustodiaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $liderNodo6;

    private User $liderNodo2;

    private User $coordinador;

    private Carpeta $nodo6;

    private Carpeta $subNodo6;

    private Documento $actaNodo6;

    private Carpeta $libre;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->liderNodo6 = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->liderNodo2 = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);

        $grupo = GrupoCustodia::create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Líderes Nodo 6']);
        $grupo->usuarios()->attach($this->liderNodo6);

        $this->nodo6 = Carpeta::create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Nodo 6', 'grupo_custodia_id' => $grupo->id]);
        $this->subNodo6 = Carpeta::create(['dependencia_id' => $this->dependencia->id, 'carpeta_id' => $this->nodo6->id, 'nombre' => 'Octubre']);
        $this->libre = Carpeta::create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Nodo 2']);

        $this->actingAs($this->liderNodo6)->post(route('documentos.store'), [
            'nombre' => 'Acta secreta del nodo 6', 'carpeta_id' => $this->subNodo6->id, 'archivo' => $this->archivoPdf(),
        ])->assertSessionHasNoErrors();
        $this->actaNodo6 = Documento::sole();
    }

    public function test_el_lider_del_nodo_2_no_ve_nada_del_nodo_6(): void
    {
        $this->actingAs($this->liderNodo2)->get(route('documentos.index'))
            ->assertSee('Nodo 2')->assertDontSee('Nodo 6');

        $this->actingAs($this->liderNodo2)->get(route('documentos.index', ['q' => 'secreta']))
            ->assertDontSee('Acta secreta del nodo 6');

        // Ni por enlace directo, ni la subcarpeta, ni el documento, ni el ZIP.
        $this->assertNoDejaVer($this->actingAs($this->liderNodo2)->get(route('documentos.index', ['carpeta' => $this->nodo6->uuid])), 'Octubre', 'Abrir la carpeta');
        $this->assertNoDejaVer($this->actingAs($this->liderNodo2)->get(route('documentos.index', ['carpeta' => $this->subNodo6->uuid])), 'Acta secreta', 'Abrir la subcarpeta');
        $this->assertNoDejaVer($this->actingAs($this->liderNodo2)->get(route('documentos.show', $this->actaNodo6)), 'Acta secreta', 'Abrir el documento');
        $this->assertNoDejaVer($this->actingAs($this->liderNodo2)->get(route('documentos.descargar', $this->actaNodo6)), 'Acta secreta', 'Descargarlo');
        $this->assertNoDejaVer($this->actingAs($this->liderNodo2)->get(route('carpetas.descargar', $this->nodo6)), 'Acta secreta', 'Bajar el ZIP');
    }

    public function test_el_lider_del_nodo_2_no_edita_mueve_ni_inactiva_lo_del_nodo_6(): void
    {
        $this->actingAs($this->liderNodo2)->get(route('carpetas.edit', $this->nodo6))->assertForbidden();
        $this->actingAs($this->liderNodo2)->put(route('carpetas.update', $this->nodo6), ['nombre' => 'Cambiado'])->assertForbidden();
        $this->actingAs($this->liderNodo2)->put(route('documentos.update', $this->actaNodo6), ['nombre' => 'Cambiado'])->assertForbidden();
        $this->actingAs($this->liderNodo2)->patch(route('carpetas.inactivar', $this->nodo6))->assertForbidden();

        $this->actingAs($this->liderNodo2)->post(route('documentos.lote'), [
            'accion' => 'mover', 'documentos' => [$this->actaNodo6->uuid], 'destino' => $this->libre->id,
        ])->assertSessionHasErrors('documentos');

        $this->assertSame('Nodo 6', $this->nodo6->fresh()->nombre);
        $this->assertSame($this->subNodo6->id, $this->actaNodo6->fresh()->carpeta_id);
    }

    public function test_el_lider_del_nodo_2_no_mete_nada_en_el_nodo_6_por_ningun_camino(): void
    {
        $this->actingAs($this->liderNodo2)->post(route('documentos.store'), [
            'nombre' => 'Intruso', 'carpeta_id' => $this->nodo6->id, 'archivo' => $this->archivoPdf(),
        ])->assertSessionHasErrors('carpeta_id');

        $this->actingAs($this->liderNodo2)->post(route('carpetas.store'), ['nombre' => 'Intrusa', 'carpeta_id' => $this->nodo6->id])
            ->assertSessionHasErrors('carpeta_id');

        $this->actingAs($this->liderNodo2)->post(route('admin.enlaces.store'), ['carpeta_id' => $this->nodo6->id])
            ->assertSessionHasErrors('carpeta_id');

        $this->actingAs($this->liderNodo2)->post(route('documentos.store'), [
            'nombre' => 'Mío', 'carpeta_id' => $this->libre->id, 'archivo' => $this->archivoPdf(),
        ]);
        $mio = Documento::where('nombre', 'Mío')->sole();

        $this->actingAs($this->liderNodo2)->post(route('documentos.lote'), [
            'accion' => 'copiar', 'documentos' => [$mio->uuid], 'destino' => $this->nodo6->id,
        ])->assertSessionHasErrors('destino');

        $this->actingAs($this->liderNodo2)->post(route('documentos.copiar', $mio), ['carpeta_id' => $this->nodo6->id])
            ->assertSessionHasErrors('carpeta_id');

        $this->assertSame(1, Documento::where('carpeta_id', $this->subNodo6->id)->count());
        $this->assertSame(0, Documento::where('carpeta_id', $this->nodo6->id)->count());
    }

    public function test_el_lider_del_nodo_6_trabaja_normal_en_su_carpeta(): void
    {
        $this->actingAs($this->liderNodo6)->get(route('documentos.index', ['carpeta' => $this->subNodo6->uuid]))
            ->assertOk()->assertSee('Acta secreta del nodo 6');

        $this->actingAs($this->liderNodo6)->put(route('documentos.update', $this->actaNodo6), ['nombre' => 'Acta revisada'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Acta revisada', $this->actaNodo6->fresh()->nombre);
    }

    public function test_un_miembro_con_lectura_revisa_pero_no_modifica(): void
    {
        $revisor = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        GrupoCustodia::first()->usuarios()->attach($revisor);

        $this->actingAs($revisor)->get(route('documentos.show', $this->actaNodo6))->assertOk();
        $this->actingAs($revisor)->get(route('documentos.descargar', $this->actaNodo6))->assertOk();
        $this->actingAs($revisor)->put(route('documentos.update', $this->actaNodo6), ['nombre' => 'X'])->assertForbidden();
    }

    public function test_coordinacion_ve_todo_y_asigna_o_quita_la_custodia(): void
    {
        $this->actingAs($this->coordinador)->get(route('documentos.index'))->assertSee('Nodo 6');

        $this->actingAs($this->coordinador)->put(route('carpetas.update', $this->nodo6), [
            'nombre' => 'Nodo 6', 'grupo_custodia_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->nodo6->fresh()->grupo_custodia_id);
        $this->assertDatabaseHas('auditorias', ['accion' => 'carpeta.custodia', 'auditable_id' => $this->nodo6->id]);
        $this->actingAs($this->liderNodo2)->get(route('documentos.index'))->assertSee('Nodo 6');
    }

    public function test_un_editor_no_se_asigna_la_custodia(): void
    {
        $grupo = GrupoCustodia::create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Líderes Nodo 2']);
        $grupo->usuarios()->attach($this->liderNodo2);

        $this->actingAs($this->liderNodo2)->put(route('carpetas.update', $this->libre), [
            'nombre' => 'Nodo 2', 'grupo_custodia_id' => $grupo->id,
        ]);

        $this->assertNull($this->libre->fresh()->grupo_custodia_id, 'Solo Coordinación asigna custodia.');
        $this->actingAs($this->liderNodo2)->get(route('admin.custodia.index'))->assertForbidden();
    }

    public function test_abrir_un_documento_custodiado_queda_en_la_auditoria(): void
    {
        $this->actingAs($this->liderNodo6)->get(route('documentos.show', $this->actaNodo6))->assertOk();
        $this->assertDatabaseHas('auditorias', ['accion' => 'documento.abierto', 'auditable_id' => $this->actaNodo6->id]);

        // Abrir uno que no está custodiado no se registra (como siempre).
        $this->actingAs($this->liderNodo2)->post(route('documentos.store'), [
            'nombre' => 'Libre', 'carpeta_id' => $this->libre->id, 'archivo' => $this->archivoPdf(),
        ]);
        $libre = Documento::where('nombre', 'Libre')->sole();
        $this->actingAs($this->liderNodo2)->get(route('documentos.show', $libre))->assertOk();
        $this->assertDatabaseMissing('auditorias', ['accion' => 'documento.abierto', 'auditable_id' => $libre->id]);
    }

    public function test_coordinacion_crea_un_grupo_con_miembros(): void
    {
        $this->actingAs($this->coordinador)->post(route('admin.custodia.store'), [
            'nombre' => 'Líderes Nodo 3', 'usuarios' => [$this->liderNodo2->id],
        ])->assertSessionHasNoErrors();

        $grupo = GrupoCustodia::where('nombre', 'Líderes Nodo 3')->sole();
        $this->assertTrue($grupo->tieneA($this->liderNodo2));

        $ajeno = User::factory()->create();
        $this->actingAs($this->coordinador)->put(route('admin.custodia.update', $grupo), [
            'nombre' => 'Líderes Nodo 3', 'usuarios' => [$ajeno->id],
        ])->assertSessionHasErrors('usuarios.0');
    }
}
