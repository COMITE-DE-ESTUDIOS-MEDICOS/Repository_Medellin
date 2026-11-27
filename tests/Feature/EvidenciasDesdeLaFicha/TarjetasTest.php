<?php

namespace Tests\Feature\EvidenciasDesdeLaFicha;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DOC-34: las tarjetas de evidencia en la ficha de una derivación. */
class TarjetasTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
    }

    private function usuario(RolDependencia $rol): User
    {
        return $this->usuarioCon($rol, $this->dependencia);
    }

    private function derivacion(string $estado = Derivacion::SOLUCIONADA): Derivacion
    {
        return Derivacion::create([
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->calle->id,
            'numero' => (int) Derivacion::withoutGlobalScopes()->max('numero') + 1,
            'remitente' => 'alcaldia', 'entidad' => 'Alcaldía', 'descripcion' => 'Requiere traslado.',
            'ubicacion' => 'Parque de Castilla', 'comuna' => 3, 'estado' => $estado,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
            'nombre' => 'Ana Campo', 'cedula' => '123',
        ]);
    }

    private function con(Derivacion $caso, int $n, array $documento = []): Documento
    {
        $d = Documento::factory()->create($documento + ['dependencia_id' => $this->dependencia->id]);
        $caso->forceFill(["evidencia{$n}_documento_id" => $d->id])->save();

        return $d;
    }

    private function ficha(User $u, Derivacion $c)
    {
        return $this->actingAs($u)->get(route('admin.turnos.derivaciones.show', $c));
    }

    public function test_doc34_ca01_solucionada_muestra_cargar_solo_en_las_vacias(): void
    {
        $caso = $this->derivacion();
        $this->con($caso, 2);

        $r = $this->ficha($this->usuario(RolDependencia::Edicion), $caso)->assertOk();

        $r->assertSee(route('admin.turnos.derivaciones.evidencia', [$caso, 1]), false);
        $r->assertSee(route('admin.turnos.derivaciones.evidencia', [$caso, 3]), false);
        $r->assertDontSee(route('admin.turnos.derivaciones.evidencia', [$caso, 2]), false);
        $this->assertSame(2, substr_count($r->getContent(), 'Cargar evidencia'));
        $r->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_doc34_ca03_lectura_no_ve_el_boton(): void
    {
        $this->ficha($this->usuario(RolDependencia::Lectura), $this->derivacion())
            ->assertForbidden(); // Lectura ni siquiera ve la ficha: no hay botón que mostrar
    }

    public function test_doc34_ca04_tarjeta_llena_no_ofrece_cargar(): void
    {
        $caso = $this->derivacion();
        foreach ([1, 2, 3] as $n) {
            $this->con($caso, $n);
        }

        $this->ficha($this->usuario(RolDependencia::Coordinacion), $caso)->assertDontSee('Cargar evidencia');
    }

    public function test_doc34_ca05_sin_boton_en_pendiente(): void
    {
        $this->ficha($this->usuario(RolDependencia::Coordinacion), $this->derivacion(Derivacion::PENDIENTE))
            ->assertOk()->assertDontSee('Cargar evidencia');
    }

    public function test_doc34_ca10_dice_quien_y_cuando_cargo_desde_la_ficha(): void
    {
        $caso = $this->derivacion();
        $autor = User::factory()->create(['name' => 'Marta Ríos']);
        $d = $this->con($caso, 1, ['creado_por' => $autor->id]);
        $this->con($caso, 2);

        $r = $this->ficha($this->usuario(RolDependencia::Edicion), $caso);

        $r->assertSee('Cargada desde la ficha por Marta Ríos el '.$d->created_at->fechaHora());
        $this->assertSame(1, substr_count($r->getContent(), 'Cargada desde la ficha'));
    }

    public function test_doc34_ca11_dice_la_version_vigente_si_hay_mas_de_una(): void
    {
        $caso = $this->derivacion();
        $d = $this->con($caso, 1);
        $almacen = app(AlmacenamientoDocumentos::class);
        $almacen->guardarVersion($d, $this->archivoJpg('a.jpg'));
        $this->actingAs(User::factory()->create(['name' => 'Luis Mora']));
        $v2 = $almacen->guardarVersion($d, $this->archivoJpg('b.jpg'));

        $this->ficha($this->usuario(RolDependencia::Edicion), $caso)
            ->assertSee('Versión 2 · cambiada el '.$v2->created_at->fechaHora().' por Luis Mora');
    }

    public function test_doc34_abrir_en_el_repositorio_depende_de_ver_el_documento(): void
    {
        $caso = $this->derivacion();
        $d = $this->con($caso, 1, ['activo' => false]);
        $enlace = route('documentos.show', $d);

        $this->ficha($this->usuario(RolDependencia::Lectura), $caso)->assertDontSee($enlace, false);
        $this->ficha($this->usuario(RolDependencia::Administracion), $caso)->assertSee($enlace, false)->assertSee('Abrir en el repositorio');
    }
}
