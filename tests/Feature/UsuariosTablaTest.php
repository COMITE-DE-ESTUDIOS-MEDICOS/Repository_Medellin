<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Livewire\Usuarios;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La lista de usuarios con la tabla de Filament: buscar, aislar por dependencia y revocar con rangos. */
class UsuariosTablaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dependencia = Dependencia::factory()->create();
    }

    /** Livewire::test no pasa por los middlewares: la dependencia se pone a mano. */
    private function como(User $usuario): void
    {
        $this->actingAs($usuario);
        app(ContextoDependencia::class)->establecer($this->dependencia);
    }

    public function test_busca_por_documento_con_puntos_y_no_muestra_otras_dependencias(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $ana = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $ana->update(['documento' => '1017234567']);
        $ajeno = $this->usuarioCon(RolDependencia::Edicion);
        $this->como($coordinador);

        Livewire::test(Usuarios::class)
            ->assertCanSeeTableRecords([$coordinador, $ana])
            ->assertCanNotSeeTableRecords([$ajeno])
            ->searchTable('1.017.234')
            ->assertCanSeeTableRecords([$ana])
            ->assertCanNotSeeTableRecords([$coordinador]);
    }

    public function test_revoca_el_acceso_sin_borrar_la_cuenta_y_respeta_los_rangos(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->como($coordinador);

        Livewire::test(Usuarios::class)
            ->assertActionHidden(TestAction::make('revocar')->table($coordinador))
            ->assertActionHidden(TestAction::make('revocar')->table($admin))
            ->callAction(TestAction::make('revocar')->table($editor));

        $this->assertFalse($editor->fresh()->perteneceA($this->dependencia));
        $this->assertModelExists($editor);
        $this->assertDatabaseHas('auditorias', ['auditable_id' => $editor->id, 'accion' => 'usuario.desactivado']);
    }

    public function test_edicion_no_entra(): void
    {
        $this->como($this->usuarioCon(RolDependencia::Edicion, $this->dependencia));

        Livewire::test(Usuarios::class)->assertForbidden();
    }
}
