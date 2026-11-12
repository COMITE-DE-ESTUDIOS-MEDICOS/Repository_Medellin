<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\User;
use App\Services\Auditor;
use App\Services\AuthentikProvisioner;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
        protected AuthentikProvisioner $authentik,
    ) {
    }

    /** La lista es una tabla de Filament (App\Livewire\Usuarios): búsqueda, filtros y revocar. */
    public function index(): View
    {
        $this->autorizar();

        return view('admin.usuarios.index', ['roles' => RolDependencia::cases()]);
    }

    public function create(Request $request): View
    {
        $this->autorizar();

        return view('admin.usuarios.create', ['roles' => $this->rolesAsignables($request)]);
    }

    public function store(GuardarUsuarioRequest $request): RedirectResponse
    {
        $this->autorizar();
        $dependencia = $this->contexto->requerida();

        $rol = RolDependencia::from($request->string('rol')->toString());

        // firstOrCreate y no firstOrNew: una cuenta que ya existe pertenece
        // también a otras dependencias, y darle acceso aquí no autoriza a
        // tocarle el nombre, el cargo ni el estado. Si ya existe se devuelve
        // intacta —los valores de abajo se ignoran— y lo único que cambia es
        // la pivote. Además resuelve solo la carrera de dos administradores
        // dando de alta el mismo documento a la vez.
        $usuario = User::firstOrCreate(['documento' => $request->string('documento')->toString()], [
            'name' => $request->string('name')->toString(),
            'email' => $request->input('email'),
            'cargo' => $request->input('cargo'),
            'activo' => $request->boolean('activo', true),
            // El cast 'hashed' del modelo la cifra al asignarla.
            'password' => $request->string('password')->toString(),
        ]);

        $esCuentaNueva = $usuario->wasRecentlyCreated;

        // Un usuario puede pertenecer a varias dependencias con roles distintos.
        $usuario->dependencias()->syncWithoutDetaching([
            $dependencia->id => ['rol' => $rol->value, 'aprueba_novedades' => $request->boolean('aprueba_novedades')],
        ]);

        $this->auditor->registrar(
            AccionAuditoria::UsuarioCreado,
            $usuario,
            $esCuentaNueva
                ? "Creó la cuenta de {$usuario->name} con rol {$rol->etiqueta()}"
                : "Dio acceso a {$usuario->name}, que ya tenía cuenta, con rol {$rol->etiqueta()}",
        );

        // Solo la cuenta nueva: a quien ya existía se le sumó una dependencia,
        // y su identidad en Authentik —si la tiene— ya está creada, con su
        // propia clave, que aquí no se toca. El método es idempotente de todos
        // modos, pero la intención se lee mejor aquí.
        //
        // La contraseña va en claro y por separado del modelo a propósito: en
        // el objeto ya está cifrada por el cast, y Authentik necesita la que
        // tecleó el administrador para que la persona entre con ella.
        if ($esCuentaNueva) {
            $this->authentik->crear($usuario, $request->string('password')->toString());
        }

        $redireccion = redirect()
            ->route('admin.usuarios.index')
            ->with('exito', $esCuentaNueva ? 'Usuario creado.' : "Acceso asignado a {$usuario->name}.");

        // El aviso va por 'error' y no pegado al de éxito: es rojo, se lee
        // aparte, y no se disfraza de confirmación.
        if ($esCuentaNueva && blank($usuario->authentik_id)) {
            $redireccion->with('error', $this->avisoDeAuthentik());
        }

        return $redireccion;
    }

    public function edit(Request $request, User $usuario): View
    {
        $this->autorizar();
        $this->verificarPertenencia($usuario);
        $this->verificarRango($request, $usuario);

        return view('admin.usuarios.edit', [
            'usuario' => $usuario,
            'rolActualUsuario' => $usuario->rolEn($this->contexto->id()),
            'apruebaNovedades' => (bool) $usuario->dependencias()->whereKey($this->contexto->id())->first()?->pivot->aprueba_novedades,
            'roles' => $this->rolesAsignables($request),
        ]);
    }

    public function update(GuardarUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $this->autorizar();
        $this->verificarPertenencia($usuario);
        $this->verificarRango($request, $usuario);

        $usuario->fill([
            'name' => $request->string('name'),
            'documento' => $request->string('documento'),
            'email' => $request->input('email'),
            'cargo' => $request->input('cargo'),
            'activo' => $request->boolean('activo'),
        ]);

        // En claro solo de paso, para poder fijarla también en Authentik: en
        // el modelo entra cifrada y de ahí ya no se puede releer.
        $contrasena = $request->filled('password') ? $request->string('password')->toString() : null;

        if ($contrasena !== null) {
            $usuario->password = Hash::make($contrasena);
        }

        $usuario->save();

        $usuario->dependencias()->updateExistingPivot(
            $this->contexto->id(),
            ['rol' => $request->string('rol')->toString(), 'aprueba_novedades' => $request->boolean('aprueba_novedades')],
        );

        $this->auditor->registrar(
            AccionAuditoria::UsuarioActualizado,
            $usuario,
            "Actualizó el acceso de {$usuario->name}",
        );

        // La ficha entera viaja de vuelta, con el estado incluido: el
        // documento corregido aquí es el username de allá, y una cuenta
        // apagada aquí tiene que quedar apagada allá o deja un ingreso que
        // nadie vigila. Si no tiene identidad, esto no hace nada.
        $this->authentik->actualizar($usuario, $contrasena);

        // Y si no la tiene —porque Authentik no respondió el día del alta, o
        // porque la cuenta es anterior a todo esto—, guardar la ficha es el
        // reintento. Hace falta contraseña: allá no sirve de nada una
        // identidad que no puede entrar, y la de aquí está cifrada y no se
        // puede releer. A una cuenta apagada tampoco se le crea: para eso se
        // la apagó.
        $faltaIdentidad = blank($usuario->authentik_id) && $usuario->activo;

        if ($faltaIdentidad && $contrasena !== null) {
            $this->authentik->crear($usuario, $contrasena);
        }

        $redireccion = redirect()
            ->route('admin.usuarios.index')
            ->with('exito', 'Usuario actualizado.');

        // Se vuelve a mirar después del intento, no antes: si la identidad se
        // creó recién, no hay nada que avisar.
        if (blank($usuario->authentik_id) && $usuario->activo) {
            $redireccion->with('error', $contrasena === null
                ? "{$usuario->name} todavía no existe en Authentik: ponle una contraseña aquí para crearlo."
                : $this->avisoDeAuthentik());
        }

        return $redireccion;
    }

    /** Quita el acceso a esta dependencia sin borrar la cuenta. */
    public function revocar(Request $request, User $usuario): RedirectResponse
    {
        $this->autorizar();
        $this->verificarPertenencia($usuario);
        $this->verificarRango($request, $usuario);

        abort_if($usuario->id === $request->user()->id, 403, 'No puedes revocar tu propio acceso.');

        $usuario->dependencias()->detach($this->contexto->id());

        $this->auditor->registrar(
            AccionAuditoria::UsuarioDesactivado,
            $usuario,
            "Revocó el acceso de {$usuario->name} a la dependencia",
        );

        return back()->with('exito', 'Acceso revocado.');
    }

    /**
     * Por qué la cuenta quedó sin identidad en Authentik, en palabras para
     * quien administra.
     *
     * Sin esto el aprovisionamiento falla en silencio: la pantalla dice
     * «Usuario creado», la persona no puede entrar, y nadie sabe por qué
     * hasta que alguien se acuerda de mirar un log.
     */
    protected function avisoDeAuthentik(): string
    {
        if (! $this->authentik->configurado()) {
            return 'No se creó en Authentik, que no está configurado en este servidor.'
                .' Mientras tanto, esa persona no puede ingresar.';
        }

        return 'No se pudo crear en Authentik. Mira el detalle en la auditoría'
            .' y vuelve a guardar esta ficha para reintentarlo.';
    }

    protected function autorizar(): void
    {
        abort_unless($this->contexto->puedeGestionar(), 403, 'No tienes permiso para gestionar usuarios.');
    }

    protected function verificarPertenencia(User $usuario): void
    {
        abort_unless($usuario->perteneceA($this->contexto->id()), 404);
    }

    /**
     * Nadie administra a quien está por encima suyo.
     *
     * Sin esto, Coordinación podría abrir la ficha de un administrador y
     * cambiarle la contraseña, o bajarle el rol: dos formas de quedarse con
     * la dependencia sin tener nunca el permiso para ello.
     */
    protected function verificarRango(Request $request, User $usuario): void
    {
        $actor = $request->user();

        // La cuenta superadmin es de plataforma, no de la dependencia: solo
        // otro superadmin la toca.
        abort_if(
            $usuario->es_superadmin && ! $actor->es_superadmin,
            403,
            'Esa cuenta es de plataforma y no se gestiona desde aquí.',
        );

        $mio = $actor->rolEn($this->contexto->id())?->nivel() ?? 0;
        $suyo = $usuario->rolEn($this->contexto->id())?->nivel() ?? 0;

        abort_if($suyo > $mio, 403, 'No puedes gestionar a alguien con un rol por encima del tuyo.');
    }

    /** Los roles que este usuario puede repartir, nunca por encima del suyo. */
    protected function rolesAsignables(Request $request): array
    {
        return $request->user()->rolEn($this->contexto->id())?->asignables() ?? [];
    }
}
