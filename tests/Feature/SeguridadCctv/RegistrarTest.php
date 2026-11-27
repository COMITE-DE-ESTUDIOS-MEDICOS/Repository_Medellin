<?php

namespace Tests\Feature\SeguridadCctv;

use App\Enums\RolDependencia;
use App\Models\Auditoria;
use App\Models\CasoCctv;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Nodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** DOC-33 (CCTV-03): registrar y corregir un caso. */
class RegistrarTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private Colaborador $persona;

    private User $responsable;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(14, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id]);
        $this->persona = Colaborador::factory()->en($this->calle, $this->nodo)->create();
        $this->responsable = $this->conCasilla(RolDependencia::Edicion, $this->calle, true);
    }

    private function conCasilla(RolDependencia $rol, Componente $componente, bool $casilla): User
    {
        $usuario = $this->usuarioCon($rol, $this->dependencia, $componente);
        $usuario->dependencias()->updateExistingPivot($this->dependencia->id, ['responsable_cctv' => $casilla]);

        return $usuario->unsetRelation('dependencias');
    }

    /** @param  array<string, mixed>  $datos */
    private function registrar(array $datos = [], ?User $usuario = null): TestResponse
    {
        return $this->actingAs($usuario ?? $this->responsable)->post(route('admin.turnos.cctv.store'), $datos + [
            'clave_formulario' => (string) Str::uuid(),
            'entidad' => 'policia',
            'requerido_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'cedula_asignado' => $this->persona->documento,
            'nodo_id' => $this->nodo->id,
            'descripcion' => 'Persona sospechosa frente al CAI.',
            'ubicacion' => 'Cra. 65 #97-121',
            'comuna' => 5,
        ]);
    }

    public function test_doc33_ca04_quien_tiene_la_casilla_registra_y_edicion_sin_ella_no(): void
    {
        $this->actingAs($this->responsable)->get(route('admin.turnos.cctv.create'))
            ->assertOk()->assertSee('Entidad que solicita')->assertSee('Nodo que atiende');

        $this->registrar()->assertSessionHasNoErrors()->assertRedirect(route('admin.turnos.cctv.create'))
            ->assertSessionHas('caso.creado.codigo', 'CCTV-001');

        $caso = CasoCctv::sole();
        $this->assertSame([CasoCctv::ABIERTO, $this->responsable->id, $this->calle->id, $this->dependencia->id], [$caso->estado, $caso->creado_por, $caso->componente_id, $caso->dependencia_id]);
        $this->assertSame('CCTV-001', $caso->codigo());

        // El número en grande al volver al formulario.
        $this->actingAs($this->responsable)->withSession(['caso.creado' => ['id' => $caso->id, 'codigo' => 'CCTV-001', 'ubicacion' => 'x']])
            ->get(route('admin.turnos.cctv.create'))->assertSee('CCTV-001')->assertSee('Registrar otro');

        $sinCasilla = $this->conCasilla(RolDependencia::Edicion, $this->calle, false);
        $this->actingAs($sinCasilla)->get(route('admin.turnos.cctv.create'))->assertForbidden();
        $this->registrar([], $sinCasilla)->assertForbidden();

        $lectura = $this->conCasilla(RolDependencia::Lectura, $this->calle, true);
        $this->registrar([], $lectura)->assertForbidden();
        $this->assertSame(1, CasoCctv::count());
    }

    public function test_doc33_ca05_registrar_exige_los_datos_y_el_soporte_es_opcional_e_imagen(): void
    {
        // Los datos se crean antes de la primera petición: ya fija el componente y no deja crear en otro.
        $otro = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
        $nodoAjeno = Nodo::factory()->create(['componente_id' => $otro->id]);
        $ajena = Colaborador::factory()->en($otro, $nodoAjeno)->create();
        $inactiva = Colaborador::factory()->en($this->calle, $this->nodo)->create(['activo' => false]);
        $nodoInactivo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'activo' => false]);
        $this->registrar(['entidad' => 'otras'])->assertSessionHasErrors('entidad_otra');
        $this->registrar(['entidad' => 'bomberos'])->assertSessionHasErrors('entidad');
        $this->registrar(['requerido_at' => now()->addHour()->format('Y-m-d\TH:i')])->assertSessionHasErrors('requerido_at');
        $this->registrar(['descripcion' => ''])->assertSessionHasErrors('descripcion');
        $this->registrar(['ubicacion' => ''])->assertSessionHasErrors('ubicacion');
        $this->registrar(['comuna' => 99])->assertSessionHasErrors('comuna');
        $this->registrar(['comuna' => ''])->assertSessionHasErrors('comuna');
        $this->registrar(['soporte' => $this->archivoPdf()])->assertSessionHasErrors('soporte');

        // Persona y nodo: inactivos o de otro componente no valen.
        $this->registrar(['cedula_asignado' => $ajena->documento])->assertSessionHasErrors('cedula_asignado');
        $this->registrar(['cedula_asignado' => $inactiva->documento])->assertSessionHasErrors('cedula_asignado');
        $this->registrar(['nodo_id' => $nodoAjeno->id])->assertSessionHasErrors('nodo_id');
        $this->registrar(['nodo_id' => $nodoInactivo->id])->assertSessionHasErrors('nodo_id');
        $this->assertSame(0, CasoCctv::count());

        // Sin soporte, con «Otras» y su texto.
        $this->registrar(['entidad' => 'otras', 'entidad_otra' => 'Defensa Civil'])->assertSessionHasNoErrors();
        $this->assertSame('Otras: Defensa Civil', CasoCctv::sole()->entidadLegible());

        // Con soporte imagen: queda como documento en la carpeta con custodia.
        $this->registrar(['soporte' => $this->archivoJpg()])->assertSessionHasNoErrors();
        $caso = CasoCctv::where('numero', 2)->sole();
        $this->assertSame('Caso CCTV-002 · Soporte', $caso->soporte->nombre);
        $this->assertSame('Seguridad CCTV · Calle', $caso->soporte->carpeta->nombre);
        $this->assertNotNull($caso->soporte->carpeta->grupo_custodia_id);
    }

    public function test_doc33_ca06_consecutivo_por_dependencia_independiente_y_doble_toque(): void
    {
        $clave = (string) Str::uuid();
        $this->registrar(['clave_formulario' => $clave])->assertSessionHas('caso.creado.codigo', 'CCTV-001');
        $this->registrar(['clave_formulario' => $clave])->assertSessionHas('caso.creado.codigo', 'CCTV-001');
        $this->assertSame(1, CasoCctv::count());

        $this->registrar()->assertSessionHas('caso.creado.codigo', 'CCTV-002');

        // Los números ya usados (también anulados o de otro componente) no se reutilizan.
        CasoCctv::where('numero', 2)->update(['estado' => CasoCctv::ANULADO]);
        $this->registrar()->assertSessionHas('caso.creado.codigo', 'CCTV-003');
        $this->assertSame([1, 2, 3], CasoCctv::withoutGlobalScopes()->orderBy('numero')->pluck('numero')->all());
    }

    public function test_doc33_ca15_registrar_deja_auditoria(): void
    {
        $this->registrar()->assertSessionHasNoErrors();

        $caso = CasoCctv::sole();
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'caso_cctv.registrado', 'auditable_id' => $caso->id, 'user_id' => $this->responsable->id, 'componente_id' => $this->calle->id,
        ]);
    }

    public function test_doc33_ca13_corregir_exige_motivo_si_cambia_la_hora_recalcula_y_audita(): void
    {
        $caso = CasoCctv::factory()->de($this->calle)->atendido(60)->create([
            'requerido_at' => now()->subHours(3), 'nodo_id' => $this->nodo->id, 'colaborador_asignado_id' => $this->persona->id,
            'entidad' => 'camaras', 'comuna' => 5,
        ]);
        $respuesta = $caso->atendido_at->copy();

        $this->actingAs($this->responsable)->get(route('admin.turnos.cctv.edit', $caso))
            ->assertOk()->assertSee('Corregir '.$caso->codigo())->assertSee('Motivo');

        $base = fn (array $d = []) => $d + [
            'entidad' => 'camaras', 'requerido_at' => $caso->requerido_at->format('Y-m-d\TH:i'),
            'cedula_asignado' => $this->persona->documento, 'nodo_id' => $this->nodo->id,
            'descripcion' => $caso->descripcion, 'ubicacion' => 'Parque Berrío', 'comuna' => 5,
        ];
        $enviar = fn (array $d) => $this->actingAs($this->responsable)->put(route('admin.turnos.cctv.update', $caso), $base($d));

        // Sin tocar la hora no hace falta motivo.
        $enviar([])->assertSessionHasNoErrors()->assertRedirect(route('admin.turnos.cctv.show', $caso));
        $this->assertSame('Parque Berrío', $caso->fresh()->ubicacion);

        // Cambiar la hora: motivo obligatorio, no futura, no posterior a la respuesta.
        $nueva = now()->subHours(4)->format('Y-m-d\TH:i');
        $enviar(['requerido_at' => $nueva])->assertSessionHasErrors('motivo');
        $enviar(['requerido_at' => now()->addHour()->format('Y-m-d\TH:i'), 'motivo' => 'x'])->assertSessionHasErrors('requerido_at');
        $enviar(['requerido_at' => $respuesta->copy()->addMinutes(5)->format('Y-m-d\TH:i'), 'motivo' => 'x'])->assertSessionHasErrors('requerido_at');
        $this->assertSame(60, $caso->fresh()->tiempo_respuesta_min);

        $enviar(['requerido_at' => $nueva, 'motivo' => 'Se anotó mal la hora'])->assertSessionHasNoErrors();
        $caso->refresh();
        $this->assertSame(now()->subHours(4)->format('Y-m-d H:i'), $caso->requerido_at->format('Y-m-d H:i'));
        $this->assertTrue($respuesta->equalTo($caso->atendido_at));
        $this->assertSame(CasoCctv::minutosEntre($caso->requerido_at, $respuesta), $caso->tiempo_respuesta_min);

        $auditoria = Auditoria::where('accion', 'caso_cctv.corregido')->latest('id')->first();
        $this->assertSame('Se anotó mal la hora', $auditoria->datos['motivo']);
        $this->assertArrayHasKey('requerido_at', $auditoria->datos['cambios']);
        $this->assertNotNull($auditoria->datos['cambios']['requerido_at']['antes']);
        $this->assertSame(2, Auditoria::where('accion', 'caso_cctv.corregido')->count());
    }

    public function test_doc33_ca13_un_caso_cerrado_o_anulado_no_se_corrige_ni_sin_casilla(): void
    {
        $datos = ['entidad' => 'camaras', 'requerido_at' => now()->subHours(2)->format('Y-m-d\TH:i'), 'cedula_asignado' => $this->persona->documento,
            'nodo_id' => $this->nodo->id, 'descripcion' => 'd', 'ubicacion' => 'u', 'comuna' => 5];

        foreach ([CasoCctv::CERRADO, CasoCctv::ANULADO] as $estado) {
            $caso = CasoCctv::factory()->de($this->calle)->create(['estado' => $estado]);
            $this->actingAs($this->responsable)->get(route('admin.turnos.cctv.edit', $caso))->assertForbidden();
            $this->actingAs($this->responsable)->put(route('admin.turnos.cctv.update', $caso), $datos)->assertForbidden();
        }

        $abierto = CasoCctv::factory()->de($this->calle)->create();
        $sinCasilla = $this->conCasilla(RolDependencia::Edicion, $this->calle, false);
        $this->actingAs($sinCasilla)->get(route('admin.turnos.cctv.edit', $abierto))->assertForbidden();
        $this->actingAs($sinCasilla)->put(route('admin.turnos.cctv.update', $abierto), $datos)->assertForbidden();
    }

    public function test_doc33_ca04_no_corrige_un_caso_de_otro_componente(): void
    {
        $basica = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
        $ajeno = CasoCctv::factory()->de($basica)->create();

        $this->actingAs($this->responsable)->get(route('admin.turnos.cctv.edit', $ajeno))->assertNotFound();
    }
}
