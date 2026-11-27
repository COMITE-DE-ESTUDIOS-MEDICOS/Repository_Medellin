<?php

namespace Tests\Feature\EvidenciasDesdeLaFicha;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Http\Controllers\Admin\EvidenciaFichaController;
use App\Models\Auditoria;
use App\Models\CasoCctv;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** DOC-34 (EVF-01): cargar una evidencia vacía desde la ficha, en Derivaciones y en Seguridad CCTV. */
class CargarTest extends TestCase
{
    use RefreshDatabase;

    private const YA_TIENE = 'Esta evidencia ya tiene un archivo; no se reemplaza';

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

    private function derivacion(array $datos = [], ?Componente $componente = null): Derivacion
    {
        $componente ??= $this->calle;

        return Derivacion::create($datos + [
            'dependencia_id' => $componente->dependencia_id, 'componente_id' => $componente->id,
            'numero' => (int) Derivacion::withoutGlobalScopes()->max('numero') + 1,
            'remitente' => 'alcaldia', 'entidad' => 'Alcaldía', 'descripcion' => 'Requiere traslado.',
            'ubicacion' => 'Parque de Castilla', 'comuna' => 3, 'estado' => Derivacion::SOLUCIONADA,
            'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
            'atendida_at' => now(), 'tiempo_respuesta_min' => 85, 'cumplio_requerimiento' => true,
            'nombre' => 'Ana Campo', 'cedula' => '123',
        ]);
    }

    private function cctv(array $estado = [], ?Componente $componente = null): CasoCctv
    {
        return CasoCctv::factory()->de($componente ?? $this->calle)->atendido()->create($estado + ['nombre' => 'Ana Campo', 'cedula' => '123']);
    }

    private function cargarDerivacion(User $usuario, Derivacion $caso, int $n = 2, array $datos = [])
    {
        return $this->actingAs($usuario)->post(
            route('admin.turnos.derivaciones.evidencia', [$caso, $n]),
            $datos + ["evidencia{$n}" => $this->archivoJpg()],
        );
    }

    private function cargarCctv(User $usuario, CasoCctv $caso, int $n = 3, array $datos = [])
    {
        return $this->actingAs($usuario)->post(
            route('admin.turnos.cctv.evidencia', [$caso, $n]),
            $datos + ["evidencia{$n}" => $this->archivoJpg()],
        );
    }

