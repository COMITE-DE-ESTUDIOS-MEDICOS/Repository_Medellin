<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quien ya inició sesión y abre «/» o «ingresar» (páginas de invitado) va a la
 * llegada. Antes Laravel lo mandaba a «/», que también es de invitado, y el
 * navegador quedaba en un ciclo de redirecciones.
 */
class SesionIniciadaRedireccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_sesion_la_raiz_y_el_ingreso_llevan_a_la_llegada(): void
    {
        $usuario = User::factory()->create();

        foreach (['/', '/ingresar'] as $direccion) {
            $this->actingAs($usuario)->get($direccion)->assertRedirect(route('inicio'));
        }
    }

    public function test_sin_sesion_la_raiz_sigue_llevando_al_ingreso(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
