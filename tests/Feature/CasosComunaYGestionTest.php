<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** DOC-23 (CAS-01 a CAS-03): comuna, gestión realizada y paginación de los casos. */
class CasosComunaYGestionTest extends TestCase
{
    use RefreshDatabase;

    private const GESTION = 'Se habló con el ciudadano y se trasladó al hogar de paso.';

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private User $edicion;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 2']);
        $this->edicion = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $jose = Colaborador::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'documento' => '1017000111', 'nodo_id' => $this->nodo->id,
            'nombre' => 'José Amaya Pérez', 'componente_id' => $this->calle->id,
        ]);
        Marcacion::factory()->de($jose)->create(['marcada_at' => now()->subHours(2)]);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create(['tipo' => 'derivacion']);
    }

    /** Un caso DC abierto, creado a las 10:00. */
    private function caso(array $datos = []): Derivacion
    {
        return Derivacion::create($datos + [
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $this->calle->id,
            'numero' => (int) Derivacion::withoutGlobalScopes()->max('numero') + 1, 'creado_por' => $this->edicion->id,
            'remitente' => 'alcaldia', 'entidad' => 'Alcaldía', 'descripcion' => 'Habitante de calle requiere traslado.',
            'ubicacion' => 'Parque de Castilla', 'comuna' => 3, 'estado' => Derivacion::PENDIENTE,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
        ]);
    }

    private function crear(array $datos = [])
    {
        return $this->actingAs($this->edicion)->post(route('admin.turnos.derivaciones.store'), $datos + [
            'clave_formulario' => (string) Str::uuid(),
            'componente_id' => $this->calle->id,
            'descripcion' => 'Habitante de calle requiere traslado.',
            'remitente' => 'alcaldia',
            'ubicacion' => 'Cra. 65 #97-121 '.Str::random(4),
            'comuna' => 5,
            'soporte' => $this->archivoPdf('oficio.pdf'),
        ]);
    }

    private function atender(array $datos = [])
    {
        return $this->post(route('derivacion.atender', ['token' => $this->token]), $datos + [
            'cedula' => '1017000111',
            'numero' => '1',
            'nodo_id' => $this->nodo->id,
            'gestion_realizada' => self::GESTION,
            'cumplio_requerimiento' => '1',
            'evidencia1' => $this->archivoJpg('uno.jpg'),
            'evidencia2' => $this->archivoJpg('dos.jpg'),
            'evidencia3' => $this->archivoJpg('tres.jpg'),
        ]);
    }

    /** Las filas del Excel con este filtro, por código, y los índices de columna. */
    private function excel(array $filtro = []): array
    {
        $filas = $this->hojaXlsx($this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.exportar', $filtro))->assertOk(), 'Derivaciones');
        $col = array_flip($filas[0]);

        return [collect(array_slice($filas, 1))->keyBy(fn ($f) => $f[$col['CÓDIGO DEL CASO']]), $col];
    }

    public function test_doc23_ca01_la_lista_se_ve_de_a_25_50_o_100_y_los_indicadores_y_el_excel_cuentan_todo(): void
    {
        foreach (range(1, 60) as $i) {
            $this->caso();
        }

        $ver = fn (array $q) => $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.index', $q))->assertOk();

        $r = $ver([]);
        $this->assertSame(25, $r->viewData('derivaciones')->count());
        $this->assertSame(60, $r->viewData('derivaciones')->total());
        $this->assertSame(60, $r->viewData('traza')['pendientes'], 'Los indicadores cuentan todo el filtro, no la página.');

        $r = $ver(['por_pagina' => 50, 'estado' => 'pendiente']);
        $this->assertSame(50, $r->viewData('derivaciones')->count());
        // El selector se conserva al pasar de página y al volver a filtrar.
        $this->assertStringContainsString('por_pagina=50', $r->viewData('derivaciones')->nextPageUrl());
        $r->assertSee('name="por_pagina" value="50"', false);

        $this->assertSame(10, $ver(['por_pagina' => 50, 'page' => 2])->viewData('derivaciones')->count());
        $this->assertSame(60, $ver(['por_pagina' => 100])->viewData('derivaciones')->count());

        // Fuera de la lista blanca, lo de la plataforma.
        $this->assertSame(25, $ver(['por_pagina' => 1000])->viewData('derivaciones')->count());

        [$filas] = $this->excel(['por_pagina' => 25]);
        $this->assertCount(60, $filas);
    }

    public function test_doc23_ca02_crear_un_caso_exige_una_comuna_de_la_lista(): void
    {
        $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.create'))->assertOk()
            ->assertSee('Comuna *')->assertSee('3 · Manrique')->assertSee('90 · Santa Elena')->assertDontSee('Fuera de Medellín');

        $this->crear(['comuna' => ''])->assertSessionHasErrors(['comuna' => 'Elige la comuna o el corregimiento del caso.']);
        foreach (['17', '0', '49', 'abc', '3.5'] as $malo) {
            $this->crear(['comuna' => $malo])->assertSessionHasErrors('comuna');
        }
        $this->assertSame(0, Derivacion::count());

        $this->crear(['comuna' => '80'])->assertSessionHasNoErrors();
        $this->assertSame(80, Derivacion::sole()->comuna);
        $this->assertSame('80 · San Antonio de Prado', Derivacion::sole()->nombreComuna());
    }

    public function test_doc23_ca03_la_comuna_se_ve_en_la_ficha_la_lista_el_excel_y_al_atender(): void
    {
        $caso = $this->caso(['comuna' => 3]);

        $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.show', $caso))->assertOk()->assertSee('3 · Manrique');
        $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.index'))->assertOk()->assertSee('3 · Manrique');

        [$filas, $col] = $this->excel();
        $this->assertSame('3 · Manrique', $filas['DC-001'][$col['COMUNA']]);

        $this->postJson(route('derivacion.caso', ['token' => $this->token]), ['cedula' => '1017000111', 'numero' => '1'])
            ->assertOk()->assertJsonPath('comuna', '3 · Manrique');

        // Edición la corrige; un valor fuera de la lista no.
        $datos = ['descripcion' => 'x', 'remitente' => 'alcaldia', 'ubicacion' => 'Parque'];
        $this->actingAs($this->edicion)->put(route('admin.turnos.derivaciones.update', $caso), $datos + ['comuna' => 99])->assertSessionHasErrors('comuna');
        $this->actingAs($this->edicion)->put(route('admin.turnos.derivaciones.update', $caso), $datos + ['comuna' => 14])->assertSessionHasNoErrors();
        $this->assertSame(14, $caso->fresh()->comuna);
        $this->assertDatabaseHas('auditorias', ['accion' => 'derivacion.actualizada', 'auditable_id' => $caso->id]);

        $lectura = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $this->actingAs($lectura)->put(route('admin.turnos.derivaciones.update', $caso), $datos + ['comuna' => 1])->assertForbidden();
        $this->assertSame(14, $caso->fresh()->comuna);
    }

    public function test_doc23_ca04_el_filtro_por_comuna_limita_lista_indicadores_y_excel(): void
    {
        $this->caso(['comuna' => 3]);
        $this->caso(['comuna' => 3]);
        $this->caso(['comuna' => 14]);
        $this->caso(['comuna' => null]);

        $ver = fn (array $q) => $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.index', $q))->assertOk();

        $this->assertSame([1, 2], $ver(['comuna' => 3])->viewData('derivaciones')->pluck('numero')->sort()->values()->all());
        $this->assertSame(2, $ver(['comuna' => 3])->viewData('traza')['pendientes']);
        $this->assertSame([4], $ver(['comuna' => 'sin'])->viewData('derivaciones')->pluck('numero')->all());
        $this->assertSame(1, $ver(['comuna' => 'sin'])->viewData('traza')['pendientes']);
        $this->assertSame(4, $ver(['comuna' => 'cualquiera'])->viewData('derivaciones')->count(), 'Un valor raro no filtra.');
        $ver([])->assertSee('Sin comuna');

        $this->assertSame(['DC-001', 'DC-002'], $this->excel(['comuna' => 3])[0]->keys()->sort()->values()->all());
        $this->assertSame(['DC-004'], $this->excel(['comuna' => 'sin'])[0]->keys()->all());
    }

    public function test_doc23_ca09_atender_exige_la_gestion_realizada_y_se_ve_en_la_ficha_y_el_excel(): void
    {
        $caso = $this->caso();

        $this->get(route('derivacion.enlace', ['token' => $this->token]))->assertOk()
            ->assertSee('Atención o gestión realizada')->assertSee('Qué se hizo en el sitio: a quién se atendió, qué se entregó, a dónde se remitió…');

        $this->atender(['gestion_realizada' => ''])->assertSessionHasErrors(['gestion_realizada' => 'Escribe la atención o gestión realizada: qué se hizo en el sitio.']);
        $this->atender(['gestion_realizada' => 'Corto'])->assertSessionHasErrors(['gestion_realizada' => 'La atención o gestión realizada debe tener al menos 10 caracteres.']);
        $this->atender(['gestion_realizada' => str_repeat('a', 2001)])->assertSessionHasErrors(['gestion_realizada' => 'La atención o gestión realizada no puede pasar de 2000 caracteres.']);
        $this->assertTrue($caso->fresh()->estaPendiente());

        $this->atender()->assertSessionHasNoErrors();
        $caso->refresh();
        $this->assertSame([Derivacion::SOLUCIONADA, self::GESTION], [$caso->estado, $caso->gestion_realizada]);

        $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.show', $caso))->assertOk()
            ->assertSee('Atención o gestión realizada')->assertSee(self::GESTION);
        [$filas, $col] = $this->excel();
        $this->assertSame(self::GESTION, $filas['DC-001'][$col['GESTIÓN REALIZADA']]);
        $this->assertSame(['COMUNA', 'GESTIÓN REALIZADA'], array_slice(array_keys($col), -2), 'Al final, sin mover las demás.');

        // Edición la corrige, sin dejarla vacía.
        $datos = ['descripcion' => 'x', 'remitente' => 'alcaldia', 'ubicacion' => 'Parque', 'comuna' => 3];
        $this->actingAs($this->edicion)->put(route('admin.turnos.derivaciones.update', $caso), $datos + ['gestion_realizada' => ''])->assertSessionHasErrors('gestion_realizada');
        $this->actingAs($this->edicion)->put(route('admin.turnos.derivaciones.update', $caso), $datos + ['gestion_realizada' => 'Se remitió a la Secretaría de Salud.'])->assertSessionHasNoErrors();
        $this->assertSame('Se remitió a la Secretaría de Salud.', $caso->fresh()->gestion_realizada);

        // El cierre a mano del superadministrador también la pide.
        $otro = $this->caso();
        $superadmin = User::factory()->superadmin()->create();
        $cierre = ['colaborador_id' => Colaborador::first()->id, 'nodo_id' => $this->nodo->id, 'atendida_at' => now()->format('Y-m-d').'T10:40',
            'cumplio_requerimiento' => '1', 'motivo' => 'Sin señal'];
        $comoSuper = fn () => $this->actingAs($superadmin)->withSession(['dependencia_id' => $this->dependencia->id]);
        $comoSuper()->get(route('admin.turnos.derivaciones.show', $otro))->assertSee('Atención o gestión realizada *');
        $comoSuper()->patch(route('admin.turnos.derivaciones.contingencia', $otro), $cierre)->assertSessionHasErrors('gestion_realizada');
        $comoSuper()->patch(route('admin.turnos.derivaciones.contingencia', $otro), $cierre + ['gestion_realizada' => self::GESTION])->assertSessionHasNoErrors();
        $this->assertSame(self::GESTION, $otro->fresh()->gestion_realizada);
    }

    public function test_doc23_ca10_los_anteriores_sin_comuna_ni_gestion_siguen_con_raya(): void
    {
        $anterior = $this->caso(['comuna' => null, 'estado' => Derivacion::SOLUCIONADA, 'nombre' => 'José Amaya Pérez', 'cedula' => '1017000111',
            'nodo_id' => $this->nodo->id, 'atendida_at' => now(), 'hora_respuesta' => '10:30', 'tiempo_respuesta_min' => 30, 'cumplio_requerimiento' => true]);

        $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.index'))->assertOk()->assertSee('DC-001')->assertSee('—');
        $this->actingAs($this->edicion)->get(route('admin.turnos.derivaciones.show', $anterior))->assertOk()->assertSee('—');

        [$filas, $col] = $this->excel();
        $this->assertSame(['—', '—'], [$filas['DC-001'][$col['COMUNA']], $filas['DC-001'][$col['GESTIÓN REALIZADA']]]);

        // Se corrige sin exigirle comuna ni gestión, y se le pueden poner.
        $datos = ['descripcion' => 'x', 'remitente' => 'alcaldia', 'ubicacion' => 'Parque'];
        $this->actingAs($this->edicion)->put(route('admin.turnos.derivaciones.update', $anterior), $datos)->assertSessionHasNoErrors();
        $this->actingAs($this->edicion)->put(route('admin.turnos.derivaciones.update', $anterior), $datos + ['comuna' => 7])->assertSessionHasNoErrors();
        $this->assertSame('7 · Robledo', $anterior->fresh()->nombreComuna());
    }
}
