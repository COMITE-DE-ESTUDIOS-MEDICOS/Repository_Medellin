<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El menú lateral por grupos: qué ve cada rol, qué viene abierto y los contadores. */
class MenuPorGruposTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
    }

    public function test_lectura_no_ve_los_grupos_de_turnos_ni_de_administracion(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)->get(route('documentos.index'))
            ->assertSee('Documentos')
            ->assertDontSee('data-grupo-menu="turnos"', false)
            ->assertDontSee('data-grupo-menu="administracion"', false);
    }

    public function test_coordinacion_ve_turnos_en_el_orden_del_trabajo(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);

        $this->actingAs($coordinador)->get(route('documentos.index'))
            ->assertSeeInOrder(['Turnos', 'Enlaces por cédula', 'Personas de campo', 'En turno ahora', 'Novedades', 'Marcaciones y reporte'])
            ->assertSeeInOrder(['Atención territorial', 'Derivaciones'])
            ->assertSeeInOrder(['Administración', 'Componentes y nodos', 'Usuarios', 'Tipos de documento', 'Auditoría'])
            ->assertDontSee('Enviar aviso');
    }

    public function test_administracion_ve_los_componentes_en_administracion(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->actingAs($admin)->get(route('documentos.index'))
            ->assertSeeInOrder(['Administración', 'Componentes y nodos', 'Usuarios', 'Tipos de documento', 'Enviar aviso', 'Auditoría']);
    }

    public function test_edicion_gestiona_todo_turnos_menos_los_componentes(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->actingAs($editor)->get(route('documentos.index'))
            ->assertSeeInOrder(['Turnos', 'Enlaces por cédula', 'Personas de campo', 'En turno ahora', 'Novedades', 'Marcaciones y reporte'])
            ->assertSeeInOrder(['Atención territorial', 'Derivaciones'])
            ->assertDontSee('Componentes y nodos')
            ->assertDontSee('data-grupo-menu="administracion"', false);
    }

    public function test_lo_principal_no_va_en_un_desplegable(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)->get(route('documentos.index'))
            ->assertSee('<div data-grupo-menu="principal">', false)
            ->assertDontSee('<details data-grupo-menu="principal"', false);
    }

    public function test_el_grupo_de_la_pagina_actual_viene_abierto_y_las_pantallas_hijas_marcan_su_enlace(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $persona = Colaborador::factory()->en(Componente::factory()->create(['dependencia_id' => $this->dependencia->id]))->create();

        $html = $this->actingAs($coordinador)
            ->get(route('admin.turnos.colaboradores.show', $persona))
            ->getContent();

        $this->assertMatchesRegularExpression('/data-grupo-menu="turnos"\s+open/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-grupo-menu="administracion"\s+open/', $html);
        $this->assertMatchesRegularExpression('#'.preg_quote(route('admin.turnos.colaboradores.index'), '#').'"\s+aria-current="page"#', $html);
    }

    public function test_cerrado_el_grupo_muestra_sus_pendientes_en_el_titulo(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $componente = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);
        Colaborador::factory()->en($componente)->autoregistrado()->count(3)->create();

        $this->actingAs($coordinador)->get(route('documentos.index'))
            ->assertSee('aria-label="3 pendientes"', false);
    }

    public function test_el_menu_tiene_scroll_sin_barra(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)->get(route('documentos.index'))
            ->assertSee('sin-barra flex-1', false);
    }
}
