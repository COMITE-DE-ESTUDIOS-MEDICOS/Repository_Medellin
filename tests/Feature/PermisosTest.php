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
 * Aquí vive la seguridad del sistema. Cada prueba responde a «¿qué es
 * imposible?», no a «¿qué línea se ejecuta?».
 */
class PermisosTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create([
            'nombre' => 'Inclusión Social',
            'slug' => 'inclusion-social',
        ]);
    }

    private function usuario(RolDependencia $rol): User
    {
        return $this->usuarioCon($rol, $this->dependencia);
    }

    private function documento(string $nombre = 'Acta 001', bool $activo = true): Documento
    {
        $factory = Documento::factory();

        if (! $activo) {
            $factory = $factory->inactivo();
        }

        $documento = $factory->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => $nombre,
        ]);

        DocumentoVersion::factory()
            ->conArchivoEnDisco('contenido de '.$nombre)
            ->create(['documento_id' => $documento->id]);

        return $documento;
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que los tres roles sí pueden
    |--------------------------------------------------------------------------
    */

    public function test_los_tres_roles_ven_el_explorador(): void
    {
        foreach (RolDependencia::cases() as $rol) {
            $this->actingAs($this->usuario($rol))
                ->get(route('documentos.index'))
                ->assertOk();
        }
    }

    public function test_los_tres_roles_descargan_un_documento_activo(): void
    {
        $documento = $this->documento();

        foreach (RolDependencia::cases() as $rol) {
            $respuesta = $this->actingAs($this->usuario($rol))
                ->get(route('documentos.descargar', $documento));

            $respuesta->assertOk();

            $this->assertSame(
                'contenido de Acta 001',
                $respuesta->streamedContent(),
                "El rol {$rol->value} no recibió el archivo.",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que solo lectura no puede
    |--------------------------------------------------------------------------
    | En todas se manda una petición impecable: lo que tiene que cortar es el
    | rol, no la validación. Si el 403 llegara por un campo mal puesto, la
    | prueba no estaría demostrando nada.
    */

    public function test_un_lector_no_puede_subir_documentos(): void
    {
        $lector = $this->usuario(RolDependencia::Lectura);

        $this->actingAs($lector)
            ->get(route('documentos.create'))
            ->assertForbidden();

        $this->actingAs($lector)
            ->post(route('documentos.store'), [
                'nombre' => 'Acta que no debería entrar',
                'archivo' => $this->archivoPdf(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('documentos', 0);
        $this->assertEmpty(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_un_lector_no_puede_crear_carpetas(): void
    {
        $lector = $this->usuario(RolDependencia::Lectura);

        $this->actingAs($lector)
            ->get(route('carpetas.create'))
            ->assertForbidden();

        $this->actingAs($lector)
            ->post(route('carpetas.store'), ['nombre' => 'Carpeta prohibida'])
            ->assertForbidden();

        $this->assertDatabaseCount('carpetas', 0);
    }

    public function test_un_lector_no_puede_editar_los_metadatos_de_un_documento(): void
    {
        $documento = $this->documento();
        $lector = $this->usuario(RolDependencia::Lectura);

        $this->actingAs($lector)
            ->get(route('documentos.edit', $documento))
            ->assertForbidden();

        $this->actingAs($lector)
            ->put(route('documentos.update', $documento), ['nombre' => 'Renombrada por un lector'])
            ->assertForbidden();

        $this->assertDatabaseHas('documentos', ['id' => $documento->id, 'nombre' => 'Acta 001']);
    }

    public function test_un_lector_no_puede_subir_una_version_nueva(): void
    {
        $documento = $this->documento();
        $lector = $this->usuario(RolDependencia::Lectura);

        $this->actingAs($lector)
            ->post(route('documentos.versiones.store', $documento), [
                'archivo' => $this->archivoPdf('v2.pdf', 'segunda'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('documento_versiones', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que edición sí puede
    |--------------------------------------------------------------------------
    */

    public function test_edicion_sube_documentos_crea_carpetas_edita_y_versiona(): void
    {
        $editor = $this->usuario(RolDependencia::Edicion);

        $this->actingAs($editor)
            ->post(route('carpetas.store'), ['nombre' => 'Actas de comité'])
            ->assertRedirect();

        $this->assertDatabaseHas('carpetas', ['nombre' => 'Actas de comité']);

        $this->actingAs($editor)
            ->post(route('documentos.store'), [
                'nombre' => 'Acta de julio',
                'archivo' => $this->archivoPdf(),
            ])
            ->assertRedirect();

        $documento = Documento::withoutGlobalScopes()->where('nombre', 'Acta de julio')->firstOrFail();

        $this->actingAs($editor)
            ->put(route('documentos.update', $documento), ['nombre' => 'Acta de julio (corregida)'])
            ->assertRedirect();

        $this->assertDatabaseHas('documentos', ['id' => $documento->id, 'nombre' => 'Acta de julio (corregida)']);

        $this->actingAs($editor)
            ->post(route('documentos.versiones.store', $documento), [
                'archivo' => $this->archivoPdf('v2.pdf', 'segunda'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documento_versiones', ['documento_id' => $documento->id, 'numero' => 2]);
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que solo administración puede
    |--------------------------------------------------------------------------
    */

    public function test_lectura_no_inactiva_y_edicion_inactiva_con_motivo_pero_no_reactiva(): void
    {
        $activo = $this->documento('Acta viva');
        $inactivo = $this->documento('Acta guardada', activo: false);

        $lector = $this->usuario(RolDependencia::Lectura);
        $this->actingAs($lector)->patch(route('documentos.inactivar', $activo), ['motivo' => 'Ya no aplica'])->assertForbidden();
        $this->actingAs($lector)->patch(route('documentos.reactivar', $inactivo))->assertForbidden();

        $editor = $this->usuario(RolDependencia::Edicion);
        $this->actingAs($editor)->patch(route('documentos.inactivar', $activo), [])->assertSessionHasErrors('motivo');
        $this->actingAs($editor)->patch(route('documentos.inactivar', $activo), ['motivo' => 'Duplicado'])->assertRedirect();
        $this->actingAs($editor)->patch(route('documentos.reactivar', $inactivo))->assertForbidden();

        $this->assertDatabaseHas('documentos', ['id' => $activo->id, 'activo' => false, 'motivo_inactivacion' => 'Duplicado']);
        $this->assertDatabaseHas('documentos', ['id' => $inactivo->id, 'activo' => false]);
    }

    public function test_administracion_inactiva_y_reactiva(): void
    {
        $documento = $this->documento();
        $admin = $this->usuario(RolDependencia::Administracion);

        $this->actingAs($admin)
            ->patch(route('documentos.inactivar', $documento), ['motivo' => 'Duplicado'])
            ->assertRedirect();

        $this->assertDatabaseHas('documentos', ['id' => $documento->id, 'activo' => false]);

        $this->actingAs($admin)
            ->patch(route('documentos.reactivar', $documento))
            ->assertRedirect();

        $this->assertDatabaseHas('documentos', ['id' => $documento->id, 'activo' => true]);
    }

    public function test_solo_administracion_entra_a_usuarios_y_a_auditoria(): void
    {
        foreach ([RolDependencia::Lectura, RolDependencia::Edicion] as $rol) {
            $usuario = $this->usuario($rol);

            $this->actingAs($usuario)->get(route('admin.usuarios.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('auditoria.index'))->assertForbidden();
        }

        $admin = $this->usuario(RolDependencia::Administracion);

        $this->actingAs($admin)->get(route('admin.usuarios.index'))->assertOk();
        $this->actingAs($admin)->get(route('auditoria.index'))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Documentos inactivos
    |--------------------------------------------------------------------------
    */

    public function test_un_documento_inactivo_desaparece_para_lectura_y_edicion(): void
    {
        $this->documento('Acta publica');
        $inactivo = $this->documento('Acta reservada', activo: false);

        foreach ([RolDependencia::Lectura, RolDependencia::Edicion] as $rol) {
            $usuario = $this->usuario($rol);

            $this->actingAs($usuario)
                ->get(route('documentos.index'))
                ->assertOk()
                ->assertSee('Acta publica')
                ->assertDontSee('Acta reservada');

            $this->actingAs($usuario)
                ->get(route('documentos.show', $inactivo))
                ->assertForbidden();
        }
    }

    public function test_administracion_sigue_viendo_el_documento_inactivo(): void
    {
        $inactivo = $this->documento('Acta reservada', activo: false);
        $admin = $this->usuario(RolDependencia::Administracion);

        $this->actingAs($admin)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Acta reservada');

        $this->actingAs($admin)
            ->get(route('documentos.show', $inactivo))
            ->assertOk();
    }

    /**
     * DocumentoPolicy::update exige $documento->activo, así que un documento
     * inactivo queda congelado hasta que alguien lo reactive. Es coherente con
     * la trazabilidad que persigue el proyecto, pero conviene confirmarlo:
     * si algún día se quisiera corregir metadatos sin reactivar, esta prueba
     * es la que hay que cambiar.
     */
    public function test_un_documento_inactivo_no_se_puede_editar_ni_siquiera_administrando(): void
    {
        $inactivo = $this->documento('Acta reservada', activo: false);
        $admin = $this->usuario(RolDependencia::Administracion);

        $this->actingAs($admin)
            ->get(route('documentos.edit', $inactivo))
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('documentos.update', $inactivo), ['nombre' => 'Otro nombre'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('documentos.versiones.store', $inactivo), ['archivo' => $this->archivoPdf()])
            ->assertForbidden();

        $this->assertDatabaseHas('documentos', ['id' => $inactivo->id, 'nombre' => 'Acta reservada']);
    }

    /*
    |--------------------------------------------------------------------------
    | Sin sesión
    |--------------------------------------------------------------------------
    */

    public function test_sin_sesion_todas_las_rutas_protegidas_llevan_al_ingreso(): void
    {
        $documento = $this->documento();
        $version = $documento->versiones()->firstOrFail();
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);

        $rutas = [
            ['get', route('documentos.index')],
            ['get', route('documentos.create')],
            ['post', route('documentos.store')],
            ['get', route('documentos.show', $documento)],
            ['get', route('documentos.edit', $documento)],
            ['put', route('documentos.update', $documento)],
            ['post', route('documentos.versiones.store', $documento)],
            ['get', route('documentos.descargar', $documento)],
            ['get', route('documentos.versiones.descargar', [$documento, $version])],
            ['get', route('documentos.previsualizar', $documento)],
            ['patch', route('documentos.inactivar', $documento)],
            ['patch', route('documentos.reactivar', $documento)],
            ['get', route('carpetas.create')],
            ['post', route('carpetas.store')],
            ['get', route('carpetas.edit', $carpeta)],
            ['put', route('carpetas.update', $carpeta)],
            ['patch', route('carpetas.inactivar', $carpeta)],
            ['patch', route('carpetas.reactivar', $carpeta)],
            ['get', route('perfil.edit')],
            ['put', route('perfil.update')],
            ['put', route('perfil.password')],
            ['get', route('admin.usuarios.index')],
            ['get', route('admin.usuarios.create')],
            ['post', route('admin.usuarios.store')],
            ['get', route('admin.tipos.index')],
            ['post', route('admin.tipos.store')],
            ['get', route('auditoria.index')],
            ['put', route('dependencia.cambiar', $this->dependencia)],
        ];

        $fallos = [];

        foreach ($rutas as [$metodo, $url]) {
            $respuesta = $this->{$metodo}($url);

            if (! $respuesta->isRedirect(route('login'))) {
                $fallos[] = strtoupper($metodo).' '.$url.' → '.$respuesta->status();
            }
        }

        $this->assertSame([], $fallos, "Rutas que no llevan al ingreso:\n".implode("\n", $fallos));
        $this->assertGuest();
    }
}
