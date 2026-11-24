<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Livewire\Usuarios;
use App\Models\Auditoria;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** DOC-25 (ACC-07): gestión de usuarios por componente, en el servidor. */
class AccesoPorComponenteUsuariosTest extends TestCase
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

    private function alta(string $rol, ?int $componente, string $documento = '1122334455'): array
    {
        return ['name' => 'Nueva Persona', 'documento' => $documento, 'rol' => $rol, 'componente_id' => $componente, 'activo' => 0];
    }

    private function ficha(User $u, string $rol, ?int $componente): array
    {
        return ['name' => $u->name, 'documento' => $u->documento, 'email' => $u->email, 'rol' => $rol, 'componente_id' => $componente, 'activo' => 0];
    }

    private function pivote(string $documento): ?object
    {
        return User::where('documento', $documento)->first()?->dependencias()->whereKey($this->dependencia->id)->first()?->pivot;
    }

    public function test_doc25_ca08_el_componente_es_obligatorio_salvo_para_administracion(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        foreach (['lectura', 'edicion', 'coordinacion'] as $rol) {
            $this->actingAs($admin)->post(route('admin.usuarios.store'), $this->alta($rol, null))
                ->assertSessionHasErrors(['componente_id' => 'Elige el componente de la persona.']);
        }
        $this->assertDatabaseMissing('users', ['documento' => '1122334455']);

        $this->actingAs($admin)->put(route('admin.usuarios.update', $editor), $this->ficha($editor, 'edicion', null))
            ->assertSessionHasErrors('componente_id');
        $this->assertSame($this->calle->id, $editor->fresh()->componenteEn($this->dependencia));

        // Administración: sin componente, aunque llegue uno.
        $jefe = User::factory()->superadmin()->create();
        $this->actingAs($jefe)->post(route('admin.usuarios.store'), $this->alta('administracion', $this->basica->id))
            ->assertSessionHasNoErrors();
        $this->assertNull($this->pivote('1122334455')->componente_id);
    }

    public function test_doc25_ca08_coordinacion_solo_asigna_su_componente_y_solo_ve_a_los_suyos(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $deCalle = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);
        $deBasica = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->actingAs($coordinador)->post(route('admin.usuarios.store'), $this->alta('edicion', $this->basica->id))
            ->assertSessionHasErrors(['componente_id' => 'Solo puedes asignar un componente activo que tengas a cargo.']);
        $this->actingAs($coordinador)->post(route('admin.usuarios.store'), $this->alta('edicion', $this->calle->id))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->calle->id, (int) $this->pivote('1122334455')->componente_id);

        // No lo pasa a otro componente.
        $this->actingAs($coordinador)->put(route('admin.usuarios.update', $deCalle), $this->ficha($deCalle, 'edicion', $this->basica->id))
            ->assertSessionHasErrors('componente_id');
        $this->assertSame($this->calle->id, $deCalle->fresh()->componenteEn($this->dependencia));

        // Los de otro componente no existen para ella.
        $this->actingAs($coordinador)->get(route('admin.usuarios.edit', $deBasica))->assertNotFound();
        $this->actingAs($coordinador)->put(route('admin.usuarios.update', $deBasica), $this->ficha($deBasica, 'edicion', $this->calle->id))->assertNotFound();
        $this->actingAs($coordinador)->delete(route('admin.usuarios.revocar', $deBasica))->assertNotFound();
        $this->assertSame($this->basica->id, $deBasica->fresh()->componenteEn($this->dependencia));

        $this->actingAs($coordinador);
        app(ContextoDependencia::class)->establecer($this->dependencia);
        Livewire::test(Usuarios::class)
            ->assertCanSeeTableRecords([$coordinador, $deCalle])
            ->assertCanNotSeeTableRecords([$deBasica, $admin])
            ->assertTableFilterHidden('componente');
    }

    public function test_doc25_ca08_administracion_elige_cualquiera_y_el_cambio_queda_en_la_auditoria(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);

        $this->actingAs($admin)->post(route('admin.usuarios.store'), $this->alta('lectura', $this->basica->id))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->basica->id, (int) $this->pivote('1122334455')->componente_id);
        $this->assertTrue(Auditoria::where('accion', AccionAuditoria::UsuarioCreado->value)->where('descripcion', 'like', '%en Básica')->exists());

        $this->actingAs($admin)->put(route('admin.usuarios.update', $editor), $this->ficha($editor, 'edicion', $this->basica->id))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->basica->id, $editor->fresh()->componenteEn($this->dependencia));

        $registro = Auditoria::where('accion', AccionAuditoria::UsuarioActualizado->value)->where('auditable_id', $editor->id)->sole();
        $this->assertSame(['componente' => ['antes' => 'Calle', 'despues' => 'Básica']], $registro->datos);
        $this->assertStringContainsString('pasó de Calle a Básica', $registro->descripcion);
    }

    public function test_doc25_ca08_dar_acceso_a_quien_ya_esta_en_la_dependencia_se_rechaza(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $deBasica = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica);
        $deBasica->update(['documento' => '5544332211']);

        $this->actingAs($admin)->post(route('admin.usuarios.store'), $this->alta('coordinacion', $this->calle->id, '55.443.322-11'))
            ->assertSessionHasErrors(['documento' => 'Esa persona ya tiene acceso aquí; edítala desde la lista.']);

        $pivote = $this->pivote('5544332211');
        $this->assertSame('lectura', $pivote->rol);
        $this->assertSame($this->basica->id, (int) $pivote->componente_id);
    }

    public function test_doc25_ca09_administracion_ve_a_todos_y_bajarla_a_coordinacion_exige_componente(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $otroAdmin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $deCalle = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);
        $deBasica = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);

        $this->assertNull($admin->componenteEn($this->dependencia));

        $this->actingAs($admin);
        app(ContextoDependencia::class)->establecer($this->dependencia);
        Livewire::test(Usuarios::class)
            ->assertCanSeeTableRecords([$admin, $otroAdmin, $deCalle, $deBasica])
            ->filterTable('componente', $this->basica->id)
            ->assertCanSeeTableRecords([$deBasica])
            ->assertCanNotSeeTableRecords([$deCalle, $admin]);

        $this->actingAs($admin)->get(route('admin.usuarios.edit', $deBasica))->assertOk();

        // Otra cuenta de Administración solo la gestiona el superadministrador (P-02).
        $this->actingAs($admin)->put(route('admin.usuarios.update', $otroAdmin), $this->ficha($otroAdmin, 'coordinacion', $this->calle->id))
            ->assertForbidden();

        $jefe = User::factory()->superadmin()->create();
        $this->actingAs($jefe)->put(route('admin.usuarios.update', $otroAdmin), $this->ficha($otroAdmin, 'coordinacion', null))
            ->assertSessionHasErrors('componente_id');
        $this->actingAs($jefe)->put(route('admin.usuarios.update', $otroAdmin), $this->ficha($otroAdmin, 'coordinacion', $this->basica->id))
            ->assertSessionHasNoErrors();

        $otroAdmin = $otroAdmin->fresh();
        $this->assertSame(RolDependencia::Coordinacion, $otroAdmin->rolEn($this->dependencia));
        $this->assertSame($this->basica->id, $otroAdmin->componenteEn($this->dependencia));
    }

    public function test_doc25_ca10_solo_el_superadministrador_da_el_rol_administracion(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->assertNotContains(RolDependencia::Administracion, $admin->rolesAsignablesEn($this->dependencia));

        $this->actingAs($admin)->post(route('admin.usuarios.store'), $this->alta('administracion', null))
            ->assertSessionHasErrors('rol');
        $this->actingAs($admin)->put(route('admin.usuarios.update', $editor), $this->ficha($editor, 'administracion', null))
            ->assertSessionHasErrors('rol');
        $this->assertSame(RolDependencia::Edicion, $editor->fresh()->rolEn($this->dependencia));

        $jefe = User::factory()->superadmin()->create();
        $this->actingAs($jefe)->put(route('admin.usuarios.update', $editor), $this->ficha($editor, 'administracion', $this->calle->id))
            ->assertSessionHasNoErrors();
        $this->assertSame(RolDependencia::Administracion, $editor->fresh()->rolEn($this->dependencia));
        $this->assertNull($editor->fresh()->componenteEn($this->dependencia));
    }

    public function test_doc25_cada_quien_edita_su_ficha_sin_cambiar_su_acceso(): void
    {
        // Edwar (2026-11-24): Administración edita su ficha; su acceso lo cambia el superadministrador.
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->actingAs($admin)->get(route('admin.usuarios.edit', $admin))->assertOk();
        $this->actingAs($admin)->put(route('admin.usuarios.update', $admin), ['name' => 'Nombre Nuevo'] + $this->ficha($admin, 'coordinacion', $this->calle->id))
            ->assertSessionHasNoErrors();

        $admin = $admin->fresh();
        $this->assertSame('Nombre Nuevo', $admin->name);
        $this->assertSame(RolDependencia::Administracion, $admin->rolEn($this->dependencia), 'No se baja el rol.');
        $this->assertNull($admin->componenteEn($this->dependencia));
        $this->assertTrue($admin->activo, 'No se desactiva a sí misma.');

        // Coordinación tampoco se cambia de componente ni de rol a sí misma.
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $this->actingAs($coordinador)->put(route('admin.usuarios.update', $coordinador), $this->ficha($coordinador, 'lectura', $this->basica->id))
            ->assertSessionHasNoErrors();
        $this->assertSame(RolDependencia::Coordinacion, $coordinador->fresh()->rolEn($this->dependencia));
        $this->assertSame($this->calle->id, $coordinador->fresh()->componenteEn($this->dependencia));
    }
}
