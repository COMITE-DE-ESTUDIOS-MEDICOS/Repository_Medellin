<?php

namespace Tests\Feature\IngresosEgresos;

use App\Models\Componente;
use App\Models\Estadia;
use App\Models\Sede;
use App\Models\SedeCapacidad;
use App\Models\UsuarioHc;
use App\Services\ContextoDependencia;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DOC-27 (IYE-02, plan D1, D3 y D4): esquema, hora de Colombia, días de estadía y capacidad. */
class DiasDeEstadiaTest extends TestCase
{
    use RefreshDatabase;

    private function bogota(string $fecha): Carbon
    {
        return Carbon::parse($fecha, 'America/Bogota');
    }

    public function test_doc27_ca23_dias_de_estadia_en_hora_de_colombia_con_cualquier_zona_del_servidor(): void
    {
        $zonaInicial = date_default_timezone_get();

        try {
            foreach (['America/Bogota', 'UTC'] as $zona) {
                config(['app.timezone' => $zona]);
                date_default_timezone_set($zona);
                Carbon::setTestNow($this->bogota('2026-12-05 10:00'));

                // 11:50 p. m. del 3 a 12:10 a. m. del 4: dos días, aunque en UTC ambos caen el 4 y el 5.
                $estadia = Estadia::factory()->egresada()->create([
                    'ingreso_en' => $this->bogota('2026-12-03 23:50'),
                    'egreso_en' => $this->bogota('2026-12-04 00:10'),
                ])->fresh();

                $this->assertSame(2, $estadia->dias, $zona);
                $this->assertSame('2026-12-03 23:50:00', $estadia->getRawOriginal('ingreso_en'), $zona);
                $this->assertSame('2026-12-04 00:10:00', $estadia->getRawOriginal('egreso_en'), $zona);
                $this->assertSame('11:50 p. m.', $estadia->ingreso_en->hora(), $zona);

                // Instantes dados en UTC se guardan en hora de Colombia.
                $utc = Estadia::factory()->egresada()->create([
                    'ingreso_en' => Carbon::parse('2026-12-04 04:50', 'UTC'),
                    'egreso_en' => Carbon::parse('2026-12-04 05:10', 'UTC'),
                ])->fresh();
                $this->assertSame('2026-12-03 23:50:00', $utc->getRawOriginal('ingreso_en'), $zona);
                $this->assertSame(2, $utc->dias, $zona);

                // Activa: cuenta hasta hoy en Colombia (3 al 5 = 3 días).
                $activa = Estadia::factory()->create(['ingreso_en' => $this->bogota('2026-12-03 08:00')])->fresh();
                $this->assertSame(3, $activa->dias, $zona);

                // Mismo día = 1; ingreso y egreso iguales = 0; salida a las 12:00 a. m. cuenta ese día.
                $this->assertSame(1, Estadia::diasDeEstadia($this->bogota('2026-12-03 08:00'), $this->bogota('2026-12-03 20:00')));
                $this->assertSame(0, Estadia::diasDeEstadia($this->bogota('2026-12-03 08:00'), $this->bogota('2026-12-03 08:00')));
                $this->assertSame(2, Estadia::diasDeEstadia($this->bogota('2026-12-03 22:00'), $this->bogota('2026-12-04 00:00')));

                // Anulada: no se cuenta.
                $this->assertNull(Estadia::factory()->anulada()->create()->dias);
            }
        } finally {
            Carbon::setTestNow();
            date_default_timezone_set($zonaInicial);
        }
    }

    public function test_doc27_ca02_el_cast_guarda_al_minuto_con_los_segundos_en_cero(): void
    {
        $estadia = Estadia::factory()->create(['ingreso_en' => '2026-12-03 23:50:37'])->fresh();

        $this->assertSame('2026-12-03 23:50:00', $estadia->getRawOriginal('ingreso_en'));
        $this->assertNull($estadia->egreso_en);
    }

    public function test_doc27_una_activa_por_persona_y_varias_egresadas_conviven(): void
    {
        $persona = UsuarioHc::factory()->create();
        $comun = ['dependencia_id' => $persona->dependencia_id, 'componente_id' => $persona->componente_registro_id, 'usuario_hc_id' => $persona->id];

        Estadia::factory()->egresada()->count(2)->create($comun);
        Estadia::factory()->anulada()->create($comun);
        $this->assertSame(3, Estadia::withoutGlobalScopes()->whereNull('activa_usuario_hc_id')->count());

        Estadia::factory()->create($comun);

        $this->expectException(UniqueConstraintViolationException::class);
        Estadia::factory()->create($comun);
    }

    public function test_doc27_la_capacidad_vigente_cuenta_el_mismo_dia_y_gana_la_ultima_escrita(): void
    {
        $sede = Sede::factory()->create();
        $nueva = fn (int $capacidad, string $desde) => SedeCapacidad::create(['sede_id' => $sede->id, 'capacidad' => $capacidad, 'vigente_desde' => $desde]);
        $nueva(10, '2026-12-01');
        $nueva(15, '2026-12-01');
        $nueva(8, '2026-12-10');

        $this->assertNull($sede->capacidadEn(Carbon::parse('2025-12-31 23:59')));
        $this->assertSame(20, $sede->capacidadEn(Carbon::parse('2026-11-30 23:59')));
        $this->assertSame(15, $sede->capacidadEn(Carbon::parse('2026-12-01 00:00')));
        $this->assertSame(15, $sede->capacidadEn(Carbon::parse('2026-12-01 23:59:59')));
        $this->assertSame(8, $sede->capacidadEn(Carbon::parse('2026-12-10 00:00')));
    }

    public function test_doc27_entre_componentes_es_el_unico_salto_y_estadia_activa_lo_usa(): void
    {
        $propia = Estadia::factory()->create();
        $persona = UsuarioHc::withoutGlobalScopes()->find($propia->usuario_hc_id);
        $otroComponente = Componente::factory()->create(['dependencia_id' => $propia->dependencia_id]);
        $ajena = Estadia::factory()->en($otroComponente)->create(['usuario_hc_id' => UsuarioHc::factory()->create(['dependencia_id' => $propia->dependencia_id])->id]);

        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($propia->dependencia);
        $contexto->establecerComponente($propia->componente);

        $this->assertSame([$propia->id], Estadia::pluck('id')->all());
        $this->assertEqualsCanonicalizing([$propia->id, $ajena->id], Estadia::entreComponentes()->pluck('id')->all());
        $this->assertSame($propia->id, $persona->estadiaActiva->id);
        $this->assertSame($ajena->id, UsuarioHc::find($ajena->usuario_hc_id)->estadiaActiva->id);

        // El salto está en dos líneas de Estadia.php y en ninguna otra parte de sus consultas.
        $this->assertSame(2, substr_count(file_get_contents(app_path('Models/Estadia.php')), 'withoutGlobalScope(ComponenteScope::class)'));
    }
}
