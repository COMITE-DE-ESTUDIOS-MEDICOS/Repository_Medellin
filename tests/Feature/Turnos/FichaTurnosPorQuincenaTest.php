<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Marcacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** La ficha de la persona: un turno por fila, por quincena; lo anulado va al historial. */
class FichaTurnosPorQuincenaTest extends TestCase
{
    use RefreshDatabase;

    private Colaborador $persona;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        $dependencia = Dependencia::factory()->create();
        $calle = Componente::factory()->create([
            'dependencia_id' => $dependencia->id,
            'config' => ['descanso_min' => 0, 'descanso_parcial_min' => 0],
        ]);
        $this->persona = Colaborador::factory()->en($calle)->create();
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $dependencia);
        $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    }

    private function marca(string $tipo, string $cuando, array $mas = []): Marcacion
    {
        $f = Marcacion::factory()->de($this->persona);

        return ($tipo === 'salida' ? $f->salida() : $f)->create(['marcada_at' => Carbon::parse($cuando)] + $mas);
    }

    private function ficha(?string $q = null)
    {
        return $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.colaboradores.show', $this->persona).($q ? "?q={$q}" : ''))
            ->assertOk();
    }

    public function test_un_turno_por_fila_con_sus_horas_y_el_turno_abierto_en_turno(): void
    {
        $this->marca('entrada', '2026-10-17 05:00');
        $this->marca('salida', '2026-10-17 12:45');
        $this->marca('entrada', '2026-10-20 05:10');

        $this->ficha()
            ->assertSeeTextInOrder(['16 – 31', '2 turnos', '7,8 h'])
            ->assertSeeTextInOrder(['20/10', '5:10 a. m.', 'en turno'])
            ->assertSeeTextInOrder(['17/10', '5:00 a. m.', '12:45 p. m.', '7 h 45']);
    }

    public function test_el_turno_de_noche_que_cruza_la_quincena_queda_en_la_suya_sin_marcas_sueltas(): void
    {
        $this->marca('entrada', '2026-10-15 21:00');
        $this->marca('salida', '2026-10-16 04:30');

        $this->ficha()->assertSeeText('0 turnos')->assertDontSee('sin pareja');
        $this->ficha('2026-10-01')->assertSeeTextInOrder(['1 – 15', '1 turno', '9:00 p. m.', '4:30 a. m. (+1)', '7 h 30']);
    }

    public function test_lo_anulado_no_es_fila_va_al_historial_con_su_motivo(): void
    {
        $this->marca('entrada', '2026-10-18 05:00');
        $this->marca('salida', '2026-10-18 05:03', ['anulada_at' => now(), 'anulada_por' => $this->coordinador->id, 'motivo_anulacion' => 'Reintento: quedó de más']);
        $this->marca('salida', '2026-10-18 13:00');

        $this->ficha()
            ->assertSeeTextInOrder(['1 turno', '1 corrección'])
            ->assertSeeTextInOrder(['5:00 a. m.', '1:00 p. m.', '8 h 00'])
            ->assertSeeTextInOrder(['Historial de correcciones', 'Anulada por', 'Reintento: quedó de más']);
    }

    public function test_una_salida_de_dias_despues_lleva_su_fecha(): void
    {
        $this->marca('entrada', '2026-10-17 12:30');
        $this->marca('salida', '2026-10-19 14:35');

        $this->ficha()->assertSeeTextInOrder(['17/10', '12:30 p. m.', '2:35 p. m. (19/10)', '50 h 05']);
    }

    public function test_una_salida_sin_entrada_se_senala_para_corregirla(): void
    {
        $this->marca('salida', '2026-10-19 04:40');

        $this->ficha()->assertSeeTextInOrder(['Salida sin pareja', '4:40 a. m.']);
    }
}
