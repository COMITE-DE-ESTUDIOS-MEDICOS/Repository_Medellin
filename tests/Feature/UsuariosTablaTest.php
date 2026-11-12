<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Livewire\Usuarios;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_doc22_sincronizar_activo_lo_deja_en_el_grupo_e_inactivo_lo_saca(): void
    {
        config([
            'services.authentik.base_url' => 'https://authentik.test',
            'services.authentik.api_token' => 'x',
            'services.authentik.grupo_id' => 'g1',
        ]);
        Http::fake([
            '*/core/users/?*' => Http::response(['results' => [['pk' => 77, 'username' => '1017234567']]]),
            '*' => Http::response([], 204),
        ]);

        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $ana = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $ana->update(['documento' => '1017234567']);
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->como($coordinador);

        Livewire::test(Usuarios::class)
            ->assertActionHidden(TestAction::make('sincronizar')->table($admin))
            ->callAction(TestAction::make('sincronizar')->table($ana))
            ->assertNotified('Authentik: '.$ana->name.' quedó en el grupo de Documenta.');

        $this->assertSame('77', (string) $ana->fresh()->authentik_id);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/groups/g1/add_user/') && $r->data() === ['pk' => 77]);

        $ana->update(['activo' => false]);

        Livewire::test(Usuarios::class)->callAction(TestAction::make('sincronizar')->table($ana));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/groups/g1/remove_user/'));
        // La cuenta compartida nunca se toca.
        Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PATCH', 'PUT', 'DELETE'], true));
    }

    public function test_doc22_sincronizar_si_falla_dice_la_causa_sin_la_respuesta(): void
    {
        config([
            'services.authentik.base_url' => 'https://authentik.test',
            'services.authentik.api_token' => 'x',
            'services.authentik.grupo_id' => 'g1',
        ]);
        Http::fake(['*' => Http::response(['detail' => 'secreto interno'], 403)]);

        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $ana = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $ana->update(['documento' => '1017234567']);
        $this->como($coordinador);

        Livewire::test(Usuarios::class)
            ->callAction(TestAction::make('sincronizar')->table($ana))
            ->assertNotified(Notification::make()->title('Authentik: no se pudo sincronizar.')->body('El token no tiene permiso en Authentik.')->danger());
    }

    public function test_edicion_no_entra(): void
    {
        $this->como($this->usuarioCon(RolDependencia::Edicion, $this->dependencia));

        Livewire::test(Usuarios::class)->assertForbidden();
    }
}