    private function archivosEnDisco(): int
    {
        return count(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    private function conEvidencia(Derivacion|CasoCctv $caso, int $n): Derivacion|CasoCctv
    {
        $documento = Documento::factory()->create(['dependencia_id' => $caso->dependencia_id]);
        $caso->forceFill(["evidencia{$n}_documento_id" => $documento->id])->save();

        return $caso;
    }

    public function test_doc34_ca01_edicion_coordinacion_y_administracion_cargan_en_una_derivacion_solucionada(): void
    {
        foreach ([RolDependencia::Edicion, RolDependencia::Coordinacion, RolDependencia::Administracion] as $rol) {
            $caso = $this->derivacion();
            $campos = ['estado', 'hora_respuesta', 'tiempo_respuesta_min', 'cumplio_requerimiento', 'nombre', 'atendida_at', 'respondida_por'];
            $antes = $caso->fresh()->only($campos);
            $usuario = $this->usuario($rol);

            $this->cargarDerivacion($usuario, $caso)
                ->assertRedirect(route('admin.turnos.derivaciones.show', $caso))
                ->assertSessionHas('exito');

            $caso->refresh();
            $documento = Documento::find($caso->evidencia2_documento_id);
            $this->assertNotNull($documento);
            $this->assertSame($usuario->id, $documento->creado_por);
            $this->assertSame('Derivaciones · Calle', $documento->carpeta->nombre);
            $this->assertSame(1, $documento->versiones()->count());
            $this->assertNull($caso->evidencia1_documento_id);
            $this->assertEquals($antes, $caso->only($campos));
        }
    }

    public function test_doc34_ca01_el_superadministrador_tambien_carga(): void
    {
        $caso = $this->derivacion();

        $this->cargarDerivacion(User::factory()->superadmin()->create(), $caso, 1)->assertRedirect();

        $this->assertNotNull($caso->fresh()->evidencia1_documento_id);
    }

    public function test_doc34_ca02_un_responsable_carga_en_un_caso_cctv_atendido_y_en_uno_cerrado(): void
    {
        $responsable = $this->usuario(RolDependencia::Edicion, true);
        $cerrador = $this->usuario(RolDependencia::Coordinacion);
        $atendido = $this->cctv();
        $cerrado = $this->cctv(['estado' => CasoCctv::CERRADO, 'cerrado_por' => $cerrador->id, 'cerrado_at' => now()]);
        $campos = ['estado', 'atendido_at', 'tiempo_respuesta_min', 'nombre', 'cerrado_por', 'cerrado_at'];

        foreach ([$atendido, $cerrado] as $caso) {
            $antes = $caso->fresh()->only($campos);

            $this->cargarCctv($responsable, $caso)->assertRedirect(route('admin.turnos.cctv.show', $caso));

            $caso->refresh();
            $documento = Documento::find($caso->evidencia3_documento_id);
            $this->assertNotNull($documento);
            $this->assertSame($responsable->id, $documento->creado_por);
            $this->assertSame('Seguridad CCTV · Calle', $documento->carpeta->nombre);
            $this->assertSame(CasoCctv::GRUPO_CUSTODIA, $documento->carpeta->grupoCustodia->nombre);
            $this->assertEquals($antes, $caso->only($campos));
        }
    }

    public function test_doc34_ca03_quien_no_puede_cargar_recibe_403(): void
    {
        $derivacion = $this->derivacion();
        $caso = $this->cctv();

        $this->cargarDerivacion($this->usuario(RolDependencia::Lectura), $derivacion)->assertForbidden();
        $this->cargarCctv($this->usuario(RolDependencia::Edicion), $caso)->assertForbidden();
        $this->cargarCctv($this->usuario(RolDependencia::Lectura, true), $caso)->assertForbidden();

        $this->assertNull($derivacion->fresh()->evidencia2_documento_id);
        $this->assertNull($caso->fresh()->evidencia3_documento_id);
        $this->assertSame(0, $this->archivosEnDisco());
    }

    public function test_doc34_ca04_no_se_reemplaza_una_evidencia_que_ya_tiene_archivo(): void
    {
        $derivacion = $this->conEvidencia($this->derivacion(), 1);
        $caso = $this->conEvidencia($this->cctv(), 1);
        $originales = [$derivacion->evidencia1_documento_id, $caso->evidencia1_documento_id];

        $this->cargarDerivacion($this->usuario(RolDependencia::Edicion), $derivacion, 1)
            ->assertSessionHasErrors(['evidencia1' => self::YA_TIENE]);
        $this->cargarCctv($this->usuario(RolDependencia::Coordinacion), $caso, 1)
            ->assertSessionHasErrors(['evidencia1' => self::YA_TIENE]);

        $this->assertSame($originales, [$derivacion->fresh()->evidencia1_documento_id, $caso->fresh()->evidencia1_documento_id]);
        $this->assertSame(0, $this->archivosEnDisco());
        $this->assertSame(2, Documento::count());
    }

    public function test_doc34_ca05_solo_estados_que_permiten_cargar(): void
    {
        $coordinacion = $this->usuario(RolDependencia::Coordinacion);
        $pendiente = $this->derivacion(['estado' => Derivacion::PENDIENTE, 'atendida_at' => null, 'tiempo_respuesta_min' => null]);
        $eliminada = $this->derivacion();
        $eliminada->delete();
        $abierto = CasoCctv::factory()->de($this->calle)->create();
        $anulado = CasoCctv::factory()->de($this->calle)->create(['estado' => CasoCctv::ANULADO]);

        $this->cargarDerivacion($coordinacion, $pendiente)->assertForbidden();
        $this->cargarDerivacion($coordinacion, $eliminada)->assertNotFound();
        $this->cargarCctv($coordinacion, $abierto)->assertForbidden();
        $this->cargarCctv($coordinacion, $anulado)->assertForbidden();

        $this->assertSame(0, Documento::count());
        $this->assertSame(0, $this->archivosEnDisco());
    }

    public function test_doc34_ca06_otro_componente_u_otra_dependencia_da_no_encontrado(): void
    {
        $otroComponente = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica']);
        $otraDependencia = Dependencia::factory()->create();
        $ajeno = Componente::factory()->create(['dependencia_id' => $otraDependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $coordinacion = $this->usuario(RolDependencia::Coordinacion);

        // Los casos se crean antes de la primera petición: después el contexto ya queda parado en Calle.
        $casos = array_map(fn (Componente $c): array => [$this->derivacion([], $c), $this->cctv([], $c)], [$otroComponente, $ajeno]);

        foreach ($casos as [$derivacion, $caso]) {
            $this->cargarDerivacion($coordinacion, $derivacion)->assertNotFound();
            $this->cargarCctv($coordinacion, $caso)->assertNotFound();
        }

        $this->assertSame(0, Documento::count());
        $this->assertSame(0, $this->archivosEnDisco());
    }

    public function test_doc34_ca07_un_archivo_que_no_es_imagen_o_pasa_de_25_mb_no_deja_nada(): void
    {
        $derivacion = $this->derivacion();
        $caso = $this->cctv();
        $edicion = $this->usuario(RolDependencia::Edicion);
        $responsable = $this->usuario(RolDependencia::Edicion, true);

        $this->cargarDerivacion($edicion, $derivacion, 2, ['evidencia2' => $this->archivoPdf()])
            ->assertSessionHasErrors(['evidencia2' => 'La evidencia debe ser una imagen (JPG, PNG…)']);
        $this->cargarCctv($responsable, $caso, 3, ['evidencia3' => UploadedFile::fake()->image('grande.jpg')->size(25601)])
            ->assertSessionHasErrors(['evidencia3' => 'La evidencia no puede pasar de 25 MB']);
        $this->cargarCctv($responsable, $caso, 3, ['evidencia3' => null])->assertSessionHasErrors('evidencia3');

        $this->assertNull($derivacion->fresh()->evidencia2_documento_id);
        $this->assertNull($caso->fresh()->evidencia3_documento_id);
        $this->assertSame(0, Documento::count());
        $this->assertSame(0, $this->archivosEnDisco());
    }

    public function test_doc34_ca08_la_tarjeta_se_comprueba_sobre_una_relectura_dentro_de_la_transaccion(): void
    {
        $caso = $this->derivacion();
        $viejo = Derivacion::find($caso->id);

        // Otra persona llenó la tarjeta después de que se leyó el modelo de la ruta.
        $this->conEvidencia($caso, 2);
        $this->assertNull($viejo->evidencia2_documento_id);

        $this->actingAs($this->usuario(RolDependencia::Edicion));
        $peticion = Request::create('/', 'POST', [], [], ['evidencia2' => $this->archivoJpg()]);

        try {
            app(EvidenciaFichaController::class)->derivacion($peticion, $viejo, 2);
            $this->fail('Debió rechazar la carga.');
        } catch (ValidationException $e) {
            $this->assertSame([self::YA_TIENE], $e->errors()['evidencia2']);
        }

        $this->assertSame(1, Documento::count());
        $this->assertSame(0, $this->archivosEnDisco());
    }

    public function test_doc34_ca09_cada_carga_queda_auditada(): void
    {
        $derivacion = $this->derivacion();
        $caso = $this->cctv();
        $edicion = $this->usuario(RolDependencia::Edicion);
        $responsable = $this->usuario(RolDependencia::Edicion, true);

        $this->cargarDerivacion($edicion, $derivacion, 2)->assertRedirect();
        $this->cargarCctv($responsable, $caso, 3)->assertRedirect();

        $registros = Auditoria::where('accion', AccionAuditoria::EvidenciaCargadaDesdeFicha->value)->orderBy('id')->get();
        $this->assertCount(2, $registros);

        [$deDerivacion, $deCctv] = $registros;
        $this->assertSame($edicion->id, $deDerivacion->user_id);
        $this->assertSame(Derivacion::class, $deDerivacion->auditable_type);
        $this->assertSame($derivacion->id, (int) $deDerivacion->auditable_id);
        $this->assertSame(2, $deDerivacion->datos['evidencia']);
        $this->assertSame($derivacion->fresh()->evidencia2_documento_id, $deDerivacion->datos['documento']);
        $this->assertSame($responsable->id, $deCctv->user_id);
        $this->assertSame(CasoCctv::class, $deCctv->auditable_type);
        $this->assertSame(3, $deCctv->datos['evidencia']);
    }
}
