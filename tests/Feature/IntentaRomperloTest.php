<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Los seis puntos donde el autor del código sospecha que puede fallar.
 *
 * Estas pruebas afirman el comportamiento DESEADO, no el observado. Si
 * alguna se pone roja, la respuesta correcta es leer el mensaje y decidir
 * qué se arregla en la aplicación, no relajar la aserción.
 */
class IntentaRomperloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
    }

    /*
    |--------------------------------------------------------------------------
    | 1. UsuarioController@store usa firstOrNew por correo
    |--------------------------------------------------------------------------
    */

    /** @return array{0: User, 1: Dependencia, 2: Dependencia, 3: User} */
    private function escenarioDeDosDependencias(): array
    {
        $alfa = Dependencia::factory()->create(['nombre' => 'Alfa', 'slug' => 'alfa']);
        $beta = Dependencia::factory()->create(['nombre' => 'Beta', 'slug' => 'beta']);

        $deBeta = User::factory()->create([
            'name' => 'Ana Ramírez',
            'documento' => '1234567890',
            'email' => 'ana@medellin.gov.co',
            'cargo' => 'Coordinadora en Beta',
        ]);

        $this->darRol($deBeta, RolDependencia::Administracion, $beta);

        return [$deBeta, $alfa, $beta, $this->usuarioCon(RolDependencia::Administracion, $alfa)];
    }

    private function datosDeAlta(string $documento): array
    {
        return [
            'name' => 'Nombre puesto desde Alfa',
            'documento' => $documento,
            'email' => 'otro@medellin.gov.co',
            'cargo' => 'Auxiliar',
            'rol' => RolDependencia::Lectura->value,
            'componente_id' => Componente::withoutGlobalScopes()->where('dependencia_id', Dependencia::where('slug', 'alfa')->value('id'))->value('id'),
            'activo' => 0,
            'password' => 'ClavePuesta123',
            'password_confirmation' => 'ClavePuesta123',
        ];
    }

    /**
     * El alta de un documento que ya existe sí llega al controlador, así que
     * esta prueba recorre de verdad el firstOrCreate. Si alguien volviera a
     * un firstOrNew con fill(), aquí se vería: un administrador de Alfa no
     * puede tocarle a nadie de Beta el nombre, el cargo, el estado ni la
     * contraseña con la excusa de darle acceso.
     */
    public function test_dar_acceso_a_un_documento_que_ya_existe_no_debe_pisar_la_cuenta_de_la_otra_dependencia(): void
    {
        [$deBeta, , $beta, $adminAlfa] = $this->escenarioDeDosDependencias();

        $passwordOriginal = $deBeta->password;

        $this->actingAs($adminAlfa)
            ->post(route('admin.usuarios.store'), $this->datosDeAlta('1234567890'));

        $deBeta->refresh();

        $dano = [];

        if ($deBeta->name !== 'Ana Ramírez') {
            $dano[] = 'le cambió el nombre a «'.$deBeta->name.'»';
        }

        if ($deBeta->cargo !== 'Coordinadora en Beta') {
            $dano[] = 'le cambió el cargo a «'.$deBeta->cargo.'»';
        }

        if ($deBeta->activo !== true) {
            $dano[] = 'la desactivó en toda la plataforma, también en Beta';
        }

        if ($deBeta->password !== $passwordOriginal) {
            $dano[] = 'le cambió la contraseña';
        }

        $this->assertSame(
            [],
            $dano,
            'Un administrador de Alfa modificó la cuenta de alguien de Beta con solo darle acceso: '
            .implode('; ', $dano),
        );

        // Y su rol en Beta sigue intacto.
        $this->assertSame(RolDependencia::Administracion, $deBeta->rolEn($beta));
    }

    /**
     * El propio formulario lo promete: «Si la persona ya tiene cuenta en otra
     * dependencia, se le suma el acceso a esta». Y el controlador está escrito
     * para eso, con firstOrNew y syncWithoutDetaching.
     */
    public function test_dar_acceso_a_alguien_que_ya_tiene_cuenta_en_otra_dependencia_debe_funcionar(): void
    {
        [$deBeta, $alfa, $beta, $adminAlfa] = $this->escenarioDeDosDependencias();

        $this->actingAs($adminAlfa)
            ->post(route('admin.usuarios.store'), $this->datosDeAlta('1234567890'));

        $errores = session('errors')?->getBag('default')?->keys() ?? [];

        $this->assertSame(
            [],
            $errores,
            'El alta se rechazó por '.implode(', ', $errores).'. GuardarUsuarioRequest aplica '
            .'unique:users,documento también al dar de alta: ahí $this->route(\'usuario\') es null, '
            .'así que el ->ignore() solo surte efecto al editar. Con eso, firstOrCreate y '
            .'syncWithoutDetaching del controlador nunca llegan a ejecutarse, y la promesa del '
            .'formulario («se le suma el acceso a esta») no se cumple.',
        );

        $deBeta->refresh();

        $this->assertSame(RolDependencia::Lectura, $deBeta->rolEn($alfa), 'No quedó con acceso a Alfa.');
        $this->assertSame(RolDependencia::Administracion, $deBeta->rolEn($beta), 'Perdió el acceso a Beta.');
    }

    /**
     * El alta ya no exige que el documento sea único, pero la edición sí: es
     * lo que impide que un administrador se quede con el documento de otra
     * cuenta y acabe suplantándola al iniciar sesión, que ahora es justo lo
     * que se teclea para entrar.
     */
    public function test_al_editar_no_se_puede_quedar_con_el_documento_de_otra_cuenta(): void
    {
        [$deBeta, $alfa, , $adminAlfa] = $this->escenarioDeDosDependencias();

        $companero = $this->usuarioCon(RolDependencia::Lectura, $alfa);
        $documentoOriginal = $companero->documento;

        $this->actingAs($adminAlfa)
            ->put(route('admin.usuarios.update', $companero), [
                'name' => $companero->name,
                'documento' => '1234567890',
                'email' => $companero->email,
                'rol' => RolDependencia::Lectura->value,
                'componente_id' => $companero->componenteEn($alfa),
                'activo' => 1,
            ])
            ->assertSessionHasErrors('documento');

        $this->assertSame($documentoOriginal, $companero->fresh()->documento);
        $this->assertSame('1234567890', $deBeta->fresh()->documento);

        // Y conservar el suyo al editar sigue siendo legítimo.
        $this->actingAs($adminAlfa)
            ->put(route('admin.usuarios.update', $companero), [
                'name' => 'Nombre corregido',
                'documento' => $documentoOriginal,
                'email' => $companero->email,
                'rol' => RolDependencia::Lectura->value,
                'componente_id' => $companero->componenteEn($alfa),
                'activo' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Nombre corregido', $companero->fresh()->name);
    }

    public function test_dar_de_alta_a_alguien_nuevo_sigue_funcionando(): void
    {
        [, $alfa, , $adminAlfa] = $this->escenarioDeDosDependencias();

        $this->actingAs($adminAlfa)
            ->post(route('admin.usuarios.store'), $this->datosDeAlta('9876543210'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $nueva = User::where('documento', '9876543210')->firstOrFail();

        $this->assertSame('Nombre puesto desde Alfa', $nueva->name);
        $this->assertSame(RolDependencia::Lectura, $nueva->rolEn($alfa));
    }

    /**
     * Los puntos del documento se quitan al guardar. Si no, «1.234.567» y
     * «1234567» serían dos cuentas de la misma persona y el índice único no
     * se enteraría.
     */
    public function test_el_documento_se_guarda_sin_puntos_ni_espacios(): void
    {
        [, $alfa, , $adminAlfa] = $this->escenarioDeDosDependencias();

        $this->actingAs($adminAlfa)
            ->post(route('admin.usuarios.store'), $this->datosDeAlta('98.765.432'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['documento' => '98765432']);
        $this->assertDatabaseMissing('users', ['documento' => '98.765.432']);

        // Y volver a darlo de alta escrito de otra forma reconoce la cuenta
        // en vez de crear una segunda.
        $this->actingAs($adminAlfa)
            ->post(route('admin.usuarios.store'), $this->datosDeAlta('98 765 432'));

        $this->assertSame(1, User::where('documento', '98765432')->count());
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Documento::visiblesPara() con el contexto vacío
    |--------------------------------------------------------------------------
    */

    public function test_visibles_para_con_el_contexto_vacio_devuelve_solo_los_activos(): void
    {
        $dependencia = Dependencia::factory()->create();
        $admin = $this->usuarioCon(RolDependencia::Administracion, $dependencia);

        Documento::factory()->create(['dependencia_id' => $dependencia->id, 'nombre' => 'Activo']);
        Documento::factory()->inactivo()->create(['dependencia_id' => $dependencia->id, 'nombre' => 'Inactivo']);

        $this->assertNull(app(ContextoDependencia::class)->id(), 'La prueba no está probando lo que cree.');

        // Sin contexto, puedeAdministrarEn(null) es false hasta para quien
        // administra. Lo conservador es esconder los inactivos; lo peligroso
        // sería lo contrario.
        foreach (['un administrador' => $admin, 'nadie' => null] as $quien => $usuario) {
            $this->assertSame(
                ['Activo'],
                Documento::visiblesPara($usuario)->orderBy('id')->pluck('nombre')->all(),
                "Con el contexto vacío y $quien, se filtraron documentos inactivos.",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Mover una carpeta dentro de su propia descendencia
    |--------------------------------------------------------------------------
    */

    public function test_no_se_puede_mover_una_carpeta_dentro_de_su_propia_descendencia(): void
    {
        $dependencia = Dependencia::factory()->create();
        $editor = $this->usuarioCon(RolDependencia::Edicion, $dependencia);

        $n1 = Carpeta::factory()->raiz()->create(['dependencia_id' => $dependencia->id, 'nombre' => 'Nivel 1']);
        $n2 = Carpeta::factory()->dentroDe($n1)->create(['nombre' => 'Nivel 2']);
        $n3 = Carpeta::factory()->dentroDe($n2)->create(['nombre' => 'Nivel 3']);
        $n4 = Carpeta::factory()->dentroDe($n3)->create(['nombre' => 'Nivel 4']);

        $intentos = [
            'la raíz dentro de su bisnieta' => [$n1, $n4],
            'la raíz dentro de su nieta' => [$n1, $n3],
            'la raíz dentro de su hija' => [$n1, $n2],
            'el nivel 2 dentro de su nieta' => [$n2, $n4],
            'una carpeta dentro de sí misma' => [$n2, $n2],
        ];

        $colados = [];

        foreach ($intentos as $descripcion => [$carpeta, $destino]) {
            $padreAntes = $carpeta->fresh()->carpeta_id;

            $this->actingAs($editor)->put(route('carpetas.update', $carpeta), [
                'nombre' => $carpeta->nombre,
                'carpeta_id' => $destino->id,
            ]);

            if ($carpeta->fresh()->carpeta_id !== $padreAntes) {
                $colados[] = $descripcion;
            }
        }

        $this->assertSame([], $colados, 'Se coló un movimiento que deja el árbol en ciclo: '.implode(', ', $colados));

        // Y un movimiento legítimo sí pasa, para que la prueba no esté en
        // verde solo porque nada se puede mover nunca.
        $this->actingAs($editor)
            ->put(route('carpetas.update', $n3), ['nombre' => 'Nivel 3'])
            ->assertRedirect();

        $this->assertNull($n3->fresh()->carpeta_id);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. La previsualización sirve contenido del usuario en el navegador
    |--------------------------------------------------------------------------
    */

    public function test_renombrar_un_html_o_un_svg_a_jpg_no_burla_la_validacion(): void
    {
        $dependencia = Dependencia::factory()->create();
        $editor = $this->usuarioCon(RolDependencia::Edicion, $dependencia);

        $intentos = [
            'HTML disfrazado' => $this->archivo(
                'foto.jpg',
                '<html><body><script>alert(document.cookie)</script></body></html>',
                'image/jpeg',
            ),
            'SVG disfrazado' => $this->archivo(
                'foto.jpg',
                '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                'image/jpeg',
            ),
        ];

        foreach ($intentos as $descripcion => $archivo) {
            $this->actingAs($editor)
                ->post(route('documentos.store'), ['nombre' => $descripcion, 'archivo' => $archivo])
                ->assertSessionHasErrors('archivo.0');
        }

        // La validación mira el contenido, no la extensión ni el mime que
        // declara el navegador: ninguno de los dos llegó a disco.
        $this->assertDatabaseCount('documentos', 0);
        $this->assertEmpty(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_la_previsualizacion_no_deja_que_el_navegador_ejecute_el_archivo(): void
    {
        $dependencia = Dependencia::factory()->create();
        $lector = $this->usuarioCon(RolDependencia::Lectura, $dependencia);

        $documento = Documento::factory()->create(['dependencia_id' => $dependencia->id]);

        // Suponiendo que un archivo así entrara por otra vía (una migración,
        // un cambio de configuración), la segunda barrera tiene que aguantar.
        DocumentoVersion::factory()
            ->tipo('image/svg+xml', 'svg', 'grafico.svg')
            ->conArchivoEnDisco('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')
            ->create(['documento_id' => $documento->id]);

        $respuesta = $this->actingAs($lector)->get(route('documentos.previsualizar', $documento));

        $respuesta->assertOk();

        // Se sirve con el tipo detectado al subir, y el navegador no puede
        // adivinar otro distinto.
        $this->assertStringStartsWith('image/svg+xml', $respuesta->headers->get('content-type'));
        $respuesta->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringStartsWith('inline', $respuesta->headers->get('content-disposition'));

        $csp = $respuesta->headers->get('content-security-policy');

        $this->assertNotNull($csp, 'Se sirve contenido del usuario sin Content-Security-Policy.');
        $this->assertStringContainsString("default-src 'none'", $csp);

        // Sin script-src declarado, los scripts caen en default-src 'none'.
        // Declarar uno permisivo aquí sería abrir la puerta.
        $this->assertStringNotContainsString('script-src', $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Los uuid en las URLs
    |--------------------------------------------------------------------------
    */

    public function test_ninguna_ruta_acepta_el_id_numerico_en_lugar_del_uuid(): void
    {
        $dependencia = Dependencia::factory()->create();
        $admin = $this->usuarioCon(RolDependencia::Administracion, $dependencia);

        $documento = Documento::factory()->create(['dependencia_id' => $dependencia->id]);
        DocumentoVersion::factory()->conArchivoEnDisco()->create(['documento_id' => $documento->id]);

        $carpeta = Carpeta::factory()->create(['dependencia_id' => $dependencia->id]);

        $rutas = [
            ['get', '/documentos/'.$documento->id],
            ['get', '/documentos/'.$documento->id.'/editar'],
            ['get', '/documentos/'.$documento->id.'/descargar'],
            ['get', '/documentos/'.$documento->id.'/previsualizar'],
            ['put', '/documentos/'.$documento->id],
            ['post', '/documentos/'.$documento->id.'/versiones'],
            ['patch', '/documentos/'.$documento->id.'/inactivar'],
            ['patch', '/documentos/'.$documento->id.'/reactivar'],
            ['get', '/carpetas/'.$carpeta->id.'/editar'],
            ['put', '/carpetas/'.$carpeta->id],
            ['patch', '/carpetas/'.$carpeta->id.'/inactivar'],
        ];

        $colados = [];

        foreach ($rutas as [$metodo, $url]) {
            $estado = $this->actingAs($admin)->{$metodo}($url)->status();

            if ($estado !== 404) {
                $colados[] = strtoupper($metodo).' '.$url.' → '.$estado;
            }
        }

        $this->assertSame([], $colados, "Rutas que resuelven con el id numérico:\n".implode("\n", $colados));

        // Con el uuid sí resuelve: la prueba no está en verde por accidente.
        $this->actingAs($admin)->get(route('documentos.show', $documento))->assertOk();
        $this->assertNotSame((string) $documento->id, $documento->uuid);
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Borrar un usuario que subió documentos
    |--------------------------------------------------------------------------
    */

    public function test_borrar_un_usuario_no_se_lleva_por_delante_sus_documentos(): void
    {
        $dependencia = Dependencia::factory()->create();
        $autor = $this->usuarioCon(RolDependencia::Edicion, $dependencia);

        $documento = Documento::factory()->creadoPor($autor)->create(['dependencia_id' => $dependencia->id]);
        $version = DocumentoVersion::factory()->subidaPor($autor)->create(['documento_id' => $documento->id]);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $dependencia->id, 'creado_por' => $autor->id]);

        $autor->delete();

        $this->assertDatabaseMissing('users', ['id' => $autor->id]);

        // El rastro documental sobrevive, huérfano pero entero: es justo lo
        // que pide un repositorio con vocación de archivo.
        $this->assertDatabaseHas('documentos', ['id' => $documento->id]);
        $this->assertNull($documento->fresh()->creado_por, 'El documento se fue con el usuario o conservó un id muerto.');
        $this->assertNull($documento->fresh()->actualizado_por);

        $this->assertDatabaseHas('documento_versiones', ['id' => $version->id]);
        $this->assertNull($version->fresh()->subido_por);

        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id]);
        $this->assertNull($carpeta->fresh()->creado_por);

        // Lo único que sí desaparece es su acceso a la dependencia.
        $this->assertDatabaseMissing('dependencia_usuario', ['user_id' => $autor->id]);
    }
}
