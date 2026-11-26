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
 * DOC-26 (UHC-05, plan D5): UsuarioHc::buscar y la marca «coincide con un
 * dato anterior». Corre igual contra MySQL:
 * DB_CONNECTION=mysql DB_DATABASE=documenta_pruebas php artisan test --filter=UsuariosHcBusquedaTest
 */
class UsuariosHcBusquedaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $basica;

    private Componente $estabilizacion;

    private Componente $otraBasica;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->basica = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica', 'slug' => 'basica', 'modulos' => ['usuarios_hc']]);
        $this->estabilizacion = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Estabilización', 'slug' => 'estabilizacion', 'modulos' => ['usuarios_hc']]);

        $otra = Dependencia::factory()->create();
        $this->otraBasica = Componente::factory()->create(['dependencia_id' => $otra->id, 'nombre' => 'Básica', 'slug' => 'basica', 'modulos' => ['usuarios_hc']]);

        // Parado en Básica de la dependencia: la búsqueda cruza componentes, no dependencias.
        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($this->dependencia);
        $contexto->establecerComponente($this->basica);
    }

    /** Una ficha con todo explícito: nada al azar que case por casualidad. */
    private function ficha(Componente $componente, array $datos = []): UsuarioHc
    {
        return UsuarioHc::factory()->en($componente)->create($datos + [
            'primer_nombre' => 'Rosa',
            'primer_apellido' => 'Vélez',
            'numero_documento' => '70000001',
        ]);
    }

    /** @return list<int> */
    private function buscar(?string $texto): array
    {
        return UsuarioHc::query()->buscar($texto)->orderBy('usuarios_hc.id')->pluck('usuarios_hc.id')->all();
    }

    private function juan(array $datos = []): UsuarioHc
    {
        return $this->ficha($this->basica, $datos + [
            'primer_nombre' => 'Juan',
            'segundo_nombre' => 'Carlos',
            'primer_apellido' => 'Peña',
            'segundo_apellido' => 'Pérez',
            'alias' => 'El Mono',
            'numero_documento' => '1023456789',
            'numero_ficha' => 'F-120',
        ]);
    }

    public function test_doc26_ca03_busca_por_palabras_documento_y_ficha_normalizados(): void
    {
        $juan = $this->juan();
        // De otro componente de la misma dependencia: también sale (PA-01).
        $ana = $this->ficha($this->estabilizacion, ['primer_nombre' => 'Ana', 'primer_apellido' => 'Ruiz', 'numero_documento' => '80000002', 'numero_ficha' => 'B-7']);

        // Cada palabra, en cualquier orden y en parte.
        $this->assertSame([$juan->id], $this->buscar('perez juan'));
        $this->assertSame([$juan->id], $this->buscar('pena'));
        $this->assertSame([$juan->id], $this->buscar('carl pér'));
        $this->assertSame([], $this->buscar('juan ruiz'));
        // Parte del documento, escrito con puntos o espacios.
        $this->assertSame([$juan->id], $this->buscar('23.456'));
        $this->assertSame([$juan->id], $this->buscar('1 023 456'));
        // Ficha exacta, sin mayúsculas ni guiones.
        $this->assertSame([$juan->id], $this->buscar('f-120'));
        $this->assertSame([$juan->id], $this->buscar('F120'));
        $this->assertSame([], $this->buscar('F12'));
        $this->assertSame([$ana->id], $this->buscar('b7'));
        $this->assertSame([$ana->id], $this->buscar('ANA'));
    }

    public function test_doc26_ca04_un_dato_anterior_lleva_a_la_ficha_vigente(): void
    {
        $juan = $this->juan();
        $juan->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Pedro Gómez']);
        $juan->identidades()->create(['campo' => IdentidadUsuarioHc::DOCUMENTO, 'valor' => '998877']);
        $juan->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'Z-9']);
        $this->ficha($this->basica);

        foreach (['gomez pedro' => IdentidadUsuarioHc::NOMBRE, '9988' => IdentidadUsuarioHc::DOCUMENTO, 'z9' => IdentidadUsuarioHc::FICHA] as $texto => $campo) {
            $this->assertSame([$juan->id], $this->buscar($texto), $texto);

            $fila = UsuarioHc::query()->buscar($texto)->with('identidades')->sole();
            $this->assertSame($campo, $fila->coincidenciaAnterior($texto), $texto);
        }

        // Con los datos vigentes no hay marca.
        foreach (['juan', 'mono', '0234', 'F-120'] as $texto) {
            $this->assertNull(UsuarioHc::query()->buscar($texto)->with('identidades')->sole()->coincidenciaAnterior($texto), $texto);
        }
    }

    public function test_doc26_uhc05_tildes_mayusculas_y_enie_no_cuentan(): void
    {
        $juan = $this->juan();

        foreach (['PEÑA', 'pena', 'Peña', 'PÉREZ', 'perez', 'Pérez Peña', 'jUaN cArLoS'] as $texto) {
            $this->assertSame([$juan->id], $this->buscar($texto), $texto);
        }
    }

    public function test_doc26_uhc05_los_signos_cuentan_como_espacio(): void
    {
        $juan = $this->juan();

        foreach (['Peña-Pérez', 'Pérez, Juan.', '(juan) "carlos"', 'juan;pena'] as $texto) {
            $this->assertSame([$juan->id], $this->buscar($texto), $texto);
        }
    }

    public function test_doc26_uhc05_cada_palabra_en_el_nombre_o_en_el_alias(): void
    {
        $juan = $this->juan();

        $this->assertSame([$juan->id], $this->buscar('mono'));
        $this->assertSame([$juan->id], $this->buscar('el mono'));
        $this->assertSame([$juan->id], $this->buscar('mono juan'));
        $this->assertSame([], $this->buscar('mono ruiz'));
    }

    public function test_doc26_uhc05_ficha_exacta_sin_guiones(): void
    {
        $juan = $this->juan();
        // Guardada sin guion: «F-120» la encuentra igual (PA-20).
        $otra = $this->ficha($this->basica, ['numero_ficha' => 'A120']);

        foreach (['F120', 'f-120', ' F--120 ', 'F-120'] as $texto) {
            $this->assertSame([$juan->id], $this->buscar($texto), $texto);
        }
        $this->assertSame([$otra->id], $this->buscar('A-120'));
        // Exacta: ni el comienzo ni el final.
        $this->assertSame([], $this->buscar('F12'));
        $this->assertSame([], $this->buscar('F1200'));
        $this->assertSame([], $this->buscar('120'));
    }

    public function test_doc26_uhc05_ficha_reasignada_solo_trae_a_quien_la_tiene_vigente(): void
    {
        $antes = $this->ficha($this->basica, ['primer_nombre' => 'Luis', 'numero_documento' => '70000011', 'numero_ficha' => 'K-1']);
        $antes->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-7']);
        $ahora = $this->ficha($this->estabilizacion, ['primer_nombre' => 'Marta', 'numero_documento' => '70000022', 'numero_ficha' => 'F7']);

        // Alguien la tiene vigente: solo esa persona.
        $this->assertSame([$ahora->id], $this->buscar('F-7'));

        // Nadie la tiene: sale la coincidencia anterior, marcada.
        $ahora->update(['numero_ficha' => 'M-3']);
        $this->assertSame([$antes->id], $this->buscar('f7'));
        $this->assertSame(IdentidadUsuarioHc::FICHA, UsuarioHc::query()->buscar('f7')->with('identidades')->sole()->coincidenciaAnterior('f7'));

        // Vigente en otra dependencia no cuenta: allí es otra ficha (la unicidad es por dependencia).
        $this->ficha($this->otraBasica, ['numero_documento' => '70000033', 'numero_ficha' => 'F-7']);
        $this->assertSame([$antes->id], $this->buscar('F7'));
    }

    public function test_doc26_uhc05_termino_vacio_no_filtra(): void
    {
        $juan = $this->juan();
        $rosa = $this->ficha($this->estabilizacion);
        $this->ficha($this->otraBasica, ['numero_documento' => '70000099']);

        foreach (['', '   ', '...', ' - , ', null] as $texto) {
            $this->assertSame([$juan->id, $rosa->id], $this->buscar($texto), var_export($texto, true));
        }
        $this->assertNull($juan->coincidenciaAnterior(''));
    }

    public function test_doc26_uhc05_otra_dependencia_nunca_aparece_ni_por_un_dato_anterior(): void
    {
        $juan = $this->juan();
        $ajena = $this->ficha($this->otraBasica, [
            'primer_nombre' => 'Juan', 'primer_apellido' => 'Peña', 'alias' => 'Mono',
            'numero_documento' => '1023456789', 'numero_ficha' => 'F-120',
        ]);
        $ajena->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Pedro Gómez']);
        $ajena->identidades()->create(['campo' => IdentidadUsuarioHc::DOCUMENTO, 'valor' => '998877']);
        $ajena->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'Z-9']);

        foreach (['juan pena', 'mono', '23456', 'F120'] as $texto) {
            $this->assertSame([$juan->id], $this->buscar($texto), $texto);
        }
        foreach (['pedro gomez', '998877', 'Z9'] as $texto) {
            $this->assertSame([], $this->buscar($texto), $texto);
        }
    }

    public function test_doc26_uhc05_los_comodines_de_like_no_son_comodines(): void
    {
        $doce = $this->ficha($this->basica, ['numero_documento' => '12345']);
        $otro = $this->ficha($this->basica, ['numero_documento' => '991245']);

        // Como comodines, «12%45» y «12_45» traerían 12345; normalizados son «1245».
        foreach (['12%45', '12_45', '12\\45'] as $texto) {
            $this->assertSame([$otro->id], $this->buscar($texto), $texto);
        }
        // «%» o «_» solos quedan vacíos: no filtran (como el término vacío), no buscan «todo lo que tenga algo».
        $this->assertSame([$doce->id, $otro->id], $this->buscar('%'));
        $this->assertSame([$doce->id, $otro->id], $this->buscar('_'));
    }

    /**
     * Un texto pegado con cientos de palabras armaba un LIKE por palabra y la
     * consulta caía (SQLite: «Expression tree is too large»). Ahora se recorta
     * a BUSQUEDA_MAX_CARACTERES y BUSQUEDA_MAX_PALABRAS: lo que sobra no cuenta.
     */
    public function test_doc26_uhc05_un_texto_largo_se_recorta_y_no_rompe_la_busqueda(): void
    {
        $rosa = $this->ficha($this->basica);

        // 6 000 caracteres: la «zzz» del final queda fuera del recorte.
        $this->assertSame([$rosa->id], $this->buscar(trim(str_repeat('rosa ', 1200)).' zzz'));
        // Más de diez palabras distintas: cuentan las diez primeras.
        $this->assertSame([], $this->buscar('a b c d e f g h i zzz rosa'));
        $this->assertSame([$rosa->id], $this->buscar('rosa velez r o s a v e l z'));
    }

    /**
     * Sin contexto (consola, colas, un comando de DOC-27), la ficha vigente
     * de otra dependencia no esconde la anterior de esta: la ficha es única
     * por dependencia (spec §2). La regla se mira en la consulta, por fila.
     */
    public function test_doc26_uhc05_sin_contexto_la_ficha_vigente_de_otra_dependencia_no_esconde_la_anterior(): void
    {
        $luis = $this->ficha($this->basica, ['primer_nombre' => 'Luis', 'numero_documento' => '70000011', 'numero_ficha' => 'K-1']);
        $luis->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F-7']);
        $this->ficha($this->otraBasica, ['numero_documento' => '70000033', 'numero_ficha' => 'F-7']);

        app(ContextoDependencia::class)->establecer(null);

        $ids = UsuarioHc::query()->where('usuarios_hc.dependencia_id', $this->dependencia->id)
            ->buscar('F7')->pluck('usuarios_hc.id')->all();

        $this->assertSame([$luis->id], $ids);
    }

    public function test_doc26_uhc05_sin_marca_si_casa_con_lo_vigente_aunque_tambien_con_lo_anterior(): void
    {
        $juan = $this->juan();
        $juan->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Juan Gómez']);
        $juan->identidades()->create(['campo' => IdentidadUsuarioHc::DOCUMENTO, 'valor' => '1023456780']);
        $juan->identidades()->create(['campo' => IdentidadUsuarioHc::FICHA, 'valor' => 'F120']);

        foreach (['juan', '102345678', 'f-120'] as $texto) {
            $fila = UsuarioHc::query()->buscar($texto)->with('identidades')->sole();
            $this->assertNull($fila->coincidenciaAnterior($texto), $texto);
        }
        // Palabras repartidas entre el nombre vigente y el anterior: no casa con ninguno.
        $this->assertSame([], $this->buscar('pena gomez'));
    }

    public function test_doc26_uhc05_la_marca_no_trae_el_valor_anterior(): void
    {
        $juan = $this->ficha($this->basica, ['primer_nombre' => 'Juan']);
        $juan->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Pedro Gómez']);
        $juan->identidades()->create(['campo' => IdentidadUsuarioHc::NOMBRE, 'valor' => 'Pedro Gómez Ruiz']);

        // Dos identidades que casan no duplican la fila.
        $this->assertSame([$juan->id], $this->buscar('pedro'));

        $marca = UsuarioHc::query()->buscar('pedro')->with('identidades')->sole()->coincidenciaAnterior('pedro');
        $this->assertSame(IdentidadUsuarioHc::NOMBRE, $marca);
        $this->assertStringNotContainsStringIgnoringCase('pedro', (string) $marca);
    }

    /** «ñ» descompuesta (n + tilde combinable, pegada desde otro equipo) casa igual. */
    public function test_doc26_uhc05_enie_descompuesta_casa_igual(): void
    {
        $pena = $this->ficha($this->basica, ['primer_nombre' => 'Ana', 'primer_apellido' => "Pen\u{0303}a"]);

        $this->assertSame([$pena->id], $this->buscar('Peña'));
        $this->assertSame([$pena->id], $this->buscar('pena'));
    }

    /**
     * Lo que dice el plan D5: un texto que al normalizar queda vacío no
     * filtra, también con escritura no latina, emojis o dígitos de ancho
     * completo. Candidato: mostrar «sin resultados» en ese caso.
     */
    public function test_doc26_uhc05_texto_no_latino_queda_vacio_y_no_filtra(): void
    {
        $rosa = $this->ficha($this->basica);
        $luis = $this->ficha($this->basica, ['primer_nombre' => 'Luis', 'numero_documento' => '123']);

        foreach (['王芳', '😀', '１２３'] as $texto) {
            $this->assertSame([$rosa->id, $luis->id], $this->buscar($texto), $texto);
        }
    }
}
