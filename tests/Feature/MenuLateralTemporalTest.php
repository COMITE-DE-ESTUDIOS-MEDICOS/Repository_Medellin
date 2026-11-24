<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Enums\TemaInterfaz;
use App\Models\Carpeta;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verificación temporal de la Mejora 2. Borrar cuando se apruebe.
 */
class MenuLateralTemporalTest extends TestCase
{
    use RefreshDatabase;

    protected Dependencia $inclusion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->inclusion = Dependencia::where('slug', 'inclusion-social')->firstOrFail();
    }

    /** Misma firma que el ayudante de Tests\TestCase; por defecto, Inclusión Social. */
    protected function usuarioCon(RolDependencia $rol, ?Dependencia $dependencia = null, ?Componente $componente = null): User
    {
        $usuario = User::create([
            'name' => 'Prueba '.$rol->value,
            'email' => $rol->value.'@prueba.test',
            'password' => Hash::make('Prueba2026'),
            'activo' => true,
            'es_superadmin' => false,
        ]);

        return $this->darRol($usuario, $rol, $dependencia ?? $this->inclusion, $componente);
    }

    protected function documentoDePrueba(User $autor): Documento
    {
        $documento = Documento::create([
            'dependencia_id' => $this->inclusion->id,
            'carpeta_id' => null,
            'nombre' => 'Acta de prueba',
            'descripcion' => 'Documento creado por la verificación del menú lateral.',
            'activo' => true,
            'creado_por' => $autor->id,
        ]);

        $documento->versiones()->create([
            'numero' => 1,
            'ruta' => 'documentos/1/1/v1-prueba.pdf',
            'nombre_original' => 'acta.pdf',
            'extension' => 'pdf',
            'mime' => 'application/pdf',
            'tamano' => 1024,
            'hash' => str_repeat('a', 64),
            'subido_por' => $autor->id,
        ]);

        return $documento;
    }

    /** El contrato del sidebar que ninguna pantalla puede perder. */
    protected function assertContratoDelSidebar(string $html, RolDependencia $rol): void
    {
        // Accesibilidad del botón hamburguesa y del sidebar.
        $this->assertStringContainsString('aria-label="Menú principal"', $html);
        $this->assertStringContainsString('aria-controls="menu-lateral"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('id="menu-lateral"', $html);
        $this->assertStringContainsString('id="menu-boton"', $html);

        // Un solo control del menú, y vive en la cabecera. Si volviera a
        // aparecer uno dentro del <aside>, quedaría inalcanzable justo cuando
        // hace falta: con el menú escondido.
        $this->assertStringNotContainsString('id="menu-cerrar"', $html);

        // El buscador de la barra superior se retiró por decisión de diseño.
        // La búsqueda por texto sigue existiendo en el backend (?q=), solo que
        // ya no tiene campo en pantalla.
        //
        // Se comprueba por su placeholder, que era único. No sirve buscar
        // name="q" —la pantalla de Usuarios tiene su propio buscador— ni la
        // acción del formulario, porque documentos.store apunta a la misma
        // URL que documentos.index y solo se distinguen por el verbo.
        $this->assertStringNotContainsString('Buscar por nombre', $html);

        // Perfil y salir.
        $this->assertStringContainsString(route('perfil.edit'), $html);
        $this->assertStringContainsString(route('logout'), $html);

        // Salir aparece dos veces y no es un descuido: una en la cabecera
        // para escritorio y otra en el pie del menú para móvil, cada una
        // escondida en el tamaño de la otra. Si alguna desaparece, ese
        // tamaño de pantalla se queda sin forma de cerrar sesión.
        $this->assertSame(
            2,
            substr_count($html, route('logout')),
            'Salir debe estar dos veces: una por cada tamaño de pantalla.',
        );
        $this->assertStringContainsString('hidden shrink-0 md:block', $html);

        // Enlace siempre visible.
        $this->assertStringContainsString(route('documentos.index'), $html);

        // Pie: dependencia y rol actual.
        $this->assertStringContainsString($this->inclusion->nombre, $html);
        $this->assertStringContainsString($rol->etiqueta(), $html);

        // Control de rol sobre los enlaces de administración.
        foreach ([route('admin.usuarios.index'), route('admin.tipos.index'), route('auditoria.index')] as $ruta) {
            if ($rol->puedeGestionar()) {
                $this->assertStringContainsString($ruta, $html, "Falta el enlace $ruta para administración");
            } else {
                $this->assertStringNotContainsString($ruta, $html, "El rol {$rol->value} NO debe ver $ruta");
            }
        }
    }

    public function test_las_pantallas_conservan_el_sidebar_en_todos_los_roles(): void
    {
        foreach (RolDependencia::cases() as $rol) {
            $usuario = $this->usuarioCon($rol);
            $documento = $this->documentoDePrueba($usuario);
            $carpeta = Carpeta::where('dependencia_id', $this->inclusion->id)->firstOrFail();

            $pantallas = [
                'documentos.index' => route('documentos.index'),
                'documentos.show' => route('documentos.show', $documento),
                'perfil.edit' => route('perfil.edit'),
            ];

            if ($rol->puedeEditar()) {
                $pantallas['documentos.create'] = route('documentos.create');
                $pantallas['documentos.edit'] = route('documentos.edit', $documento);
                $pantallas['carpetas.create'] = route('carpetas.create');
                $pantallas['carpetas.edit'] = route('carpetas.edit', $carpeta);
            }

            if ($rol->puedeGestionar()) {
                $pantallas['admin.usuarios.index'] = route('admin.usuarios.index');
                $pantallas['admin.usuarios.create'] = route('admin.usuarios.create');
                // DOC-25: la ficha de otra persona; la de Administración solo la abre el superadministrador.
                $pantallas['admin.usuarios.edit'] = route('admin.usuarios.edit', $this->darRol(User::factory()->create(), RolDependencia::Lectura, $this->inclusion));
                $pantallas['admin.tipos.index'] = route('admin.tipos.index');
                $pantallas['auditoria.index'] = route('auditoria.index');
            }

            foreach ($pantallas as $nombre => $url) {
                $respuesta = $this->actingAs($usuario)->get($url);

                $respuesta->assertOk();
                $this->assertContratoDelSidebar($respuesta->getContent(), $rol);

                fwrite(STDERR, sprintf("  %-14s %-26s OK\n", $rol->value, $nombre));
            }
        }
    }

    public function test_el_selector_de_dependencia_aparece_solo_con_varias_y_sigue_funcionando(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        // Con una sola dependencia: el nombre, sin <select>.
        $html = $this->actingAs($usuario)->get(route('documentos.index'))->getContent();
        $this->assertStringNotContainsString('id="selector-dependencia"', $html);
        $this->assertStringContainsString($this->inclusion->nombre, $html);

        // Con dos: aparece el selector.
        $salud = Dependencia::where('slug', 'salud-mental')->firstOrFail();
        $salud->update(['activa' => true]);
        $usuario->dependencias()->attach($salud->id, ['rol' => RolDependencia::Lectura->value]);

        $html = $this->actingAs($usuario)->get(route('documentos.index'))->getContent();
        $this->assertStringContainsString('id="selector-dependencia"', $html);
        $this->assertStringContainsString('id="form-dependencia"', $html);
        $this->assertStringContainsString('value="salud-mental"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);

        // Y el cambio de dependencia sigue funcionando.
        $this->actingAs($usuario)
            ->put(route('dependencia.cambiar', $salud))
            ->assertRedirect();

        $this->assertSame($salud->id, session('dependencia_id'));
    }

    public function test_la_pantalla_de_ingreso_sigue_en_pie(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Ingresar', false);
    }

    /**
     * Cada clase clara que pinta fondo, texto o borde tiene que traer su pareja
     * oscura en el mismo atributo class. Es lo que evita el caso feo: texto de
     * un tema sobre fondo del otro.
     *
     * @return array<string, string> patrón de la clase clara => propiedad que debe traer dark:
     */
    protected function parejasDeTema(): array
    {
        return [
            'bg-white' => 'bg',
            'bg-slate-50' => 'bg',
            'bg-slate-100' => 'bg',
            'bg-slate-200' => 'bg',
            'bg-rose-50' => 'bg',
            'bg-rose-100' => 'bg',
            'bg-emerald-50' => 'bg',
            'bg-sky-50' => 'bg',
            'bg-sky-100' => 'bg',
            'text-slate-500' => 'text',
            'text-slate-600' => 'text',
            'text-slate-700' => 'text',
            'text-slate-800' => 'text',
            'text-slate-900' => 'text',
            'text-rose-700' => 'text',
            'text-rose-800' => 'text',
            'text-emerald-700' => 'text',
            'text-emerald-800' => 'text',
            'text-sky-700' => 'text',
            'text-sky-800' => 'text',
            'ring-slate-200' => 'ring',
            'ring-slate-300' => 'ring',
            'border-slate-200' => 'border',
            'border-slate-300' => 'border',
            'divide-slate-100' => 'divide',
            'divide-slate-200' => 'divide',
        ];
    }

    /** @return list<string> los atributos class que no traen su pareja oscura */
    protected function clasesSinParejaOscura(string $html): array
    {
        // El menú lateral es oscuro en los dos temas a propósito: no aplica.
        $html = preg_replace('/<aside id="menu-lateral".*?<\/aside>/s', '', $html);

        preg_match_all('/class="([^"]*)"/', $html, $m);

        $huerfanas = [];

        foreach (array_unique($m[1]) as $clases) {
            foreach ($this->parejasDeTema() as $clara => $propiedad) {
                // Acepta sufijo de opacidad (bg-rose-50/50) y variantes (hover:bg-slate-50).
                $tieneClara = (bool) preg_match('/(^|[\s:])'.preg_quote($clara, '/').'(\/\d+)?(\s|$)/', $clases);

                if (! $tieneClara) {
                    continue;
                }

                // La pareja puede llevar variantes: dark:hover:bg-…, dark:file:bg-…
                $tieneOscura = (bool) preg_match('/dark:(?:[a-z0-9-]+:)*'.$propiedad.'-/', $clases);

                if (! $tieneOscura) {
                    $huerfanas[] = $clara.'  →  class="'.trim(preg_replace('/\s+/', ' ', $clases)).'"';
                }
            }
        }

        return array_unique($huerfanas);
    }

    public function test_ninguna_pantalla_mezcla_colores_de_los_dos_temas(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Administracion);
        $documento = $this->documentoDePrueba($usuario);
        $carpeta = Carpeta::where('dependencia_id', $this->inclusion->id)->firstOrFail();

        $pantallas = [
            'login' => route('login'),
            'documentos.index' => route('documentos.index'),
            'documentos.show' => route('documentos.show', $documento),
            'documentos.create' => route('documentos.create'),
            'documentos.edit' => route('documentos.edit', $documento),
            'carpetas.create' => route('carpetas.create'),
            'carpetas.edit' => route('carpetas.edit', $carpeta),
            'perfil.edit' => route('perfil.edit'),
            'admin.usuarios.index' => route('admin.usuarios.index'),
            'admin.usuarios.create' => route('admin.usuarios.create'),
            'admin.usuarios.edit' => route('admin.usuarios.edit', $this->darRol(User::factory()->create(), RolDependencia::Lectura, $this->inclusion)),
            'admin.tipos.index' => route('admin.tipos.index'),
            'auditoria.index' => route('auditoria.index'),
        ];

        $problemas = [];

        foreach ($pantallas as $nombre => $url) {
            $respuesta = $nombre === 'login'
                ? $this->get($url)
                : $this->actingAs($usuario)->get($url);

            $respuesta->assertOk();

            $huerfanas = $this->clasesSinParejaOscura($respuesta->getContent());

            fwrite(STDERR, sprintf("  %-24s %s\n", $nombre, $huerfanas === [] ? 'OK' : count($huerfanas).' sin pareja'));

            foreach ($huerfanas as $h) {
                $problemas[] = $nombre.': '.$h;
            }
        }

        $this->assertSame([], $problemas, "Clases claras sin variante oscura:\n".implode("\n", $problemas));
    }

    public function test_el_tema_se_guarda_en_la_cuenta_y_sobrevive_al_cierre_de_sesion(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        // Por defecto, «sistema».
        $this->assertSame(TemaInterfaz::Sistema, $usuario->tema);

        $this->actingAs($usuario)
            ->put(route('perfil.tema'), ['tema' => 'oscuro'])
            ->assertRedirect()
            ->assertSessionHas('exito');

        // Persiste en la base, no en el navegador.
        $this->assertSame(TemaInterfaz::Oscuro, $usuario->fresh()->tema);

        // Y el HTML lo lleva ya resuelto en el <html>, que es de donde lo lee
        // el script del <head> antes de que el navegador pinte nada.
        $html = $this->actingAs($usuario->fresh())->get(route('documentos.index'))->getContent();
        $this->assertStringContainsString('data-tema="oscuro"', $html);

        // Un valor inventado no pasa la validación.
        $this->actingAs($usuario)
            ->put(route('perfil.tema'), ['tema' => 'fucsia'])
            ->assertSessionHasErrors('tema');

        $this->assertSame(TemaInterfaz::Oscuro, $usuario->fresh()->tema);
    }

    public function test_el_interruptor_de_la_barra_superior_guarda_sin_recargar(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        // El interruptor está en todas las pantallas, con sus dos iconos y sus
        // dos etiquetas listos para que los alterne el CSS.
        $html = $this->actingAs($usuario)->get(route('documentos.index'))->getContent();

        $this->assertStringContainsString('id="interruptor-tema"', $html);
        $this->assertStringContainsString('Cambiar a tema oscuro', $html);
        $this->assertStringContainsString('Cambiar a tema claro', $html);

        // Con JS: PUT JSON que guarda y responde 204, sin redirección que seguir.
        $this->actingAs($usuario)
            ->putJson(route('perfil.tema'), ['tema' => 'oscuro'])
            ->assertNoContent();

        $this->assertSame(TemaInterfaz::Oscuro, $usuario->fresh()->tema);

        // Sin JS: el mismo formulario enviado a pelo sigue funcionando.
        $this->actingAs($usuario->fresh())
            ->put(route('perfil.tema'), ['tema' => 'claro'])
            ->assertRedirect()
            ->assertSessionHas('exito');

        $this->assertSame(TemaInterfaz::Claro, $usuario->fresh()->tema);

        // El campo oculto del respaldo ofrece lo contrario de lo guardado.
        $html = $this->actingAs($usuario->fresh())->get(route('documentos.index'))->getContent();
        $this->assertStringContainsString('name="tema" value="oscuro"', $html);
    }

    public function test_el_ingreso_decide_el_tema_por_el_sistema(): void
    {
        $html = $this->get(route('login'))->getContent();

        // No hay usuario todavía: manda prefers-color-scheme, y la clase .dark
        // se pone a mano porque el variant del proyecto depende de ella.
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
        $this->assertStringContainsString("classList.toggle('dark'", $html);
    }
}
