<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\ComentarioDerivacion;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** DOC-23: comentarios en el caso de derivación (CA-05 a CA-08). */
class CasosComentariosTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private User $creador;

    private User $coordinacion;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->creador = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    private function caso(array $datos = []): Derivacion
    {
        return Derivacion::withoutGlobalScopes()->create($datos + [
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->calle->id,
            'numero' => (int) Derivacion::withoutGlobalScopes()->max('numero') + 1, 'creado_por' => $this->creador->id,
            'remitente' => 'alcaldia', 'entidad' => 'Alcaldía', 'descripcion' => 'Habitante de calle requiere traslado.',
            'ubicacion' => 'Parque de Castilla', 'estado' => Derivacion::PENDIENTE,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
        ]);
    }

    private function comentar(User $usuario, Derivacion $caso, string $texto = 'Confirma el traslado.')
    {
        return $this->actingAs($usuario)->post(route('admin.turnos.derivaciones.comentarios.store', $caso), ['texto' => $texto]);
    }

    public function test_doc23_ca05_coordinacion_y_administracion_comentan_edicion_responde_lectura_no(): void
    {
        $caso = $this->caso();
        $antes = $caso->fresh()->only(['estado', 'updated_at', 'atendida_at']);

        $this->comentar($this->coordinacion, $caso, "Llama a la Alcaldía.\nY confirma.")
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->comentar($this->usuarioCon(RolDependencia::Administracion, $this->dependencia), $caso, 'Prioridad alta.')
            ->assertSessionHasNoErrors();
        $this->comentar($this->creador, $caso, 'Listo, ya llamé.')->assertSessionHasNoErrors();
        $this->assertSame(3, $caso->comentarios()->count());

        // Lectura no comenta (ni ve el caso).
        $lectura = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $this->comentar($lectura, $caso)->assertForbidden();
        $this->actingAs($lectura)->get(route('admin.turnos.derivaciones.show', $caso))->assertForbidden();

        // Validación.
        $this->comentar($this->creador, $caso, '')->assertSessionHasErrors(['texto' => 'Escribe el comentario.']);
        $this->comentar($this->creador, $caso, str_repeat('a', 2001))->assertSessionHasErrors('texto');

        // Comentar no cambia el caso.
        $this->assertEquals($antes, $caso->fresh()->only(['estado', 'updated_at', 'atendida_at']));

        // También en un caso atendido.
        $atendido = $this->caso(['estado' => Derivacion::SOLUCIONADA, 'atendida_at' => now()]);
        $this->comentar($this->coordinacion, $atendido)->assertSessionHasNoErrors();
        $this->assertSame(Derivacion::SOLUCIONADA, $atendido->fresh()->estado);

        // La ficha: sección abierta, texto escapado con saltos de línea, formulario.
        $this->actingAs($this->creador)->get(route('admin.turnos.derivaciones.show', $caso))
            ->assertOk()
            ->assertSee('Comentarios <span class="text-[var(--muted)]">(3)</span>', false)
            ->assertSee('<details id="comentarios"', false)
            ->assertSee("Llama a la Alcaldía.\nY confirma.")
            ->assertSee(route('admin.turnos.derivaciones.comentarios.store', $caso));

        // Sin comentarios: cerrada.
        $vacio = $this->caso();
        $html = $this->actingAs($this->creador)->get(route('admin.turnos.derivaciones.show', $vacio))->assertSee('Comentarios')->getContent();
        $this->assertDoesNotMatchRegularExpression('/<details id="comentarios"[^>]*\sopen/', $html);

        // De otra dependencia, tampoco (al final: cambia la dependencia de la sesión).
        $this->comentar($this->usuarioCon(RolDependencia::Administracion), $caso)->assertForbidden();
        $this->assertSame(3, $caso->comentarios()->count());
    }

    public function test_doc23_ca06_avisa_al_creador_y_a_quienes_comentaron_no_al_autor(): void
    {
        $caso = $this->caso();
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->comentar($this->coordinacion, $caso, 'Llama a la Alcaldía.');
        $comentario = $caso->comentarios()->first();

        $this->assertCount(1, $this->creador->notifications);
        $this->assertCount(0, $this->coordinacion->notifications);
        $aviso = $this->creador->notifications->first()->data;
        $this->assertStringStartsWith('Comentario en el caso DC-001', $aviso['mensaje']);
        $this->assertStringContainsString('Llama a la Alcaldía.', $aviso['mensaje']);
        $this->assertSame(route('admin.turnos.derivaciones.show', $caso, false).'#comentario-'.$comentario->id, $aviso['url']);

        // Administración comenta: se enteran el creador y Coordinación, no ella.
        $this->comentar($admin, $caso, str_repeat('Texto largo ', 50));
        $this->assertCount(2, $this->creador->fresh()->notifications);
        $this->assertCount(1, $this->coordinacion->fresh()->notifications);
        $this->assertCount(0, $admin->fresh()->notifications);
        $this->assertLessThan(200, mb_strlen($this->coordinacion->fresh()->notifications->first()->data['mensaje']));

        // Quien ya no ve el caso no se entera.
        $this->coordinacion->dependencias()->updateExistingPivot($this->dependencia->id, ['rol' => RolDependencia::Lectura->value]);
        $this->comentar($this->creador, $caso, 'Ya quedó.');
        $this->assertCount(1, $this->coordinacion->fresh()->notifications);
        $this->assertCount(1, $admin->fresh()->notifications);
        $this->assertCount(2, $this->creador->fresh()->notifications);
    }

    public function test_doc23_ca07_borra_el_autor_o_coordinacion_y_todo_se_audita(): void
    {
        $caso = $this->caso();
        $otroEdicion = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->comentar($this->creador, $caso, 'Mío');
        $this->comentar($otroEdicion, $caso, 'Del otro');
        [$mio, $delOtro] = $caso->comentarios()->get()->all();

        // Edición no borra el de otro, y no ve el botón.
        $this->actingAs($this->creador)->delete(route('admin.turnos.derivaciones.comentarios.destroy', [$caso, $delOtro]))->assertForbidden();
        $this->actingAs($this->creador)->get(route('admin.turnos.derivaciones.show', $caso))
            ->assertSee(route('admin.turnos.derivaciones.comentarios.destroy', [$caso, $mio]))
            ->assertDontSee(route('admin.turnos.derivaciones.comentarios.destroy', [$caso, $delOtro]));

        // El autor borra el suyo; Coordinación, el de cualquiera.
        $this->actingAs($this->creador)->delete(route('admin.turnos.derivaciones.comentarios.destroy', [$caso, $mio]))->assertRedirect();
        $this->actingAs($this->coordinacion)->delete(route('admin.turnos.derivaciones.comentarios.destroy', [$caso, $delOtro]))->assertRedirect();
        $this->assertSame(0, ComentarioDerivacion::count());

        // Un comentario no se borra por la ruta de otro caso.
        $otroCaso = $this->caso();
        $this->comentar($this->creador, $otroCaso, 'Ajeno');
        $ajeno = $otroCaso->comentarios()->first();
        $this->actingAs($this->coordinacion)->delete(route('admin.turnos.derivaciones.comentarios.destroy', [$caso, $ajeno]))->assertNotFound();

        $this->assertSame(3, \App\Models\Auditoria::where('accion', 'caso.comentado')->count());
        $this->assertSame(2, \App\Models\Auditoria::where('accion', 'caso.comentario_borrado')->count());
        $this->assertDatabaseHas('auditorias', [
            'accion' => 'caso.comentario_borrado', 'user_id' => $this->coordinacion->id,
            'auditable_type' => $caso->getMorphClass(), 'auditable_id' => $caso->id,
        ]);
    }

    public function test_doc23_ca08_la_via_publica_no_muestra_los_comentarios(): void
    {
        $jose = Colaborador::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'documento' => '1017000111',
            'nombre' => 'José Amaya Pérez', 'componente_id' => $this->calle->id,
        ]);
        Marcacion::factory()->de($jose)->create(['marcada_at' => now()->subHours(3)]);
        $token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($token)->create(['tipo' => 'derivacion']);

        $caso = $this->caso();
        $this->comentar($this->coordinacion, $caso, 'Indicación interna secreta');
        $this->assertSame(1, $caso->comentarios()->count());

        auth()->logout();
        $this->postJson(route('derivacion.caso', ['token' => $token]), ['cedula' => '1017000111', 'numero' => '1'])
            ->assertOk()->assertJsonPath('codigo', 'DC-001')
            ->assertDontSee('Indicación interna secreta')->assertDontSee('comentario');
        $this->get(route('derivacion.enlace', ['token' => $token]))
            ->assertOk()->assertDontSee('Indicación interna secreta');
    }
}
