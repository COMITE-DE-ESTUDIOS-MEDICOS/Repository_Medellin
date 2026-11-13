<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioRemoto;
use Mockery;
use Tests\TestCase;

/**
 * La vuelta de Authentik: quién es esta persona en esta base de datos.
 *
 * DOC-22: el primer ingreso liga por el documento (`preferred_username`) y
 * guarda el `sub`; los siguientes entran por el `sub`. El correo no sirve.
 */
class IngresoAuthentikTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
    }

    public function test_entra_emparejando_por_documento(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['documento' => '1122334455', 'email' => null])->save();

        $this->authentikDevuelve(['preferred_username' => '1122334455']);

        $this->get(route('authentik.callback'))->assertRedirect(route('documentos.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    /** El documento se normaliza igual que al guardarlo: los puntos sobran. */
    public function test_el_documento_con_puntos_tambien_empareja(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['documento' => '1122334455'])->save();

        $this->authentikDevuelve(['preferred_username' => '1.122.334.455']);

        $this->get(route('authentik.callback'))->assertRedirect(route('documentos.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_doc22_ca06_primer_ingreso_por_documento_guarda_el_sub_y_despues_entra_por_sub(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['documento' => '1122334455'])->save();

        $this->authentikDevuelve(
            ['id' => 'sub-123', 'preferred_username' => '1122334455'],
            // Después el username cambia en Authentik: entra igual, por el sub.
            ['id' => 'sub-123', 'preferred_username' => 'OTRO'],
        );

        $this->get(route('authentik.callback'))->assertRedirect(route('documentos.index'));
        $this->assertAuthenticatedAs($usuario);
        $this->assertSame('sub-123', $usuario->fresh()->authentik_sub);

        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();

        $this->get(route('authentik.callback'))->assertRedirect(route('documentos.index'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_doc22_ca06_el_correo_ya_no_sirve_para_entrar(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['email' => 'jefe@cem.test'])->save();

        $this->authentikDevuelve([
            'preferred_username' => 'akadmin',
            'email' => 'jefe@cem.test',
        ]);

        $this->get(route('authentik.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
        $this->assertNull($usuario->fresh()->authentik_sub);
    }

    public function test_doc22_ca07_sin_preferred_username_no_entra_aunque_haya_usuarios_sin_documento(): void
    {
        $sinDocumento = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $sinDocumento->forceFill(['documento' => null])->save();
        $vacio = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $vacio->forceFill(['documento' => ''])->save();

        $usernames = [null, '', '...', ' - '];
        $this->authentikDevuelve(...array_map(fn ($u) => ['preferred_username' => $u], $usernames));

        foreach ($usernames as $username) {
            $this->get(route('authentik.callback'))
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('identificador');

            $this->assertGuest();
        }

        $this->assertNull($sinDocumento->fresh()->authentik_sub);
        $this->assertNull($vacio->fresh()->authentik_sub);
    }

    /** Otra cuenta de Authentik con el mismo documento: no reemplaza el sub guardado. */
    public function test_doc22_ca06_otro_sub_con_el_mismo_documento_no_entra(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['documento' => '1122334455', 'authentik_sub' => 'sub-original'])->save();

        Log::spy();
        $this->authentikDevuelve(['id' => 'sub-intruso', 'preferred_username' => '1122334455']);

        $this->get(route('authentik.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
        $this->assertSame('sub-original', $usuario->fresh()->authentik_sub);
        Log::shouldHaveReceived('warning')->withArgs(fn ($mensaje) => str_contains($mensaje, 'otro sub'));
    }

    public function test_una_identidad_desconocida_no_entra(): void
    {
        $this->authentikDevuelve(['preferred_username' => '9999999999']);

        $this->get(route('authentik.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    /** Authentik puede dar por buena a alguien que aquí está desactivado. */
    public function test_una_cuenta_desactivada_no_entra(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['documento' => '1122334455', 'activo' => false])->save();

        $this->authentikDevuelve(['preferred_username' => '1122334455']);

        $this->get(route('authentik.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    /**
     * PA-01: el formulario propio es la entrada de emergencia del
     * superadministrador. Alguien de Edición con su contraseña correcta no
     * entra, y recibe el mismo mensaje que con una contraseña equivocada.
     */
    public function test_doc22_el_formulario_propio_es_solo_para_el_superadministrador(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->post(route('login'), ['identificador' => $editor->documento, 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors(['identificador' => 'Las credenciales no coinciden con nuestros registros.']);
        $this->assertGuest();

        $super = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $super->forceFill(['es_superadmin' => true])->save();

        $this->post(route('login'), ['identificador' => $super->documento, 'password' => 'password'])
            ->assertRedirect(route('documentos.index'));
        $this->assertAuthenticatedAs($super);
    }

    public function test_doc22_la_pantalla_de_ingreso_destaca_la_cuenta_institucional(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeInOrder(['Entrar con la cuenta institucional', 'Acceso de emergencia (administración)']);
    }

    public function test_doc22_mi_perfil_solo_el_superadministrador_cambia_su_contrasena(): void
    {
        config(['services.authentik.base_url' => 'https://auth.cem.test']);
        $datos = ['password_actual' => 'password', 'password' => 'ClaveNueva123', 'password_confirmation' => 'ClaveNueva123'];

        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->actingAs($editor)->get(route('perfil.edit'))
            ->assertSee('Tu contraseña se cambia en Authentik')
            ->assertSee('https://auth.cem.test/if/user/#/settings', false)
            ->assertDontSee('Actualizar contraseña');
        $this->actingAs($editor)->put(route('perfil.password'), $datos)->assertForbidden();
        $this->assertTrue(Hash::check('password', $editor->fresh()->password));

        $super = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $super->forceFill(['es_superadmin' => true])->save();

        $this->actingAs($super)->get(route('perfil.edit'))->assertSee('Actualizar contraseña');
        $this->actingAs($super)->put(route('perfil.password'), $datos)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('ClaveNueva123', $super->fresh()->password));
    }

    /**
     * Lo que responde Authentik al volver del ingreso, sin salir a la red.
     * Con varios juegos de reclamos, cada ingreso devuelve el siguiente.
     */
    private function authentikDevuelve(array ...$ingresos): void
    {
        $remotos = array_map(fn (array $reclamos) => (new UsuarioRemoto)->map($reclamos + [
            'id' => 'sub-'.fake()->uuid(),
            'name' => 'Quien sea',
            'email' => null,
        ]), $ingresos);

        $proveedor = Mockery::mock(Provider::class);
        $proveedor->shouldReceive('user')->andReturn(...$remotos);

        Socialite::shouldReceive('driver')->with('authentik')->andReturn($proveedor);
    }

    /** El usuario se compara tal cual: «ADMINDOCUMENTA» no liga con «adminDocumenta». */
    public function test_doc22_el_usuario_se_compara_tal_cual(): void
    {
        $jefe = User::factory()->superadmin()->create(['documento' => 'adminDocumenta']);

        $this->authentikDevuelve(['preferred_username' => 'ADMINDOCUMENTA'], ['preferred_username' => 'adminDocumenta']);

        $this->get(route('authentik.callback'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('authentik.callback'));
        $this->assertAuthenticatedAs($jefe);
    }
}
