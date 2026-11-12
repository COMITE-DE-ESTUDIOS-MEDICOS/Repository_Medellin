<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Nodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Las fichas por tarjetas (perfil, persona de campo) cargan y llevan sus partes. */
class FichasTest extends TestCase
{
    use RefreshDatabase;

    public function test_mi_perfil_muestra_datos_apariencia_y_contrasena(): void
    {
        $yo = $this->usuarioCon(RolDependencia::Edicion);

        $this->actingAs($yo)->get(route('perfil.edit'))
            ->assertOk()
            ->assertSee(['Mis datos', 'Apariencia', 'Cambiar contraseña', $yo->name, 'Edición']);
    }

    public function test_la_ficha_de_persona_de_campo_carga_al_registrar_y_al_editar(): void
    {
        $dependencia = Dependencia::factory()->create();
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $dependencia);
        $calle = Componente::factory()->create(['dependencia_id' => $dependencia->id, 'nombre' => 'Calle']);
        Nodo::factory()->create(['componente_id' => $calle->id]);
        $persona = Colaborador::factory()->en($calle)->create(['nombre' => 'Ana Restrepo']);

        $this->actingAs($coordinador)->get(route('admin.turnos.colaboradores.create'))
            ->assertOk()->assertSee(['Quién es', 'Contacto', 'Dónde trabaja', 'Turno habitual', 'Registrar persona']);

        $this->actingAs($coordinador)->get(route('admin.turnos.colaboradores.edit', $persona))
            ->assertOk()->assertSee(['Ana Restrepo', 'Guardar cambios', 'Desactivar', 'form="ficha-persona"'], false);
    }
}
