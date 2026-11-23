<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Livewire\Turnos\Componentes;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Nodo;
use App\Models\User;
use App\Services\ContextoDependencia;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Componentes y nodos: tabla y panel de Filament, de Coordinación en adelante. */
class AdminComponentesTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
    }

    /** Livewire::test no pasa por los middlewares: la dependencia se pone a mano. */
    private function como(User $usuario): void
    {
        $this->actingAs($usuario);
        app(ContextoDependencia::class)->establecer($this->dependencia);
    }

    private function datos(array $cambios = []): array
    {
        return array_replace_recursive([
            'nombre' => 'Estabilización',
            'config' => [
                'cargos' => ['Educador', 'Educador', '  Operador   terapéutico '],
                'tolerancia_min' => 10, 'jornada_horas' => 8, 'horas_max_turno' => 12, 'turnos_por_dia' => 1,
                'sin_descanso_hasta_h' => 4, 'descanso_parcial_min' => 30, 'descanso_completo_desde_h' => 6, 'descanso_min' => 60,
                'reconocer_antes_min' => 90, 'reconocer_despues_min' => 180, 'aviso_sin_entrada_min' => 30,
                'foto_obligatoria' => true, 'ubicacion_obligatoria' => false, 'autoregistro' => true,
            ],
        ], $cambios);
    }

    public function test_crear_un_componente_guarda_reglas_cargos_limpios_y_nodos(): void
    {
        $this->como($this->admin);

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(CreateAction::class)->table(), $this->datos([
                'nodos' => [['nombre' => 'Nodo 1', 'activo' => true], ['nombre' => 'Nodo 2', 'activo' => true]],
            ]))
            ->assertHasNoFormErrors();

        $c = Componente::where('slug', 'estabilizacion')->sole();
        $this->assertSame($this->dependencia->id, $c->dependencia_id);
        $this->assertSame(['Educador', 'Operador terapéutico'], $c->cargos(), 'Sin repetidos ni espacios de más.');
        $this->assertSame(10, $c->regla('tolerancia_min'));
        $this->assertTrue($c->regla('foto_obligatoria'));
        $this->assertFalse($c->regla('ubicacion_obligatoria'));
        $this->assertSame(['Nodo 1', 'Nodo 2'], $c->nodos()->pluck('nombre')->all());
    }

    /** El formulario viejo guardaba en 0 las reglas que no tenía: el turno automático se apagaba. */
    public function test_editar_no_borra_las_reglas_del_turno_automatico(): void
    {
        $calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->como($this->admin);

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(EditAction::class)->table($calle), ['config' => ['tolerancia_min' => 20]])
            ->assertHasNoFormErrors();

        $calle->refresh();
        $this->assertSame(20, $calle->regla('tolerancia_min'));
        $this->assertSame(90, $calle->regla('reconocer_antes_min'));
        $this->assertSame(30, $calle->regla('aviso_sin_entrada_min'));
    }

    public function test_no_se_repite_el_nombre_en_la_misma_dependencia(): void
    {
        Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->como($this->admin);

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(CreateAction::class)->table(), $this->datos(['nombre' => 'calle']))
            ->assertHasFormErrors(['nombre']);
    }

    public function test_las_reglas_se_validan_entre_si(): void
    {
        $this->como($this->admin);

        Livewire::test(Componentes::class)
            ->callAction(TestAction::make(CreateAction::class)->table(), $this->datos([
                'config' => ['horas_max_turno' => 6, 'jornada_horas' => 8, 'descanso_parcial_min' => 90, 'descanso_min' => 60],
            ]))
            ->assertHasFormErrors(['config.horas_max_turno', 'config.descanso_parcial_min']);
    }

    public function test_el_descanso_muestra_lo_que_da_cada_turno_con_los_valores_puestos(): void
    {
        $calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle',
            'config' => ['sin_descanso_hasta_h' => 4, 'descanso_parcial_min' => 30, 'descanso_completo_desde_h' => 6, 'descanso_min' => 30]]);
        \App\Models\TipoTurno::create(['componente_id' => $calle->id, 'codigo' => 'T4', 'nombre' => 'Viernes mañana', 'es_turno' => true, 'hora_inicio' => '06:00', 'hora_fin' => '10:00', 'horas_nomina' => 4, 'activo' => true]);
        \App\Models\TipoTurno::create(['componente_id' => $calle->id, 'codigo' => 'N1', 'nombre' => 'Mañana', 'es_turno' => true, 'hora_inicio' => '05:00', 'hora_fin' => '12:30', 'horas_nomina' => 7, 'activo' => true]);
        $valores = fn (array $cambios = []) => fn (string $campo) => ($cambios + $calle->config)[str_replace('config.', '', $campo)] ?? null;

        $texto = Componentes::ejemploDescanso($valores(), $calle);
        $this->assertStringContainsString('T4 (4 h en el sitio) → 4 h de trabajo', $texto);
        $this->assertStringContainsString('N1 (7 h 30 min en el sitio) → 7 h de trabajo', $texto);

        // Cambia con lo que se escribe: con 60 de descanso largo, el N1 da 6 h 30 min.
        $this->assertStringContainsString('N1 (7 h 30 min en el sitio) → 6 h 30 min de trabajo', Componentes::ejemploDescanso($valores(['descanso_min' => 60]), $calle));

        // Al crear un componente, sin turnos todavía: ejemplos de 4 h, 7 h 30 min y 12 h.
        $this->assertStringContainsString('Turno de 12 h (12 h en el sitio) → 11 h 30 min de trabajo', Componentes::ejemploDescanso($valores(), null));
    }

    public function test_editar_nodos_desactivar_y_la_zona_va_completa(): void
    {
        $calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $nodo = Nodo::factory()->create(['componente_id' => $calle->id, 'nombre' => 'Nodo 1', 'orden' => 1]);
        $this->como($this->admin);

        // Un centro sin radio no sirve: o la zona completa, o nada.
        Livewire::test(Componentes::class)->mountAction(TestAction::make(EditAction::class)->table($calle))
            ->set('mountedActions.0.data.nodos.record-'.$nodo->id.'.lat', 6.25)
            ->callMountedAction()
            ->assertHasFormErrors();

        Livewire::test(Componentes::class)->mountAction(TestAction::make(EditAction::class)->table($calle))
            ->set('mountedActions.0.data.nodos.record-'.$nodo->id.'.nombre', 'Nodo Centro')
            ->set('mountedActions.0.data.nodos.record-'.$nodo->id.'.activo', false)
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $nodo->refresh();
        $this->assertSame('Nodo Centro', $nodo->nombre);
        $this->assertFalse($nodo->activo, 'Se desactiva, no se borra.');
        $this->assertSame(1, $calle->nodos()->count());
    }

    public function test_no_se_ven_los_componentes_de_otra_dependencia(): void
    {
        $propio = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $ajeno = Componente::factory()->create(['nombre' => 'Componente de Beta']);
        $this->como($this->admin);

        Livewire::test(Componentes::class)
            ->assertCanSeeTableRecords([$propio])
            ->assertCanNotSeeTableRecords([$ajeno]);
    }

    public function test_coordinacion_entra_y_edicion_no(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $this->actingAs($coordinador)->get(route('admin.turnos.componentes.index'))
            ->assertOk()->assertSee('Componentes y nodos');

        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->actingAs($editor)->get(route('admin.turnos.componentes.index'))->assertForbidden();

        $this->como($editor);
        Livewire::test(Componentes::class)->assertActionHidden(TestAction::make(CreateAction::class)->table());
    }
}
