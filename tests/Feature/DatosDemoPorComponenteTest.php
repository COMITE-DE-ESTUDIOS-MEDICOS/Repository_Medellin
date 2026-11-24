<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use Database\Seeders\CalleSeeder;
use Database\Seeders\DemoTurnosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DOC-25, ACC-13: los datos locales (DemoTurnosSeeder, turnos:simular-dia y
 * turnos:simular-semana) dejan «DEMO · Calle» con Turnos, Derivaciones y
 * Supervisión; sin Documentos (solo de Calle) y sin tocar Calle.
 */
class DatosDemoPorComponenteTest extends TestCase
{
    use RefreshDatabase;

    private const ESPERADOS = ['turnos', 'derivaciones', 'supervisiones'];

    private Dependencia $inclusion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inclusion = Dependencia::factory()->create(['slug' => 'inclusion-social']);
        (new CalleSeeder)->run();
    }

    private function demo(): Componente
    {
        return Componente::withoutGlobalScopes()
            ->where('dependencia_id', $this->inclusion->id)->where('slug', 'demo-calle')->sole();
    }

    private function calle(): Componente
    {
        return Componente::withoutGlobalScopes()
            ->where('dependencia_id', $this->inclusion->id)->where('slug', 'calle')->sole();
    }

    private function assertModulosDeDemo(Componente $demo): void
    {
        $this->assertEqualsCanonicalizing(self::ESPERADOS, $demo->modulos);
        $this->assertFalse($demo->tieneModulo('documentos'));
    }

    public function test_doc25_acc13_demo_turnos_seeder_deja_demo_con_turnos_derivaciones_y_supervision(): void
    {
        $this->calle()->forceFill(['modulos' => array_keys(Componente::MODULOS)])->save();

        $this->seed(DemoTurnosSeeder::class);

        $this->assertModulosDeDemo($this->demo());
        // Calle no pierde ninguno.
        $this->assertEqualsCanonicalizing(array_keys(Componente::MODULOS), $this->calle()->modulos);
        // No crea cuentas: solo personas de campo, todas en su componente.
        $this->assertSame(0, User::count());
        $this->assertSame(0, Colaborador::withoutGlobalScopes()->where('documento', 'like', '9000001%')
            ->where(fn ($q) => $q->whereNull('componente_id')->orWhere('componente_id', '!=', $this->demo()->id))->count());
    }

    public function test_doc25_acc13_demo_turnos_seeder_dos_veces_no_duplica(): void
    {
        $this->seed(DemoTurnosSeeder::class);
        $antes = [Componente::withoutGlobalScopes()->count(), Colaborador::withoutGlobalScopes()->count()];

        $this->seed(DemoTurnosSeeder::class);

        $this->assertSame($antes, [Componente::withoutGlobalScopes()->count(), Colaborador::withoutGlobalScopes()->count()]);
        $this->assertModulosDeDemo($this->demo());
    }

    public function test_doc25_acc13_simular_dia_deja_demo_con_sus_modulos_y_no_duplica(): void
    {
        $this->artisan('turnos:simular-dia', ['fecha' => '2026-10-20', '--personas' => 5])->assertSuccessful();

        $this->assertModulosDeDemo($this->demo());
        $this->assertSame(5, Colaborador::withoutGlobalScopes()->where('componente_id', $this->demo()->id)->count());
        $antes = Componente::withoutGlobalScopes()->count();

        $this->artisan('turnos:simular-dia', ['fecha' => '2026-10-20', '--personas' => 5])->assertSuccessful();

        $this->assertSame($antes, Componente::withoutGlobalScopes()->count());
        $this->assertSame(5, Colaborador::withoutGlobalScopes()->where('componente_id', $this->demo()->id)->count());
        $this->assertModulosDeDemo($this->demo());
    }

    public function test_doc25_acc13_simular_dia_arregla_un_demo_que_quedo_sin_modulos_y_no_toca_calle(): void
    {
        $this->calle()->forceFill(['modulos' => array_keys(Componente::MODULOS)])->save();
        // Un DEMO · Calle de antes de DOC-25: sin módulos.
        Componente::factory()->sinModulos()->create(['dependencia_id' => $this->inclusion->id, 'slug' => 'demo-calle', 'nombre' => 'DEMO · Calle']);

        $this->artisan('turnos:simular-dia', ['fecha' => '2026-10-20', '--personas' => 5])->assertSuccessful();

        $this->assertModulosDeDemo($this->demo());
        $this->assertEqualsCanonicalizing(array_keys(Componente::MODULOS), $this->calle()->modulos);
    }

    public function test_doc25_acc13_simular_semana_deja_demo_con_sus_modulos(): void
    {
        $this->artisan('turnos:simular-semana', ['desde' => '2026-10-19', '--dias' => 1, '--personas' => 5])->assertSuccessful();

        $this->assertModulosDeDemo($this->demo());
    }

    public function test_doc25_acc13_quien_esta_en_demo_abre_turnos_derivaciones_y_supervision(): void
    {
        $this->artisan('turnos:simular-dia', ['fecha' => '2026-10-20', '--personas' => 5])->assertSuccessful();
        $coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $this->inclusion, $this->demo());

        foreach (self::ESPERADOS as $modulo) {
            $this->actingAs($coordinacion)->get(route(Componente::MODULOS[$modulo][1]))->assertOk();
        }
    }
}
