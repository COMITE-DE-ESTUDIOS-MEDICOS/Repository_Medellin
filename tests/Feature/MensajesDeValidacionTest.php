<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * La aplicación corre con APP_LOCALE=es. Sin archivos de idioma, Laravel
 * imprime la clave cruda —«validation.required», «validation.after»— en
 * todos los formularios, no solo en el que se notó.
 */
class MensajesDeValidacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ningun_formulario_muestra_la_clave_cruda_de_la_regla(): void
    {
        $dependencia = Dependencia::factory()->create();
        $admin = $this->usuarioCon(RolDependencia::Administracion, $dependencia);
        $editor = $this->usuarioCon(RolDependencia::Edicion, $dependencia);

        $formularios = [
            'ingreso' => fn () => $this->post(route('login'), []),
            'subir documento' => fn () => $this->actingAs($editor)->post(route('documentos.store'), []),
            'crear carpeta' => fn () => $this->actingAs($editor)->post(route('carpetas.store'), []),
            'crear usuario' => fn () => $this->actingAs($admin)->post(route('admin.usuarios.store'), []),
            'crear enlace' => fn () => $this->actingAs($admin)->post(route('admin.enlaces.store'), []),
            'cambiar contraseña' => fn () => $this->actingAs($admin)->put(route('perfil.password'), []),
            'editar perfil' => fn () => $this->actingAs($admin)->put(route('perfil.update'), []),
        ];

        $crudos = [];

        foreach ($formularios as $nombre => $enviar) {
            $this->flushSession();
            $enviar();

            foreach (session('errors')?->all() ?? [] as $mensaje) {
                if (str_contains($mensaje, 'validation.')) {
                    $crudos[] = $nombre.' → '.$mensaje;
                }
            }
        }

        $this->assertSame([], $crudos, "Formularios que muestran la clave cruda:\n".implode("\n", $crudos));
    }

    public function test_los_mensajes_salen_en_espanol(): void
    {
        $this->post(route('login'), [])->assertSessionHasErrors('identificador');

        $mensaje = session('errors')->first('identificador');

        $this->assertStringContainsString('obligatorio', $mensaje);
        $this->assertStringContainsString('usuario', $mensaje, 'No usó el nombre amable del campo.');
    }

    /**
     * Comprueba la traducción en español directamente, sin dejar que el
     * respaldo inglés la tape: __() devolvería el texto en inglés y la
     * prueba pasaría con un hueco dentro.
     */
    public function test_las_reglas_que_usa_la_aplicacion_estan_traducidas(): void
    {
        $reglas = [
            'required', 'string', 'integer', 'boolean', 'email', 'date', 'after',
            'after_or_equal', 'before_or_equal', 'file', 'mimes', 'mimetypes',
            'unique', 'exists', 'confirmed', 'current_password', 'enum', 'in',
            'numeric', 'image', 'uuid', 'url', 'array',
            'min.string', 'min.file', 'min.numeric', 'min.array',
            'max.string', 'max.file', 'max.numeric', 'max.array',
            'password.letters', 'password.numbers', 'password.mixed', 'password.symbols',
        ];

        $sinTraducir = [];

        foreach ($reglas as $regla) {
            if (! Lang::has('validation.'.$regla, 'es', false)) {
                $sinTraducir[] = 'validation.'.$regla;
            }
        }

        $this->assertSame([], $sinTraducir, "Reglas sin traducir al español:\n".implode("\n", $sinTraducir));
    }

    /**
     * Con el respaldo también en español, cualquier hueco vuelve a mostrar la
     * clave cruda. En inglés se ve feo, pero se lee y se puede depurar.
     */
    public function test_el_idioma_de_respaldo_es_ingles(): void
    {
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));

        // Y el respaldo existe de verdad: lang:publish dejó los archivos.
        $this->assertTrue(Lang::has('validation.required', 'en', false));
    }
}
