<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Enums\TipoNovedad;
use App\Livewire\Turnos\PuntosCriticos;
use App\Livewire\Turnos\TiposTurno;
use App\Models\Ausencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\NovedadTurno;
use App\Models\PuntoCritico;
use App\Models\Scopes\ComponenteScope;
use App\Models\Supervision;
use App\Models\TipoTurno;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** DOC-25 (ACC-03): cada usuario ve y trabaja solo los datos de su componente. */
class AislamientoPorComponenteTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    /** @var array<string, array<string, mixed>> lo sembrado en cada componente, por slug */
    private array $datos = [];

    private int $numero = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $this->dependencia = Dependencia::factory()->create();
        // Los dos con todos los módulos: aquí se prueba el dato, no el módulo (eso es ACC-04).
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle Uno', 'slug' => 'calle']);
        $this->basica = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica Dos', 'slug' => 'basica']);

        $this->datos['calle'] = $this->sembrar($this->calle, 'Calle');
        $this->datos['basica'] = $this->sembrar($this->basica, 'Basica');
    }

    /** Un registro de cada modelo en el componente, con su nombre como marca. */
    private function sembrar(Componente $c, string $marca): array
    {
        $nodo = Nodo::factory()->create(['componente_id' => $c->id, 'nombre' => "Nodo {$marca}"]);
        $persona = Colaborador::factory()->en($c, $nodo)->autoregistrado()->create(['nombre' => "Persona {$marca}", 'documento' => (string) (7000 + $c->id)]);
        $marcacion = Marcacion::factory()->de($persona)->create(['marcada_at' => now()->subHour()]);
        $tipo = TipoTurno::create(['componente_id' => $c->id, 'codigo' => 'T'.$c->id, 'nombre' => "Turno {$marca}", 'horas_nomina' => 8]);
        $punto = PuntoCritico::create(['componente_id' => $c->id, 'nodo_id' => $nodo->id, 'nombre' => "Punto {$marca}"]);

        return [
            'nodo' => $nodo,
            'persona' => $persona,
            'marcacion' => $marcacion,
            'tipo' => $tipo,
            'punto' => $punto,
            'novedad' => NovedadTurno::create([
                'dependencia_id' => $c->dependencia_id, 'colaborador_id' => $persona->id, 'componente_id' => $c->id,
                'marcacion_id' => $marcacion->id, 'tipo' => TipoNovedad::SinUbicacion, 'detalle' => "Novedad {$marca}",
            ]),
            'enlace' => EnlaceTurno::factory()->create(['componente_id' => $c->id, 'nombre' => "Enlace {$marca}"]),
            'ausencia' => Ausencia::create([
                'dependencia_id' => $c->dependencia_id, 'colaborador_id' => $persona->id, 'tipo_turno_id' => $tipo->id,
                'desde' => today(), 'hasta' => today(), 'motivo' => "Ausencia {$marca}",
            ]),
            'derivacion' => Derivacion::create([
                'dependencia_id' => $c->dependencia_id, 'componente_id' => $c->id, 'numero' => ++$this->numero,
                'creado_por' => User::factory()->create()->id, 'remitente' => 'alcaldia', 'entidad' => 'Alcaldía',
                'descripcion' => 'Requiere traslado.', 'ubicacion' => "Ubicacion {$marca}", 'comuna' => 5,
                'estado' => Derivacion::PENDIENTE, 'fecha_requerimiento' => today()->toDateString(), 'hora_requerimiento' => '10:00',
            ]),
            'supervision' => Supervision::create([
                'dependencia_id' => $c->dependencia_id, 'componente_id' => $c->id,
                'supervisor_id' => $persona->id, 'supervisor_nombre' => "Persona {$marca}", 'supervisor_cedula' => $persona->documento,
                'dinamizador1_id' => $persona->id, 'nodo_id' => $nodo->id, 'visita_at' => now()->subHour(),
                'descripcion_punto' => "Visita {$marca}", ...array_fill_keys(array_keys(Supervision::CRITERIOS), 4),
                'calificacion_global' => 4, 'incidencia' => 'leve', 'casos_escalados' => false, 'recomendaciones' => "Recomendacion {$marca}",
            ]),
        ];
    }

    private function contexto(): ContextoDependencia
    {
        return app(ContextoDependencia::class);
    }

    /** Todo el texto de un .xlsx o un .zip descargado, con los .xlsx de adentro abiertos. */
    private function textoDe(string $ruta): string
    {
        $zip = new \ZipArchive;
        $zip->open($ruta);
        $texto = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            $contenido = $zip->getFromIndex($i);
            if (str_ends_with($nombre, '.xlsx')) {
                $temporal = tempnam(sys_get_temp_dir(), 'xlsx');
                file_put_contents($temporal, $contenido);
                $contenido = $this->textoDe($temporal);
                @unlink($temporal);
            }
            $texto .= $nombre."\n".$contenido."\n";
        }
        $zip->close();

        return $texto;
    }

    private function descarga(TestResponse $respuesta): string
    {
        return $this->textoDe($respuesta->assertOk()->baseResponse->getFile()->getPathname());
    }

    /** Lo que ve $yo: lo suyo ($mio) sí, lo del otro componente ($ajeno) nunca. */
    private function verificarAislamiento(User $yo, array $mio, array $ajeno): void
    {
        $this->actingAs($yo);

        // Listados (con lo propio como control: la pantalla sí muestra ese dato).
        $listados = [
            [route('admin.turnos.colaboradores.index'), 'persona', fn ($r) => $r->nombre],
            [route('admin.turnos.enlaces.index'), 'enlace', fn ($r) => $r->nombre],
            [route('admin.turnos.novedades.index'), 'novedad', fn ($r) => $r->detalle],
            [route('admin.turnos.derivaciones.index'), 'derivacion', fn ($r) => $r->codigo()],
            [route('admin.turnos.supervisiones.index'), 'supervision', fn ($r) => $r->supervisor_nombre],
            [route('admin.turnos.tipos-turno'), 'tipo', fn ($r) => $r->nombre],
            [route('admin.turnos.puntos-criticos'), 'punto', fn ($r) => $r->nombre],
            [route('admin.turnos.colaboradores.create'), 'nodo', fn ($r) => $r->nombre],
            [route('admin.turnos.componentes.index'), 'nodo', fn ($r) => $r->componente_id === $this->calle->id ? 'Calle Uno' : 'Básica Dos'],
        ];
        foreach ($listados as [$url, $clave, $texto]) {
            $propio = $texto($mio[$clave]);
            $otro = $texto($ajeno[$clave]);
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString(e($propio), $html, "{$url} debía mostrar lo propio");
            $this->assertStringNotContainsString(e($otro), $html, "{$url} mostró lo de otro componente");
        }

        // Contadores del menú: una persona sin verificar y una novedad pendiente, las suyas.
        $this->get(route('perfil.edit'))->assertOk()
            ->assertSee('aria-label="1 sin revisar"', false)
            ->assertDontSee('aria-label="2 sin revisar"', false)
            ->assertSee('aria-label="2 pendientes"', false)
            ->assertDontSee('aria-label="4 pendientes"', false);

        // Abrir, corregir o descargar un dato del otro componente: rechazo, sin el dato.
        $persona = $ajeno['persona'];
        foreach ([
            route('admin.turnos.colaboradores.show', $persona),
            route('admin.turnos.colaboradores.edit', $persona),
            route('admin.turnos.enlaces.show', $ajeno['enlace']),
            route('admin.turnos.enlaces.imprimir', $ajeno['enlace']),
            route('admin.turnos.derivaciones.show', $ajeno['derivacion']),
            route('admin.turnos.derivaciones.edit', $ajeno['derivacion']),
            route('admin.turnos.supervisiones.show', $ajeno['supervision']),
        ] as $url) {
            $this->assertNoDejaVer($this->get($url), $persona->nombre === 'Persona Calle' ? 'Calle' : 'Basica', $url);
        }

        foreach ([
            ['put', route('admin.turnos.colaboradores.update', $persona)],
            ['patch', route('admin.turnos.colaboradores.estado', $persona)],
            ['patch', route('admin.turnos.colaboradores.verificar', $persona)],
            ['post', route('admin.turnos.ausencias.store', $persona)],
            ['post', route('admin.turnos.marcaciones.store', $persona)],
            ['delete', route('admin.turnos.ausencias.destroy', $ajeno['ausencia'])],
            ['patch', route('admin.turnos.marcaciones.corregir', $ajeno['marcacion'])],
            ['patch', route('admin.turnos.marcaciones.anular', $ajeno['marcacion'])],
            ['patch', route('admin.turnos.marcaciones.tipo', $ajeno['marcacion'])],
            ['patch', route('admin.turnos.novedades.revisar', $ajeno['novedad'])],
            ['post', route('admin.turnos.novedades.justificar', $ajeno['novedad'])],
            ['patch', route('admin.turnos.enlaces.revocar', $ajeno['enlace'])],
            ['put', route('admin.turnos.derivaciones.update', $ajeno['derivacion'])],
            ['patch', route('admin.turnos.derivaciones.contingencia', $ajeno['derivacion'])],
            ['post', route('admin.turnos.derivaciones.comentarios.store', $ajeno['derivacion'])],
            ['delete', route('admin.turnos.derivaciones.destroy', $ajeno['derivacion'])],
        ] as [$metodo, $url]) {
            $this->{$metodo}($url, ['estado' => 'aprobada', 'motivo' => 'x', 'texto' => 'x'])->assertNotFound();
        }

        // Nada cambió en lo ajeno.
        $this->assertTrue(Marcacion::withoutGlobalScopes()->find($ajeno['marcacion']->id)->anulada_at === null);
        $this->assertTrue(NovedadTurno::withoutGlobalScopes()->find($ajeno['novedad']->id)->estaPendiente());
        $this->assertTrue(EnlaceTurno::withoutGlobalScopes()->find($ajeno['enlace']->id)->activo);
        $this->assertNotNull(Derivacion::withoutGlobalScopes()->find($ajeno['derivacion']->id)?->getKey());
        $this->assertNull(Colaborador::withoutGlobalScopes()->find($persona->id)->verificado_at);

        // Excel y ZIP: lo suyo sí, lo ajeno no.
        $marcaPropia = $mio['persona']->nombre;
        $marcaAjena = $persona->nombre;
        foreach ([
            route('admin.turnos.marcaciones.exportar'),
            route('admin.turnos.novedades.exportar', ['estado' => 'todas']),
            route('admin.turnos.supervisiones.exportar'),
        ] as $url) {
            $texto = $this->descarga($this->get($url));
            $this->assertStringContainsString($marcaPropia, $texto, $url);
            $this->assertStringNotContainsString($marcaAjena, $texto, $url);
        }
        foreach ([route('admin.turnos.derivaciones.exportar'), route('admin.turnos.derivaciones.fotos')] as $url) {
            $texto = $this->descarga($this->get($url));
            $this->assertStringContainsString($mio['derivacion']->ubicacion, $texto, $url);
            $this->assertStringNotContainsString($ajeno['derivacion']->ubicacion, $texto, $url);
        }
    }

    public function test_doc25_ca06_calle_y_basica_no_ven_ni_tocan_los_datos_del_otro(): void
    {
        $deCalle = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $deBasica = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica);

        $this->verificarAislamiento($deCalle, $this->datos['calle'], $this->datos['basica']);
        $this->verificarAislamiento($deBasica, $this->datos['basica'], $this->datos['calle']);
    }

    public function test_doc25_ca06_administracion_ve_el_componente_donde_esta_parado(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        // Arranca en el primero con módulos (Calle); en Básica, ve Básica.
        $this->verificarAislamiento($admin, $this->datos['calle'], $this->datos['basica']);
        $this->withSession(['componente_id' => $this->basica->id]);
        $this->verificarAislamiento($admin, $this->datos['basica'], $this->datos['calle']);
    }

    public function test_doc25_ca06_las_consultas_del_modelo_solo_traen_lo_del_componente(): void
    {
        $this->actingAs($this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle))
            ->get(route('perfil.edit'))->assertOk();

        $calle = $this->datos['calle'];
        foreach ([
            Colaborador::class => 'persona', Marcacion::class => 'marcacion', NovedadTurno::class => 'novedad',
            EnlaceTurno::class => 'enlace', Derivacion::class => 'derivacion', Supervision::class => 'supervision',
            TipoTurno::class => 'tipo', PuntoCritico::class => 'punto', Nodo::class => 'nodo', Ausencia::class => 'ausencia',
        ] as $modelo => $clave) {
            $this->assertSame([$calle[$clave]->id], $modelo::pluck((new $modelo)->getTable().'.id')->all(), $modelo);
            $this->assertSame(2, $modelo::withoutGlobalScope(ComponenteScope::class)->count(), "{$modelo} sin el scope de componente");
        }
        $this->assertSame([$this->calle->id], Componente::pluck('id')->all());

        // Con juntas, la columna va calificada (MySQL rechaza la ambigua).
        $this->assertSame(1, Marcacion::join('colaboradores', 'colaboradores.id', '=', 'marcaciones.colaborador_id')->count());
    }

    public function test_doc25_ca06_guardar_un_registro_en_otro_componente_se_rechaza(): void
    {
        $yo = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $nodoBasica = $this->datos['basica']['nodo'];

        // Los formularios validan el componente contra toda la dependencia; el modelo lo rechaza.
        $this->actingAs($yo)->post(route('admin.turnos.colaboradores.store'), [
            'documento' => '99887766', 'nombre' => 'Intrusa', 'cargo' => 'Orientador',
            'componente_id' => $this->basica->id, 'nodo_id' => $nodoBasica->id,
        ])->assertForbidden();
        $this->actingAs($yo)->post(route('admin.turnos.enlaces.store'), [
            'tipo' => 'turno', 'componente_id' => $this->basica->id, 'nombre' => 'Intruso',
        ])->assertForbidden();
        $this->actingAs($yo)->post(route('admin.turnos.derivaciones.store'), [
            'clave_formulario' => (string) Str::uuid(), 'componente_id' => $this->basica->id,
            'remitente' => 'alcaldia', 'descripcion' => 'Intruso', 'ubicacion' => 'Ubicacion Basica', 'comuna' => 5,
            'confirmar_duplicado' => 1,
        ])->assertForbidden();

        $this->assertSame(0, Colaborador::withoutGlobalScopes()->where('nombre', 'Intrusa')->count());
        $this->assertSame(0, EnlaceTurno::withoutGlobalScopes()->where('nombre', 'Intruso')->count());
        $this->assertSame(0, Derivacion::withoutGlobalScopes()->where('descripcion', 'Intruso')->count());

        // Directo en el modelo, con el contexto de Calle: tampoco.
        $this->expectException(HttpException::class);
        Nodo::create(['componente_id' => $this->basica->id, 'nombre' => 'Nodo intruso', 'orden' => 1]);
    }

    public function test_doc25_ca06_el_aviso_de_caso_duplicado_no_muestra_un_codigo_de_otro_componente(): void
    {
        $yo = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->calle);
        $basica = $this->datos['basica']['derivacion'];
        $datos = [
            'componente_id' => $this->calle->id, 'remitente' => 'alcaldia', 'descripcion' => 'Otro caso',
            'ubicacion' => $basica->ubicacion, 'comuna' => 5,
        ];

        // El mismo remitente y la misma ubicación que un caso de Básica: en Calle no es duplicado.
        $this->actingAs($yo)->post(route('admin.turnos.derivaciones.store'), $datos + ['clave_formulario' => (string) Str::uuid()])
            ->assertSessionDoesntHaveErrors('duplicado');

        // Y con uno de Calle igual, sí avisa, con el código de Calle.
        $this->actingAs($yo)->post(route('admin.turnos.derivaciones.store'), $datos + ['clave_formulario' => (string) Str::uuid()])
            ->assertSessionHasErrors('duplicado');
        $this->assertStringNotContainsString($basica->codigo(), session('errors')->first('duplicado'));
    }

    public function test_doc25_ca06_los_catalogos_de_filament_no_dejan_tocar_los_de_otro_componente(): void
    {
        // Livewire::test no pasa por los middlewares: dependencia y componente a mano.
        $this->actingAs($this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle));
        $this->contexto()->establecer($this->dependencia);
        $this->contexto()->establecerComponente($this->calle);

        Livewire::test(TiposTurno::class)
            ->assertCanSeeTableRecords([$this->datos['calle']['tipo']])
            ->assertCanNotSeeTableRecords([$this->datos['basica']['tipo']]);
        Livewire::test(PuntosCriticos::class)
            ->assertCanSeeTableRecords([$this->datos['calle']['punto']])
            ->assertCanNotSeeTableRecords([$this->datos['basica']['punto']]);

        // Editar uno ajeno (si alguien manda su id): el modelo no lo guarda.
        $ajeno = TipoTurno::withoutGlobalScopes()->find($this->datos['basica']['tipo']->id);
        $this->expectException(HttpException::class);
        $ajeno->update(['nombre' => 'Cambiado']);
    }

    public function test_doc25_ca15_sin_componente_no_ve_ningun_dato(): void
    {
        $yo = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $yo->dependencias()->updateExistingPivot($this->dependencia->id, ['componente_id' => null]);
        $yo->unsetRelation('dependencias');

        $this->actingAs($yo)->get(route('perfil.edit'))->assertOk()->assertDontSee('sin revisar');
        $this->assertTrue($this->contexto()->filtraComponente());

        foreach ([Colaborador::class, Marcacion::class, NovedadTurno::class, EnlaceTurno::class, Derivacion::class,
            Supervision::class, TipoTurno::class, PuntoCritico::class, Nodo::class, Ausencia::class, Componente::class] as $modelo) {
            $this->assertSame(0, $modelo::count(), $modelo);
        }

        foreach (['calle', 'basica'] as $c) {
            $this->assertNoDejaVer($this->get(route('admin.turnos.colaboradores.show', $this->datos[$c]['persona'])), 'Persona', $c);
        }

        // Ni guarda en ninguno.
        $this->expectException(HttpException::class);
        Nodo::create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo suelto', 'orden' => 1]);
    }

    public function test_doc25_ca06_consola_y_via_publica_ven_todos_los_componentes(): void
    {
        // Sin contexto (cron, colas, seeders): el scope no actúa.
        $this->assertFalse($this->contexto()->filtraComponente());
        $this->assertSame(2, Colaborador::count());
        $this->assertSame(2, Componente::count());
        $this->artisan('turnos:revisar-entradas')->assertSuccessful();
        $this->artisan('derivaciones:recordar')->assertSuccessful();

        // La vía pública de Básica abre aunque haya una sesión de Calle en el navegador.
        $this->actingAs($this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle))
            ->get(route('perfil.edit'))->assertOk();
        $this->contexto()->establecer(null);
        $this->get('/t/'.$this->datos['basica']['enlace']->token_cifrado)->assertOk();
        $this->assertFalse($this->contexto()->filtraComponente());
    }
}
