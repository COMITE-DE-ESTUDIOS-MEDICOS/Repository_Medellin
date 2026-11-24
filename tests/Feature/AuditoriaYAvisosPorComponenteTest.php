<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Auditoria;
use App\Models\Aviso;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Notifications\AvisoDelSistema;
use App\Services\Avisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * DOC-25 (ACC-11, CA-13): Coordinación ve en la Auditoría solo lo que hicieron
 * los usuarios de su componente (PA-06), y Administración manda avisos a los
 * componentes que elija.
 */
class AuditoriaYAvisosPorComponenteTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    private User $admin;

    private User $coordinadorCalle;

    private User $editorCalle;

    private User $editorBasica;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);

        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->coordinadorCalle = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $this->editorCalle = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);
        $this->editorBasica = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);

        $this->editorCalle->update(['name' => 'Carla Callejera']);
        $this->editorBasica->update(['name' => 'Bruno Basiquez']);

        $this->accion($this->editorCalle, 'Accion hecha en Calle');
        $this->accion($this->editorBasica, 'Accion hecha en Basica');
    }

    private function accion(User $usuario, string $descripcion): void
    {
        Auditoria::create([
            'dependencia_id' => $this->dependencia->id, 'user_id' => $usuario->id,
            'accion' => AccionAuditoria::DocumentoCreado->value, 'descripcion' => $descripcion,
        ]);
    }

    /** Los nombres que ofrece el filtro «Usuario». */
    private function opcionesDelFiltro(User $quien): Collection
    {
        return $this->actingAs($quien)->get(route('auditoria.index'))->assertOk()->viewData('usuarios')->pluck('name');
    }

    private function aviso(array $datos = []): array
    {
        return ['titulo' => 'Mantenimiento', 'mensaje' => 'El sábado no habrá servicio.', ...$datos];
    }

    /*
    |--------------------------------------------------------------------------
    | Auditoría
    |--------------------------------------------------------------------------
    */

    public function test_doc25_ca13_coordinacion_de_calle_no_ve_acciones_de_basica_ni_en_la_lista_ni_en_el_filtro(): void
    {
        $this->actingAs($this->coordinadorCalle)->get(route('auditoria.index'))
            ->assertOk()->assertDontSee('Accion hecha en Basica')->assertDontSee('Bruno Basiquez');

        $this->assertNotContains('Bruno Basiquez', $this->opcionesDelFiltro($this->coordinadorCalle));
        $this->assertNotContains($this->admin->name, $this->opcionesDelFiltro($this->coordinadorCalle), 'Administración no es de su componente.');

        // Ni pidiéndolo por la dirección.
        $this->actingAs($this->coordinadorCalle)->get(route('auditoria.index', ['usuario' => $this->editorBasica->id]))
            ->assertOk()->assertDontSee('Accion hecha en Basica');
    }

    public function test_doc25_ca13_coordinacion_de_calle_ve_las_acciones_de_su_componente(): void
    {
        $this->actingAs($this->coordinadorCalle)->get(route('auditoria.index'))
            ->assertOk()->assertSee('Accion hecha en Calle')->assertSee('Carla Callejera');

        $this->assertEqualsCanonicalizing(
            ['Carla Callejera', $this->coordinadorCalle->name],
            $this->opcionesDelFiltro($this->coordinadorCalle)->all(),
        );
    }

    public function test_doc25_ca13_coordinacion_sin_componente_no_ve_nada(): void
    {
        $this->dependencia->usuarios()->updateExistingPivot($this->coordinadorCalle->id, ['componente_id' => null]);

        $this->actingAs($this->coordinadorCalle->fresh())->get(route('auditoria.index'))
            ->assertOk()->assertDontSee('Accion hecha en Calle')->assertDontSee('Accion hecha en Basica');
    }

    public function test_doc25_ca13_administracion_ve_todo(): void
    {
        $this->actingAs($this->admin)->get(route('auditoria.index'))
            ->assertOk()->assertSee('Accion hecha en Calle')->assertSee('Accion hecha en Basica');

        $opciones = $this->opcionesDelFiltro($this->admin);
        $this->assertContains('Bruno Basiquez', $opciones);
        $this->assertContains('Carla Callejera', $opciones);
    }

    /*
    |--------------------------------------------------------------------------
    | Enviar aviso
    |--------------------------------------------------------------------------
    */

    public function test_doc25_ca13_el_formulario_ofrece_los_componentes_activos_con_el_actual_marcado(): void
    {
        Componente::factory()->inactivo()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Componente Cerrado']);

        $html = $this->actingAs($this->admin)->get(route('admin.avisos.index'))
            ->assertOk()
            ->assertSee('Básica')
            ->assertDontSee('Componente Cerrado')
            ->getContent();

        $casilla = fn (Componente $c) => '/name="componentes\[\]" value="'.$c->id.'"[^>]*>/';
        preg_match($casilla($this->calle), $html, $calle);
        preg_match($casilla($this->basica), $html, $basica);
        $this->assertStringContainsString('checked', $calle[0], 'El componente donde está parado va marcado.');
        $this->assertStringNotContainsString('checked', $basica[0]);
    }

    public function test_doc25_ca13_un_aviso_a_calle_llega_solo_a_calle(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.avisos.store'), $this->aviso(['componentes' => [$this->calle->id]]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('exito', 'Aviso enviado a 2 personas.');

        Notification::assertSentTo([$this->editorCalle, $this->coordinadorCalle], AvisoDelSistema::class);
        // Plan P-04 (respuesta por defecto): Administración, sin componente, no lo recibe.
        Notification::assertNotSentTo([$this->editorBasica, $this->admin], AvisoDelSistema::class);

        $auditoria = Auditoria::where('accion', AccionAuditoria::AvisoEnviado->value)->sole();
        $this->assertSame($this->admin->id, $auditoria->user_id);
        $this->assertSame(['Calle'], $auditoria->datos['componentes']);
        $this->assertSame(Aviso::sole()->id, $auditoria->auditable_id);
    }

    public function test_doc25_ca13_los_roles_marcados_siguen_filtrando_dentro_del_componente(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post(route('admin.avisos.store'), $this->aviso([
            'componentes' => [$this->calle->id, $this->basica->id], 'roles' => ['edicion'],
        ]))->assertSessionHas('exito', 'Aviso enviado a 2 personas.');

        Notification::assertSentTo([$this->editorCalle, $this->editorBasica], AvisoDelSistema::class);
        Notification::assertNotSentTo([$this->coordinadorCalle, $this->admin], AvisoDelSistema::class);
    }

    public function test_doc25_ca13_sin_componentes_marcados_el_formulario_rechaza_el_envio(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post(route('admin.avisos.store'), $this->aviso())
            ->assertSessionHasErrors(['componentes' => 'Marca al menos un componente.']);
        $this->actingAs($this->admin)->post(route('admin.avisos.store'), $this->aviso(['componentes' => []]))
            ->assertSessionHasErrors('componentes');

        // Tampoco uno de otra dependencia.
        $ajeno = Componente::factory()->create();
        $this->actingAs($this->admin)->post(route('admin.avisos.store'), $this->aviso(['componentes' => [$ajeno->id]]))
            ->assertSessionHasErrors('componentes.0');

        $this->assertSame(0, Aviso::count());
        Notification::assertNothingSent();
    }

    public function test_doc25_ca13_un_aviso_de_version_sigue_llegando_a_todos(): void
    {
        Notification::fake();

        app(Avisos::class)->anunciarVersion('v9.1', "## v9.1\n\n- Componentes.\n");

        Notification::assertSentTo(
            [$this->admin, $this->coordinadorCalle, $this->editorCalle, $this->editorBasica],
            AvisoDelSistema::class,
        );
    }
}
