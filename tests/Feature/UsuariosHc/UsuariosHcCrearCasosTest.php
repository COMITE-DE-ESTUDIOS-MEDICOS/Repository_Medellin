<?php

namespace Tests\Feature\UsuariosHc;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Models\UsuarioHc;
use Database\Seeders\AccesoPorComponenteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA DOC-26 (UHC-07): casos de borde que demuestran fallos de crear y del aviso.
 * Cada prueba dice en su nombre qué regla de la spec comprueba. Si falla, el
 * hallazgo está en el código de la aplicación, no en la prueba.
 */
class UsuariosHcCrearCasosTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $basica;

    private Componente $estabilizacion;

    private Componente $calle;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->basica = $this->componente('Básica', 'basica', ['usuarios_hc']);
        $this->estabilizacion = $this->componente('Estabilización', 'estabilizacion', ['usuarios_hc']);
        $this->calle = $this->componente('Calle', 'calle', AccesoPorComponenteSeeder::MODULOS_CALLE);
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);
    }

    private function componente(string $nombre, string $slug, array $modulos): Componente
    {
        return Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => $nombre, 'slug' => $slug, 'modulos' => $modulos]);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + ['tipo_documento' => 'CC', 'numero_documento' => '1001', 'primer_nombre' => 'Ana', 'primer_apellido' => 'Gómez'];
    }

    private function crear(array $cambios = [], ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->editor)->post(route('usuarios-hc.store'), $this->datos($cambios));
    }

    /** spec §2: el primer nombre es obligatorio; «provisional» sí, pero no un espacio duro solo. */
    public function test_doc26_uhc07_primer_nombre_solo_con_espacio_duro_se_rechaza(): void
    {
        $this->crear(['primer_nombre' => "\u{00A0}\u{00A0}"])->assertSessionHasErrors('primer_nombre');
        $this->assertSame(0, UsuarioHc::count());
    }

    /**
     * spec §1 y CA-07: el aviso busca entre todas las fichas de la dependencia. La consulta trae
     * 100 candidatas ordenadas por nombre y recién después filtra; una coincidencia exacta que
     * queda más allá de la 100.ª no aparece en el aviso (al guardar sí bloquea, porque ahí no hay tope).
     */
    public function test_doc26_uhc07_aviso_encuentra_la_coincidencia_exacta_aunque_haya_cien_parecidas(): void
    {
        UsuarioHc::factory()->count(101)->en($this->basica)->create(['primer_nombre' => 'Juan', 'primer_apellido' => 'Perez']);
        $exacta = UsuarioHc::factory()->en($this->basica)->create(['numero_documento' => '99887766', 'primer_nombre' => 'Zoe', 'primer_apellido' => 'Zapata']);

        $this->actingAs($this->editor)
            ->post(route('usuarios-hc.coincidencias'), ['primer_nombre' => 'Juan', 'primer_apellido' => 'Perez', 'numero_documento' => '99887766'])
            ->assertOk()->assertSee('Zoe Zapata');

        $this->assertNotNull($exacta);
        $this->crear(['numero_documento' => '99887766'])->assertSessionHasErrors('numero_documento');
    }

    /**
     * spec §2, documento: «Se guarda y se muestra como se escribió, sin los espacios de los extremos».
     * El código lo guarda en mayúsculas (la prueba CA-08 actual también lo asume). Contradicción
     * entre la spec y el código: hay que preguntarle a Edwar cuál es la regla.
     */
    public function test_doc26_uhc07_documento_se_guarda_como_se_escribio_sin_espacios_de_los_extremos(): void
    {
        $this->crear(['numero_documento' => '  ab0012 '])->assertSessionHasNoErrors();

        $this->assertSame('ab0012', UsuarioHc::firstOrFail()->numero_documento);
    }

    /**
     * Texto larguísimo. «ß» se transliteró a «ss» (Str::ascii) y la columna nombre_busqueda es
     * VARCHAR(500): 4 nombres de 100 caracteres dan 803. SQLite no aplica el tamaño de la columna;
     * MySQL en modo estricto debería rechazar el INSERT (error 1406). No se corrió contra MySQL.
     * El dato debe caber en la columna o rechazarse con un mensaje.
     */
    public function test_doc26_uhc07_campos_de_busqueda_caben_en_sus_columnas(): void
    {
        $largo = str_repeat('ß', 100);

        // El formulario lo rechaza (prueba siguiente). Lo que no pasa por él (consola, factory)
        // se recorta en el modelo para que MySQL no lo rechace.
        $ficha = UsuarioHc::factory()->en($this->basica)->create([
            'tipo_documento' => 'SD', 'numero_documento' => null,
            'primer_nombre' => $largo, 'segundo_nombre' => $largo, 'primer_apellido' => $largo, 'segundo_apellido' => $largo, 'alias' => $largo,
        ]);

        $this->assertLessThanOrEqual(500, mb_strlen((string) $ficha->nombre_busqueda), 'nombre_busqueda no cabe en VARCHAR(500)');
        $this->assertLessThanOrEqual(200, mb_strlen((string) $ficha->alias_busqueda), 'alias_busqueda no cabe en VARCHAR(200)');
    }

    /**
     * Lo que ve el usuario (CLAUDE.md: español claro): el aviso muestra el código del tipo de
     * documento («SD sin número») en vez de su texto («Sin documento»). Además la prueba CA-07
     * existente espera «CC 900» con el código.
     */
    public function test_doc26_uhc07_aviso_muestra_el_tipo_sin_documento_en_texto_claro(): void
    {
        UsuarioHc::factory()->en($this->basica)->sinDocumento()->create(['primer_nombre' => 'Pablo', 'primer_apellido' => 'Rojas']);

        $this->actingAs($this->editor)
            ->post(route('usuarios-hc.coincidencias'), ['primer_nombre' => 'Pablo', 'primer_apellido' => 'Rojas'])
            ->assertOk()->assertSee('Sin documento')->assertDontSee('SD sin número');
    }

    /**
     * Permisos (CLAUDE.md, lecciones y spec §6): un superadministrador parado en un componente sin
     * el módulo (Calle) no crea; parado en Básica sí, y la ficha queda con Básica como registro.
     */
    public function test_doc26_uhc07_superadministrador_crea_solo_parado_en_un_componente_con_el_modulo(): void
    {
        $super = User::factory()->superadmin()->create();

        $this->actingAs($super)->withSession(['componente_id' => $this->calle->id])
            ->post(route('usuarios-hc.store'), $this->datos(['numero_documento' => '31']))->assertForbidden();
        $this->assertSame(0, UsuarioHc::count());

        $this->actingAs($super)->withSession(['componente_id' => $this->basica->id])
            ->post(route('usuarios-hc.store'), $this->datos(['numero_documento' => '32']))->assertSessionHasNoErrors();
        $this->assertSame($this->basica->id, UsuarioHc::where('documento_normalizado', '32')->firstOrFail()->componente_registro_id);
    }

    /**
     * Sin componente en el contexto (el componente del usuario está inactivo): no crea ni por la
     * ruta ni por el aviso. La ficha no puede quedar sin componente de registro (CA-20).
     */
    public function test_doc26_uhc07_sin_componente_activo_no_crea_ni_avisa(): void
    {
        $this->basica->update(['activo' => false]);

        $this->crear(['numero_documento' => '41'])->assertForbidden();
        $this->actingAs($this->editor)->post(route('usuarios-hc.coincidencias'), ['numero_documento' => '41'])->assertForbidden();
        $this->assertSame(0, UsuarioHc::count());
    }

    /**
     * Segunda vuelta (UHC-07 corregido). La consulta por nombre también trae 100 candidatas antes
     * de filtrar en PHP: cien «Juan Carlos Perez» (que PHP descarta, primer nombre distinto)
     * ordenados antes que «Juan Perez» la dejan fuera. Mismo fallo de tope que el de los
     * documentos, en la rama por nombre (spec §1 y CA-07: el aviso mira todas las fichas).
     */
    public function test_doc26_uhc07_aviso_por_nombre_no_pierde_la_persona_tras_cien_parecidas(): void
    {
        UsuarioHc::factory()->count(100)->en($this->basica)->create(['primer_nombre' => 'Juan Carlos', 'primer_apellido' => 'Perez']);
        UsuarioHc::factory()->en($this->basica)->create(['numero_documento' => '77001122', 'primer_nombre' => 'Juan', 'primer_apellido' => 'Perez']);

        $this->actingAs($this->editor)
            ->post(route('usuarios-hc.coincidencias'), ['primer_nombre' => 'Juan', 'primer_apellido' => 'Perez'])
            ->assertOk()->assertSee('Juan Perez')->assertSee('77001122');
    }

    /**
     * Guarda de la consulta nueva (debe seguir pasando): tildes, signos y apellido al final;
     * una ficha de otra dependencia con el mismo nombre no sale; el aviso no muestra el
     * identificación, sin datos de caracterización.
     */
    public function test_doc26_uhc07_aviso_por_nombre_encuentra_tildes_signos_y_apellido_final_sin_cruzar_dependencias(): void
    {
        UsuarioHc::factory()->en($this->basica)->create(['numero_documento' => '6001', 'primer_nombre' => 'José María', 'primer_apellido' => 'Peña-Ruiz']);
        $otra = Dependencia::factory()->create();
        UsuarioHc::factory()->en(Componente::factory()->create(['dependencia_id' => $otra->id, 'modulos' => ['usuarios_hc']]))
            ->create(['numero_documento' => '6002', 'primer_nombre' => 'José María', 'primer_apellido' => 'Peña-Ruiz']);

        $this->actingAs($this->editor)
            ->post(route('usuarios-hc.coincidencias'), ['primer_nombre' => 'jose maria', 'primer_apellido' => 'pena ruiz'])
            ->assertOk()->assertSee('6001')->assertDontSee('6002');
    }

    /**
     * La recorte de nombre_busqueda a 500 (UsuarioHc::booted) no debe tirar el apellido de un nombre
     * largo: buscar el segundo apellido tiene que encontrar a la persona. Con cuatro campos de
     * 100 «ß» (200 caracteres normalizados cada uno) el segundo apellido queda más allá de 500.
     */
    public function test_doc26_uhc07_recortar_la_busqueda_no_pierde_el_segundo_apellido(): void
    {
        // Corregido rechazando: el formulario no guarda un nombre cuya búsqueda no cabe, así
        // que no queda ninguna ficha con el apellido sin encontrarse.
        $largo = str_repeat('ß', 100);
        $this->crear([
            'tipo_documento' => 'SD', 'numero_documento' => null,
            'primer_nombre' => $largo, 'segundo_nombre' => $largo, 'primer_apellido' => $largo, 'segundo_apellido' => 'Zurita',
        ])->assertSessionHasErrors(['primer_nombre' => 'El nombre completo es demasiado largo. Acórtalo.']);
        $this->assertSame(0, UsuarioHc::count());

        // «Щ» se vuelve «shh»: 100 dan 300, más que la columna del alias (200).
        $this->crear(['tipo_documento' => 'SD', 'numero_documento' => null, 'alias' => str_repeat('Щ', 100)])
            ->assertSessionHasErrors(['alias' => 'El alias es demasiado largo. Acórtalo.']);

        // Un nombre largo de verdad (4 campos de 100 sin letras que se alarguen) sí entra, y se
        // encuentra por el último apellido.
        $cien = str_repeat('a', 95).' ';
        $this->crear([
            'tipo_documento' => 'SD', 'numero_documento' => null,
            'primer_nombre' => $cien.'uno', 'segundo_nombre' => $cien.'dos', 'primer_apellido' => $cien.'tres', 'segundo_apellido' => 'Zurita',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, UsuarioHc::buscar('zurita')->count());
    }

    /** Guarda (debe pasar): nombres reales con tildes y ñ, de 100 por campo, entran y se encuentran. */
    public function test_doc26_uhc07_tercera_revision_nombres_reales_de_cien_con_tildes_entran_y_se_encuentran(): void
    {
        $cien = str_repeat('é', 100);
        $this->crear([
            'tipo_documento' => 'SD', 'numero_documento' => null,
            'primer_nombre' => $cien, 'segundo_nombre' => $cien, 'primer_apellido' => $cien, 'segundo_apellido' => 'Ñandú',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, UsuarioHc::buscar('nandu')->count());
    }

    /**
     * Guarda (debe pasar): «ß» en el alias cabe justo en 200 (100 × «ss»); no debe rechazarse.
     * Y una entrada que no es texto (arreglo) no se salta el límite: la regla «string» la rechaza.
     */
    public function test_doc26_uhc07_tercera_revision_alias_justo_en_el_limite_entra_y_los_arreglos_se_rechazan(): void
    {
        $this->crear(['tipo_documento' => 'SD', 'numero_documento' => null, 'alias' => str_repeat('ß', 100)])
            ->assertSessionHasNoErrors();

        foreach (['segundo_nombre', 'primer_apellido', 'alias'] as $campo) {
            $this->crear(['numero_documento' => '77', $campo => ['x', str_repeat('a', 300)]])->assertSessionHasErrors($campo);
        }
        $this->crear(['numero_documento' => '78', 'primer_nombre' => ['Ana']])->assertSessionHasErrors('primer_nombre');
        $this->assertSame(1, UsuarioHc::count());
    }

    /**
     * Guarda (debe pasar): una persona que sale por documento y también por nombre aparece una sola
     * vez en el aviso (no se repite al juntar las dos ramas).
     */
    public function test_doc26_uhc07_tercera_revision_una_ficha_no_se_repite_entre_documento_y_nombre(): void
    {
        UsuarioHc::factory()->en($this->basica)->create(['numero_documento' => '555111', 'primer_nombre' => 'Juan', 'primer_apellido' => 'Perez']);

        $html = $this->actingAs($this->editor)
            ->post(route('usuarios-hc.coincidencias'), ['primer_nombre' => 'Juan', 'primer_apellido' => 'Perez', 'numero_documento' => '555111'])
            ->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '555111'));
    }
}
