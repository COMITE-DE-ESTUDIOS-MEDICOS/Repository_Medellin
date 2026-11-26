<?php

namespace Tests\Feature\UsuariosHc;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\User;
use App\Models\UsuarioHc;
use App\Services\ContextoDependencia;
use Database\Seeders\AccesoPorComponenteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** DOC-26 (UHC-04, plan D3): UsuarioHcPolicy. */
class UsuarioHcPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $basica;

    private Componente $estabilizacion;

    private UsuarioHc $ficha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->basica = $this->componente('Básica', 'basica', ['usuarios_hc']);
        $this->estabilizacion = $this->componente('Estabilización', 'estabilizacion', ['usuarios_hc']);
        $this->ficha = UsuarioHc::factory()->en($this->basica)->create();
    }

    private function componente(string $nombre, string $slug, array $modulos): Componente
    {
        return Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => $nombre, 'slug' => $slug, 'modulos' => $modulos]);
    }

    /** Fija dependencia y componente a mano, como el middleware. */
    private function parado(User $usuario, Componente $componente, ?Dependencia $dependencia = null): User
    {
        $contexto = app(ContextoDependencia::class);
        $contexto->establecer($dependencia ?? $this->dependencia);
        $contexto->establecerComponente($componente);

        return $usuario;
    }

    private function puede(User $usuario, string $habilidad, mixed $argumento = null): bool
    {
        return Gate::forUser($usuario)->allows($habilidad, $argumento ?? $this->ficha);
    }

    public function test_doc26_ca13_solo_el_componente_de_registro_el_lider_y_el_superadministrador_editan(): void
    {
        $edicion = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);
        $this->parado($edicion, $this->basica);
        $this->assertTrue($this->puede($edicion, 'update'));
        $this->assertTrue($this->puede($edicion, 'create', UsuarioHc::class));

        $lectura = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica);
        $this->parado($lectura, $this->basica);
        $this->assertFalse($this->puede($lectura, 'update'));
        $this->assertFalse($this->puede($lectura, 'create', UsuarioHc::class));
        $this->assertTrue($this->puede($lectura, 'view'));

        $otro = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->estabilizacion);
        $this->parado($otro, $this->estabilizacion);
        $this->assertFalse($this->puede($otro, 'update'));
        $this->assertTrue($this->puede($otro, 'view'));
        $this->assertTrue($this->puede($otro, 'create', UsuarioHc::class));

        foreach ([$this->usuarioCon(RolDependencia::Administracion, $this->dependencia), User::factory()->superadmin()->create()] as $lider) {
            $this->parado($lider, $this->estabilizacion);
            $this->assertTrue($this->puede($lider, 'update'));
        }
    }

    public function test_doc26_ca16_otro_componente_y_lectura_no_ven_caracterizacion_ni_historial(): void
    {
        $lectura = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia, $this->basica);
        $this->assertFalse($this->puede($this->parado($lectura, $this->basica), 'verCaracterizacion'));

        $otro = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $this->estabilizacion);
        $this->assertFalse($this->puede($this->parado($otro, $this->estabilizacion), 'verCaracterizacion'));

        $mismo = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia, $this->basica);
        $this->assertTrue($this->puede($this->parado($mismo, $this->basica), 'verCaracterizacion'));

        $lider = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->assertTrue($this->puede($this->parado($lider, $this->estabilizacion), 'verCaracterizacion'));
        $super = User::factory()->superadmin()->create();
        $this->assertTrue($this->puede($this->parado($super, $this->estabilizacion), 'verCaracterizacion'));
    }

    public function test_doc26_ca01_calle_sin_modulo_lo_niega_todo_incluido_el_superadministrador(): void
    {
        $calle = $this->componente('Calle', 'calle', AccesoPorComponenteSeeder::MODULOS_CALLE);
        $coordinacion = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $calle);
        $super = User::factory()->superadmin()->create();

        foreach ([$coordinacion, $super] as $usuario) {
            $this->parado($usuario, $calle);
            foreach (['view', 'verCaracterizacion', 'update'] as $habilidad) {
                $this->assertFalse($this->puede($usuario, $habilidad), $habilidad);
            }
            $this->assertFalse($this->puede($usuario, 'viewAny', UsuarioHc::class));
            $this->assertFalse($this->puede($usuario, 'create', UsuarioHc::class));
        }
    }

    public function test_doc26_ca31_la_policy_niega_todo_sobre_una_ficha_de_otra_dependencia(): void
    {
        $otra = Dependencia::factory()->create();
        $componenteAjeno = Componente::factory()->create(['dependencia_id' => $otra->id, 'modulos' => ['usuarios_hc']]);
        $ajena = UsuarioHc::factory()->en($componenteAjeno)->create();

        $lider = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->darRol($lider, RolDependencia::Administracion, $otra);
        $super = User::factory()->superadmin()->create();

        foreach ([$lider, $super] as $usuario) {
            $this->parado($usuario, $this->basica);
            $this->assertTrue($this->puede($usuario, 'update'), 'Control: la propia sí.');
            foreach (['view', 'verCaracterizacion', 'update'] as $habilidad) {
                $this->assertFalse($this->puede($usuario, $habilidad, $ajena), $habilidad);
            }
        }
    }

    public function test_doc26_ca13_conocida_y_editable_por_componente_o_id(): void
    {
        $this->assertTrue($this->ficha->conocidaPor($this->basica));
        $this->assertTrue($this->ficha->conocidaPor($this->basica->id));
        $this->assertTrue($this->ficha->editablePor($this->basica));
        $this->assertFalse($this->ficha->conocidaPor($this->estabilizacion));
        $this->assertFalse($this->ficha->editablePor($this->estabilizacion->id));
        $this->assertFalse($this->ficha->conocidaPor(null));
    }
}
