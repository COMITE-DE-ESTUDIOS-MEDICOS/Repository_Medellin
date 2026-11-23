<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Autenticación propia y mínima: los usuarios los crea un administrador.
 * No hay registro público ni recuperación por correo, y eso es una decisión
 * de diseño que conviene dejar amarrada.
 *
 * Desde DOC-22 el formulario propio es solo la entrada de emergencia del
 * superadministrador: por eso las pruebas que entran por él usan uno.
 */
class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE_GENERICO = 'Las credenciales no coinciden con nuestros registros.';

    public function test_las_credenciales_correctas_abren_sesion(): void
    {
        $usuario = $this->superadmin();

        $this->post(route('login'), ['identificador' => $usuario->documento, 'password' => 'password'])
            ->assertRedirect(route('documentos.index'));

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotNull($usuario->fresh()->ultimo_acceso_at);
    }

    public function test_las_credenciales_incorrectas_no_dicen_cual_campo_fallo(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        // Documento que no existe.
        $this->post(route('login'), ['identificador' => '9999999999', 'password' =>'password'])
            ->assertRedirect()
            ->assertSessionHasErrors(['identificador' => self::MENSAJE_GENERICO])
            ->assertSessionDoesntHaveErrors('password');

        $this->assertGuest();

        // Documento que sí existe, contraseña equivocada: el mismo mensaje.
        // Si dijera «la contraseña es incorrecta» estaría confirmando que
        // ese documento tiene cuenta.
        $this->post(route('login'), ['identificador' => $usuario->documento, 'password' => 'incorrecta'])
            ->assertRedirect()
            ->assertSessionHasErrors(['identificador' => self::MENSAJE_GENERICO])
            ->assertSessionDoesntHaveErrors('password');

        $this->assertGuest();
    }

    /**
     * El mensaje no delata la cuenta, pero el reloj tampoco debe hacerlo.
     *
     * Auth::attempt iguala la duración de todo fallo con el «timebox» de
     * Laravel. Se entra en él siempre, también sin usuario: cortocircuitar
     * antes devolvía el intento inventado en un par de milisegundos y el real
     * en doscientos, y esa diferencia basta para ir sacando qué documentos
     * están dados de alta.
     */
    public function test_el_reloj_tampoco_dice_que_cuentas_existen(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        // El limitador de seis intentos por minuto estorba a la medición;
        // lo que se comprueba aquí es el controlador, no el limitador.
        $this->withoutMiddleware(ThrottleRequests::class);

        $medir = function (string $identificador): float {
            $this->flushSession();

            $inicio = microtime(true);
            $respuesta = $this->post(route('login'), [
                'identificador' => $identificador,
                'password' => 'incorrecta',
            ]);
            $ms = (microtime(true) - $inicio) * 1000;

            // Un error 500 se cronometraría como si fuera el camino bueno.
            $respuesta->assertRedirect()->assertSessionHasErrors('identificador');

            return $ms;
        };

        $medir($usuario->documento); // calentamiento: la primera paga el arranque

        $existentes = $inventados = [];

        for ($i = 0; $i < 3; $i++) {
            $existentes[] = $medir($usuario->documento);
            $inventados[] = $medir('9999999999');
        }

        // Se comparan los mínimos: la media la ensucia cualquier pausa del
        // recolector de basura, el mínimo es el suelo real de cada camino.
        $this->assertLessThan(
            50,
            abs(min($inventados) - min($existentes)),
            'El ingreso tarda distinto según si la cuenta existe: eso permite enumerarlas.',
        );
    }

    /**
     * La cédula se escribe con puntos en unos sitios y sin ellos en otros.
     * Da igual cómo se teclee: se normaliza antes de buscar.
     */
    public function test_el_documento_se_puede_escribir_con_puntos_o_sin_ellos(): void
    {
        $usuario = $this->superadmin();
        $usuario->update(['documento' => '1234567890']);

        foreach (['1234567890', '1.234.567.890', '1 234 567 890', ' 1234567890 '] as $comoLoEscribe) {
            $this->post(route('login'), ['identificador' => $comoLoEscribe, 'password' => 'password'])
                ->assertRedirect(route('documentos.index'));

            $this->assertAuthenticatedAs($usuario);

            $this->post(route('logout'));
            $this->app['auth']->forgetGuards();
        }
    }

    /**
     * Las tres formas llevan a la misma cuenta y a la misma contraseña. El
     * documento es la identidad; el usuario y el correo son atajos, para que
     * nadie tenga que recordar cuál eligió quien lo dio de alta.
     */
    public function test_se_entra_con_documento_con_usuario_o_con_correo(): void
    {
        $usuario = $this->superadmin();

        $usuario->update([
            'documento' => '1234567890',
            'usuario' => 'jamaya',
            'email' => 'jamaya@medellin.gov.co',
        ]);

        foreach (['1234567890', 'jamaya', 'JAMAYA', 'jamaya@medellin.gov.co'] as $identificador) {
            $this->post(route('login'), ['identificador' => $identificador, 'password' => 'password'])
                ->assertRedirect(route('documentos.index'));

            $this->assertTrue(auth()->check(), "No entró con «{$identificador}».");
            $this->assertAuthenticatedAs($usuario);

            $this->post(route('logout'));
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_un_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $usuario = $this->superadmin();
        $usuario->update(['activo' => false]);

        $this->post(route('login'), ['identificador' => $usuario->documento, 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    public function test_desactivar_a_alguien_con_la_sesion_abierta_lo_expulsa_en_la_siguiente_peticion(): void
    {
        $usuario = $this->superadmin();

        $this->post(route('login'), ['identificador' => $usuario->documento, 'password' => 'password']);
        $this->get(route('documentos.index'))->assertOk();

        // Un administrador lo desactiva mientras la persona sigue navegando.
        User::whereKey($usuario->id)->update(['activo' => false]);

        // En producción cada petición arranca con el contenedor limpio y
        // relee al usuario de la base. Dentro de una prueba el guard queda
        // vivo entre peticiones y devolvería el objeto de antes, así que se
        // le olvida a mano: sin esto no se estaría probando el middleware,
        // sino la caché del guard.
        $this->app['auth']->forgetGuards();

        $this->get(route('documentos.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    public function test_no_existe_ruta_de_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->get('/registro')->assertNotFound();

        // Tampoco recuperación de contraseña por correo.
        $this->assertNull(Route::getRoutes()->getByName('register'));
        $this->assertNull(Route::getRoutes()->getByName('password.request'));
        $this->assertNull(Route::getRoutes()->getByName('password.email'));
    }

    public function test_el_ingreso_se_limita_a_seis_intentos_por_minuto(): void
    {
        for ($intento = 1; $intento <= 6; $intento++) {
            $this->post(route('login'), ['identificador' => '9999999999', 'password' =>'mala'.$intento])
                ->assertStatus(302);
        }

        $this->post(route('login'), ['identificador' => '9999999999', 'password' =>'mala7'])
            ->assertStatus(429);
    }

    public function test_cambiar_la_contrasena_exige_la_contrasena_actual(): void
    {
        $usuario = $this->superadmin();

        $this->actingAs($usuario)
            ->put(route('perfil.password'), [
                'password_actual' => 'la-que-no-es',
                'password' => 'ClaveNueva123',
                'password_confirmation' => 'ClaveNueva123',
            ])
            ->assertSessionHasErrors('password_actual');

        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));

        $this->actingAs($usuario)
            ->put(route('perfil.password'), [
                'password_actual' => 'password',
                'password' => 'ClaveNueva123',
                'password_confirmation' => 'ClaveNueva123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('ClaveNueva123', $usuario->fresh()->password));
    }

    public function test_cerrar_sesion_deja_al_usuario_fuera(): void
    {
        $usuario = $this->superadmin();

        $this->post(route('login'), ['identificador' => $usuario->documento, 'password' => 'password']);
        $this->assertAuthenticated();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('documentos.index'))->assertRedirect(route('login'));
    }

    /** Quien puede usar el formulario propio (DOC-22, PA-01). */
    private function superadmin(): User
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);
        $usuario->forceFill(['es_superadmin' => true])->save();

        return $usuario;
    }
}
