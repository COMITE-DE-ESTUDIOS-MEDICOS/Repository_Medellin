<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Http\Controllers\Admin\Turnos\CargaMasivaColaboradoresController as Carga;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Nodo;
use App\Models\User;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CargaMasivaColaboradoresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        $dependencia = Dependencia::factory()->create();
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $dependencia);
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $dependencia->id, 'nombre' => 'Calle',
            'config' => ['cargos' => ['Operador terapéutico', 'Monitor de ruta']],
        ]);
        Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 1']);
    }

    private function excel(array $filas): UploadedFile
    {
        $ruta = Xlsx::guardar(tempnam(sys_get_temp_dir(), 'carga'), 'Personas', Carga::ENCABEZADOS, $filas);

        return new UploadedFile($ruta, 'carga.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_la_plantilla_se_descarga(): void
    {
        $this->actingAs($this->admin)->get(route('admin.turnos.colaboradores.carga.plantilla'))
            ->assertOk()->assertDownload('plantilla_carga_personas_de_campo.xlsx');
    }

    public function test_carga_las_personas_con_nombres_sin_tildes_ni_mayusculas(): void
    {
        $this->actingAs($this->admin)->post(route('admin.turnos.colaboradores.carga.importar'), ['archivo' => $this->excel([
            [1017234567, 'Ana Restrepo', 'calle', 'nodo 1', 'operador terapeutico', null, null, 3001234567, null, null],
            ['1.020.111', 'Luis Gómez', 'Calle', 'Nodo 1', 'Monitor de ruta', null, null, null, 'luis@correo.com', 'Alcaldía'],
        ])])->assertRedirect(route('admin.turnos.colaboradores.index'));

        $ana = Colaborador::where('documento', '1017234567')->sole();
        $this->assertSame('Operador terapéutico', $ana->cargo);
        $this->assertSame('3001234567', $ana->telefono);
        $this->assertNotNull($ana->verificado_at);
        $this->assertTrue(Colaborador::where('documento', '1020111')->exists());
    }

    public function test_si_una_fila_falla_no_se_carga_ninguna(): void
    {
        $this->actingAs($this->admin)->post(route('admin.turnos.colaboradores.carga.importar'), ['archivo' => $this->excel([
            [1017234567, 'Ana Restrepo', 'Calle', 'Nodo 1', 'Operador terapéutico'],
            [1017234567, 'Repetida', 'Calle', 'Nodo 1', 'Operador terapéutico'],
            [1030000000, 'Sin nodo', 'Calle', 'Nodo 9', 'Operador terapéutico'],
            [1040000000, 'Cargo raro', 'Calle', 'Nodo 1', 'Astronauta'],
        ])])->assertSessionHas('carga_errores', fn (array $e) => count($e) === 3
            && str_contains($e[0], 'Fila 3') && str_contains($e[1], 'Fila 4') && str_contains($e[2], 'Fila 5'));

        $this->assertSame(0, Colaborador::count());
    }
}
