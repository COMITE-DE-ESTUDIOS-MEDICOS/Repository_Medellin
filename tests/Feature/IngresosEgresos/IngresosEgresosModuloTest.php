<?php

namespace Tests\Feature\IngresosEgresos;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use Database\Seeders\AccesoPorComponenteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DOC-27 (IYE-01, plan D2): el módulo Ingresos y egresos y su dirección. */
class IngresosEgresosModuloTest extends TestCase
{
    use RefreshDatabase;

    private function componente(string $nombre, string $slug, array $modulos, Dependencia $dependencia): Componente
    {
        return Componente::factory()->create(['dependencia_id' => $dependencia->id, 'nombre' => $nombre, 'slug' => $slug, 'modulos' => $modulos]);
    }

    /** Falta la parte del menu: la linea de sidebar.blade.php espera a UHC-06 (Jose). */
    public function test_doc27_ca21_sin_el_modulo_no_hay_direccion_y_calle_no_lo_tiene(): void
    {
        $this->assertSame(
            ['Ingresos y egresos', 'ingresos-egresos.index'],
            Componente::MODULOS['ingresos_egresos'],
        );
        $this->assertNotContains('ingresos_egresos', AccesoPorComponenteSeeder::MODULOS_CALLE);

        $dependencia = Dependencia::factory()->create();
        $calle = $this->componente('Calle', 'calle', AccesoPorComponenteSeeder::MODULOS_CALLE, $dependencia);
        $sinModulo = $this->componente('Básica', 'basica', ['usuarios_hc'], $dependencia);
        $conModulo = $this->componente('Estabilización', 'estabilizacion', ['usuarios_hc', 'ingresos_egresos'], $dependencia);

        foreach ([RolDependencia::Lectura, RolDependencia::Edicion, RolDependencia::Coordinacion] as $rol) {
            $this->actingAs($this->usuarioCon($rol, $dependencia, $calle))->get(route('ingresos-egresos.index'))->assertForbidden();
            $this->actingAs($this->usuarioCon($rol, $dependencia, $sinModulo))->get(route('ingresos-egresos.index'))->assertForbidden();
            $this->actingAs($this->usuarioCon($rol, $dependencia, $conModulo))->get(route('ingresos-egresos.index'))->assertOk()->assertSee('Ingresos y egresos');
        }

        $lider = $this->usuarioCon(RolDependencia::Administracion, $dependencia);
        $this->actingAs($lider)->withSession(['componente_id' => $conModulo->id])->get(route('ingresos-egresos.index'))->assertOk();
        $this->actingAs($lider)->withSession(['componente_id' => $calle->id])->get(route('ingresos-egresos.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('ingresos-egresos.index'))->assertRedirect();
    }
}
