<?php

namespace Tests\Feature\UsuariosHc;

use App\Enums\RolDependencia;
use App\Livewire\UsuariosHc\Listado;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Models\UsuarioHc;
use App\Services\ContextoDependencia;
use Database\Seeders\AccesoPorComponenteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UHC-06 (la lista), casos de borde de la revisión de QA: tamaño de página,
 * filtro manipulado, búsqueda restaurada, aislamiento, permisos y consultas.
 */
class UsuariosHcListadoCasosTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Componente $basica;

    private Componente $estabilizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = $this->componente('Calle', 'calle', AccesoPorComponenteSeeder::MODULOS_CALLE, $this->dependencia);
        $this->basica = $this->componente('Básica', 'basica', ['usuarios_hc'], $this->dependencia);
        $this->estabilizacion = $this->componente('Estabilización', 'estabilizacion', ['usuarios_hc'], $this->dependencia);
    }

    private function componente(string $nombre, string $slug, array $modulos, Dependencia $dependencia): Componente
    {
        return Componente::factory()->create(['dependencia_id' => $dependencia->id, 'nombre' => $nombre, 'slug' => $slug, 'modulos' => $modulos]);
    }

    private function parado(User $usuario, Dependencia $dependencia, ?Componente $componente): void
    {
        $this->actingAs($usuario);
        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($dependencia);
        $contexto->establecerComponente($componente);
    }

    /** Una tanda de fichas con nombres repetidos para probar la paginación y el orden. */
    public function test_doc26_uhc06_por_pagina_manipulado_no_trae_mas_de_lo_que_ofrece_la_tabla(): void
    {
        UsuarioHc::factory()->count(120)->en($this->basica)->create(['primer_nombre' => 'Repetida', 'primer_apellido' => 'Fila']);
        $this->parado($this->usuarioEn(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->dependencia, $this->basica);

        // La tabla ofrece 25, 50 y 100. Un valor fuera de esas opciones no debería servir una página de 120.
        $lista = Livewire::test(Listado::class)->set('tableRecordsPerPage', 500);

        $this->assertLessThanOrEqual(
            100,
            $lista->instance()->getTableRecords()->count(),
            'Una página de 500 pasó a la consulta: tableRecordsPerPage no se valida contra [25, 50, 100].',
        );
    }

    public function test_doc26_uhc06_filtro_con_valor_no_escalar_no_da_error_del_servidor(): void
    {
        UsuarioHc::factory()->en($this->basica)->create();
        $this->parado($this->usuarioEn(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->dependencia, $this->basica);

        // Un cliente manipulado manda un arreglo donde va un id: no debe ser un 500.
        Livewire::test(Listado::class)
            ->set('tableFilters.componente_registro_id.value', ['x' => 'y'])
            ->assertOk();
    }

    public function test_doc26_uhc06_accion_sobre_ficha_de_otra_dependencia_no_se_ejecuta(): void
    {
        $otraDependencia = Dependencia::factory()->create();
        $otroComponente = $this->componente('Básica', 'basica', ['usuarios_hc'], $otraDependencia);
        $ajena = UsuarioHc::factory()->en($otroComponente)->create();

        $this->parado($this->usuarioEn(RolDependencia::Administracion, $this->dependencia, $this->basica), $this->dependencia, $this->basica);

        $ejecutada = false;
        try {
            Livewire::test(Listado::class)->callTableAction('editar', $ajena);
            $ejecutada = true;
        } catch (\Throwable) {
            $ejecutada = false;
        }

        $this->assertFalse($ejecutada, 'La acción «editar» se ejecutó sobre una ficha de otra dependencia.');
    }

    public function test_doc26_uhc06_la_busqueda_guardada_no_pasa_a_otra_dependencia(): void
    {
        $usuario = User::factory()->create();
        $this->darRol($usuario, RolDependencia::Lectura, $this->dependencia, $this->basica);

        $otra = Dependencia::factory()->create();
        $otroBasica = $this->componente('Básica', 'basica', ['usuarios_hc'], $otra);
        $this->darRol($usuario, RolDependencia::Lectura, $otra, $otroBasica);

        UsuarioHc::factory()->en($this->basica)->create(['primer_nombre' => 'Juan', 'primer_apellido' => 'Pérez']);
        UsuarioHc::factory()->en($otroBasica)->create(['primer_nombre' => 'Lucía', 'primer_apellido' => 'Mora']);

        $this->parado($usuario, $this->dependencia, $this->basica);
        Livewire::test(Listado::class)->searchTable('perez');

        // Ahora en la otra dependencia: la búsqueda de la primera no debe aparecer.
        $this->parado($usuario, $otra, $otroBasica);
        $enOtra = Livewire::test(Listado::class);

        $this->assertSame('', (string) $enOtra->get('tableSearch'), 'La búsqueda de una dependencia se vio en otra.');
    }

    public function test_doc26_uhc06_superadministrador_ve_la_lista_parado_en_basica(): void
    {
        UsuarioHc::factory()->en($this->basica)->create();
        $superadmin = User::factory()->create(['es_superadmin' => true]);
        $this->parado($superadmin, $this->dependencia, $this->basica);

        Livewire::test(Listado::class)->assertCountTableRecords(1);
    }

    /**
     * La lista debe cargar lo mismo con 3 fichas que con 30: nada de una consulta por fila.
     */
    public function test_doc26_uhc06_consultas_no_crecen_con_el_numero_de_fichas(): void
    {
        $this->parado($this->usuarioEn(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->dependencia, $this->basica);

        UsuarioHc::factory()->count(3)->en($this->basica)->create()->each(fn ($f) => $f->identidades()->create(['campo' => 'nombre', 'valor' => 'Anterior']));
        // Una petición de calentamiento: la primera carga las relaciones del usuario y del componente.
        $this->consultasDeLaLista('Anterior');
        $pocas = $this->consultasDeLaLista('Anterior');

        UsuarioHc::factory()->count(27)->en($this->estabilizacion)->create()->each(fn ($f) => $f->identidades()->create(['campo' => 'nombre', 'valor' => 'Anterior']));
        $muchas = $this->consultasDeLaLista('Anterior');

        $this->assertSame($pocas, $muchas, "La lista hace consultas por fila: {$pocas} con 3 fichas y {$muchas} con 30.");
    }

    private function consultasDeLaLista(string $busqueda): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Listado::class)->searchTable($busqueda)->html();
        // Cuenta las consultas a las tablas de la ficha: usuarios_hc y usuario_hc_identidades.
        $total = count(array_filter(DB::getQueryLog(), fn (array $q) => str_contains($q['query'], 'usuario_hc') || str_contains($q['query'], 'usuarios_hc') || str_contains($q['query'], 'componentes')));
        DB::disableQueryLog();

        return $total;
    }

    /** Un valor de búsqueda guardado en sesión (antes del tope) no debe entrar más largo que el tope. */
    public function test_doc26_uhc06_la_busqueda_restaurada_de_la_sesion_respeta_el_tope(): void
    {
        $this->parado($this->usuarioEn(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->dependencia, $this->basica);

        $lista = Livewire::test(Listado::class);
        $llave = $lista->instance()->getTableSearchSessionKey();

        // Como si la sesión la hubiera guardado una versión anterior sin tope.
        session()->put($llave, str_repeat('a', 150));

        $restaurada = Livewire::test(Listado::class);

        $this->assertLessThanOrEqual(
            UsuarioHc::BUSQUEDA_MAX_CARACTERES,
            mb_strlen((string) $restaurada->get('tableSearch')),
            'La búsqueda que vuelve de la sesión no pasa por el tope de 100 (updatedTableSearch no corre al restaurarla).',
        );
    }

    /**
     * Un id de componente de otra dependencia en el filtro: se quita (no está
     * entre las opciones), la lista queda sin ese filtro y nada de la otra
     * dependencia aparece ni da error.
     */
    public function test_doc26_uhc06_filtro_con_componente_de_otra_dependencia_no_muestra_nada_de_ella(): void
    {
        $otra = Dependencia::factory()->create();
        $otroComponente = $this->componente('Ajena', 'ajena', ['usuarios_hc'], $otra);
        UsuarioHc::factory()->en($otroComponente)->create(['primer_nombre' => 'Fuera']);
        $dentro = UsuarioHc::factory()->en($this->basica)->create(['primer_nombre' => 'Dentro']);

        $this->parado($this->usuarioEn(RolDependencia::Lectura, $this->dependencia, $this->basica), $this->dependencia, $this->basica);

        $lista = Livewire::test(Listado::class)
            ->filterTable('componente_registro_id', $otroComponente->id)
            ->assertCanSeeTableRecords([$dentro])
            ->assertCountTableRecords(1)
            ->assertDontSee('Fuera')
            ->assertDontSee('Ajena');

        $this->assertNull(data_get($lista->get('tableFilters'), 'componente_registro_id.value'));
    }

    /** Guarda: sin rol en la dependencia, el boot() corta la lista aunque el componente tenga el módulo. */
    public function test_doc26_uhc06_sin_rol_en_la_dependencia_la_lista_da_403(): void
    {
        UsuarioHc::factory()->en($this->basica)->create();
        $ajeno = User::factory()->create();

        $this->parado($ajeno, $this->dependencia, $this->basica);
        Livewire::test(Listado::class)->assertForbidden();
    }

    /** Guarda: con rol pero sin componente activo (pivote nulo), no hay módulo y la lista da 403. */
    public function test_doc26_uhc06_sin_componente_la_lista_da_403(): void
    {
        UsuarioHc::factory()->en($this->basica)->create();
        $usuario = $this->darRol(User::factory()->create(), RolDependencia::Coordinacion, $this->dependencia, null);

        $this->parado($usuario, $this->dependencia, null);
        Livewire::test(Listado::class)->assertForbidden();
    }

    /**
     * Spec §1: quien de Calle escribe la dirección recibe «No tienes permiso».
     * El rechazo es el de `can:` en la ruta; su texto sale de lang/es.json.
     */
    public function test_doc26_uhc06_calle_por_direccion_recibe_no_tienes_permiso_en_espanol(): void
    {
        app()->setLocale('es');
        $deCalle = $this->usuarioEn(RolDependencia::Lectura, $this->dependencia, $this->calle);

        $this->actingAs($deCalle)
            ->get(route('usuarios-hc.index'))
            ->assertForbidden()
            ->assertSee('No tienes permiso para ver esto.')
            ->assertSee('Sin permiso')
            ->assertDontSee('unauthorized')
            ->assertDontSee('Forbidden');
    }

    private function usuarioEn(RolDependencia $rol, Dependencia $dependencia, Componente $componente): User
    {
        return $this->darRol(User::factory()->create(), $rol, $dependencia, $componente);
    }
}
