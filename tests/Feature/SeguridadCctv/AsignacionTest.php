<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\RolDependencia;
use App\Models\Auditoria;
use App\Models\CasoCctv;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** DOC-33 v1.1 (CCTV-13): persona asignada y nodo opcionales, asignación por cédula, quién atiende. */
class AsignacionTest extends TestCase
{
    use RefreshDatabase;

    private const AJENO = 'Este caso está asignado a otra persona.';

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodoJose;

    private Nodo $nodoAna;

    private Colaborador $jose;

    private Colaborador $ana;

    private User $responsable;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->nodoJose = Nodo::factory()->create(['componente_id' => $this->calle->id]);
        $this->nodoAna = Nodo::factory()->create(['componente_id' => $this->calle->id]);
        $this->jose = Colaborador::factory()->en($this->calle, $this->nodoJose)->create(['documento' => '1017000111', 'nombre' => 'José Amaya Pérez']);
        $this->ana = Colaborador::factory()->en($this->calle, $this->nodoAna)->create(['documento' => '1017000222', 'nombre' => 'Ana Campo Ruiz']);

        foreach ([$this->jose, $this->ana] as $persona) {
            Marcacion::factory()->de($persona)->create(['marcada_at' => now()->subHours(3)]);
        }

        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create(['tipo' => 'seguridad_cctv']);

