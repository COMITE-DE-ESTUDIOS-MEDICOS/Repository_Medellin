<?php

namespace Tests\Feature\EvidenciasDesdeLaFicha;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Http\Controllers\Admin\EvidenciaFichaController;
use App\Models\Auditoria;
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

/** DOC-34: cargar una evidencia vacía desde la ficha de una derivación Solucionada. */
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

    private function usuario(RolDependencia $rol): User
    {
        return $this->usuarioCon($rol, $this->dependencia);
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

    private function cargar(User $usuario, Derivacion $caso, int $n = 2, array $datos = [])
    {
        return $this->actingAs($usuario)->post(
            route('admin.turnos.derivaciones.evidencia', [$caso, $n]),
            $datos + ["evidencia{$n}" => $this->archivoJpg()],
        );
    }

    private function archivosEnDisco(): int
    {
        return count(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    private function conEvidencia(Derivacion $caso, int $n): Derivacion
    {
        $documento = Documento::factory()->create(['dependencia_id' => $caso->dependencia_id]);
        $caso->forceFill(["evidencia{$n}_documento_id" => $documento->id])->save();

        return $caso;
    }

    public function test_doc34_ca01_edicion_coordinacion_y_administracion_cargan_en_una_derivacion_solucionada(): void
    {
        foreach ([RolDependencia::Edicion, RolDependencia::Coordinacion, RolDependencia::Administracion] as $rol) {
            $caso = $this->derivacion();
            $campos = ['estado', 'tiempo_respuesta_min', 'cumplio_requerimiento', 'nombre', 'atendida_at', 'respondida_por'];
            $antes = $caso->fresh()->only($campos);
            $usuario = $this->usuario($rol);

            $this->cargar($usuario, $caso)
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

        $this->cargar(User::factory()->superadmin()->create(), $caso, 1)->assertRedirect();

        $this->assertNotNull($caso->fresh()->evidencia1_documento_id);
    }

    public function test_doc34_ca03_lectura_recibe_403(): void
    {
        $caso = $this->derivacion();

        $this->cargar($this->usuario(RolDependencia::Lectura), $caso)->assertForbidden();

        $this->assertNull($caso->fresh()->evidencia2_documento_id);
        $this->assertSame(0, $this->archivosEnDisco());
    }

    public function test_doc34_ca04_no_se_reemplaza_una_evidencia_que_ya_tiene_archivo(): void
    {
        $caso = $this->conEvidencia($this->derivacion(), 1);
        $original = $caso->evidencia1_documento_id;

        $this->cargar($this->usuario(RolDependencia::Edicion), $caso, 1)
            ->assertSessionHasErrors(['evidencia1' => self::YA_TIENE]);

        $this->assertSame($original, $caso->fresh()->evidencia1_documento_id);
        $this->assertSame(0, $this->archivosEnDisco());
        $this->assertSame(1, Documento::count());
    }

    public function test_doc34_ca05_pendiente_y_eliminada_se_rechazan(): void
    {
        $coordinacion = $this->usuario(RolDependencia::Coordinacion);
        $pendiente = $this->derivacion(['estado' => Derivacion::PENDIENTE, 'atendida_at' => null, 'tiempo_respuesta_min' => null]);
        $eliminada = $this->derivacion();
        $eliminada->delete();

        $this->cargar($coordinacion, $pendiente)->assertForbidden();
        $this->cargar($coordinacion, $eliminada)->assertNotFound();

        $this->assertSame(0, Documento::count());
        $this->assertSame(0, $this->archivosEnDisco());
    }

    public function test_doc34_ca06_otra_dependencia_da_no_encontrado(): void
    {
        $ajeno = Componente::factory()->create(['dependencia_id' => Dependencia::factory()->create()->id, 'nombre' => 'Calle', 'slug' => 'calle']);
        $coordinacion = $this->usuario(RolDependencia::Coordinacion);
        $caso = $this->derivacion([], $ajeno);

        // La spec admite «No encontrado» o «No tienes permiso»: aquí el contexto aún no se fijó al resolver la ruta y responde 403.
        $this->assertContains($this->cargar($coordinacion, $caso)->status(), [403, 404]);

        $this->assertSame(0, Documento::count());
        $this->assertSame(0, $this->archivosEnDisco());
    }

    public function test_doc34_ca07_un_archivo_que_no_es_imagen_o_pasa_de_25_mb_no_deja_nada(): void
    {
        $caso = $this->derivacion();
        $edicion = $this->usuario(RolDependencia::Edicion);

        $this->cargar($edicion, $caso, 2, ['evidencia2' => $this->archivoPdf()])
            ->assertSessionHasErrors(['evidencia2' => 'La evidencia debe ser una imagen (JPG, PNG…)']);
        $this->cargar($edicion, $caso, 2, ['evidencia2' => UploadedFile::fake()->image('grande.jpg')->size(25601)])
            ->assertSessionHasErrors(['evidencia2' => 'La evidencia no puede pasar de 25 MB']);
        $this->cargar($edicion, $caso, 2, ['evidencia2' => null])->assertSessionHasErrors('evidencia2');

        $this->assertNull($caso->fresh()->evidencia2_documento_id);
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
        $caso = $this->derivacion();
        $edicion = $this->usuario(RolDependencia::Edicion);

        $this->cargar($edicion, $caso, 2)->assertRedirect();

        $registros = Auditoria::where('accion', AccionAuditoria::EvidenciaCargadaDesdeFicha->value)->get();
        $this->assertCount(1, $registros);

        $registro = $registros[0];
        $this->assertSame($edicion->id, $registro->user_id);
        $this->assertSame(Derivacion::class, $registro->auditable_type);
        $this->assertSame($caso->id, (int) $registro->auditable_id);
        $this->assertSame(2, $registro->datos['evidencia']);
        $this->assertSame($caso->fresh()->evidencia2_documento_id, $registro->datos['documento']);
    }
}
