<?php

namespace Tests\Feature\UsuariosHc;

use App\Models\UsuarioHc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** DOC-26 (UHC-03, plan D8): listas cortas en la configuración, CAMPOS, opciones y excluyentes. */
class UsuariosHcListasTest extends TestCase
{
    use RefreshDatabase;

    /** Una lista cualquiera, armada aquí: nada de CAMPOS ni de la ficha (DOC-27 usa lo mismo). */
    private const LISTA = [
        'opciones' => ['rojo' => 'Rojo', 'verde' => 'Verde', 'azul' => 'Azul', 'gris' => 'Gris', 'nada' => 'Nada', 'no_sabe' => 'No sabe'],
        'retiradas' => ['azul', 'gris'],
        'excluyentes' => ['nada', 'no_sabe'],
    ];

    public function test_doc26_ca18_opciones_y_excluyentes_funcionan_con_cualquier_lista(): void
    {
        // Sin nada guardado: solo las vigentes, en el orden de la configuración.
        $this->assertSame(
            ['rojo' => 'Rojo', 'verde' => 'Verde', 'nada' => 'Nada', 'no_sabe' => 'No sabe'],
            UsuarioHc::opciones(self::LISTA),
        );

        // El registro tiene una retirada, una vigente renombrada después y una
        // que ya ni está en la configuración: cada una con su texto guardado.
        $this->assertSame(
            ['rojo' => 'Rojo', 'verde' => 'Verde claro', 'azul' => 'Azul cielo (retirada)', 'nada' => 'Nada', 'no_sabe' => 'No sabe', 'morado' => 'Morado (retirada)'],
            UsuarioHc::opciones(self::LISTA, ['morado' => 'Morado', 'azul' => 'Azul cielo', 'verde' => 'Verde claro']),
        );

        // Lista sin 'retiradas' ni 'excluyentes', y códigos numéricos (como las comunas).
        $comunas = ['opciones' => [1 => 'Popular', 2 => 'Santa Cruz']];
        $this->assertSame([1 => 'Popular', 2 => 'Santa Cruz'], UsuarioHc::opciones($comunas));
        $this->assertSame([1 => 'Popular', 2 => 'Santa Cruz', 99 => 'Vieja (retirada)'], UsuarioHc::opciones($comunas, ['99' => 'Vieja']));
        $this->assertFalse(UsuarioHc::chocanExcluyentes($comunas, [1, 2]));

        // Excluyentes: con cualquier otra, también entre sí; sola o repetida, no.
        $this->assertTrue(UsuarioHc::chocanExcluyentes(self::LISTA, ['nada', 'rojo']));
        $this->assertTrue(UsuarioHc::chocanExcluyentes(self::LISTA, ['nada', 'no_sabe']));
        $this->assertFalse(UsuarioHc::chocanExcluyentes(self::LISTA, ['nada']));
        $this->assertFalse(UsuarioHc::chocanExcluyentes(self::LISTA, ['nada', 'nada']));
        $this->assertFalse(UsuarioHc::chocanExcluyentes(self::LISTA, ['rojo', 'verde', 'azul']));
        $this->assertFalse(UsuarioHc::chocanExcluyentes(self::LISTA, []));
        // Un vacío del formulario ('' o null) no es «otra opción» (QA).
        $this->assertFalse(UsuarioHc::chocanExcluyentes(self::LISTA, ['nada', '']));
        $this->assertFalse(UsuarioHc::chocanExcluyentes(self::LISTA, ['nada', null]));
    }

    /** Si alguien guarda el texto que se mostró, « (retirada)» no se acumula (QA). */
    public function test_doc26_uhc03_la_marca_de_retirada_no_se_acumula(): void
    {
        $primera = UsuarioHc::opciones(self::LISTA, ['azul' => 'Azul']);
        $this->assertSame('Azul (retirada)', $primera['azul']);

        $this->assertSame('Azul (retirada)', UsuarioHc::opciones(self::LISTA, ['azul' => $primera['azul']])['azul']);
        $this->assertSame('Morado (retirada)', UsuarioHc::opciones(self::LISTA, ['morado' => 'Morado (retirada)'])['morado']);
    }

