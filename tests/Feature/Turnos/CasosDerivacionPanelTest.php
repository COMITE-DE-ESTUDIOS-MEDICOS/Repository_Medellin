<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Nodo;
use App\Models\User;
use App\Notifications\CasoAtendido;
use App\Notifications\DerivacionPendiente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DOC-20: el panel de trazabilidad, las correcciones, el Excel y los avisos (CA-09 a CA-12). */
class CasosDerivacionPanelTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private Colaborador $jose;

    private User $edicion;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 2']);
        $this->jose = Colaborador::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'documento' => '1017000111',
            'nombre' => 'José Amaya Pérez', 'cargo' => 'Monitor de ruta', 'componente_id' => $this->calle->id,
        ]);
        $this->edicion = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->superadmin = User::factory()->superadmin()->create();
    }

    /** Un caso DC abierto, creado por Edición a las 10:00. */
    private function caso(array $datos = []): Derivacion
    {
        $caso = Derivacion::create($datos + [
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->calle->id,
            'numero' => (int) Derivacion::withoutGlobalScopes()->max('numero') + 1, 'creado_por' => $this->edicion->id,
            'remitente' => 'alcaldia', 'entidad' => 'Alcaldía', 'descripcion' => 'Habitante de calle requiere traslado.',
            'ubicacion' => 'Cra. 65 #97-121, Castilla', 'estado' => Derivacion::PENDIENTE,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
        ]);
        $caso->forceFill(['created_at' => Carbon::today()->setTime(10, 0)])->saveQuietly();

        return $caso;
    }

    /** Lo que deja la persona de campo al enviar la atención a las 10:45. */
    private function atendido(array $datos = []): Derivacion
    {
        return $this->caso($datos + [
            'estado' => Derivacion::SOLUCIONADA, 'colaborador_id' => $this->jose->id, 'cedula' => '1017000111',
            'nombre' => 'José Amaya Pérez', 'cargo' => 'Monitor de ruta', 'nodo_id' => $this->nodo->id,
            'atendida_at' => Carbon::today()->setTime(10, 45), 'hora_respuesta' => '10:45', 'tiempo_respuesta_min' => 45,
            'cumplio_requerimiento' => true, 'observacion' => 'Se trasladó al hogar de paso.',
        ]);
    }

    private function textos(array $datos = []): array
    {
        return $datos + ['descripcion' => 'Corregido', 'remitente' => 'otra', 'remitente_otra' => 'Personería', 'ubicacion' => 'Castilla', 'observacion' => 'Ok'];
    }

    private function comoSuperadmin()
    {
        return $this->actingAs($this->superadmin)->withSession(['dependencia_id' => $this->dependencia->id]);
    }

    public function test_doc20_ca09_edicion_y_coordinacion_no_cambian_horas_ni_si_no_el_superadmin_si_con_motivo(): void
    {
        $caso = $this->atendido();
        $horas = ['fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '09:00',
            'atendida_at' => now()->format('Y-m-d').'T10:30', 'cumplio_requerimiento' => '0', 'motivo' => 'Intento'];

        foreach ([RolDependencia::Edicion, RolDependencia::Coordinacion, RolDependencia::Administracion] as $rol) {
            $usuario = $this->usuarioCon($rol, $this->dependencia);
            $this->actingAs($usuario)->get(route('admin.turnos.derivaciones.edit', $caso))->assertOk()->assertDontSee('Fecha y hora de cierre');
            $this->actingAs($usuario)->put(route('admin.turnos.derivaciones.update', $caso), $this->textos($horas))->assertSessionHasNoErrors();

            $caso->refresh();
            $this->assertSame(['Personería', 'otra', '10:00', '10:45', true, 45], [
                $caso->entidad, $caso->remitente, substr($caso->hora_requerimiento, 0, 5), $caso->atendida_at->format('H:i'),
                $caso->cumplio_requerimiento, $caso->tiempo_respuesta_min,
            ], "{$rol->value} no cambia horas ni el Sí/No.");
        }
        $this->assertDatabaseMissing('auditorias', ['accion' => 'caso.contingencia']);

        // El superadministrador sí, pero con motivo.
        $this->comoSuperadmin()->get(route('admin.turnos.derivaciones.edit', $caso))->assertOk()->assertSee('Fecha y hora de cierre');
        $this->comoSuperadmin()->put(route('admin.turnos.derivaciones.update', $caso), $this->textos(['motivo' => ''] + $horas))
            ->assertSessionHasErrors('motivo');
        $this->assertSame(45, $caso->fresh()->tiempo_respuesta_min);

        // Con «No», la observación es obligatoria.
        $this->comoSuperadmin()->put(route('admin.turnos.derivaciones.update', $caso), $this->textos(['motivo' => 'Se cayó la señal', 'observacion' => ''] + $horas))
            ->assertSessionHasErrors('observacion');

        // 07:00 a 10:30 pasa la meta: sin justificación, no se guarda.
        $this->comoSuperadmin()->put(route('admin.turnos.derivaciones.update', $caso), $this->textos(['hora_requerimiento' => '07:00', 'motivo' => 'Se cayó la señal'] + $horas))
            ->assertSessionHasErrors('justificacion');

        $this->comoSuperadmin()->put(route('admin.turnos.derivaciones.update', $caso), $this->textos(['motivo' => 'Se cayó la señal'] + $horas))
            ->assertSessionHasNoErrors();
        $caso->refresh();
        $this->assertSame(['09:00', '10:30', '10:30', false, 90], [
            substr($caso->hora_requerimiento, 0, 5), $caso->atendida_at->format('H:i'), substr($caso->hora_respuesta, 0, 5),
            $caso->cumplio_requerimiento, $caso->tiempo_respuesta_min,
        ]);

        $auditoria = \App\Models\Auditoria::where('accion', 'caso.contingencia')->sole();
        $this->assertSame('Se cayó la señal', $auditoria->datos['motivo']);
        $this->assertSame('10:00', substr($auditoria->datos['antes']['hora_requerimiento'], 0, 5));
        $this->assertSame('09:00', substr($auditoria->datos['despues']['hora_requerimiento'], 0, 5));
        $this->assertSame(45, (int) $auditoria->datos['antes']['tiempo_respuesta_min']);
        $this->assertSame(90, (int) $auditoria->datos['despues']['tiempo_respuesta_min']);
    }

    public function test_doc20_ca09_cierre_a_mano_solo_superadmin(): void
    {
        $caso = $this->caso();
        $cierre = ['colaborador_id' => $this->jose->id, 'nodo_id' => $this->nodo->id, 'atendida_at' => now()->format('Y-m-d').'T10:40',
            'cumplio_requerimiento' => '1', 'motivo' => 'El celular de José se quedó sin batería'];

        foreach ([RolDependencia::Edicion, RolDependencia::Coordinacion, RolDependencia::Administracion] as $rol) {
            $usuario = $this->usuarioCon($rol, $this->dependencia);
            $this->actingAs($usuario)->get(route('admin.turnos.derivaciones.show', $caso))->assertOk()->assertDontSee('Cerrar a mano');
            $this->actingAs($usuario)->patch(route('admin.turnos.derivaciones.contingencia', $caso), $cierre)->assertForbidden();
        }
        $this->assertTrue($caso->fresh()->estaPendiente());

        $this->comoSuperadmin()->get(route('admin.turnos.derivaciones.show', $caso))->assertOk()->assertSee('Cerrar a mano (contingencia)');
        $this->comoSuperadmin()->patch(route('admin.turnos.derivaciones.contingencia', $caso), ['motivo' => ''] + $cierre)->assertSessionHasErrors('motivo');
        $this->comoSuperadmin()->patch(route('admin.turnos.derivaciones.contingencia', $caso), ['cumplio_requerimiento' => '0'] + $cierre)->assertSessionHasErrors('observacion');

        $this->comoSuperadmin()->patch(route('admin.turnos.derivaciones.contingencia', $caso), $cierre)->assertSessionHasNoErrors();
        $caso->refresh();
        $this->assertSame(['solucionada', '1017000111', 'José Amaya Pérez', $this->nodo->id, '10:40', 40, true], [
            $caso->estado, $caso->cedula, $caso->nombre, $caso->nodo_id, $caso->atendida_at->format('H:i'), $caso->tiempo_respuesta_min, $caso->cumplio_requerimiento,
        ]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'caso.contingencia', 'auditable_id' => $caso->id]);
        $this->assertSame(1, $this->edicion->notifications()->where('type', CasoAtendido::class)->count(), 'El creador recibe su aviso.');

        // Ya atendido, no se cierra otra vez.
        $this->comoSuperadmin()->patch(route('admin.turnos.derivaciones.contingencia', $caso), $cierre)->assertStatus(422);
    }

    public function test_doc20_ca10_la_ficha_y_el_excel_muestran_creador_persona_nodo_horas_y_cumplimiento(): void
    {
        $atendido = $this->atendido();
        $abierto = $this->caso(['ubicacion' => 'Parque de Boston']);

        $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.show', $atendido))->assertOk()
            ->assertSee($atendido->codigo())
            ->assertSee($this->edicion->name)
            ->assertSee('José Amaya Pérez')->assertSee('1017000111')->assertSee('Monitor de ruta')
            ->assertSee('Nodo 2')
            ->assertSee('10:00 a. m.')->assertSee('10:45 a. m.')
            ->assertSee('45 min')->assertSee('cumplió la meta')
            ->assertSee('¿Se cumplió el requerimiento?')->assertSee('Se trasladó al hogar de paso.')
            ->assertDontSee('Responder')->assertDontSee('Cerrar a mano');

        $index = $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.index'))->assertOk();
        $this->assertSame('José Amaya Pérez', $index->viewData('traza')['personas']->sole()->nombre);
        $this->assertSame(1, (int) $index->viewData('traza')['personas']->sole()->a_tiempo);
        $index->assertSee('Nuevo caso');

        // La búsqueda por código: «DC-2», «dc-002» o «2».
        foreach (['DC-2', 'dc-002', '2'] as $q) {
            $ids = $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.index', ['q' => $q]))->viewData('derivaciones')->pluck('id');
            $this->assertContains($abierto->id, $ids, "Busca «{$q}».");
            $this->assertNotContains($atendido->id, $ids);
        }

        $filas = $this->hojaXlsx($this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.exportar'))->assertOk(), 'Derivaciones');
        $encabezados = $filas[0];
        $this->assertSame(['CÓDIGO DEL CASO', 'QUIÉN LO CREÓ', 'FECHA Y HORA DE CREACIÓN', 'FECHA Y HORA DE ATENCIÓN', '¿SE CUMPLIÓ EL REQUERIMIENTO?', 'OBSERVACIÓN'], array_slice($encabezados, 21));
        $col = array_flip($encabezados);
        $porCodigo = collect(array_slice($filas, 1))->keyBy(fn ($f) => $f[$col['CÓDIGO DEL CASO']]);

        $fila = $porCodigo['DC-001'];
        $this->assertSame([$this->edicion->name, '1017000111', 'José Amaya Pérez', 'Nodo 2', 'Atendido', 'Sí', 'Se trasladó al hogar de paso.'], [
            $fila[$col['QUIÉN LO CREÓ']], (string) $fila[$col['CC DE QUIEN ESCALA LA NOVEDAD']], $fila[$col['NOMBRE DE QUIEN ESCALA LA NOVEDAD']],
            $fila[$col['NODO QUE ATIENDE EL REQUERIEMIENTO']], $fila[$col['ESTADO']], $fila[$col['¿SE CUMPLIÓ EL REQUERIMIENTO?']], $fila[$col['OBSERVACIÓN']],
        ]);
        $this->assertSame(now()->format('d/m/Y').' 10:00', $fila[$col['FECHA Y HORA DE CREACIÓN']]);
        $this->assertSame(now()->format('d/m/Y').' 10:45', $fila[$col['FECHA Y HORA DE ATENCIÓN']]);
        $this->assertSame('0:45', $fila[$col['TIEMPO DE RESPUESTA']]);

        // Abierto: sin persona ni nodo.
        $fila = $porCodigo['DC-002'];
        $this->assertSame([null, null, null, 'Abierto'], [
            $fila[$col['CC DE QUIEN ESCALA LA NOVEDAD']], $fila[$col['NOMBRE DE QUIEN ESCALA LA NOVEDAD']],
            $fila[$col['NODO QUE ATIENDE EL REQUERIEMIENTO']], $fila[$col['ESTADO']],
        ]);
    }

    public function test_doc20_ca11_un_solo_aviso_al_vencer_y_al_atender_se_retira(): void
    {
        $coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $lectura = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $caso = $this->caso();
        $avisos = fn (User $u) => $u->notifications()->where('type', DerivacionPendiente::class)->count();

        // A las 12:59 (2 h 59 min) todavía no.
        Carbon::setTestNow(Carbon::today()->setTime(12, 59));
        $this->artisan('derivaciones:recordar')->assertSuccessful();
        $this->assertSame(0, $avisos($this->edicion));

        // Pasada la meta, uno solo, a Edición y Coordinación; Lectura no.
        Carbon::setTestNow(Carbon::today()->setTime(13, 5));
        $this->artisan('derivaciones:recordar');
        Carbon::setTestNow(Carbon::today()->setTime(15, 0));
        $this->artisan('derivaciones:recordar');
        $this->assertSame([1, 1, 0], [$avisos($this->edicion), $avisos($coordinacion), $avisos($lectura)]);
        $this->assertSame('DC-001 venció sin atención: lleva 3 h 5 min.', $this->edicion->notifications()->first()->data['mensaje']);
        $this->assertNotNull($caso->fresh()->ultimo_aviso_at);

        // La persona de campo lo atiende: el creador recibe su aviso y el de vencido se retira.
        $caso->update(['estado' => Derivacion::SOLUCIONADA, 'colaborador_id' => $this->jose->id, 'nombre' => 'José Amaya Pérez',
            'cedula' => '1017000111', 'nodo_id' => $this->nodo->id, 'atendida_at' => now(), 'hora_respuesta' => '15:00',
            'tiempo_respuesta_min' => 300, 'cumplio_requerimiento' => true, 'justificacion' => 'Lejos.']);
        CasoAtendido::enviar($caso);

        $this->assertSame([0, 0], [$avisos($this->edicion), $avisos($coordinacion)]);
        $this->assertSame(1, $this->edicion->notifications()->where('type', CasoAtendido::class)->count());
        $this->assertSame(0, $coordinacion->notifications()->where('type', CasoAtendido::class)->count());

        // La campana los pone en la pestaña «Derivaciones».
        $this->actingAs($this->edicion)->get(route('notificaciones.index', ['tipo' => 'derivaciones']))->assertOk()->assertSee('DC-001 atendido');
    }

    public function test_doc20_ca12_los_anteriores_siguen_y_su_hora_de_cierre_solo_la_ajusta_el_superadmin(): void
    {
        $anterior = Derivacion::create([
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->calle->id, 'colaborador_id' => $this->jose->id,
            'cedula' => '1017000111', 'nombre' => 'José Amaya Pérez', 'cargo' => 'Monitor de ruta', 'nodo_id' => $this->nodo->id,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00', 'entidad' => 'Policía Nacional',
            'descripcion' => 'Traslado', 'ubicacion' => 'Castilla', 'estado' => Derivacion::SOLUCIONADA,
            'respuesta' => 'Atendido.', 'hora_respuesta' => '10:30', 'tiempo_respuesta_min' => 30,
        ]);
        $pendiente = Derivacion::create(['hora_requerimiento' => '07:55', 'estado' => Derivacion::PENDIENTE] + $anterior->only([
            'dependencia_id', 'componente_id', 'colaborador_id', 'cedula', 'nombre', 'cargo', 'nodo_id', 'fecha_requerimiento', 'entidad', 'descripcion', 'ubicacion',
        ]));

        $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.index'))->assertOk()->assertSee('registro anterior')->assertSee("#{$anterior->id}");
        $filas = $this->hojaXlsx($this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.exportar')), 'Derivaciones');
        $this->assertSame(['Registro anterior', 'Registro anterior'], array_column(array_slice($filas, 1), 21));

        $datos = ['nodo_id' => $this->nodo->id, 'entidad' => 'ICBF', 'descripcion' => 'Traslado', 'ubicacion' => 'Castilla', 'respuesta' => 'Atendido.'];

        foreach ([RolDependencia::Edicion, RolDependencia::Coordinacion, RolDependencia::Administracion] as $rol) {
            $this->actingAs($this->usuarioCon($rol, $this->dependencia))
                ->put(route('admin.turnos.derivaciones.update', $anterior), $datos + ['hora_respuesta' => '10:20', 'motivo' => 'x'])->assertSessionHasNoErrors();
            $this->assertSame(['10:30', 30], [substr($anterior->fresh()->hora_respuesta, 0, 5), $anterior->fresh()->tiempo_respuesta_min]);
        }
        $this->assertSame('ICBF', $anterior->fresh()->entidad);

        $horas = ['fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00', 'hora_respuesta' => '10:20'];
        $this->comoSuperadmin()->put(route('admin.turnos.derivaciones.update', $anterior), $datos + $horas)->assertSessionHasErrors('motivo');
        $this->comoSuperadmin()->put(route('admin.turnos.derivaciones.update', $anterior), $datos + $horas + ['motivo' => 'Acta del 10-11'])->assertSessionHasNoErrors();
        $this->assertSame(['10:20', 20], [substr($anterior->fresh()->hora_respuesta, 0, 5), $anterior->fresh()->tiempo_respuesta_min]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'caso.contingencia', 'auditable_id' => $anterior->id]);

        // La pendiente anterior recibe el aviso de vencido una vez, con su código «#id».
        $this->artisan('derivaciones:recordar');
        $this->artisan('derivaciones:recordar');
        $this->assertSame(["#{$pendiente->id} venció sin atención: lleva 3 h 5 min."], $this->edicion->notifications()->where('type', DerivacionPendiente::class)->get()->pluck('data.mensaje')->all());

        // Y también se puede cerrar a mano.
        $this->comoSuperadmin()->patch(route('admin.turnos.derivaciones.contingencia', $pendiente), [
            'colaborador_id' => $this->jose->id, 'nodo_id' => $this->nodo->id, 'atendida_at' => now()->format('Y-m-d').'T10:30',
            'cumplio_requerimiento' => '1', 'justificacion' => 'Sin móvil', 'motivo' => 'No se respondió a tiempo en Documenta',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['solucionada', 155], [$pendiente->fresh()->estado, $pendiente->fresh()->tiempo_respuesta_min]);
        $this->assertSame(0, $this->edicion->notifications()->where('type', DerivacionPendiente::class)->count());
    }
}
