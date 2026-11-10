<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** DOC-20 · DCA-02: la oficina crea el caso DC-NNN. */
class CasosDerivacionCrearTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(14, 5, 30));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->lider = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function crear(array $datos = [])
    {
        return $this->actingAs($this->lider)->post(route('admin.turnos.derivaciones.store'), $datos + [
            'clave_formulario' => (string) Str::uuid(),
            'componente_id' => $this->calle->id,
            'descripcion' => 'Habitante de calle requiere traslado.',
            'remitente' => 'alcaldia',
            'ubicacion' => 'Cra. 65 #97-121, Castilla '.Str::random(4),
            'soporte' => $this->archivoPdf('oficio.pdf'),
        ]);
    }

    public function test_doc20_ca01_edicion_crea_caso_consecutivo_con_hora_del_servidor(): void
    {
        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.create'))
            ->assertOk()->assertSee('Mensaje para la atención')->assertSee('Autoridades locales del sector');

        $this->crear(['ubicacion' => 'Parque de Castilla'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.turnos.derivaciones.create'))
            ->assertSessionHas('caso.creado.codigo', 'DC-001');

        $caso = Derivacion::sole();
        $this->assertSame(
            [1, 'pendiente', 'alcaldia', 'Alcaldía', $this->lider->id, $this->calle->id, $this->dependencia->id],
            [$caso->numero, $caso->estado, $caso->remitente, $caso->entidad, $caso->creado_por, $caso->componente_id, $caso->dependencia_id],
        );
        $this->assertSame(now()->toDateString(), $caso->fecha_requerimiento->toDateString());
        $this->assertSame('14:05', substr($caso->hora_requerimiento, 0, 5));
        $this->assertNull($caso->colaborador_id);
        $this->assertSame('Abierto', $caso->estadoLegible());

        $this->assertSame('Caso DC-001 · Soporte', $caso->soporte->nombre);
        $this->assertSame('Derivaciones · Calle', $caso->soporte->carpeta->nombre);
        $this->assertDatabaseHas('auditorias', ['accion' => 'caso.creado', 'auditable_id' => $caso->id]);

        // La confirmación muestra el código en grande.
        $this->actingAs($this->lider)->withSession(['caso.creado' => ['codigo' => 'DC-001', 'hora' => '2:05 p. m.', 'limite' => '5:05 p. m.', 'ubicacion' => 'Parque']])
            ->get(route('admin.turnos.derivaciones.create'))
            ->assertSee('DC-001')->assertSee('Crear otro caso')->assertSee('Ver casos');

        $this->crear()->assertSessionHas('caso.creado.codigo', 'DC-002');
    }

    public function test_doc20_ca02_el_codigo_no_se_repite_y_la_misma_clave_devuelve_el_mismo_caso(): void
    {
        $this->crear();
        $this->crear();
        $this->assertSame([1, 2], Derivacion::orderBy('numero')->pluck('numero')->all());

        $clave = (string) Str::uuid();
        $this->crear(['clave_formulario' => $clave])->assertSessionHas('caso.creado.codigo', 'DC-003');
        $this->crear(['clave_formulario' => $clave])->assertSessionHas('caso.creado.codigo', 'DC-003');
        $this->assertSame(1, Derivacion::where('clave_formulario', $clave)->count());
        $this->assertSame(3, Derivacion::count());

        // Las eliminadas también cuentan: el número no se reutiliza.
        Derivacion::where('numero', 3)->first()->delete();
        $this->crear()->assertSessionHas('caso.creado.codigo', 'DC-004');

        Derivacion::where('numero', 4)->update(['numero' => 999]);
        $this->crear();
        $this->assertSame('DC-1000', Derivacion::where('numero', 1000)->sole()->codigo());
        $this->assertSame('DC-007', Derivacion::codigoDe(7));

        // Otra dependencia empieza su propia cuenta.
        $otra = Dependencia::factory()->create();
        $componente = Componente::factory()->create(['dependencia_id' => $otra->id]);
        $this->lider = $this->usuarioCon(RolDependencia::Edicion, $otra);
        $this->crear(['componente_id' => $componente->id])->assertSessionHas('caso.creado.codigo', 'DC-001');
    }

    public function test_doc20_lectura_no_crea_casos(): void
    {
        $this->lider = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.create'))->assertForbidden();
        $this->crear()->assertForbidden();
        $this->assertSame(0, Derivacion::count());
    }

    public function test_doc20_el_soporte_y_los_campos_son_obligatorios(): void
    {
        $this->crear(['soporte' => null])->assertSessionHasErrors('soporte');
        $this->crear(['remitente' => 'otra'])->assertSessionHasErrors('remitente_otra');
        $this->crear(['remitente' => 'cualquiera', 'descripcion' => '', 'ubicacion' => ''])
            ->assertSessionHasErrors(['remitente', 'descripcion', 'ubicacion']);
        $this->assertSame(0, Derivacion::count());

        $this->crear(['remitente' => 'otra', 'remitente_otra' => '  Personería  '])->assertSessionHasNoErrors();
        $this->assertSame(['otra', 'Personería'], [Derivacion::sole()->remitente, Derivacion::sole()->entidad]);
    }

    public function test_doc20_el_componente_debe_ser_de_la_dependencia(): void
    {
        $ajeno = Componente::factory()->create();

        $this->crear(['componente_id' => $ajeno->id])->assertSessionHasErrors('componente_id');
        $this->assertSame(0, Derivacion::count());
    }

    public function test_doc20_un_posible_duplicado_se_avisa_y_se_puede_confirmar(): void
    {
        $this->crear(['ubicacion' => 'Parque de Castilla']);

        // Misma ubicación (sin importar mayúsculas ni espacios) y mismo remitente.
        $this->crear(['ubicacion' => '  parque   DE castilla '])
            ->assertSessionHasErrors(['duplicado' => 'Ya hay un caso abierto con la misma ubicación y el mismo remitente: DC-001 (creado a las 2:05 p. m.). Si es otro caso, marca «Crear de todas formas» y vuelve a elegir el soporte.']);
        $this->assertSame(1, Derivacion::count());

        // Otro remitente no es duplicado.
        $this->crear(['ubicacion' => 'Parque de Castilla', 'remitente' => 'autoridades'])->assertSessionHasNoErrors();

        $this->crear(['ubicacion' => 'Parque de Castilla', 'confirmar_duplicado' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(3, Derivacion::count());

        // Pasada la ventana, ya no avisa.
        Carbon::setTestNow(now()->addMinutes(Derivacion::VENTANA_DUPLICADO_MIN + 1));
        $this->crear(['ubicacion' => 'Parque de Castilla'])->assertSessionHasNoErrors();
    }
}
