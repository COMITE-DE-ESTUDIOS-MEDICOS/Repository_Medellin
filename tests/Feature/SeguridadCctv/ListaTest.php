<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\RolDependencia;
use App\Livewire\SeguridadCctv\Casos;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** DOC-33 (CCTV-05): la lista de casos con Filament. */
class ListaTest extends TestCase
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

    private function entrar(RolDependencia $rol = RolDependencia::Edicion, bool $casilla = false): User
    {
        $usuario = $this->usuarioCon($rol, $this->dependencia, $this->calle);
        $usuario->dependencias()->updateExistingPivot($this->dependencia->id, ['responsable_cctv' => $casilla]);
        $this->actingAs($usuario->unsetRelation('dependencias'));

        // Livewire::test no pasa por los middlewares: dependencia y componente a mano.
        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($this->dependencia);
        $contexto->establecerComponente($this->calle);

        return $usuario;
    }

    public function test_doc33_ca01_la_pagina_solo_abre_con_el_modulo_y_trae_los_botones_segun_el_permiso(): void
    {
        $edicion = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);
        $this->actingAs($edicion)->get(route('admin.turnos.cctv.index'))
            ->assertOk()
            ->assertSee('Reporte')
            ->assertDontSee('Nuevo caso')
            ->assertDontSee('Enlace para atender');

        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia, $this->calle);
        $this->actingAs($admin)->get(route('admin.turnos.cctv.index'))
            ->assertOk()
            ->assertSee('Nuevo caso')
            ->assertSee('Enlace para atender');
    }

    public function test_doc33_ca01_sin_el_modulo_la_pagina_no_abre_ni_por_direccion_directa(): void
    {
        $sinModulo = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica);
        $this->actingAs($sinModulo)->get(route('admin.turnos.cctv.index'))->assertForbidden();
    }

    public function test_doc33_ca02_la_lista_solo_trae_los_casos_del_componente(): void
    {
        $propio = CasoCctv::factory()->de($this->calle)->create();
        $ajeno = CasoCctv::factory()->de($this->basica)->create();
        $this->entrar();

        Livewire::test(Casos::class)
            ->assertCanSeeTableRecords([$propio])
            ->assertCanNotSeeTableRecords([$ajeno])
            ->assertSee($propio->codigo());
    }

    public function test_doc33_ca01_la_lista_muestra_el_tiempo_en_horas_y_minutos_sin_vencido(): void
    {
        CasoCctv::factory()->de($this->calle)->atendido(85)->create();
        $this->entrar();

        Livewire::test(Casos::class)
            ->assertSee('1 h 25 min')
            ->assertDontSee('encido');
    }

    public function test_doc33_ca01_filtra_por_estado_entidad_comuna_y_nodo(): void
    {
        $abierto = CasoCctv::factory()->de($this->calle)->create();
        $atendido = CasoCctv::factory()->de($this->calle)->atendido()->create(['entidad' => 'policia', 'comuna' => 5]);
        $this->entrar();

        Livewire::test(Casos::class)
            ->filterTable('estado', CasoCctv::ATENDIDO)
            ->assertCanSeeTableRecords([$atendido])->assertCanNotSeeTableRecords([$abierto])
            ->removeTableFilters()
            ->filterTable('entidad', 'policia')
            ->assertCanSeeTableRecords([$atendido])->assertCanNotSeeTableRecords([$abierto])
            ->removeTableFilters()
            ->filterTable('comuna', 5)
            ->assertCanSeeTableRecords([$atendido])->assertCanNotSeeTableRecords([$abierto])
            ->removeTableFilters()
            ->filterTable('nodo_id', $abierto->nodo_id ?? 0)
            ->assertCountTableRecords($abierto->nodo_id ? 2 : 0);
    }

    public function test_doc33_ca01_filtra_por_rango_de_fechas_del_requerimiento_con_los_dos_dias_incluidos(): void
    {
        $antes = CasoCctv::factory()->de($this->calle)->create(['requerido_at' => '2026-11-09 23:59:00']);
        $inicio = CasoCctv::factory()->de($this->calle)->create(['requerido_at' => '2026-11-10 00:00:00']);
        $fin = CasoCctv::factory()->de($this->calle)->create(['requerido_at' => '2026-11-12 23:59:59']);
        $despues = CasoCctv::factory()->de($this->calle)->create(['requerido_at' => '2026-11-13 00:00:00']);
        $this->entrar();

        Livewire::test(Casos::class)
            ->filterTable('requerimiento', ['desde' => '2026-11-10', 'hasta' => '2026-11-12'])
            ->assertCanSeeTableRecords([$inicio, $fin])
            ->assertCanNotSeeTableRecords([$antes, $despues]);
    }
}
