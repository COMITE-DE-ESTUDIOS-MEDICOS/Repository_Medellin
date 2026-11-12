<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coordinación administra personas y estructura, pero no retira contenido.
 *
 * La línea es esa: puede crear usuarios, carpetas, enlaces y tipos, y ver la
 * auditoría; no puede esconder de la dependencia una carpeta ni un documento,
 * ni ver lo que ya está escondido.
 */
class CoordinacionTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    private function documento(string $nombre = 'Acta 001', bool $activo = true): Documento
    {
        $factory = Documento::factory();

        if (! $activo) {
            $factory = $factory->inactivo();
        }

        $documento = $factory->create(['dependencia_id' => $this->dependencia->id, 'nombre' => $nombre]);

        DocumentoVersion::factory()
            ->conArchivoEnDisco('contenido')
            ->create(['documento_id' => $documento->id]);

        return $documento;
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que sí puede
    |--------------------------------------------------------------------------
    */

    public function test_gestiona_usuarios_tipos_enlaces_y_auditoria(): void
    {
        foreach ([
            route('admin.usuarios.index'),
            route('admin.usuarios.create'),
            route('admin.tipos.index'),
            route('admin.enlaces.index'),
            route('admin.enlaces.create'),
            route('auditoria.index'),
        ] as $url) {
            $this->actingAs($this->coordinador)->get($url)->assertOk();
        }
    }

    public function test_crea_usuarios_en_su_dependencia(): void
    {
        $this->actingAs($this->coordinador)
            ->post(route('admin.usuarios.store'), [
                'name' => 'Nueva Auxiliar',
                'documento' => '1122334455',
                'email' => 'nueva.auxiliar@medellin.gov.co',
                'rol' => RolDependencia::Edicion->value,
                'activo' => 1,
                'password' => 'ClaveNueva123',
                'password_confirmation' => 'ClaveNueva123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $nueva = User::where('documento', '1122334455')->firstOrFail();

        $this->assertSame(RolDependencia::Edicion, $nueva->rolEn($this->dependencia));
    }

    public function test_crea_y_edita_carpetas_como_edicion(): void
    {
        $this->actingAs($this->coordinador)
            ->post(route('carpetas.store'), ['nombre' => 'Actas de comité'])
            ->assertRedirect();

        $carpeta = Carpeta::withoutGlobalScopes()->where('nombre', 'Actas de comité')->firstOrFail();

        $this->actingAs($this->coordinador)
            ->put(route('carpetas.update', $carpeta), ['nombre' => 'Actas de comité 2026'])
            ->assertRedirect();

        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'nombre' => 'Actas de comité 2026']);
    }

    public function test_sube_documentos_y_versiones(): void
    {
        $this->actingAs($this->coordinador)
            ->post(route('documentos.store'), [
                'archivo' => [$this->archivoPdf('acta.pdf')],
                'nombres' => ['Acta de septiembre'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documentos', ['nombre' => 'Acta de septiembre']);
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que no puede: retirar contenido
    |--------------------------------------------------------------------------
    */

    public function test_puede_inactivar_y_reactivar_carpetas(): void
    {
        $activa = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $inactiva = Carpeta::factory()->inactiva()->create(['dependencia_id' => $this->dependencia->id]);

        $this->actingAs($this->coordinador)
            ->patch(route('carpetas.inactivar', $activa))
            ->assertRedirect();

        $this->actingAs($this->coordinador)
            ->patch(route('carpetas.reactivar', $inactiva))
            ->assertRedirect();

        $this->assertDatabaseHas('carpetas', ['id' => $activa->id, 'activa' => false]);
        $this->assertDatabaseHas('carpetas', ['id' => $inactiva->id, 'activa' => true]);
    }

    public function test_inactiva_documentos_pero_no_los_reactiva(): void
    {
        $activo = $this->documento('Acta viva');
        $inactivo = $this->documento('Acta guardada', activo: false);

        $this->actingAs($this->coordinador)
            ->patch(route('documentos.inactivar', $activo), ['motivo' => 'Ya no aplica'])
            ->assertRedirect();

        $this->actingAs($this->coordinador)
            ->patch(route('documentos.reactivar', $inactivo))
            ->assertForbidden();

        $this->assertDatabaseHas('documentos', ['id' => $activo->id, 'activo' => false]);
        $this->assertDatabaseHas('documentos', ['id' => $inactivo->id, 'activo' => false]);
    }

    /** Si viera lo retirado, la restricción anterior sería decorativa. */
    public function test_no_ve_lo_que_administracion_ya_retiro(): void
    {
        $this->documento('Acta publica');
        $inactivo = $this->documento('Acta reservada', activo: false);

        $this->actingAs($this->coordinador)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Acta publica')
            ->assertDontSee('Acta reservada');

        $this->actingAs($this->coordinador)
            ->get(route('documentos.show', $inactivo))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que no puede: ascender a nadie, ni a sí mismo
    |--------------------------------------------------------------------------
    */

    public function test_no_puede_crear_un_administrador(): void
    {
        $this->actingAs($this->coordinador)
            ->post(route('admin.usuarios.store'), [
                'name' => 'Aspirante',
                'documento' => '5566778899',
                'email' => 'aspirante@medellin.gov.co',
                'rol' => RolDependencia::Administracion->value,
                'activo' => 1,
            ])
            ->assertSessionHasErrors('rol');

        $this->assertDatabaseMissing('users', ['documento' => '5566778899']);
    }

    public function test_el_formulario_no_le_ofrece_el_rol_de_administracion(): void
    {
        $this->actingAs($this->coordinador)
            ->get(route('admin.usuarios.create'))
            ->assertOk()
            ->assertSee('Coordinación')
            ->assertDontSee('value="administracion"', false);
    }

    public function test_no_puede_tocar_la_ficha_de_un_administrador(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->actingAs($this->coordinador)->get(route('admin.usuarios.edit', $admin))->assertForbidden();

        $this->actingAs($this->coordinador)
            ->put(route('admin.usuarios.update', $admin), [
                'name' => 'Degradado',
                'documento' => $admin->documento,
                'email' => $admin->email,
                'rol' => RolDependencia::Lectura->value,
                'activo' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->coordinador)
            ->delete(route('admin.usuarios.revocar', $admin))
            ->assertForbidden();

        $this->assertSame(RolDependencia::Administracion, $admin->fresh()->rolEn($this->dependencia));
    }

    /** La cuenta de plataforma no se gestiona desde una dependencia. */
    public function test_nadie_de_la_dependencia_toca_al_superadmin(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $superadmin = User::factory()->superadmin()->create();
        $this->darRol($superadmin, RolDependencia::Administracion, $this->dependencia);

        $this->actingAs($admin)->get(route('admin.usuarios.edit', $superadmin))->assertForbidden();
        $this->actingAs($this->coordinador)->get(route('admin.usuarios.edit', $superadmin))->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Y edición sigue sin gestionar nada
    |--------------------------------------------------------------------------
    */

    public function test_edicion_no_alcanza_lo_que_coordinacion_si(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        foreach ([
            route('admin.usuarios.index'),
            route('admin.tipos.index'),
            route('auditoria.index'),
        ] as $url) {
            $this->actingAs($editor)->get($url)->assertForbidden();
        }
    }
}
