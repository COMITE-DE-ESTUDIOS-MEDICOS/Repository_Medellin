<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Http\Middleware\EstablecerDependencia;
use App\Livewire\Turnos\PuntosCriticos;
use App\Livewire\Turnos\TiposTurno;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\PuntoCritico;
use App\Models\TipoTurno;
use App\Services\ContextoDependencia;
use Database\Seeders\CalleSeeder;
use Database\Seeders\CatalogoTurnosCalleSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tipos de turno y puntos críticos: tablas de Filament dentro de Documenta.
 * Los manejan los líderes (Edición en adelante); nadie ve los de otra dependencia.
 */
class CatalogosMallaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $inclusion;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inclusion = Dependencia::factory()->create(['slug' => 'inclusion-social']);
        (new CalleSeeder)->run();
        (new CatalogoTurnosCalleSeeder)->run();
        $this->calle = Componente::withoutGlobalScopes()->where('slug', 'calle')->sole();
    }

    /** Livewire::test no pasa por los middlewares: la dependencia se pone a mano. */
    private function comoEditor(): void
    {
        $this->actingAs($this->usuarioCon(RolDependencia::Edicion, $this->inclusion));
        app(ContextoDependencia::class)->establecer($this->inclusion);
    }

    public function test_el_catalogo_de_calle_viene_cargado_con_sus_codigos(): void
    {
        $n1 = TipoTurno::where('codigo', 'N1')->sole();
        $this->assertSame('5:00 a. m. – 12:30 p. m.', $n1->horario());
        $this->assertSame('Lun–Sáb', $n1->diasLegibles());
        $this->assertEquals(7, $n1->horas_nomina);
        $this->assertSame('Dom y festivos', TipoTurno::where('codigo', 'C13')->sole()->diasLegibles());
        $this->assertFalse(TipoTurno::where('codigo', 'D')->sole()->es_turno);
        $this->assertGreaterThan(30, PuntoCritico::count());

        (new CatalogoTurnosCalleSeeder)->run();
        $this->assertSame(1, TipoTurno::where('codigo', 'N1')->count(), 'Correrlo otra vez no duplica.');
    }

    public function test_las_paginas_son_de_documenta_y_se_abren_desde_sus_modulos(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->inclusion);

        $this->actingAs($editor)->get(route('admin.turnos.colaboradores.index'))
            ->assertSee(route('admin.turnos.tipos-turno'), false);
        $this->actingAs($editor)->get(route('admin.turnos.en-turno'))
            ->assertSee(route('admin.turnos.puntos-criticos'), false);

        // Con el menú y la barra de Documenta, y la tabla de Filament dentro.
        $this->actingAs($editor)->get(route('admin.turnos.tipos-turno'))
            ->assertOk()->assertSee('Tipos de turno')->assertSee('Personas de campo')->assertSee('Mañana');
        $this->actingAs($editor)->get(route('admin.turnos.puntos-criticos'))
            ->assertOk()->assertSee('Puntos críticos')->assertSee('Apoyo control territorial');
    }

    public function test_lectura_no_entra(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->inclusion);

        $this->actingAs($lector)->get(route('admin.turnos.tipos-turno'))->assertForbidden();
        $this->actingAs($lector)->get(route('admin.turnos.puntos-criticos'))->assertForbidden();
    }

    public function test_edicion_crea_un_tipo_de_turno_y_no_repite_el_codigo(): void
    {
        $this->comoEditor();

        Livewire::test(TiposTurno::class)
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'componente_id' => $this->calle->id, 'codigo' => 'N2', 'nombre' => 'Tarde', 'es_turno' => true,
                'hora_inicio' => '12:30', 'hora_fin' => '20:00', 'dias' => [1, 2, 3, 4, 5, 6], 'horas_nomina' => 7,
            ])
            ->assertHasNoFormErrors();

        $this->assertSame('Lun–Sáb', TipoTurno::where('codigo', 'N2')->sole()->diasLegibles());

        Livewire::test(TiposTurno::class)
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'componente_id' => $this->calle->id, 'codigo' => 'N2', 'nombre' => 'Otra', 'horas_nomina' => 7,
            ])
            ->assertHasFormErrors(['codigo' => 'unique']);
    }

    public function test_un_punto_critico_guarda_sus_coordenadas_y_se_ve_en_el_mapa(): void
    {
        $this->comoEditor();
        $punto = PuntoCritico::where('nombre', 'Terminal del Norte')->sole();

        Livewire::test(PuntosCriticos::class)
            ->callAction(TestAction::make(EditAction::class)->table($punto), ['coordenadas' => '6.2786, -75.5703'])
            ->assertHasNoFormErrors();

        $punto->refresh();
        $this->assertSame([6.2786, -75.5703], [$punto->lat, $punto->lng]);
        $this->assertStringContainsString('openstreetmap.org/?mlat=6.2786&mlon=-75.5703', $punto->urlMapa());

        Livewire::test(PuntosCriticos::class)
            ->callAction(TestAction::make(EditAction::class)->table($punto), ['coordenadas' => 'por el parque'])
            ->assertHasFormErrors(['coordenadas']);
    }

    public function test_no_se_ve_lo_de_otra_dependencia(): void
    {
        $otra = Dependencia::factory()->create();
        $ajeno = Componente::factory()->create(['dependencia_id' => $otra->id]);
        $tipoAjeno = TipoTurno::create(['componente_id' => $ajeno->id, 'codigo' => 'ZZ9', 'nombre' => 'De otra dependencia']);
        $puntoAjeno = PuntoCritico::create(['componente_id' => $ajeno->id, 'nombre' => 'Punto de otra dependencia']);

        $this->comoEditor();

        Livewire::test(TiposTurno::class)
            ->assertCanSeeTableRecords(TipoTurno::where('codigo', 'N1')->get())
            ->assertCanNotSeeTableRecords([$tipoAjeno]);
        Livewire::test(PuntosCriticos::class)->assertCanNotSeeTableRecords([$puntoAjeno]);
    }

    /** Si se pierde, filtrar o guardar en la tabla correría sin dependencia y sin filtro. */
    public function test_la_dependencia_se_establece_tambien_en_los_clics_de_livewire(): void
    {
        $this->assertContains(EstablecerDependencia::class, Livewire::getPersistentMiddleware());
    }
}