        $this->responsable = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);
        $this->responsable->dependencias()->updateExistingPivot($this->dependencia->id, ['responsable_cctv' => true]);
        $this->responsable->unsetRelation('dependencias');
    }

    /** @param  array<string, mixed>  $datos */
    private function registrar(array $datos = []): TestResponse
    {
        return $this->actingAs($this->responsable)->post(route('admin.turnos.cctv.store'), $datos + [
            'clave_formulario' => (string) Str::uuid(),
            'entidad' => 'policia',
            'requerido_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'descripcion' => 'Persona sospechosa frente al CAI.',
            'ubicacion' => 'Cra. 65 #97-121',
            'comuna' => 5,
        ]);
    }

    /** @param  array<string, mixed>  $datos */
    private function corregir(CasoCctv $caso, array $datos = []): TestResponse
    {
        return $this->actingAs($this->responsable)->put(route('admin.turnos.cctv.update', $caso), $datos + [
            'entidad' => 'policia',
            'requerido_at' => $caso->requerido_at->format('Y-m-d\TH:i'),
            'descripcion' => $caso->descripcion,
            'ubicacion' => $caso->ubicacion,
            'comuna' => 5,
        ]);
    }

    private function caso(array $datos = []): CasoCctv
    {
        return CasoCctv::factory()->de($this->calle)->create($datos + ['numero' => 1, 'requerido_at' => now()->subMinutes(85)]);
    }

    private function consultar(string $cedula)
    {
        return $this->postJson(route('cctv.caso', ['token' => $this->token]), ['cedula' => $cedula, 'numero' => '1']);
    }

    private function atender(string $cedula)
    {
        return $this->post(route('cctv.atender', ['token' => $this->token]), [
            'cedula' => $cedula, 'numero' => '1', 'evidencia1' => $this->archivoJpg('uno.jpg'),
        ]);
    }

    public function test_doc33_ca20_se_registra_sin_persona_y_sin_nodo_y_se_muestra_sin_asignar(): void
    {
        $this->registrar()->assertSessionHasNoErrors();

        $caso = CasoCctv::sole();
        $this->assertNull($caso->colaborador_asignado_id);
        $this->assertNull($caso->nodo_id);

        $this->actingAs($this->responsable)->get(route('admin.turnos.cctv.show', $caso))
            ->assertOk()->assertSee('Sin asignar')->assertSee('Sin nodo');
        $this->actingAs($this->responsable)->get(route('admin.turnos.cctv.index'))->assertOk();
        $this->actingAs($this->responsable)->get(route('admin.turnos.cctv.exportar'))->assertOk();

        // Con datos: la persona por cédula y el nodo activo del componente.
        $this->registrar(['cedula_asignado' => '1.017.000.111', 'nodo_id' => $this->nodoAna->id])->assertSessionHasNoErrors();
        $conDatos = CasoCctv::where('numero', 2)->sole();
        $this->assertSame([$this->jose->id, $this->nodoAna->id], [$conDatos->colaborador_asignado_id, $conDatos->nodo_id]);

        $inactivo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'activo' => false]);
        $this->registrar(['nodo_id' => $inactivo->id])->assertSessionHasErrors('nodo_id');
    }

    public function test_doc33_ca21_la_cedula_muestra_solo_el_nombre_de_una_persona_activa_del_componente(): void
    {
        $basica = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
        $ajena = Colaborador::factory()->en($basica)->create(['documento' => '1017000333', 'nombre' => 'Luis Otro Componente']);
        Colaborador::factory()->en($this->calle)->create(['documento' => '1017000444', 'activo' => false]);
        $ruta = route('admin.turnos.cctv.persona');

        $this->actingAs($this->responsable)->postJson($ruta, ['cedula' => '1.017.000.111'])
            ->assertOk()->assertExactJson(['nombre' => 'José Amaya Pérez']);
        foreach (['1017000333', '1017000444', '99999', ''] as $cedula) {
            $this->actingAs($this->responsable)->postJson($ruta, ['cedula' => $cedula])->assertOk()->assertExactJson(['nombre' => null]);
        }

        // Solo por POST, con permiso.
        $this->actingAs($this->responsable)->get($ruta)->assertNotFound();
        $lectura = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->calle);
        $this->actingAs($lectura)->postJson($ruta, ['cedula' => '1017000111'])->assertForbidden();

        // Al guardar, el servidor revalida y no revela nombres de otro componente.
        $respuesta = $this->registrar(['cedula_asignado' => $ajena->documento]);
        $respuesta->assertSessionHasErrors('cedula_asignado');
        $this->assertStringNotContainsString('Luis', json_encode(session('errors')->getBag('default')->all()));
        $this->registrar(['cedula_asignado' => '1017000444'])->assertSessionHasErrors('cedula_asignado');
        $this->assertSame(0, CasoCctv::count());

        // No hay lista desplegable de personas.
        $this->actingAs($this->responsable)->get(route('admin.turnos.cctv.create'))
            ->assertOk()->assertSee('cedula_asignado')->assertDontSee('colaborador_asignado_id')->assertDontSee('Ana Campo');
    }

    public function test_doc33_ca22_con_persona_asignada_solo_ella_atiende_y_la_otra_no_ve_nada(): void
    {
        $caso = $this->caso(['colaborador_asignado_id' => $this->jose->id]);

        $respuesta = $this->consultar('1017000222')->assertStatus(422)->assertExactJson(['message' => self::AJENO]);
        $this->assertStringNotContainsString('José', $respuesta->getContent());
        $this->assertStringNotContainsString('1017000111', $respuesta->getContent());

        // La misma respuesta de siempre para quien no es nadie: no se sondea.
        $this->consultar('99999999')->assertStatus(422)->assertExactJson(['message' => 'No hay un caso abierto con ese número.']);
        $auditoria = Auditoria::where('accion', 'caso_cctv.consultado')->latest('id')->skip(1)->first();
        $this->assertTrue($auditoria->datos['asignado_a_otra']);

        // Enviar directo al servidor tampoco sirve.
        $this->atender('1017000222')->assertSessionHasErrors(['numero' => self::AJENO]);
        $this->assertSame(CasoCctv::ABIERTO, $caso->fresh()->estado);
        $this->assertCount(0, Storage::disk(config('repositorio.disco'))->allFiles());

        $this->consultar('1017000111')->assertOk()->assertJsonPath('codigo', 'CCTV-001');
        $this->atender('1017000111')->assertSessionHasNoErrors();
        $this->assertSame([CasoCctv::ATENDIDO, $this->jose->id], [$caso->fresh()->estado, $caso->fresh()->colaborador_id]);
    }

    public function test_doc33_ca23_sin_persona_asignada_cualquiera_en_turno_lo_atiende(): void
    {
        $caso = $this->caso();

        $this->consultar('1017000222')->assertOk()->assertJsonPath('codigo', 'CCTV-001');
        $this->atender('1017000222')->assertSessionHasNoErrors();
        $this->assertSame([CasoCctv::ATENDIDO, $this->ana->id], [$caso->fresh()->estado, $caso->fresh()->colaborador_id]);
    }

    public function test_doc33_ca24_el_nodo_es_el_de_quien_atiende_si_el_caso_no_traia(): void
    {
        $sinNodo = $this->caso();
        $conNodo = $this->caso(['numero' => 2, 'nodo_id' => $this->nodoJose->id]);

        $this->atender('1017000222')->assertSessionHasNoErrors();
        $this->assertSame($this->nodoAna->id, $sinNodo->fresh()->nodo_id);

        $this->post(route('cctv.atender', ['token' => $this->token]), [
            'cedula' => '1017000222', 'numero' => '2', 'evidencia1' => $this->archivoJpg('dos.jpg'),
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->nodoJose->id, $conNodo->fresh()->nodo_id);

        // PA-12: sin nodo en ninguno de los dos, se atiende igual y queda sin nodo.
        $this->ana->update(['nodo_id' => null]);
        $tercero = $this->caso(['numero' => 3]);
        $this->post(route('cctv.atender', ['token' => $this->token]), [
            'cedula' => '1017000222', 'numero' => '3', 'evidencia1' => $this->archivoJpg('tres.jpg'),
        ])->assertSessionHasNoErrors();
        $this->assertSame([CasoCctv::ATENDIDO, null], [$tercero->fresh()->estado, $tercero->fresh()->nodo_id]);
    }

    public function test_doc33_ca25_corregir_quita_o_cambia_la_asignacion_y_el_nodo_con_auditoria(): void
    {
        $caso = $this->caso(['colaborador_asignado_id' => $this->jose->id, 'nodo_id' => $this->nodoJose->id]);

        // Cambiar: ahora solo Ana.
        $this->corregir($caso, ['cedula_asignado' => '1017000222', 'nodo_id' => $this->nodoAna->id])->assertSessionHasNoErrors();
        $this->assertSame([$this->ana->id, $this->nodoAna->id], [$caso->fresh()->colaborador_asignado_id, $caso->fresh()->nodo_id]);
        $this->consultar('1017000111')->assertStatus(422)->assertExactJson(['message' => self::AJENO]);

        $auditoria = Auditoria::where('accion', 'caso_cctv.corregido')->latest('id')->first();
        $this->assertSame($this->jose->id, $auditoria->datos['cambios']['colaborador_asignado_id']['antes']);
        $this->assertSame($this->ana->id, $auditoria->datos['cambios']['colaborador_asignado_id']['despues']);

        // Quitar: lo atiende cualquiera en turno.
        $this->corregir($caso, ['cedula_asignado' => '', 'nodo_id' => ''])->assertSessionHasNoErrors();
        $this->assertSame([null, null], [$caso->fresh()->colaborador_asignado_id, $caso->fresh()->nodo_id]);
        $this->consultar('1017000111')->assertOk();

        // Una cédula que no sirve no cambia nada.
        $this->corregir($caso, ['cedula_asignado' => '99999'])->assertSessionHasErrors('cedula_asignado');

        // Cerrado o anulado: ya no se corrige.
        $caso->update(['estado' => CasoCctv::CERRADO]);
        $this->corregir($caso, ['cedula_asignado' => ''])->assertForbidden();
    }
}
