<?php

namespace Tests\Feature\UsuariosHc;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Auditoria;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Models\UsuarioHc;
use Database\Seeders\AccesoPorComponenteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** DOC-26 (UHC-07, plan D6): crear una ficha y el aviso de coincidencias. */
class UsuariosHcCrearTest extends TestCase
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

    private function existente(array $atributos = [], ?Componente $componente = null): UsuarioHc
    {
        return UsuarioHc::factory()->en($componente ?? $this->basica)->create($atributos);
    }

    public function test_doc26_ca05_se_crea_sin_documento_con_nombre_provisional(): void
    {
        $this->crear(['tipo_documento' => 'SD', 'numero_documento' => null, 'primer_nombre' => 'Provisional', 'primer_apellido' => null])
            ->assertRedirect(route('usuarios-hc.index'))->assertSessionHas('exito');

        $ficha = UsuarioHc::firstOrFail();
        $this->assertSame('SD', $ficha->tipo_documento);
        $this->assertSame('Sin documento', $ficha->tipo_documento_texto);
        $this->assertNull($ficha->numero_documento);
        $this->assertSame('Provisional', $ficha->nombre_completo);
        $this->assertSame(UsuarioHc::VIVA, $ficha->estado_vital);
        $this->assertSame($this->editor->id, $ficha->creado_por);

        // Dos «Sin documento» no chocan; con un número, se rechaza.
        $this->crear(['tipo_documento' => 'SD', 'numero_documento' => null, 'primer_nombre' => 'Otra'])->assertSessionHasNoErrors();
        $this->assertSame(2, UsuarioHc::count());
        $this->crear(['tipo_documento' => 'SD', 'numero_documento' => '123'])->assertSessionHasErrors('numero_documento');
        // Sin «Sin documento», el número es obligatorio.
        $this->crear(['numero_documento' => null])->assertSessionHasErrors('numero_documento');
        $this->assertSame(2, UsuarioHc::count());

        $auditoria = Auditoria::where('accion', AccionAuditoria::UsuarioHcCreado->value)->oldest('id')->firstOrFail();
        $this->assertSame($ficha->id, (int) $auditoria->auditable_id);
        $this->assertSame('Creó la ficha de Provisional', $auditoria->descripcion);
        $this->assertNull($auditoria->datos);
        $this->assertSame(2, Auditoria::where('accion', AccionAuditoria::UsuarioHcCreado->value)->count());
    }

    public function test_doc26_ca06_no_se_repite_documento_ni_ficha_en_la_dependencia(): void
    {
        // Creada en otro componente: igual choca (la dependencia entera).
        $this->existente(['numero_documento' => 'AB0012', 'numero_ficha' => 'F-120', 'primer_nombre' => 'Juan', 'primer_apellido' => 'Pérez'], $this->estabilizacion);

        $r = $this->crear(['numero_documento' => '  ab0012 ']);
        $r->assertSessionHasErrors(['numero_documento' => 'Ya existe una ficha con ese documento: Juan Pérez, ficha F-120']);
        $this->crear(['numero_documento' => '77', 'numero_ficha' => 'F120'])
            ->assertSessionHasErrors(['numero_ficha' => 'Ya existe una ficha con ese número de ficha: Juan Pérez, Cédula de ciudadanía AB0012']);
        $this->assertSame(1, UsuarioHc::count());

        // Mismo número con otro tipo: sí.
        $this->crear(['tipo_documento' => 'TI', 'numero_documento' => 'AB0012'])->assertSessionHasNoErrors();
        $this->assertSame(2, UsuarioHc::count());

        // La carrera: otra persona guarda lo mismo entre la validación y el insert; gana el índice único.
        // Aquí la fila «de la otra persona» se escribe dentro de la misma transacción de la prueba, así que
        // se deshace junto con el intento y el mensaje es el general; en producción ya estaría guardada y
        // el mensaje diría su nombre (el mismo método que usa la validación).
        $hecho = false;
        UsuarioHc::creating(function (UsuarioHc $f) use (&$hecho) {
            if ($f->numero_documento === '5555' && ! $hecho) {
                $hecho = true;
                DB::table('usuarios_hc')->insert([
                    'dependencia_id' => $f->dependencia_id, 'componente_registro_id' => $this->estabilizacion->id,
                    'tipo_documento' => 'CC', 'tipo_documento_texto' => 'Cédula de ciudadanía', 'numero_documento' => '5555',
                    'documento_normalizado' => '5555', 'primer_nombre' => 'Rosa', 'nombre_completo' => 'Rosa',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $this->crear(['numero_documento' => '5555'])
            ->assertSessionHasErrors(['numero_documento' => 'Ya existe una ficha con ese documento o ese número de ficha.']);
        $this->assertTrue($hecho);
        $this->assertSame(0, UsuarioHc::where('documento_normalizado', '5555')->count());
        // La transacción se deshizo: la creación fallida no dejó auditoría.
        $this->assertSame(1, Auditoria::where('accion', AccionAuditoria::UsuarioHcCreado->value)->count());
    }

    public function test_doc26_ca07_nombre_parecido_avisa_con_solo_identificacion_y_deja_crear(): void
    {
        $igual = $this->existente(['numero_documento' => '900', 'numero_ficha' => 'F-1', 'primer_nombre' => 'José', 'segundo_nombre' => 'Luis', 'primer_apellido' => 'Peña', 'segundo_apellido' => 'Ruiz', 'alias' => 'El Flaco'], $this->estabilizacion);
        $otraPersona = $this->existente(['primer_nombre' => 'Jose', 'primer_apellido' => 'Torres']);
        $this->actingAs($this->editor);
        $url = route('usuarios-hc.coincidencias');

        // Primer nombre y primer apellido, sin tildes ni mayúsculas, aunque la otra tenga más nombres.
        $this->post($url, ['primer_nombre' => 'JOSE', 'primer_apellido' => 'pena'])->assertOk()
            ->assertSee('José Luis Peña Ruiz')->assertSee('F-1')->assertSee('Estabilización')->assertDontSee('Torres');
        // Alias.
        $this->post($url, ['alias' => 'el  flaco'])->assertOk()->assertSee('José Luis Peña Ruiz');
        // Mismo documento con otro tipo.
        $this->post($url, ['tipo_documento' => 'TI', 'numero_documento' => '900'])->assertOk()->assertSee('José Luis Peña Ruiz')->assertSee('Cédula de ciudadanía 900');
        // La misma ficha vigente.
        $this->post($url, ['numero_ficha' => 'f1'])->assertOk()->assertSee('José Luis Peña Ruiz');
        // Solo el primer nombre no basta; sin nada, vacío.
        $this->post($url, ['primer_nombre' => 'Jose'])->assertOk()->assertDontSee('Peña');
        $this->assertSame('', trim($this->post($url, [])->assertOk()->getContent()));
        // «Sin documento» no compara números.
        $this->post($url, ['tipo_documento' => 'SD', 'numero_documento' => '900'])->assertDontSee('Peña');

        // Avisa, no bloquea: se puede crear igual.
        $this->crear(['numero_documento' => '901', 'primer_nombre' => 'José', 'primer_apellido' => 'Peña'])->assertSessionHasNoErrors();
        $this->assertSame(1, UsuarioHc::where('documento_normalizado', '901')->count());

        // Tope de 10.
        UsuarioHc::factory()->count(12)->en($this->basica)->create(['primer_nombre' => 'Mara', 'primer_apellido' => 'Soto']);
        $html = $this->post($url, ['primer_nombre' => 'Mara', 'primer_apellido' => 'Soto'])->getContent();
        $this->assertSame(10, substr_count($html, '<li>'));
        $this->assertNotNull($igual);
        $this->assertNotNull($otraPersona);
    }

    public function test_doc26_ca08_documento_con_letras_y_ceros_se_guarda_tal_cual(): void
    {
        $this->crear(['numero_documento' => ' 00ab120 '])->assertSessionHasNoErrors();

        // Se guarda como se escribió, solo recortado; en mayúsculas solo para comparar (§2).
        $this->assertSame('00ab120', UsuarioHc::firstOrFail()->numero_documento);
        $this->assertSame('00AB120', UsuarioHc::firstOrFail()->documento_normalizado);
    }

    public function test_doc26_ca20_la_ficha_guarda_el_componente_de_registro(): void
    {
        $this->crear(['numero_documento' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($this->basica->id, UsuarioHc::firstOrFail()->componente_registro_id);

        // Quien manda otro componente en la petición no lo cambia: lo pone el servidor.
        $this->crear(['numero_documento' => '2', 'componente_registro_id' => $this->estabilizacion->id, 'dependencia_id' => 999]);
        $segunda = UsuarioHc::where('documento_normalizado', '2')->firstOrFail();
        $this->assertSame($this->basica->id, $segunda->componente_registro_id);
        $this->assertSame($this->dependencia->id, $segunda->dependencia_id);

        // El coordinador líder: el componente en que está parado.
        $lider = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->actingAs($lider)->withSession(['componente_id' => $this->estabilizacion->id])
            ->post(route('usuarios-hc.store'), $this->datos(['numero_documento' => '3']))->assertSessionHasNoErrors();
        $this->assertSame($this->estabilizacion->id, UsuarioHc::where('documento_normalizado', '3')->firstOrFail()->componente_registro_id);
    }

    public function test_doc26_ca21_lista_coincidencias_y_mensajes_solo_muestran_identificacion(): void
    {
        $otra = Dependencia::factory()->create();
        $ajena = UsuarioHc::factory()->en(Componente::factory()->create(['dependencia_id' => $otra->id, 'modulos' => ['usuarios_hc']]))
            ->create(['numero_documento' => '4040', 'numero_ficha' => 'Z-9', 'primer_nombre' => 'Ana', 'primer_apellido' => 'Gómez', 'alias' => 'Lola']);
        $propia = $this->existente(['numero_documento' => '5050', 'primer_nombre' => 'Ana', 'primer_apellido' => 'Gómez']);

        $this->actingAs($this->editor);
        // Otra dependencia: no aparece en el aviso ni choca al guardar.
        $r = $this->post(route('usuarios-hc.coincidencias'), ['primer_nombre' => 'Ana', 'primer_apellido' => 'Gomez', 'numero_documento' => '4040', 'numero_ficha' => 'Z9', 'alias' => 'Lola']);
        $r->assertOk()->assertSee('5050')->assertDontSee('4040')->assertDontSee('Z-9');
        $this->crear(['numero_documento' => '4040', 'numero_ficha' => 'z9'])->assertSessionHasNoErrors();

        // El mensaje de repetido dice solo identificación.
        $this->crear(['numero_documento' => '5050'])->assertSessionHasErrors(['numero_documento' => 'Ya existe una ficha con ese documento: '.$propia->nombre_completo]);
        $this->assertNotNull($ajena);
    }

    public function test_doc26_ca28_documento_solo_letras_y_numeros_ascii_de_1_a_20(): void
    {
        foreach (['12.345', '12-34', '12 34', 'ÁB12', 'ñ12', 'AB1ñ', str_repeat('1', 21), 'A_1'] as $malo) {
            $this->crear(['numero_documento' => $malo])
                ->assertSessionHasErrors(['numero_documento' => 'Escribe el número solo con letras y números, sin puntos, guiones ni espacios.']);
        }
        $this->assertSame(0, UsuarioHc::count());

        foreach (['1', str_repeat('A', 20), 'ab0012'] as $bueno) {
            $this->crear(['numero_documento' => $bueno])->assertSessionHasNoErrors();
        }
        $this->assertSame(3, UsuarioHc::count());
        $this->assertSame('ab0012', UsuarioHc::where('documento_normalizado', 'AB0012')->value('numero_documento'));

        // «  ab0012 » choca con «AB0012» del mismo tipo.
        $this->crear(['numero_documento' => '  ab0012 '])->assertSessionHasErrors('numero_documento');
        $this->assertSame(3, UsuarioHc::count());
    }

    public function test_doc26_ca29_ficha_igual_sin_mayusculas_espacios_ni_guion(): void
    {
        $this->crear(['numero_documento' => '1', 'numero_ficha' => ' f-120 '])->assertSessionHasNoErrors();
        $ficha = UsuarioHc::firstOrFail();
        $this->assertSame('F-120', $ficha->numero_ficha);
        $this->assertSame('F120', $ficha->ficha_normalizada);

        foreach (['F120', 'f-120', 'F--1-20', ' f120 '] as $choca) {
            $this->crear(['numero_documento' => '2', 'numero_ficha' => $choca])->assertSessionHasErrors('numero_ficha');
        }
        $this->assertSame(1, UsuarioHc::count());

        // «a12» = « A12 ».
        $this->crear(['numero_documento' => '3', 'numero_ficha' => 'a12'])->assertSessionHasNoErrors();
        $this->crear(['numero_documento' => '4', 'numero_ficha' => ' A12 '])->assertSessionHasErrors('numero_ficha');

        $texto = 'Escribe la ficha solo con letras, números y guiones, sin puntos ni espacios.';
        foreach (['F.120', 'Ñ-1', 'FÁ1', 'F 120', str_repeat('A', 31)] as $malo) {
            $this->crear(['numero_documento' => '5', 'numero_ficha' => $malo])->assertSessionHasErrors(['numero_ficha' => $texto]);
        }
        $this->crear(['numero_documento' => '5', 'numero_ficha' => '---'])
            ->assertSessionHasErrors(['numero_ficha' => 'La ficha necesita al menos una letra o un número.']);
        $this->assertSame(2, UsuarioHc::count());

        // Sin ficha: varias fichas sin número no chocan.
        $this->crear(['numero_documento' => '6'])->assertSessionHasNoErrors();
        $this->crear(['numero_documento' => '7'])->assertSessionHasNoErrors();
        $this->assertSame(4, UsuarioHc::count());

        // Buscar «F120» encuentra «F-120», que se muestra como se escribió.
        $this->actingAs($this->editor);
        $this->assertSame(['F-120'], UsuarioHc::buscar('F120')->pluck('numero_ficha')->all());
    }

    // El «no encontrado» de CA-31 llega con las rutas que abren una ficha (UHC-11, UHC-12).
    public function test_doc26_ca31_crear_y_el_aviso_no_tocan_fichas_de_otra_dependencia(): void
    {
        // Las rutas de crear no abren una ficha por dirección; lo que podrían filtrar es la otra dependencia.
        $otra = Dependencia::factory()->create();
        $componenteAjeno = Componente::factory()->create(['dependencia_id' => $otra->id, 'modulos' => ['usuarios_hc']]);
        $ajena = UsuarioHc::factory()->en($componenteAjeno)->create(['numero_documento' => '8080', 'primer_nombre' => 'Eva', 'primer_apellido' => 'Luna']);

        // Líder en las dos dependencias, parado en la primera.
        $lider = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->darRol($lider, RolDependencia::Administracion, $otra);
        $sesion = ['dependencia_id' => $this->dependencia->id, 'componente_id' => $this->basica->id];

        $this->actingAs($lider)->withSession($sesion)->post(route('usuarios-hc.coincidencias'), ['primer_nombre' => 'Eva', 'primer_apellido' => 'Luna', 'numero_documento' => '8080'])
            ->assertOk()->assertDontSee('Luna')->assertDontSee('8080');

        // Y no es dueña de la ficha ajena: crea la suya.
        $this->actingAs($lider)->withSession($sesion)->post(route('usuarios-hc.store'), $this->datos(['numero_documento' => '8080']))->assertSessionHasNoErrors();
        $this->assertSame($this->dependencia->id, UsuarioHc::withoutGlobalScopes()->where('documento_normalizado', '8080')->latest('id')->value('dependencia_id'));
        $this->assertSame($otra->id, $ajena->fresh()->dependencia_id);
    }

    public function test_doc26_ca01_calle_y_lectura_no_crean(): void
    {
        $deCalle = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->calle);
        $lectura = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica);

        foreach ([$deCalle, $lectura] as $usuario) {
            $this->actingAs($usuario)->get(route('usuarios-hc.create'))->assertForbidden();
            $this->actingAs($usuario)->post(route('usuarios-hc.store'), $this->datos())->assertForbidden();
            $this->actingAs($usuario)->post(route('usuarios-hc.coincidencias'), ['primer_nombre' => 'Ana'])->assertForbidden();
        }
        $this->assertSame(0, UsuarioHc::count());

        $this->actingAs($this->editor)->get(route('usuarios-hc.create'))->assertOk()->assertSee('Nueva ficha de Usuario HC');
        $this->actingAs($this->editor)->get(route('usuarios-hc.create'))->assertSee('Sin documento');
    }

    public function test_doc26_ca21_la_ruta_de_coincidencias_tiene_limite_de_peticiones(): void
    {
        $this->assertContains('throttle:60,1', Route::getRoutes()->getByName('usuarios-hc.coincidencias')->gatherMiddleware());
        foreach (['create', 'store', 'coincidencias'] as $nombre) {
            $this->assertContains('can:create,'.UsuarioHc::class, Route::getRoutes()->getByName("usuarios-hc.{$nombre}")->gatherMiddleware());
        }
    }
}
