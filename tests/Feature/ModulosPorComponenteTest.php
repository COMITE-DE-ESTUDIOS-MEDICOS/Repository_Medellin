<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\EnlaceCarga;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\PuntoCritico;
use App\Models\Supervision;
use App\Models\TipoTurno;
use App\Models\User;
use App\Models\UsuarioHc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DOC-25 (ACC-04): primero el módulo del componente, después el rol. */
class ModulosPorComponenteTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $this->basica = Componente::factory()->sinModulos()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
    }

    /**
     * Las rutas GET de cada módulo, sacadas del enrutador por su middleware:
     * una ruta nueva dentro de un grupo queda cubierta sin tocar esta prueba.
     *
     * @return array<string, list<Ruta>>
     */
    private function rutasPorModulo(): array
    {
        $porModulo = [];
        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            foreach ($ruta->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'can:modulo,') && in_array('GET', $ruta->methods(), true)) {
                    $porModulo[trim(substr($m, strlen('can:modulo,')), "'")][] = $ruta;
                }
            }
        }

        return $porModulo;
    }

    /** Un registro real de cada cosa con dirección propia, en el componente dado. */
    private function parametrosEn(Componente $c): array
    {
        $nodo = Nodo::factory()->create(['componente_id' => $c->id]);
        $persona = Colaborador::factory()->en($c, $nodo)->autoregistrado()->create(['documento' => (string) (8000 + $c->id)]);
        $documento = Documento::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $version = DocumentoVersion::factory()->conArchivoEnDisco('secreto de calle')->create(['documento_id' => $documento->id]);

        return [
            'documento' => $documento->getRouteKey(),
            'version' => $version->getRouteKey(),
            'carpeta' => Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id])->getRouteKey(),
            'colaborador' => $persona->id,
            'enlace' => EnlaceTurno::factory()->create(['componente_id' => $c->id])->id,
            'derivacion' => Derivacion::create([
                'dependencia_id' => $this->dependencia->id, 'componente_id' => $c->id, 'numero' => $c->id,
                'creado_por' => User::factory()->create()->id, 'remitente' => 'alcaldia', 'entidad' => 'Alcaldía',
                'descripcion' => 'Requiere traslado.', 'ubicacion' => 'Parque', 'comuna' => 5,
                'estado' => Derivacion::PENDIENTE, 'fecha_requerimiento' => today()->toDateString(), 'hora_requerimiento' => '10:00',
            ])->id,
            'supervision' => Supervision::create([
                'dependencia_id' => $this->dependencia->id, 'componente_id' => $c->id,
                'supervisor_id' => $persona->id, 'supervisor_nombre' => 'Persona', 'supervisor_cedula' => $persona->documento,
                'dinamizador1_id' => $persona->id, 'nodo_id' => $nodo->id, 'visita_at' => now()->subHour(),
                'descripcion_punto' => 'Visita', ...array_fill_keys(array_keys(Supervision::CRITERIOS), 4),
                'calificacion_global' => 4, 'incidencia' => 'leve', 'casos_escalados' => false, 'recomendaciones' => 'Ninguna',
            ])->id,
            'usuarioHc' => UsuarioHc::factory()->en($c)->create()->id,
        ];
    }

    private function url(Ruta $ruta, array $parametros): string
    {
        return url(preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($ruta, $parametros) {
            $this->assertArrayHasKey($m[1], $parametros, "La ruta {$ruta->uri()} pide {{$m[1]}}: agrégalo a parametrosEn().");

            return $parametros[$m[1]];
        }, $ruta->uri()));
    }

    public function test_doc25_ca05_basica_recibe_403_en_cada_ruta_de_cada_modulo_y_calle_sigue_entrando(): void
    {
        $rutas = $this->rutasPorModulo();
        $this->assertEqualsCanonicalizing(array_keys(Componente::MODULOS), array_keys($rutas));
        // Las pantallas, reportes y descargas que hoy trae cada módulo (piso, no techo).
        $this->assertGreaterThanOrEqual(10, count($rutas['documentos']));
        $this->assertGreaterThanOrEqual(13, count($rutas['turnos']));
        $this->assertGreaterThanOrEqual(7, count($rutas['derivaciones']));
        $this->assertGreaterThanOrEqual(3, count($rutas['supervisiones']));

        // Con datos de Básica: la dirección existe y la vincula; lo que niega es el módulo.
        $parametros = $this->parametrosEn($this->basica);

        $coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->basica);
        $administracion = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $super = User::factory()->superadmin()->create();

        foreach ([[$coordinacion, []], [$administracion, ['componente_id' => $this->basica->id]], [$super, ['componente_id' => $this->basica->id]]] as [$usuario, $sesion]) {
            foreach ($rutas as $modulo => $lista) {
                foreach ($lista as $ruta) {
                    $respuesta = $this->actingAs($usuario)->withSession($sesion)->get($this->url($ruta, $parametros));
                    $this->assertSame(403, $respuesta->status(), "{$modulo}: {$ruta->uri()} debía negarse.");
                    $this->assertStringNotContainsString('secreto de calle', $respuesta->getContent());
                }
                $this->assertFalse(Gate::forUser($usuario)->allows('modulo', $modulo));
            }
        }

        // Las Policies también (acciones de Filament y el menú), aunque el rol alcance.
        foreach ([Documento::class, Carpeta::class, EnlaceCarga::class, Colaborador::class, EnlaceTurno::class,
            Marcacion::class, TipoTurno::class, PuntoCritico::class, Derivacion::class, Supervision::class, UsuarioHc::class] as $modelo) {
            $this->assertFalse(Gate::forUser($super)->allows('viewAny', $modelo), $modelo);
        }

        // Lo que no es de ningún módulo sigue abierto.
        foreach (['perfil.edit', 'manual', 'notificaciones.index'] as $nombre) {
            $this->actingAs($coordinacion)->get(route($nombre))->assertOk();
        }

        // Calle (y Administración o superadministrador parados en Calle) entran como antes.
        $deCalle = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        foreach ([[$deCalle, []], [$administracion, ['componente_id' => $this->calle->id]], [$super, ['componente_id' => $this->calle->id]]] as [$usuario, $sesion]) {
            foreach (Componente::MODULOS as [, $llegada]) {
                $this->actingAs($usuario)->withSession($sesion)->get(route($llegada))->assertOk();
            }
            $this->assertTrue(Gate::forUser($usuario)->allows('viewAny', TipoTurno::class));
        }
    }

    public function test_doc25_ca05_las_rutas_publicas_y_las_de_administracion_no_piden_modulo(): void
    {
        $fuera = ['turno.enlace', 'derivacion.enlace', 'supervision.enlace', 'envio.formulario', 'derivaciones.archivo',
            'perfil.edit', 'manual', 'notificaciones.index', 'dependencia.cambiar', 'logout',
            'admin.usuarios.index', 'admin.avisos.index', 'auditoria.index', 'admin.turnos.componentes.index'];

        foreach ($fuera as $nombre) {
            $middleware = Route::getRoutes()->getByName($nombre)->gatherMiddleware();
            $this->assertEmpty(preg_grep('/^can:modulo,/', array_filter($middleware, 'is_string')), $nombre);
        }
    }

    public function test_doc25_ca07_sin_documentos_no_se_abre_ni_descarga_ni_se_cambia_nada_del_repositorio(): void
    {
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $documento = Documento::factory()->en($carpeta)->create();
        $version = DocumentoVersion::factory()->conArchivoEnDisco('acta reservada')->create(['documento_id' => $documento->id]);
        $enlace = EnlaceCarga::factory()->hacia($carpeta)->create();
        // Administración: su rol lo permite todo; el componente donde está parado, no.
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $enBasica = ['componente_id' => $this->basica->id];

        foreach ([
            route('documentos.index'),
            route('documentos.show', $documento),
            route('documentos.descargar', $documento),
            route('documentos.versiones.descargar', [$documento, $version]),
            route('documentos.previsualizar', $documento),
            route('carpetas.descargar', $carpeta),
            route('admin.enlaces.index'),
            route('admin.tipos.index'),
            route('admin.custodia.index'),
        ] as $url) {
            $respuesta = $this->actingAs($admin)->withSession($enBasica)->get($url);
            $this->assertSame(403, $respuesta->status(), $url);
            $this->assertStringNotContainsString('acta reservada', $respuesta->getContent());
        }

        $this->actingAs($admin)->withSession($enBasica)
            ->post(route('documentos.store'), ['nombre' => 'Nuevo', 'archivo' => $this->archivoPdf()])->assertForbidden();
        $this->actingAs($admin)->withSession($enBasica)
            ->patch(route('documentos.inactivar', $documento), ['motivo' => 'Ya no sirve para nada'])->assertForbidden();
        $this->actingAs($admin)->withSession($enBasica)->patch(route('admin.enlaces.revocar', $enlace))->assertForbidden();
        $this->actingAs($admin)->withSession($enBasica)->patch(route('carpetas.inactivar', $carpeta), ['motivo' => 'Ya no sirve para nada'])->assertForbidden();

        $this->assertSame(1, Documento::count());
        $this->assertTrue($documento->fresh()->activo);
        $this->assertTrue($carpeta->fresh()->activa);
        $this->assertTrue($enlace->fresh()->activo);

        // Las Policies niegan lo mismo (botones y acciones dentro de las pantallas).
        foreach ([['view', $documento], ['download', $documento], ['view', $carpeta], ['view', $enlace], ['revocar', $enlace]] as [$accion, $registro]) {
            $this->assertFalse(Gate::forUser($admin)->allows($accion, $registro), $accion.' '.$registro::class);
        }

        // Parado en Calle, lo mismo se abre.
        $this->actingAs($admin)->withSession(['componente_id' => $this->calle->id])
            ->get(route('documentos.descargar', $documento))->assertOk();
        $this->assertTrue(Gate::forUser($admin)->allows('download', $documento));
    }

    public function test_doc25_ca11_encender_un_modulo_lo_abre_y_apagarlo_lo_cierra_sin_tocar_el_programa(): void
    {
        $edicion = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);

        $this->actingAs($edicion)->get(route('admin.turnos.enlaces.index'))->assertForbidden();

        $this->basica->forceFill(['modulos' => ['turnos']])->save();

        $this->actingAs($edicion)->get(route('admin.turnos.enlaces.index'))->assertOk();
        $this->assertTrue(Gate::forUser($edicion)->allows('viewAny', EnlaceTurno::class));
        // Solo el que se encendió.
        $this->actingAs($edicion)->get(route('admin.turnos.derivaciones.index'))->assertForbidden();
        $this->actingAs($edicion)->get(route('documentos.index'))->assertForbidden();

        $this->basica->forceFill(['modulos' => []])->save();

        $this->actingAs($edicion)->get(route('admin.turnos.enlaces.index'))->assertForbidden();
        $this->assertFalse(Gate::forUser($edicion)->allows('viewAny', EnlaceTurno::class));
    }
}
