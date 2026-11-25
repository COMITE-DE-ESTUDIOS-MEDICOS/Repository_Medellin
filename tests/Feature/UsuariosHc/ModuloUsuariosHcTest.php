<?php

namespace Tests\Feature\UsuariosHc;

use App\Enums\RolDependencia;
use App\Livewire\Turnos\Componentes;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Database\Seeders\AccesoPorComponenteSeeder;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** DOC-26 (UHC-01, plan D2): el módulo Usuarios HC y la llegada. */
class ModuloUsuariosHcTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        // Calle como queda en producción: sus cuatro módulos, sin Usuarios HC.
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle', 'modulos' => AccesoPorComponenteSeeder::MODULOS_CALLE]);
    }

    private function componente(string $nombre, string $slug, array $modulos): Componente
    {
        return Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => $nombre, 'slug' => $slug, 'modulos' => $modulos]);
    }

    public function test_doc26_ca01_basica_y_estabilizacion_llegan_con_cualquier_rol_y_calle_recibe_rechazo(): void
    {
        $basica = $this->componente('Básica', 'basica', ['usuarios_hc']);
        $estabilizacion = $this->componente('Estabilización', 'estabilizacion', ['usuarios_hc']);

        foreach ([$basica, $estabilizacion] as $componente) {
            foreach ([RolDependencia::Lectura, RolDependencia::Edicion, RolDependencia::Coordinacion] as $rol) {
                $usuario = $this->usuarioCon($rol, $this->dependencia, $componente);

                $this->actingAs($usuario)->get(route('inicio'))->assertRedirect(route('usuarios-hc.index'));
                $this->actingAs($usuario)->get(route('usuarios-hc.index'))->assertOk()->assertSee('Usuarios HC');
            }
        }

        // Calle, con cualquier rol: rechazo por dirección directa y llega a Documentos.
        foreach ([RolDependencia::Lectura, RolDependencia::Edicion, RolDependencia::Coordinacion] as $rol) {
            $deCalle = $this->usuarioCon($rol, $this->dependencia, $this->calle);
            $this->actingAs($deCalle)->get(route('usuarios-hc.index'))->assertForbidden();
            $this->actingAs($deCalle)->get(route('inicio'))->assertRedirect(route('documentos.index'));
        }

        // El coordinador líder (Administración): entra parado en Básica, no parado en Calle.
        $lider = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->actingAs($lider)->withSession(['componente_id' => $basica->id])->get(route('usuarios-hc.index'))->assertOk();
        $this->actingAs($lider)->withSession(['componente_id' => $this->calle->id])->get(route('usuarios-hc.index'))->assertForbidden();
    }

    public function test_doc26_ca35_el_modulo_aparece_solo_cuando_el_superadministrador_lo_enciende(): void
    {
        $basica = $this->componente('Básica', 'basica', []);
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $basica);

        $this->actingAs($lector)->get(route('usuarios-hc.index'))->assertForbidden();
        $this->actingAs($lector)->get(route('inicio'))->assertOk()->assertViewIs('inicio');

        // La casilla sale sola de Componente::MODULOS en «Componentes y nodos».
        $super = User::factory()->superadmin()->create();
        $this->actingAs($super);
        app(ContextoDependencia::class)->establecer($this->dependencia);
        app(ContextoDependencia::class)->establecerComponente($basica);
        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($basica), ['modulos' => ['usuarios_hc']])
            ->assertHasNoFormErrors();

        $this->assertSame(['usuarios_hc'], Componente::withoutGlobalScopes()->findOrFail($basica->id)->modulos);

        $this->actingAs($lector)->get(route('inicio'))->assertRedirect(route('usuarios-hc.index'));
        $this->actingAs($lector)->get(route('usuarios-hc.index'))->assertOk();
    }
}
