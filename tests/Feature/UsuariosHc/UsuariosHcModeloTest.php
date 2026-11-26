<?php

namespace Tests\Feature\UsuariosHc;

use App\Models\CaracterizacionUsuarioHc;
use App\Models\Cie10;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\FotoUsuarioHc;
use App\Models\IdentidadUsuarioHc;
use App\Models\Pais;
use App\Models\UsuarioHc;
use App\Services\ContextoDependencia;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** DOC-26 (UHC-02, plan D1 y D5): esquema, modelos, normalización y edad. */
class UsuariosHcModeloTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $basica;

    private Componente $estabilizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->basica = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica', 'modulos' => ['usuarios_hc']]);
        $this->estabilizacion = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Estabilización', 'slug' => 'estabilizacion', 'modulos' => ['usuarios_hc']]);
    }

    public function test_doc26_uhc02_las_ocho_tablas_existen_sin_borrado_suave_y_los_catalogos_vacios(): void
    {
        foreach (['usuarios_hc', 'usuario_hc_identidades', 'usuario_hc_caracterizaciones', 'usuario_hc_respuestas',
            'usuario_hc_cambios', 'usuario_hc_fotos', 'catalogo_cie10', 'catalogo_paises'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla);
            $this->assertFalse(Schema::hasColumn($tabla, 'deleted_at'), "{$tabla} no lleva SoftDeletes.");
        }

        $this->assertTrue(Schema::hasColumns('usuario_hc_cambios', ['codigo_antes', 'codigo_despues', 'antes', 'despues', 'motivo']));
        $this->assertFalse(Schema::hasColumn('usuario_hc_respuestas', 'estado'), 'P-17: la respuesta anterior vive en el historial.');
        $this->assertSame(0, Cie10::count());
        $this->assertSame(0, Pais::count());
    }

    public function test_doc26_uhc02_normalizar_texto_y_codigo(): void
    {
        $this->assertSame('pena alvarez j', UsuarioHc::normalizarTexto('Peña Álvarez, J.'));
        $this->assertSame('maria jose', UsuarioHc::normalizarTexto('  MARÍA   josé '));
        $this->assertSame('', UsuarioHc::normalizarTexto('...'));
        $this->assertSame('', UsuarioHc::normalizarTexto(null));

        $this->assertSame('1234567', UsuarioHc::normalizarCodigo('12.345 67'));
        $this->assertSame('F200', UsuarioHc::normalizarCodigo('F20.0'));
        // Uno solo para documento y ficha: el guion no cuenta.
        foreach (['F-120', 'f-120 ', 'F120', 'F--120'] as $ficha) {
            $this->assertSame('F120', UsuarioHc::normalizarCodigo($ficha), $ficha);
        }
        $this->assertSame('', UsuarioHc::normalizarCodigo('---'));
        $this->assertSame('', UsuarioHc::normalizarCodigo(null));
    }

    public function test_doc26_uhc02_al_guardar_arma_el_nombre_completo_y_las_columnas_normalizadas(): void
    {
        $ficha = UsuarioHc::factory()->en($this->basica)->create([
            'primer_nombre' => '  Juan ',
            'segundo_nombre' => '',
            'primer_apellido' => 'Peña',
            'segundo_apellido' => ' Álvarez ',
            'alias' => 'El Mono, Jr.',
            'numero_documento' => '  ab0012 ',
            'numero_ficha' => ' f-120 ',
        ]);

        $this->assertSame('Juan Peña Álvarez', $ficha->nombre_completo);
        $this->assertNull($ficha->segundo_nombre);
        $this->assertSame('juan pena alvarez', $ficha->nombre_busqueda);
        $this->assertSame('el mono jr', $ficha->alias_busqueda);
        // El documento, como se escribió y recortado; la ficha, en mayúsculas y con su guion.
        $this->assertSame('ab0012', $ficha->numero_documento);
        $this->assertSame('AB0012', $ficha->documento_normalizado);
        $this->assertSame('F-120', $ficha->numero_ficha);
        $this->assertSame('F120', $ficha->ficha_normalizada);
        $this->assertSame(UsuarioHc::VIVA, $ficha->fresh()->estado_vital);
        $this->assertSame(0, $ficha->fresh()->revision);

        // Vacíos: NULL, no '' (los índices únicos admiten varios).
        $sinDatos = UsuarioHc::factory()->en($this->basica)->sinDocumento()->create(['primer_nombre' => 'Provisional', 'primer_apellido' => null, 'alias' => '  ', 'numero_ficha' => '   ']);
        $this->assertSame('Provisional', $sinDatos->nombre_completo);
        foreach (['numero_documento', 'documento_normalizado', 'numero_ficha', 'ficha_normalizada', 'alias', 'alias_busqueda'] as $columna) {
            $this->assertNull($sinDatos->fresh()->{$columna}, $columna);
        }

        // Al corregir se vuelven a armar.
        $ficha->update(['segundo_nombre' => 'Carlos', 'numero_ficha' => 'a12', 'alias' => null]);
        $this->assertSame('Juan Carlos Peña Álvarez', $ficha->fresh()->nombre_completo);
        $this->assertSame('juan carlos pena alvarez', $ficha->fresh()->nombre_busqueda);
        $this->assertSame('A12', $ficha->fresh()->numero_ficha);
        $this->assertSame('A12', $ficha->fresh()->ficha_normalizada);
        $this->assertNull($ficha->fresh()->alias_busqueda);
    }

    public function test_doc26_uhc02_identidades_y_catalogos_se_normalizan_al_guardar(): void
    {
        $ficha = UsuarioHc::factory()->en($this->basica)->create();

        $nombre = $ficha->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Peña Álvarez, J.']);
        $documento = $ficha->identidades()->create(['campo' => IdentidadUsuarioHc::DOCUMENTO, 'valor' => '12.345 67']);
        $numero = $ficha->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'f-120']);
        $vacia = $ficha->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => '---']);

        $this->assertSame('pena alvarez j', $nombre->valor_busqueda);
        $this->assertSame('1234567', $documento->valor_busqueda);
        $this->assertSame('F120', $numero->valor_busqueda);
        $this->assertNull($vacia->valor_busqueda);
        $this->assertNotNull($nombre->fresh()->created_at);

        $this->assertSame('esquizofrenia paranoide', Cie10::create(['codigo' => 'F200', 'nombre' => 'ESQUIZOFRENIA PARANOIDE'])->nombre_busqueda);
        $this->assertSame('peru', Pais::create(['codigo' => '604', 'nombre' => 'PERÚ', 'nombre_oficial' => 'PERU'])->nombre_busqueda);
    }

    public function test_doc26_uhc02_los_indices_unicos_admiten_vacios_y_comparan_lo_normalizado(): void
    {
        // Dos «Sin documento» y dos sin ficha en la misma dependencia: no chocan (CA-05, CA-06).
        UsuarioHc::factory()->count(2)->en($this->basica)->sinDocumento()->create();
        UsuarioHc::factory()->count(2)->en($this->basica)->create(['numero_ficha' => null]);
        $this->assertSame(4, UsuarioHc::count());

        // El mismo número con otro tipo, sí.
        UsuarioHc::factory()->en($this->basica)->create(['tipo_documento' => 'CC', 'numero_documento' => 'AB0012']);
        UsuarioHc::factory()->en($this->basica)->create(['tipo_documento' => 'PAS', 'numero_documento' => 'AB0012']);

        // Mismo tipo y documento escrito distinto, aunque sea del otro componente: choca.
        $this->assertChoca(fn () => UsuarioHc::factory()->en($this->estabilizacion)->create(['tipo_documento' => 'CC', 'numero_documento' => '  ab0012 ']));

        // «F120» y «F-120» en la misma dependencia chocan, también desde el otro componente.
        UsuarioHc::factory()->en($this->basica)->create(['numero_ficha' => 'F120']);
        $this->assertChoca(fn () => UsuarioHc::factory()->en($this->estabilizacion)->create(['numero_ficha' => 'f-120']));

        // En otra dependencia, ni el documento ni la ficha chocan.
        $otra = Componente::factory()->create(['dependencia_id' => Dependencia::factory()->create()->id]);
        UsuarioHc::factory()->en($otra)->create(['tipo_documento' => 'CC', 'numero_documento' => 'AB0012', 'numero_ficha' => 'F-120']);

        $this->assertSame(1, UsuarioHc::where('dependencia_id', $otra->dependencia_id)->count());
    }

    public function test_doc26_uhc02_la_ficha_se_filtra_por_dependencia_y_no_por_componente(): void
    {
        $deBasica = UsuarioHc::factory()->en($this->basica)->create();
        $deEstabilizacion = UsuarioHc::factory()->en($this->estabilizacion)->create();
        $deOtra = UsuarioHc::factory()->create();

        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($this->dependencia);
        $contexto->establecerComponente($this->basica);

        $this->assertEqualsCanonicalizing([$deBasica->id, $deEstabilizacion->id], UsuarioHc::pluck('id')->all());
        $this->assertNull(UsuarioHc::find($deOtra->id));
    }

    public function test_doc26_uhc02_el_componente_de_registro_se_ve_desde_otro_componente(): void
    {
        $ficha = UsuarioHc::factory()->en($this->estabilizacion)->create();

        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($this->dependencia);
        $contexto->establecerComponente($this->basica);

        // Sin el salto, Estabilización no se vería parado en Básica.
        $this->assertNull(Componente::find($this->estabilizacion->id));

        $this->assertSame('Estabilización', UsuarioHc::findOrFail($ficha->id)->componenteRegistro?->nombre);
        $this->assertSame('Estabilización', UsuarioHc::with('componenteRegistro')->findOrFail($ficha->id)->componenteRegistro?->nombre);
    }

    public function test_doc26_uhc02_el_componente_de_registro_no_se_llena_desde_un_formulario(): void
    {
        $ficha = new UsuarioHc(['componente_registro_id' => $this->estabilizacion->id, 'primer_nombre' => 'Ana', 'ficha_normalizada' => 'X1']);

        $this->assertNull($ficha->componente_registro_id);
        $this->assertNull($ficha->ficha_normalizada);
        $this->assertSame('Ana', $ficha->primer_nombre);
    }

    public function test_doc26_uhc02_la_foto_vigente_tiene_que_ser_de_la_ficha(): void
    {
        $ficha = UsuarioHc::factory()->en($this->basica)->create();
        $otra = UsuarioHc::factory()->en($this->basica)->create();
        $anterior = FotoUsuarioHc::create(['usuario_hc_id' => $ficha->id, 'ruta' => 'usuarios-hc/1/1/a.jpg', 'tamano' => 10]);
        $vigente = FotoUsuarioHc::create(['usuario_hc_id' => $ficha->id, 'ruta' => 'usuarios-hc/1/1/b.jpg', 'tamano' => 10]);
        $ajena = FotoUsuarioHc::create(['usuario_hc_id' => $otra->id, 'ruta' => 'usuarios-hc/1/2/c.jpg', 'tamano' => 10]);

        $this->assertNull($ficha->foto);

        $ficha->forceFill(['foto_id' => $vigente->id])->save();
        $this->assertTrue($vigente->is($ficha->fresh()->foto));
        $this->assertTrue($vigente->is(UsuarioHc::with('foto')->findOrFail($ficha->id)->foto));
        $this->assertCount(2, $ficha->fotos);
        $this->assertNotNull($anterior->fresh());

        // Una foto de otra ficha no es la vigente, aunque foto_id apunte a ella.
        $ficha->forceFill(['foto_id' => $ajena->id])->save();
        $this->assertNull($ficha->fresh()->foto);
        $this->assertNull(UsuarioHc::with('foto')->findOrFail($ficha->id)->foto);
    }

    public function test_doc26_ca12_edad_en_anios_cumplidos_o_sin_informacion(): void
    {
        // 25/11/2026 a las 10 p. m. en Bogotá: en UTC ya es 26/11.
        $this->travelTo(now()->parse('2026-11-26 03:00:00', 'UTC'));

        $ficha = UsuarioHc::factory()->en($this->basica)->create();
        $this->assertSame(CaracterizacionUsuarioHc::SIN_INFORMACION, $ficha->edad, 'Sin caracterización.');

        $caracterizacion = $ficha->caracterizacion()->create(['fecha_nacimiento' => null]);
        $this->assertSame('Sin información', $caracterizacion->edad);
        $this->assertSame('Sin información', $ficha->fresh()->edad);

        foreach (['1990-11-25' => 36, '1990-11-26' => 35, '2025-11-26' => 0, '2000-02-29' => 26] as $nacimiento => $edad) {
            $caracterizacion->update(['fecha_nacimiento' => $nacimiento]);
            $this->assertSame($edad, $caracterizacion->fresh()->edad, $nacimiento);
            $this->assertSame($edad, $ficha->fresh()->edad, $nacimiento);
        }

        // Una fecha futura no da una edad negativa (QA): la rechaza el formulario (UHC-14),
        // y si llega por otro camino dice «Sin información».
        foreach (['2028-11-26', '2026-11-26'] as $futura) {
            $caracterizacion->update(['fecha_nacimiento' => $futura]);
            $this->assertSame(CaracterizacionUsuarioHc::SIN_INFORMACION, $caracterizacion->fresh()->edad, $futura);
        }
    }

    public function test_doc26_ca20_editar_por_asignacion_masiva_no_cambia_el_componente_de_registro(): void
    {
        $ficha = UsuarioHc::factory()->en($this->basica)->create();

        $ficha->update(['componente_registro_id' => $this->estabilizacion->id, 'primer_nombre' => 'Otro']);

        $this->assertSame($this->basica->id, $ficha->fresh()->componente_registro_id);
        $this->assertSame('Otro', $ficha->fresh()->primer_nombre);
    }

    /** Un número pegado desde Excel trae espacios duros (U+00A0): se recortan como los normales. */
    public function test_doc26_uhc02_el_espacio_duro_de_los_extremos_se_recorta(): void
    {
        $ficha = UsuarioHc::factory()->en($this->basica)->create(['numero_ficha' => "\u{00A0}f-120\u{00A0}", 'numero_documento' => "\u{00A0}123 ", 'primer_nombre' => "\u{00A0}Ana"]);

        $ficha = $ficha->fresh();
        $this->assertSame('F-120', $ficha->numero_ficha);
        $this->assertSame('F120', $ficha->ficha_normalizada);
        $this->assertSame('123', $ficha->numero_documento);
        $this->assertSame('Ana', $ficha->primer_nombre);
    }

    /**
     * Borde de D5 que el índice no ataja: un documento hecho solo de signos queda
     * NULL, y dos «CC ...» conviven. Lo tiene que rechazar la validación (UHC-07).
     */
    public function test_doc26_uhc02_un_documento_solo_de_signos_queda_vacio_al_normalizar(): void
    {
        $a = UsuarioHc::factory()->en($this->basica)->create(['tipo_documento' => 'CC', 'numero_documento' => '...']);
        $b = UsuarioHc::factory()->en($this->basica)->create(['tipo_documento' => 'CC', 'numero_documento' => '-.-']);

        $this->assertNull($a->fresh()->documento_normalizado);
        $this->assertNull($b->fresh()->documento_normalizado);
    }

    /** Nada se borra: con historial o fotos, la base de datos no deja borrar la ficha. */
    public function test_doc26_uhc02_una_ficha_con_historial_no_se_puede_borrar(): void
    {
        $ficha = UsuarioHc::factory()->en($this->basica)->create();
        $ficha->cambios()->create(['seccion' => 'identificacion', 'campo' => 'primer_nombre', 'antes' => 'Ana', 'despues' => 'Anna', 'motivo' => 'Error de digitación']);

        try {
            $ficha->delete();
            $this->fail('La ficha con historial no debía poder borrarse.');
        } catch (QueryException) {
            $this->assertSame(1, $ficha->cambios()->count());
        }
    }

    /** Como la usará DOC-27: varias fichas en una dependencia dada, sin en(). */
    public function test_doc26_uhc02_la_fabrica_crea_varias_fichas_en_una_dependencia_sin_chocar(): void
    {
        $fichas = UsuarioHc::factory()->count(3)->create(['dependencia_id' => $this->dependencia->id]);

        $this->assertCount(3, $fichas);
        foreach ($fichas as $ficha) {
            $this->assertSame($this->dependencia->id, $ficha->componenteRegistro->dependencia_id);
        }
    }

    private function assertChoca(callable $crear): void
    {
        try {
            $crear();
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('El índice único debía rechazarla.');
    }
}
