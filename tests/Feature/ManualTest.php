<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El manual de usuario: para todos los roles, con su versión para guardar en PDF. */
class ManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_cualquier_rol_lo_abre_desde_el_menu_y_lo_guarda_en_pdf(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura);

        $this->actingAs($lector)->get(route('documentos.index'))->assertSee(route('manual'), false); // en el menú de su cuenta

        $this->actingAs($lector)->get(route('manual'))
            ->assertOk()
            ->assertSee('Manual de usuario')
            ->assertSee('Roles y permisos')
            ->assertSee(route('manual.pdf'), false);

        $this->actingAs($lector)->get(route('manual.pdf'))
            ->assertOk()
            ->assertSee('Guardar como PDF')
            ->assertSee('@page', false)
            ->assertSee('Preguntas frecuentes');
    }

    public function test_sin_sesion_no(): void
    {
        $this->get(route('manual'))->assertRedirect(route('login'));
    }
}
