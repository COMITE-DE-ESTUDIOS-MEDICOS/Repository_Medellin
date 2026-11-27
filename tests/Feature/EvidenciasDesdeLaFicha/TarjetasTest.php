<?php

namespace Tests\Feature\EvidenciasDesdeLaFicha;

use App\Enums\RolDependencia;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DOC-34 (EVF-02): las tarjetas de evidencia en las fichas de Derivaciones y de Seguridad CCTV. */
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

    private function usuario(RolDependencia $rol, bool $casilla = false): User
    {
        $usuario = $this->usuarioCon($rol, $this->dependencia, $this->calle);
        $usuario->dependencias()->updateExistingPivot($this->dependencia->id, ['responsable_cctv' => $casilla]);

        return $usuario->unsetRelation('dependencias');
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

    private function cctv(array $estado = []): CasoCctv
    {
        return CasoCctv::factory()->de($this->calle)->atendido()->create($estado + ['nombre' => 'Ana Campo', 'cedula' => '123']);
    }

    private function con(Derivacion|CasoCctv $caso, int $n, array $documento = []): Documento
    {
        $d = Documento::factory()->create($documento + ['dependencia_id' => $this->dependencia->id]);
        $caso->forceFill(["evidencia{$n}_documento_id" => $d->id])->save();

        return $d;
    }

    private function fichaD(User $u, Derivacion $c)
    {
        return $this->actingAs($u)->get(route('admin.turnos.derivaciones.show', $c));
    }

    private function fichaC(User $u, CasoCctv $c)
    {
        return $this->actingAs($u)->get(route('admin.turnos.cctv.show', $c));
    }

    public function test_doc34_ca01_derivacion_solucionada_muestra_cargar_solo_en_las_vacias(): void
    {
        $caso = $this->derivacion();
        $this->con($caso, 2);

        $r = $this->fichaD($this->usuario(RolDependencia::Edicion), $caso)->assertOk();

        $r->assertSee(route('admin.turnos.derivaciones.evidencia', [$caso, 1]), false);
        $r->assertSee(route('admin.turnos.derivaciones.evidencia', [$caso, 3]), false);
        $r->assertDontSee(route('admin.turnos.derivaciones.evidencia', [$caso, 2]), false);
        $this->assertSame(2, substr_count($r->getContent(), 'Cargar evidencia'));
        $r->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_doc34_ca03_lectura_no_ve_el_boton_en_derivaciones(): void
    {
        $this->fichaD($this->usuario(RolDependencia::Lectura), $this->derivacion())
            ->assertForbidden(); // Lectura ni siquiera ve la ficha: no hay botón que mostrar
    }

    public function test_doc34_ca03_cctv_sin_casilla_no_ve_el_boton_y_con_casilla_si(): void
    {
        $caso = $this->cctv();

        $this->fichaC($this->usuario(RolDependencia::Edicion), $caso)->assertDontSee('Cargar evidencia');
        $this->fichaC($this->usuario(RolDependencia::Edicion, true), $caso)
            ->assertSee(route('admin.turnos.cctv.evidencia', [$caso, 1]), false)
            ->assertSee('Cargar evidencia');
    }

    public function test_doc34_ca04_tarjeta_llena_no_ofrece_cargar(): void
    {
        $caso = $this->derivacion();
        foreach ([1, 2, 3] as $n) {
            $this->con($caso, $n);
        }

        $this->fichaD($this->usuario(RolDependencia::Coordinacion), $caso)->assertDontSee('Cargar evidencia');
    }

    public function test_doc34_ca05_sin_boton_en_pendiente_abierto_y_anulado(): void
    {
        $u = $this->usuario(RolDependencia::Coordinacion);

        $this->fichaD($u, $this->derivacion(Derivacion::PENDIENTE))->assertOk()->assertDontSee('Cargar evidencia');
        $this->fichaC($u, $this->cctv(['estado' => CasoCctv::ABIERTO]))->assertOk()->assertDontSee('Cargar evidencia');
        $this->fichaC($u, $this->cctv(['estado' => CasoCctv::ANULADO]))->assertOk()->assertDontSee('Cargar evidencia');
    }

    public function test_doc34_ca10_dice_quien_y_cuando_cargo_desde_la_ficha(): void
    {
        $caso = $this->derivacion();
        $autor = User::factory()->create(['name' => 'Marta Ríos']);
        $d = $this->con($caso, 1, ['creado_por' => $autor->id]);
        $this->con($caso, 2);

        $r = $this->fichaD($this->usuario(RolDependencia::Edicion), $caso);

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

        $this->fichaD($this->usuario(RolDependencia::Edicion), $caso)
            ->assertSee('Versión 2 · cambiada el '.$v2->created_at->fechaHora().' por Luis Mora');
    }

    public function test_doc34_abrir_en_el_repositorio_depende_de_ver_el_documento(): void
    {
        $caso = $this->derivacion();
        $d = $this->con($caso, 1, ['activo' => false]);
        $enlace = route('documentos.show', $d);

        $this->fichaD($this->usuario(RolDependencia::Lectura), $caso)->assertDontSee($enlace, false);
        $this->fichaD($this->usuario(RolDependencia::Administracion), $caso)->assertSee($enlace, false)->assertSee('Abrir en el repositorio');
    }

    public function test_doc34_cctv_muestra_las_cuatro_tarjetas(): void
    {
        $caso = $this->cctv();

        $r = $this->fichaC($this->usuario(RolDependencia::Coordinacion), $caso)->assertOk();

        foreach (['Soporte', 'Evidencia 1', 'Evidencia 2', 'Evidencia 3'] as $t) {
            $r->assertSee($t);
        }
        $this->assertSame(4, substr_count($r->getContent(), 'Sin archivo'));
        $this->assertSame(3, substr_count($r->getContent(), 'Cargar evidencia'));
    }
}
