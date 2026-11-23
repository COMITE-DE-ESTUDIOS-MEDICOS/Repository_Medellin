<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use Database\Seeders\CalleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Fase 0 del módulo de turnos: la base sobre la que construyen la
 * administración y la vía pública. Si esto falla, no se mezcla.
 */
class AdminBaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_calle_nace_con_sus_seis_nodos_y_sembrar_dos_veces_no_duplica(): void
    {
        $inclusion = Dependencia::factory()->create(['slug' => 'inclusion-social']);

        (new CalleSeeder)->run();
        (new CalleSeeder)->run();

        $calle = Componente::withoutGlobalScopes()->where('dependencia_id', $inclusion->id)->sole();

        $this->assertSame('calle', $calle->slug);
        $this->assertSame(
            ['Nodo 1', 'Nodo 2', 'Nodo 3', 'Nodo 4', 'Nodo 5', 'Nodo 6'],
            $calle->nodos->pluck('nombre')->all(),
        );
        $this->assertSame(CalleSeeder::CARGOS, $calle->cargos());
    }

    public function test_los_cargos_que_ya_cambio_administracion_no_se_pisan(): void
    {
        $inclusion = Dependencia::factory()->create(['slug' => 'inclusion-social']);
        (new CalleSeeder)->run();

        $calle = Componente::withoutGlobalScopes()->where('dependencia_id', $inclusion->id)->sole();
        $calle->update(['config' => ['cargos' => ['Solo este']]]);
        (new CalleSeeder)->run();

        $this->assertSame(['Solo este'], $calle->fresh()->cargos());
    }

    public function test_sin_la_dependencia_el_seeder_no_hace_nada(): void
    {
        (new CalleSeeder)->run();

        $this->assertSame(0, Componente::withoutGlobalScopes()->count());
    }

    public function test_la_cedula_se_encuentra_con_o_sin_puntos(): void
    {
        $colaborador = Colaborador::factory()->create(['documento' => '1.234.567']);

        $this->assertSame('1234567', $colaborador->documento);
        $this->assertTrue($colaborador->is(Colaborador::porDocumento($colaborador->dependencia_id, '1234567')));
        $this->assertTrue($colaborador->is(Colaborador::porDocumento($colaborador->dependencia_id, '1.234.567')));
    }

    public function test_la_misma_cedula_en_otra_dependencia_es_otra_persona(): void
    {
        $aqui = Colaborador::factory()->create(['documento' => '555555']);
        $alla = Dependencia::factory()->create();

        $this->assertNull(Colaborador::porDocumento($alla->id, '555555'));
        $this->assertTrue($aqui->is(Colaborador::porDocumento($aqui->dependencia_id, '555555')));
    }

    public function test_el_saludo_no_revela_el_nombre_completo(): void
    {
        $colaborador = Colaborador::factory()->make(['nombre' => 'José Amaya Pérez']);

        $this->assertSame('Jos…', $colaborador->saludo());
    }

    public function test_las_reglas_del_componente_caen_en_su_defecto(): void
    {
        $componente = Componente::factory()->conReglas(['foto_obligatoria' => false])->create();

        $this->assertFalse($componente->regla('foto_obligatoria'));
        $this->assertSame(Componente::REGLAS['horas_max_turno'], $componente->regla('horas_max_turno'));
        $this->assertSame('x', $componente->regla('no_existe', 'x'));
    }

    public function test_una_marcacion_no_se_edita_ni_se_borra(): void
    {
        $marcacion = Marcacion::factory()->create();

        try {
            $marcacion->update(['motivo' => 'cambiada']);
            $this->fail('Se pudo editar una marcación.');
        } catch (LogicException) {
        }

        try {
            $marcacion->delete();
            $this->fail('Se pudo borrar una marcación.');
        } catch (LogicException) {
        }

        $this->assertNull($marcacion->fresh()->motivo);
    }

    public function test_los_dos_enlaces_comparten_el_token_y_no_se_confunden(): void
    {
        $turno = EnlaceTurno::factory()->conToken($token = EnlaceTurno::generarToken())->create();
        $carga = EnlaceCarga::factory()->create();

        $this->assertTrue($turno->is(EnlaceTurno::porToken($token)));
        $this->assertNull(EnlaceCarga::porToken($token));
        $this->assertTrue($carga->is(EnlaceCarga::porToken($carga->token())));
        $this->assertNotSame($token, $turno->getRawOriginal('token_cifrado'), 'El token está guardado en claro.');
    }

    public function test_el_enlace_de_turno_vencido_o_revocado_no_esta_vigente(): void
    {
        $this->assertTrue(EnlaceTurno::factory()->create()->estaVigente());
        $this->assertFalse(EnlaceTurno::factory()->vencido()->create()->estaVigente());
        $this->assertFalse(EnlaceTurno::factory()->revocado()->create()->estaVigente());
    }

    public function test_una_dependencia_no_ve_los_colaboradores_ni_las_marcaciones_de_otra(): void
    {
        $alfa = Dependencia::factory()->create();
        $beta = Dependencia::factory()->create();
        $calleBeta = Componente::factory()->create(['dependencia_id' => $beta->id]);
        $deBeta = Colaborador::factory()->en($calleBeta)->create();
        Marcacion::factory()->de($deBeta)->create();

        // Sin el «Calle» que el helper crea (ACC-04): alfa no tiene ningún componente.
        $admin = \App\Models\User::factory()->create();
        $admin->dependencias()->attach($alfa->id, ['rol' => RolDependencia::Administracion->value]);
        $this->actingAs($admin);
        app(\App\Services\ContextoDependencia::class)->establecer($alfa);

        $this->assertSame(0, Colaborador::count());
        $this->assertSame(0, Marcacion::count());
        $this->assertSame(0, Componente::count());
    }

    public function test_los_nodos_se_alcanzan_por_su_componente(): void
    {
        $componente = Componente::factory()->create();
        Nodo::factory()->create(['componente_id' => $componente->id, 'orden' => 2, 'nombre' => 'B']);
        Nodo::factory()->create(['componente_id' => $componente->id, 'orden' => 1, 'nombre' => 'A']);

        $this->assertSame(['A', 'B'], $componente->nodos->pluck('nombre')->all());
    }
}