    /** Las comunas (lista plana, claves numéricas) envueltas en 'opciones'; el código guardado llega como texto. */
    public function test_doc26_uhc03_comunas_con_codigos_que_llegan_como_texto(): void
    {
        $lista = ['opciones' => config('repositorio.comunas'), 'retiradas' => [16]];
        $resultado = UsuarioHc::opciones($lista, ['16' => 'Belén']);

        $this->assertSame('Belén (retirada)', $resultado[16]);
        $this->assertSame(array_keys(config('repositorio.comunas')), array_keys($resultado));
        $this->assertFalse(UsuarioHc::chocanExcluyentes(['opciones' => [1 => 'a'], 'excluyentes' => [1]], ['1', 1]));
        $this->assertTrue(UsuarioHc::chocanExcluyentes(['opciones' => [1 => 'a', 2 => 'b'], 'excluyentes' => [1]], ['1', 2]));
    }

    public function test_doc26_uhc03_un_tipo_retirado_de_la_configuracion_no_se_ofrece_salvo_a_quien_lo_tiene(): void
    {
        config(['repositorio.usuarios_hc.tipos_documento.retiradas' => ['PEP']]);
        $tipos = config('repositorio.usuarios_hc.tipos_documento');

        $this->assertArrayNotHasKey('PEP', UsuarioHc::opciones($tipos));
        $this->assertSame('Permiso Especial de Permanencia (retirada)', UsuarioHc::opciones($tipos, ['PEP' => 'Permiso Especial de Permanencia'])['PEP']);
    }

    public function test_doc26_uhc03_la_configuracion_real_tiene_sus_excluyentes(): void
    {
        $sustancias = config('repositorio.usuarios_hc.sustancias');
        $discapacidad = config('repositorio.usuarios_hc.discapacidad');

        $this->assertTrue(UsuarioHc::chocanExcluyentes($sustancias, ['no_consume', 'alcohol']));
        $this->assertFalse(UsuarioHc::chocanExcluyentes($sustancias, ['alcohol', 'tabaco']));
        $this->assertTrue(UsuarioHc::chocanExcluyentes($discapacidad, ['ninguna', 'sin_informacion']));
        $this->assertTrue(UsuarioHc::chocanExcluyentes($discapacidad, ['sin_informacion', 'visual']));
        $this->assertFalse(UsuarioHc::chocanExcluyentes($discapacidad, ['fisica', 'visual']));
    }

    /** Los textos de la spec §2 y §3, tal cual y en su orden. */
    public function test_doc26_uhc03_la_configuracion_tiene_las_listas_de_la_spec_con_sus_textos(): void
    {
        $esperado = [
            'tipos_documento' => ['Cédula de ciudadanía', 'Tarjeta de identidad', 'Cédula de extranjería', 'Permiso por Protección Temporal', 'Permiso Especial de Permanencia', 'Pasaporte', 'Registro civil', 'Sin documento', 'Otro'],
            'si_no' => ['Sí', 'No'],
            'escolaridad' => ['Primaria', 'Secundaria', 'Bachiller', 'Técnico', 'Tecnólogo', 'Profesional', 'Ninguno'],
            'sustancias' => ['Alcohol', 'Basuco', 'Cocaína', 'Heroína', 'Marihuana', 'Sacol', 'Tabaco', 'Tusi', 'Perico', 'No consume'],
            'nivel_apoyo' => ['Sin red identificada', 'Red identificada, sin apoyo disponible', 'Contacto y apoyo emocional', 'Apoyo práctico limitado', 'Apoyo práctico sostenido', 'Vinculación no recomendable actualmente', 'Pendiente de valoración'],
            'clasificacion' => ['Habitante en calle', 'Habitante de calle', 'Persona en riesgo de calle', 'Sin clasificación', 'Matrícula en resocialización', 'Pendiente de valoración'],
            'sexo' => ['Femenino', 'Masculino', 'No binario', 'Transgénero'],
            'orientacion' => ['Heterosexual', 'Homosexual', 'Bisexual', 'Pansexual', 'Asexual', 'Otra', 'Prefiere no responder'],
            'eps' => ['Asmet', 'Asociación Indígena del Cauca', 'Emsanar', 'Capital Salud', 'Coosalud', 'Nueva EPS', 'Sanitas', 'Salud Total', 'Savia Salud', 'Sura', 'Sin afiliación'],
            'discapacidad' => ['Física', 'Auditiva', 'Visual', 'Sordoceguera', 'Intelectual / cognitiva', 'Psicosocial (mental)', 'Ninguna', 'Sin información'],
            'autonomia' => ['Autónomo', 'Heterónomo'],
            'independencia' => ['Independiente', 'Requiere supervisión', 'Requiere ayuda parcial', 'Requiere ayuda total', 'Pendiente de valoración'],
        ];

        $listas = config('repositorio.usuarios_hc');
        $this->assertEqualsCanonicalizing(array_keys($esperado), array_keys($listas));

        foreach ($esperado as $nombre => $textos) {
            $this->assertSame($textos, array_values($listas[$nombre]['opciones']), $nombre);
            // Al empezar no hay nada retirado.
            $this->assertSame([], $listas[$nombre]['retiradas'], $nombre);
        }

        $this->assertSame(['F', 'M', 'NB', 'T'], array_keys($listas['sexo']['opciones']));
        $this->assertSame(['no_consume'], $listas['sustancias']['excluyentes']);
        $this->assertSame(['ninguna', 'sin_informacion'], $listas['discapacidad']['excluyentes']);
    }

