<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Livewire\Turnos\Componentes;
use App\Models\Auditoria;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * DOC-25, ACC-09: «Componentes y nodos». Crear un componente y cambiar sus
 * módulos es solo del superadministrador (CA-10); Documentos en un solo
 * componente (CA-11, RN-51); el nombre no se repite en toda la dependencia.
 */
class AccesoPorComponenteComponentesTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
    }

    /** Livewire::test no pasa por los middlewares: dependencia y componente a mano. */
    private function como(User $usuario, Componente $componente): void
    {
        $this->actingAs($usuario);
        app(ContextoDependencia::class)->establecer($this->dependencia);
        app(ContextoDependencia::class)->establecerComponente($componente);
    }

    private function modulos(Componente $componente): array
    {
        return Componente::withoutGlobalScopes()->findOrFail($componente->id)->modulos ?? [];
    }

    public function test_doc25_ca10_solo_el_superadministrador_crea_componentes(): void
    {
        $administracion = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $super = User::factory()->superadmin()->create();

        foreach ([$administracion, $coordinacion] as $usuario) {
            $this->assertFalse($usuario->can('create', Componente::class));
            $this->como($usuario, $this->calle);
            Livewire::test(Componentes::class)->assertActionHidden(TestAction::make(CreateAction::class)->table());
        }

        $this->como($super, $this->calle);
        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(CreateAction::class)->table(), ['nombre' => 'Estabilización', 'modulos' => ['turnos'], 'nodos' => []])
            ->assertHasNoFormErrors();

        $nuevo = Componente::withoutGlobalScopes()->where('slug', 'estabilizacion')->sole();
        $this->assertSame(['turnos'], $nuevo->modulos);
        $this->assertTrue(Auditoria::where('accion', AccionAuditoria::ComponenteGuardado)->where('auditable_id', $nuevo->id)->exists());
    }

    public function test_doc25_ca10_el_superadministrador_cambia_los_modulos_y_queda_en_la_auditoria(): void
    {
        $this->como(User::factory()->superadmin()->create(), $this->basica);

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->basica), ['modulos' => ['turnos', 'derivaciones']])
            ->assertHasNoFormErrors();

        $this->assertSame(['turnos', 'derivaciones'], $this->modulos($this->basica));
        $auditoria = Auditoria::where('accion', AccionAuditoria::ComponenteGuardado)->where('auditable_id', $this->basica->id)->sole();
        $this->assertContains('modulos', $auditoria->datos['cambios']);
    }

    /** Coordinación y Administración editan reglas y nodos, pero los módulos no, aunque los metan en la petición. */
    public function test_doc25_ca10_administracion_y_coordinacion_editan_lo_suyo_sin_tocar_los_modulos(): void
    {
        $administracion = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica);

        foreach ([[$administracion, 20], [$coordinacion, 25]] as [$usuario, $tolerancia]) {
            $this->como($usuario, $this->basica);

            Livewire::test(Componentes::class)
                ->mountAction(TestAction::make(EditAction::class)->table($this->basica))
                ->set('mountedActions.0.data.modulos', ['documentos', 'turnos'])
                ->set('mountedActions.0.data.config.tolerancia_min', $tolerancia)
                ->set('mountedActions.0.data.config.cargos', ['Educador'])
                ->callMountedAction()
                ->assertHasNoFormErrors();

            $basica = Componente::withoutGlobalScopes()->findOrFail($this->basica->id);
            $this->assertSame($tolerancia, $basica->regla('tolerancia_min'), 'Sus reglas sí se guardan.');
            $this->assertSame(['Educador'], $basica->cargos());
            $this->assertSame([], $basica->modulos, 'Los módulos no cambian.');
        }
    }

    public function test_doc25_ca10_el_campo_modulos_solo_lo_ve_el_superadministrador(): void
    {
        $this->como($this->usuarioCon(RolDependencia::Administracion, $this->dependencia), $this->calle);
        Livewire::test(Componentes::class)
            ->mountAction(TestAction::make(EditAction::class)->table($this->calle))
            ->assertFormFieldHidden('modulos');

        $this->como(User::factory()->superadmin()->create(), $this->calle);
        Livewire::test(Componentes::class)
            ->mountAction(TestAction::make(EditAction::class)->table($this->calle))
            ->assertFormFieldVisible('modulos');
    }

    public function test_doc25_ca10_coordinacion_solo_ve_su_componente(): void
    {
        $this->como($this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica), $this->basica);

        Livewire::test(Componentes::class)
            ->assertCanSeeTableRecords([$this->basica])
            ->assertCanNotSeeTableRecords([$this->calle]);
    }

    public function test_doc25_ca11_documentos_no_se_enciende_en_un_segundo_componente(): void
    {
        $this->como(User::factory()->superadmin()->create(), $this->basica);

        $prueba = Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->basica), ['modulos' => ['documentos']])
            ->assertHasFormErrors(['modulos']);
        $this->assertSame('Documentos ya es de Calle: no se puede usar en dos componentes (RN-51).', $prueba->errors()->first('mountedActions.0.data.modulos'));

        $this->assertSame([], $this->modulos($this->basica));

        // Calle, que ya lo tiene, lo conserva al guardar.
        $this->como(User::factory()->superadmin()->create(), $this->calle);
        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->calle), ['config' => ['tolerancia_min' => 20]])
            ->assertHasNoFormErrors();
        $this->assertContains('documentos', $this->modulos($this->calle));
    }

    public function test_doc25_ca11_encender_un_modulo_desde_el_panel_abre_sus_pantallas(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);
        $this->actingAs($editor)->get(route('admin.turnos.enlaces.index'))->assertForbidden();

        $this->como(User::factory()->superadmin()->create(), $this->basica);
        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->basica), ['modulos' => ['turnos']])
            ->assertHasNoFormErrors();
        $this->actingAs($editor)->get(route('admin.turnos.enlaces.index'))->assertOk();

        $this->como(User::factory()->superadmin()->create(), $this->basica);
        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->basica), ['modulos' => []])
            ->assertHasNoFormErrors();
        $this->actingAs($editor)->get(route('admin.turnos.enlaces.index'))->assertForbidden();
    }

    /** D4: parado en Básica, el nombre de Calle (que el scope esconde) tampoco se repite. */
    public function test_doc25_ca10_el_nombre_no_se_repite_en_ningun_componente_de_la_dependencia(): void
    {
        $this->como(User::factory()->superadmin()->create(), $this->basica);

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(CreateAction::class)->table(), ['nombre' => 'calle', 'nodos' => []])
            ->assertHasFormErrors(['nombre'])
            ->assertHasNoFormErrors(['modulos']);

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($this->basica), ['nombre' => 'Calle'])
            ->assertHasFormErrors(['nombre']);
    }
}
