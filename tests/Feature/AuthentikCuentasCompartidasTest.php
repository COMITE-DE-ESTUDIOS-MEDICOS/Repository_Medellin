<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\AuthentikProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * DOC-22: una sola cuenta de Authentik por persona, compartida entre las apps.
 * Documenta solo la crea si no existe y la mete o la saca de su grupo.
 *
 * Nunca se habla con un Authentik real: todo pasa por Http::fake.
 */
class AuthentikCuentasCompartidasTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://authentik.test';

    private const GRUPO = 'uuid-de-prueba';

    private Dependencia $dependencia;

    private User $admin;

    /** Cuentas que ya existen en Authentik: username => pk. */
    private array $existentes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.authentik.base_url' => self::BASE,
            'services.authentik.api_token' => 'x',
            'services.authentik.grupo_id' => self::GRUPO,
        ]);

        $this->dependencia = Dependencia::factory()->create();
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
    }

    /*
    |--------------------------------------------------------------------------
    | CA-01, CA-02: dar acceso
    |--------------------------------------------------------------------------
    */

    public function test_doc22_ca01_crea_sin_contrasena_y_agrega_al_grupo(): void
    {
        $this->authentikContesta();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $nueva = User::where('documento', '1122334455')->firstOrFail();
        $this->assertSame('77', $nueva->authentik_id);

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && str_starts_with($r->url(), self::BASE.'/api/v3/core/users/?')
            && $r['username'] === '1122334455');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/api/v3/core/users/'
            && $r->hasHeader('Authorization', 'Bearer x')
            && $r->data() === ['username' => '1122334455', 'name' => 'Nueva Auxiliar', 'is_active' => true, 'email' => 'nueva@cem.test']);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/api/v3/core/groups/'.self::GRUPO.'/add_user/'
            && $r->data() === ['pk' => 77]);

        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::AuthentikAprovisionado->value,
            'auditable_id' => $nueva->id,
        ]);
    }

    public function test_doc22_ca01_sin_correo_no_envia_el_campo(): void
    {
        $this->authentikContesta();

        $datos = $this->altaDe('1122334455');
        unset($datos['email']);

        $this->actingAs($this->admin)->post(route('admin.usuarios.store'), $datos)->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/api/v3/core/users/'
            && ! array_key_exists('email', $r->data()));
    }

    public function test_doc22_ca02_si_ya_existe_solo_lo_agrega_al_grupo(): void
    {
        // La API devuelve también un username parecido: la coincidencia exacta se hace aquí.
        $this->existentes = ['11223344550' => 91, '1122334455' => 55];
        $this->authentikContesta();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertSessionHasNoErrors();

        $this->assertSame('55', User::where('documento', '1122334455')->value('authentik_id'));

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/api/v3/core/groups/'.self::GRUPO.'/add_user/'
            && $r->data() === ['pk' => 55]);

        $this->assertNingunCambioALaCuenta();

        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::AuthentikAprovisionado->value,
            'descripcion' => 'Agregó a Documenta la cuenta que ya existía en Authentik de Nueva Auxiliar',
        ]);
    }

    public function test_dar_acceso_dos_veces_no_duplica_la_cuenta(): void
    {
        $this->authentikContesta();

        $usuario = User::factory()->create();
        $provisioner = app(AuthentikProvisioner::class);

        $this->assertTrue($provisioner->darAcceso($usuario));
        $this->assertTrue($provisioner->darAcceso($usuario));

        $this->assertCount(1, Http::recorded(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/api/v3/core/users/'));
        $this->assertCount(2, Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/add_user/')));
    }

    public function test_sin_documento_no_hace_nada_y_lo_anota(): void
    {
        $this->authentikContesta();

        $usuario = User::factory()->create(['documento' => null]);

        $this->assertFalse(app(AuthentikProvisioner::class)->darAcceso($usuario));

        Http::assertNothingSent();
        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::AuthentikFallo->value,
            'auditable_id' => $usuario->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CA-03: editar no toca la cuenta
    |--------------------------------------------------------------------------
    */

    public function test_doc22_ca03_editar_no_envia_nada_de_la_cuenta(): void
    {
        $this->authentikContesta();

        $empleado = $this->empleadoConCuenta();

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, nombre: 'Otro Nombre', correo: 'otro@cem.test', documento: '9988776655'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('9988776655', $empleado->fresh()->documento);

        // Solo se asegura el grupo (idempotente): nada de nombre, correo ni username.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/add_user/') && $r->data() === ['pk' => 77]);
        Http::assertNotSent(fn (Request $r) => str_contains($r->body(), 'Otro Nombre')
            || str_contains($r->body(), 'otro@cem.test')
            || str_contains($r->body(), '9988776655'));
        $this->assertNingunCambioALaCuenta();
    }

    /*
    |--------------------------------------------------------------------------
    | CA-04: desactivar, revocar, reactivar
    |--------------------------------------------------------------------------
    */

    public function test_doc22_ca04_desactivar_saca_del_grupo_y_reactivar_lo_devuelve(): void
    {
        $this->authentikContesta();

        $empleado = $this->empleadoConCuenta();

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, activo: false))
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/api/v3/core/groups/'.self::GRUPO.'/remove_user/'
            && $r->data() === ['pk' => 77]);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/add_user/'));

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado->fresh(), activo: true))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/api/v3/core/groups/'.self::GRUPO.'/add_user/'
            && $r->data() === ['pk' => 77]);

        // La cuenta nunca se desactiva ni se borra.
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' || str_contains($r->body(), 'is_active'));
        $this->assertNingunCambioALaCuenta();
    }

    public function test_doc22_ca04_si_no_se_pudo_sacar_del_grupo_se_avisa(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'sin permiso'], 403)]);

        $empleado = $this->empleadoConCuenta();

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, activo: false))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'todavía puede entrar'));

        $this->assertFalse($empleado->fresh()->activo);
    }

    public function test_doc22_ca04_revocar_la_ultima_dependencia_saca_del_grupo(): void
    {
        $this->authentikContesta();

        $empleado = $this->empleadoConCuenta();

        $this->actingAs($this->admin)
            ->delete(route('admin.usuarios.revocar', $empleado))
            ->assertRedirect();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/remove_user/') && $r->data() === ['pk' => 77]);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' || str_contains($r->body(), 'is_active'));
    }

    public function test_doc22_ca04_revocar_si_le_queda_otra_dependencia_no_lo_saca(): void
    {
        $this->authentikContesta();

        $empleado = $this->empleadoConCuenta();
        $this->darRol($empleado, RolDependencia::Lectura, Dependencia::factory()->create());

        $this->actingAs($this->admin)->delete(route('admin.usuarios.revocar', $empleado));

        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | CA-05: ninguna contraseña viaja
    |--------------------------------------------------------------------------
    */

    public function test_doc22_ca05_ninguna_contrasena_viaja(): void
    {
        $this->authentikContesta();

        // Aunque alguien mande el campo a mano, se ignora.
        $datos = $this->altaDe('1122334455') + ['password' => 'Secreta123', 'password_confirmation' => 'Secreta123'];

        $this->actingAs($this->admin)->post(route('admin.usuarios.store'), $datos)->assertSessionHasNoErrors();

        $nueva = User::where('documento', '1122334455')->firstOrFail();
        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $nueva), $this->fichaDe($nueva) + ['password' => 'Secreta123', 'password_confirmation' => 'Secreta123']);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'set_password')
            || str_contains($r->body(), 'password')
            || str_contains($r->body(), 'Secreta123'));

        // Y tampoco queda como clave local: nadie la conoce.
        $this->assertFalse(password_verify('Secreta123', $nueva->fresh()->password));
    }

    public function test_el_formulario_no_pide_contrasena_y_explica_el_enlace_de_recuperacion(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.create'))
            ->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertSee('enlace de recuperación');
    }

    /*
    |--------------------------------------------------------------------------
    | CA-08: Authentik caído
    |--------------------------------------------------------------------------
    */

    public function test_doc22_ca08_si_authentik_falla_la_operacion_local_sigue(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'sin autorización'], 403)]);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', fn (string $aviso) => str_contains($aviso, 'auditoría'));

        $nueva = User::where('documento', '1122334455')->firstOrFail();
        $this->assertNull($nueva->authentik_id);
        $this->assertTrue($nueva->activo);
        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::AuthentikFallo->value,
            'auditable_id' => $nueva->id,
        ]);

        // Desactivar también se completa aquí aunque allá falle.
        $empleado = $this->empleadoConCuenta();

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, activo: false))
            ->assertSessionHasNoErrors();

        $this->assertFalse($empleado->fresh()->activo);
        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::AuthentikFallo->value,
            'auditable_id' => $empleado->id,
        ]);
    }

    public function test_doc22_ca08_si_falla_solo_el_grupo_guarda_el_pk_y_avisa(): void
    {
        Http::fake(fn (Request $r) => match (true) {
            $r->method() === 'GET' => Http::response(['results' => []]),
            str_ends_with($r->url(), '/core/users/') => Http::response(['pk' => 77], 201),
            default => Http::response(['detail' => 'no'], 403),
        });

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertSessionHas('error');

        // Guardar la ficha de nuevo es el reintento, y ya no vuelve a crear la cuenta.
        $this->assertSame('77', User::where('documento', '1122334455')->value('authentik_id'));
    }

    public function test_sin_grupo_configurado_no_se_llama_a_authentik_y_se_avisa(): void
    {
        config(['services.authentik.grupo_id' => null]);
        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertSessionHas('error', fn (string $aviso) => str_contains($aviso, 'no está configurado'));

        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Validación del formulario (venían de la prueba anterior)
    |--------------------------------------------------------------------------
    */

    public function test_un_correo_repetido_se_rechaza_con_un_mensaje(): void
    {
        $this->authentikContesta();
        User::factory()->create(['email' => 'ocupado@cem.test']);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), ['email' => 'ocupado@cem.test'] + $this->altaDe('1122334455'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['documento' => '1122334455']);
    }

    public function test_dos_cuentas_sin_correo_conviven(): void
    {
        $this->authentikContesta();

        foreach (['1122334455', '5544332211'] as $documento) {
            $datos = $this->altaDe($documento);
            unset($datos['email']);

            $this->actingAs($this->admin)->post(route('admin.usuarios.store'), $datos)->assertSessionHasNoErrors();
        }

        $this->assertSame(2, User::whereIn('documento', ['1122334455', '5544332211'])->count());
    }

    public function test_el_documento_se_llama_usuario_y_sus_errores_hablan_de_usuario(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.create'))
            ->assertSee('Usuario *')
            ->assertDontSee('Alias de emergencia');

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), ['documento' => ''] + $this->altaDe('x'))
            ->assertSessionHasErrors(['documento' => 'El usuario es obligatorio con acceso web: por norma interna, la cédula de la persona.']);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('12'))
            ->assertSessionHasErrors(['documento' => 'El usuario solo admite números (la cédula), sin puntos ni espacios.']);

        $otro = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $empleado = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, documento: $otro->documento))
            ->assertSessionHasErrors(['documento' => 'Ese usuario ya lo tiene otra persona con acceso web.']);
    }

    public function test_el_alias_de_emergencia_solo_es_del_superadministrador(): void
    {
        $this->authentikContesta();

        $jefe = User::factory()->superadmin()->create();
        $this->darRol($jefe, RolDependencia::Administracion, $this->dependencia);
        $empleado = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->actingAs($jefe)->get(route('admin.usuarios.edit', $jefe))->assertSee('Alias de emergencia (opcional)');
        $this->actingAs($jefe)->get(route('admin.usuarios.edit', $empleado))->assertDontSee('Alias de emergencia');

        $this->actingAs($jefe)
            ->put(route('admin.usuarios.update', $jefe), $this->fichaDe($jefe) + ['rol' => RolDependencia::Administracion->value, 'usuario' => 'Jefe.Plataforma'])
            ->assertSessionHasNoErrors();
        $this->assertSame('jefe.plataforma', $jefe->fresh()->usuario);

        // A los demás ni se les valida ni se les guarda.
        $this->actingAs($jefe)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado) + ['usuario' => '###'])
            ->assertSessionHasNoErrors();
        $this->assertNull($empleado->fresh()->usuario);
    }

    /*
    |--------------------------------------------------------------------------
    | Comandos
    |--------------------------------------------------------------------------
    */

    public function test_sincronizar_grupo_simulado_no_escribe_nada(): void
    {
        $this->existentes = ['1000000001' => 55];
        $this->authentikContesta();

        $existe = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $existe->forceFill(['documento' => '1000000001'])->save();
        $nuevo = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $sinDependencia = User::factory()->create();
        $inactivo = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $inactivo->forceFill(['activo' => false])->save();

        $this->artisan('authentik:sincronizar-grupo', ['--simular' => true])
            ->expectsOutputToContain('Agregar al grupo la cuenta que ya existe')
            ->expectsOutputToContain('Crear la cuenta sin contraseña')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
        Http::assertNotSent(fn (Request $r) => in_array($r['username'] ?? null, [$sinDependencia->documento, $inactivo->documento], true));
        $this->assertNull($nuevo->fresh()->authentik_id);
    }

    public function test_sincronizar_grupo_agrega_a_los_activos(): void
    {
        $this->authentikContesta();

        $empleado = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->artisan('authentik:sincronizar-grupo')->assertSuccessful();

        $this->assertSame('77', $empleado->fresh()->authentik_id);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/add_user/'));
    }

    public function test_revisar_lista_sin_documento_sin_cuenta_y_superadmins_sin_cambiar_nada(): void
    {
        $this->existentes = ['1000000001' => 55];
        $this->authentikContesta();

        User::factory()->create(['documento' => '1000000001', 'name' => 'Con Cuenta']);
        User::factory()->create(['documento' => '1000000002', 'name' => 'Sin Cuenta']);
        User::factory()->create(['documento' => null, 'name' => 'Sin Documento']);
        User::factory()->superadmin()->create(['name' => 'Jefe Plataforma']);

        $this->artisan('authentik:revisar')
            ->expectsOutputToContain('Sin Cuenta')
            ->expectsOutputToContain('Sin Documento')
            ->expectsOutputToContain('Jefe Plataforma')
            ->doesntExpectOutputToContain('Con Cuenta')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    /*
    |--------------------------------------------------------------------------
    | Andamiaje
    |--------------------------------------------------------------------------
    */

    /** Authentik fingido: busca en $existentes, crea con pk 77, el grupo responde 204. */
    private function authentikContesta(): void
    {
        Http::fake(function (Request $r) {
            if ($r->method() === 'GET' && str_starts_with($r->url(), self::BASE.'/api/v3/core/users/')) {
                // Como la API real: un filtro que puede traer parecidos.
                $resultados = collect($this->existentes)
                    ->filter(fn ($pk, $username) => str_starts_with((string) $username, (string) $r['username']))
                    ->map(fn ($pk, $username) => ['pk' => $pk, 'username' => (string) $username])
                    ->values();

                return Http::response(['results' => $resultados]);
            }

            if ($r->method() === 'POST' && $r->url() === self::BASE.'/api/v3/core/users/') {
                return Http::response(['pk' => 77], 201);
            }

            return Http::response(null, 204);
        });
    }

    private function assertNingunCambioALaCuenta(): void
    {
        Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PATCH', 'PUT', 'DELETE'], true)
            || ($r->method() === 'POST' && str_contains($r->url(), '/core/users/')));
    }

    private function empleadoConCuenta(): User
    {
        $empleado = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $empleado->forceFill(['authentik_id' => '77'])->save();

        return $empleado;
    }

    /** @return array<string, mixed> */
    private function altaDe(string $documento): array
    {
        return [
            'name' => 'Nueva Auxiliar',
            'documento' => $documento,
            'email' => 'nueva@cem.test',
            'rol' => RolDependencia::Edicion->value,
            'activo' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function fichaDe(User $usuario, ?string $nombre = null, ?string $correo = null, ?string $documento = null, bool $activo = true): array
    {
        return [
            'name' => $nombre ?? $usuario->name,
            'documento' => $documento ?? $usuario->documento,
            'email' => $correo ?? $usuario->email,
            'rol' => RolDependencia::Edicion->value,
            'activo' => $activo ? 1 : 0,
        ];
    }

    /** Norma de Edwar: personas, solo números; el superadministrador, letras y números tal cual. */
    public function test_doc22_el_usuario_de_una_persona_es_solo_numeros_y_el_del_superadmin_lleva_letras(): void
    {
        $this->authentikContesta();

        $jefe = User::factory()->superadmin()->create();
        $this->darRol($jefe, RolDependencia::Administracion, $this->dependencia);
        $empleado = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->actingAs($jefe)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, documento: 'AB1234567'))
            ->assertSessionHasErrors(['documento' => 'El usuario solo admite números (la cédula), sin puntos ni espacios.']);

        $this->actingAs($jefe)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, documento: '1.017.000.222'))
            ->assertSessionHasNoErrors();
        $this->assertSame('1017000222', $empleado->fresh()->documento);

        $this->actingAs($jefe)
            ->put(route('admin.usuarios.update', $jefe), $this->fichaDe($jefe, documento: ' adminDocumenta ') + ['rol' => RolDependencia::Administracion->value])
            ->assertSessionHasNoErrors();
        $this->assertSame('adminDocumenta', $jefe->fresh()->documento, 'Tal cual, con sus minúsculas.');

        $this->actingAs($jefe)
            ->put(route('admin.usuarios.update', $jefe), $this->fichaDe($jefe, documento: 'admin Documenta') + ['rol' => RolDependencia::Administracion->value])
            ->assertSessionHasErrors(['documento' => 'El usuario del superadministrador solo admite letras y números, sin espacios.']);
    }
}
