<?php

namespace Tests\Feature\UsuariosHc;

use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\IdentidadUsuarioHc;
use App\Models\UsuarioHc;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UHC-05, casos de borde (de la segunda revisión de QA): la ficha reasignada dentro de la misma
 * consulta (NOT EXISTS correlacionado), los topes de terminos() y la marca.
 */
class UsuariosHcBusquedaCasosTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $a;

    private Dependencia $b;

    private Componente $basicaA;

    private Componente $estabA;

    private Componente $basicaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Dependencia::factory()->create();
        $this->b = Dependencia::factory()->create();
        $this->basicaA = Componente::factory()->create(['dependencia_id' => $this->a->id, 'slug' => 'basica', 'modulos' => ['usuarios_hc']]);
        $this->estabA = Componente::factory()->create(['dependencia_id' => $this->a->id, 'slug' => 'estabilizacion', 'modulos' => ['usuarios_hc']]);
        $this->basicaB = Componente::factory()->create(['dependencia_id' => $this->b->id, 'slug' => 'basica', 'modulos' => ['usuarios_hc']]);
    }

    private function ficha(Componente $c, array $datos): UsuarioHc
    {
        return UsuarioHc::factory()->en($c)->create($datos + [
            'primer_nombre' => 'Rosa',
            'primer_apellido' => 'Velez',
            'numero_ficha' => null,
        ]);
    }

    private function enA(): void
    {
        $ctx = app(ContextoDependencia::class);
        $ctx->establecer($this->a);
        $ctx->establecerComponente($this->basicaA);
    }

    /** @return list<int> */
    private function ids($q): array
    {
        return $q->orderBy('usuarios_hc.id')->pluck('usuarios_hc.id')->all();
    }

    /**
     * El SQL: el filtro de dependencia queda fuera del grupo de buscar() (AND),
     * y el NOT EXISTS está atado a la fila de afuera por el alias. Se lee, no
     * se ejecuta contra MySQL.
     */
    public function test_doc26_uhc05_sql_agrupado_y_correlacionado(): void
    {
        $this->enA();

        // MySQL cita con ` y SQLite con ": se compara con un solo estilo.
        $sql = str_replace('`', '"', UsuarioHc::query()->buscar('F-7')->toSql());

        // El scope global va al final, con AND, fuera del grupo de buscar().
        $this->assertStringStartsWith('select * from "usuarios_hc" where (', $sql);
        $this->assertStringEndsWith(')) and "usuarios_hc"."dependencia_id" = ?', $sql);
        $this->assertStringContainsString(
            'not exists (select 1 from "usuarios_hc" as "ficha_vigente" where "ficha_vigente"."ficha_normalizada" = ? and "ficha_vigente"."dependencia_id" = "usuarios_hc"."dependencia_id")',
            $sql,
        );
        $this->assertSame(1, substr_count($sql, '"dependencia_id" = ?'), $sql);
    }

    /** Sin contexto: cada fila se mide contra su propia dependencia, a la vez. */
    public function test_doc26_uhc05_sin_contexto_dos_dependencias_a_la_vez(): void
    {
        $viejaA = $this->ficha($this->basicaA, ['numero_documento' => '50000001', 'numero_ficha' => 'K-1']);
        $viejaA->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-7']);
        $vigenteA = $this->ficha($this->estabA, ['numero_documento' => '50000002', 'numero_ficha' => 'F7']);
        $viejaB = $this->ficha($this->basicaB, ['numero_documento' => '50000003', 'numero_ficha' => 'K-2']);
        $viejaB->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'f7']);

        app(ContextoDependencia::class)->establecer(null);

        $this->assertSame([$vigenteA->id, $viejaB->id], $this->ids(UsuarioHc::query()->buscar('F-7')));
    }

    /** Con contexto en A, la anterior de B nunca se cuela por la rama OR de la ficha. */
    public function test_doc26_uhc05_con_contexto_la_rama_de_ficha_no_escapa_a_otra_dependencia(): void
    {
        $viejaB = $this->ficha($this->basicaB, ['numero_documento' => '50000003']);
        $viejaB->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-7']);
        $viejaA = $this->ficha($this->basicaA, ['numero_documento' => '50000001']);
        $viejaA->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-7']);

        $this->enA();

        $this->assertSame([$viejaA->id], $this->ids(UsuarioHc::query()->buscar('f7')));
        // Y con un filtro de afuera puesto a mano, sin contexto, igual.
        app(ContextoDependencia::class)->establecer(null);
        $this->assertSame([$viejaB->id], $this->ids(UsuarioHc::query()->where('usuarios_hc.dependencia_id', $this->b->id)->buscar('f7')));
    }

    /** Dos anteriores con el mismo número y nadie vigente: salen las dos, marcadas. */
    public function test_doc26_uhc05_varias_anteriores_sin_vigente_salen_todas_con_marca_de_ficha(): void
    {
        $uno = $this->ficha($this->basicaA, ['numero_documento' => '50000001']);
        $uno->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-7']);
        $dos = $this->ficha($this->estabA, ['numero_documento' => '50000002']);
        $dos->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F7']);

        $this->enA();

        $filas = UsuarioHc::query()->buscar('F--7')->with('identidades')->orderBy('id')->get();
        $this->assertSame([$uno->id, $dos->id], $filas->pluck('id')->all());
        foreach ($filas as $fila) {
            $this->assertSame(IdentidadUsuarioHc::FICHA, $fila->coincidenciaAnterior('F--7'));
        }
    }

    /**
     * Ficha reasignada, pero la persona de antes también casa por otro dato
     * anterior: sale, y la marca dice ese dato, no «ficha».
     */
    public function test_doc26_uhc05_reasignada_pero_casa_por_documento_anterior_marca_documento(): void
    {
        $antes = $this->ficha($this->basicaA, ['numero_documento' => '50000001', 'numero_ficha' => 'K-1']);
        $antes->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-7']);
        $antes->identidades()->create(['campo' => IdentidadUsuarioHc::DOCUMENTO, 'valor' => 'XF7Y']);
        $ahora = $this->ficha($this->estabA, ['numero_documento' => '50000002', 'numero_ficha' => 'F-7']);

        $this->enA();

        $filas = UsuarioHc::query()->buscar('F7')->with('identidades')->orderBy('id')->get()->keyBy('id');
        $this->assertSame([$antes->id, $ahora->id], $filas->keys()->all());
        $this->assertSame(IdentidadUsuarioHc::DOCUMENTO, $filas[$antes->id]->coincidenciaAnterior('F7'));
        $this->assertNull($filas[$ahora->id]->coincidenciaAnterior('F7'));
    }

    /** Ninguna fila que trae buscar() queda con una marca que no explica por qué vino. */
    public function test_doc26_uhc05_marca_coherente_en_un_lote_mezclado(): void
    {
        $vig = $this->ficha($this->basicaA, ['primer_nombre' => 'Pedro', 'numero_documento' => '11120', 'numero_ficha' => '120']);
        $vig->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => '120']);
        $nom = $this->ficha($this->basicaA, ['numero_documento' => '20000002']);
        $nom->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Mario 120']);
        $fic = $this->ficha($this->estabA, ['numero_documento' => '20000003']);
        $fic->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => '1-2-0']); // reasignada: no sale
        $doc = $this->ficha($this->estabA, ['numero_documento' => '20000004']);
        $doc->identidades()->create(['campo' => IdentidadUsuarioHc::DOCUMENTO, 'valor' => '9.120.000']);

        $this->enA();

        $filas = UsuarioHc::query()->buscar('120')->with('identidades')->orderBy('id')->get()->keyBy('id');
        $this->assertSame([$vig->id, $nom->id, $doc->id], $filas->keys()->all());
        $this->assertNull($filas[$vig->id]->coincidenciaAnterior('120'));
        $this->assertSame(IdentidadUsuarioHc::NOMBRE, $filas[$nom->id]->coincidenciaAnterior('120'));
        $this->assertSame(IdentidadUsuarioHc::DOCUMENTO, $filas[$doc->id]->coincidenciaAnterior('120'));
    }

    /** Miles de palabras distintas (no repetidas, como en la prueba de UHC-05): no cae. */
    public function test_doc26_uhc05_texto_enorme_de_palabras_distintas_no_rompe(): void
    {
        $rosa = $this->ficha($this->basicaA, ['numero_documento' => '50000001']);
        $this->enA();

        $palabras = implode(' ', array_map(fn ($i) => 'p'.$i, range(1, 20000)));
        $this->assertSame([], $this->ids(UsuarioHc::query()->buscar($palabras)));
        // Cuentan las diez primeras («rosa p1 … p9»): ninguna ficha las tiene todas.
        $this->assertSame([], $this->ids(UsuarioHc::query()->buscar('rosa '.$palabras)));
        $this->assertSame([$rosa->id], $this->ids(UsuarioHc::query()->buscar('rosa velez '.str_repeat('rosa ', 5000))));
        $this->assertNull($rosa->coincidenciaAnterior('rosa '.$palabras));
        // Multibyte: 100 «ñ» se cortan por carácter, no por byte, y quedan en «n».
        $this->assertSame([], $this->ids(UsuarioHc::query()->buscar(str_repeat('ñ', 5000))));
    }

    /**
     * Como la usará DOC-27: buscar() dentro de un whereHas desde otra tabla.
     * La correlación con «usuarios_hc» sigue apuntando a la fila correcta.
     */
    public function test_doc26_uhc05_buscar_dentro_de_where_has(): void
    {
        $antes = $this->ficha($this->basicaA, ['numero_documento' => '50000001']);
        $iAntes = $antes->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-7']);
        $ahora = $this->ficha($this->estabA, ['numero_documento' => '50000002', 'numero_ficha' => 'F7']);
        $iAhora = $ahora->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Otro']);
        $viejaB = $this->ficha($this->basicaB, ['numero_documento' => '50000003']);
        $iB = $viejaB->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F7']);

        app(ContextoDependencia::class)->establecer(null);

        $ids = IdentidadUsuarioHc::query()->whereHas('usuarioHc', fn ($q) => $q->buscar('f7'))->orderBy('id')->pluck('id')->all();
        $this->assertSame([$iAhora->id, $iB->id], $ids);
        $this->assertNotContains($iAntes->id, $ids);
    }

    /** El tope cuenta después de normalizar: los signos del comienzo no se comen el número. */
    public function test_doc26_uhc05_los_signos_del_comienzo_no_cuentan_para_el_tope(): void
    {
        $rosa = $this->ficha($this->basicaA, ['numero_documento' => '1023456789']);
        $luis = $this->ficha($this->basicaA, ['primer_nombre' => 'Luis', 'numero_documento' => '1023000000']);
        $this->enA();

        // 150 puntos y luego el número completo: busca «1023456789», no queda vacío ni recortado.
        $this->assertSame([$rosa->id], $this->ids(UsuarioHc::query()->buscar(str_repeat('.', 150).'1023456789')));
        // 150 espacios y luego un nombre: filtra por ese nombre.
        $this->assertSame([$luis->id], $this->ids(UsuarioHc::query()->buscar(str_repeat(' ', 150).'luis')));
    }
}