    public function test_doc26_uhc03_sin_documento_es_sd_en_tipos_de_documento(): void
    {
        $tipos = config('repositorio.usuarios_hc.tipos_documento.opciones');

        $this->assertSame('SD', UsuarioHc::SIN_DOCUMENTO);
        $this->assertSame('Sin documento', $tipos[UsuarioHc::SIN_DOCUMENTO]);
        $this->assertSame(['CC', 'TI', 'CE', 'PPT', 'PEP', 'PAS', 'RC', 'SD', 'OTRO'], array_keys($tipos));
    }

    /** Códigos estables: cortos, sin tildes ni espacios; caben en usuario_hc_respuestas.opcion (50). */
    public function test_doc26_uhc03_codigos_de_excluyentes_y_retiradas_existen_y_son_estables(): void
    {
        foreach (config('repositorio.usuarios_hc') as $nombre => $lista) {
            foreach (array_merge($lista['retiradas'] ?? [], $lista['excluyentes'] ?? []) as $codigo) {
                $this->assertArrayHasKey($codigo, $lista['opciones'], "{$nombre}: {$codigo}");
            }
            foreach (array_keys($lista['opciones']) as $codigo) {
                $this->assertMatchesRegularExpression('/^[A-Za-z0-9_]{1,30}$/', (string) $codigo, "{$nombre}: {$codigo}");
            }
        }
    }

    public function test_doc26_uhc03_estan_los_22_campos_y_cada_uno_apunta_a_una_fuente_que_existe(): void
    {
        // Los 22 campos de lista de la spec §3, en su orden: si falta uno, falla.
        $this->assertSame([
            'escolaridad', 'sustancias', 'red_apoyo', 'nivel_apoyo', 'clasificacion', 'sexo', 'orientacion_sexual',
            'nacionalidad', 'comuna', 'eps', 'listado_censal', 'diagnostico_psiquiatria', 'otros_diagnosticos_psiquiatria',
            'diagnosticos_medicina_general', 'proteccion_infancia', 'discapacidad', 'cedula_fisica', 'portabilidad',
            'codigo_900', 'oxigeno_dependiente', 'autonomia', 'independencia',
        ], array_keys(UsuarioHc::CAMPOS));

        foreach (UsuarioHc::CAMPOS as $campo => $definicion) {
            $this->assertLessThanOrEqual(50, strlen($campo), $campo);
            $this->assertNotEmpty($definicion['etiqueta'], $campo);
            $this->assertContains($definicion['tipo'], ['una', 'varias', 'varias_con_principal'], $campo);

            [$origen, $nombre] = array_pad(explode(':', $definicion['fuente'], 2), 2, null);
            match ($origen) {
                'config' => $this->assertNotEmpty(config("repositorio.usuarios_hc.{$nombre}.opciones"), $campo),
                'comunas' => $this->assertNotEmpty(config('repositorio.comunas'), $campo),
                'catalogo' => $this->assertTrue(Schema::hasTable("catalogo_{$nombre}"), $campo),
            };
        }

        // Las fechas y el tiempo en calle son columnas, no listas.
        foreach (['fecha_nacimiento', 'fecha_ingreso_sistema', 'calle_anios', 'calle_meses', 'calle_semanas'] as $columna) {
            $this->assertArrayNotHasKey($columna, UsuarioHc::CAMPOS);
        }
        // Solo medicina general marca principal (PA-08).
        $this->assertSame(['diagnosticos_medicina_general'], array_keys(array_filter(UsuarioHc::CAMPOS, fn ($c) => $c['tipo'] === 'varias_con_principal')));
        $this->assertSame('varias', UsuarioHc::CAMPOS['otros_diagnosticos_psiquiatria']['tipo']);
    }
}
