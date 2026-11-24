<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\Componente;
use App\Models\Scopes\ComponenteScope;
use App\Models\User;
use App\Services\Auditor;
use App\Services\AuthentikProvisioner;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        return view('admin.usuarios.create', [
            'roles' => $this->rolesAsignables($request),
            'componentes' => $request->user()->componentesAsignablesEn($this->contexto->requerida()),
        ]);
    }

    public function store(GuardarUsuarioRequest $request): RedirectResponse
    {
        $this->autorizar();
        $dependencia = $this->contexto->requerida();

        $rol = RolDependencia::from($request->string('rol')->toString());
        $componente = $this->componenteElegido($request, $rol);

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
            // Nadie la conoce: las personas entran por Authentik, donde
            // definen la suya (DOC-22). La columna no admite nulo. El cast
            // 'hashed' del modelo la cifra al asignarla.
            'password' => Str::random(64),
        ]);

        $esCuentaNueva = $usuario->wasRecentlyCreated;

        // Un usuario puede pertenecer a varias dependencias con roles distintos.
        // Quien ya está en esta no llega aquí: GuardarUsuarioRequest lo rechaza.
        $usuario->dependencias()->syncWithoutDetaching([
            $dependencia->id => [
                'rol' => $rol->value,
                'aprueba_novedades' => $request->boolean('aprueba_novedades'),
                'componente_id' => $componente?->id,
            ],
        ]);

        $en = $componente ? " en {$componente->nombre}" : '';

        $this->auditor->registrar(
            AccionAuditoria::UsuarioCreado,
            $usuario,
            $esCuentaNueva
                ? "Creó la cuenta de {$usuario->name} con rol {$rol->etiqueta()}{$en}"
                : "Dio acceso a {$usuario->name}, que ya tenía cuenta, con rol {$rol->etiqueta()}{$en}",
            ['componente' => $componente?->nombre],
        );

        // Nueva o ya existente: si está activa, queda en el grupo de
        // Documenta. Su cuenta de Authentik, si ya existía, no se toca.
        $enAuthentik = $usuario->activo && $this->authentik->darAcceso($usuario);

        $redireccion = redirect()
            ->route('admin.usuarios.index')
            ->with('exito', $esCuentaNueva ? 'Usuario creado.' : "Acceso asignado a {$usuario->name}.");

        // El aviso va por 'error' y no pegado al de éxito: es rojo, se lee
        // aparte, y no se disfraza de confirmación.
        if ($usuario->activo && ! $enAuthentik) {
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
            // En su propia ficha no cambia su acceso: solo ve su rol.
            'roles' => $usuario->is($request->user()) && ! $request->user()->es_superadmin
                ? [$usuario->rolEn($this->contexto->id())]
                : $this->rolesAsignables($request),
            'componentes' => $request->user()->componentesAsignablesEn($this->contexto->requerida()),
            'componenteActualUsuario' => $usuario->componenteEn($this->contexto->id()),
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

        // El alias de emergencia solo es del superadministrador (PA-01).
        if ($usuario->es_superadmin) {
            $usuario->usuario = $request->filled('usuario') ? $request->string('usuario')->toString() : null;
        }

        $usuario->save();

        $rol = RolDependencia::from($request->string('rol')->toString());
        $componente = $this->componenteElegido($request, $rol);
        $antes = $this->componenteDeId($usuario->componenteEn($this->contexto->id()));

        $usuario->dependencias()->updateExistingPivot(
            $this->contexto->id(),
            ['rol' => $rol->value, 'aprueba_novedades' => $request->boolean('aprueba_novedades'), 'componente_id' => $componente?->id],
        );

        $cambio = $antes?->id !== $componente?->id;

        $this->auditor->registrar(
            AccionAuditoria::UsuarioActualizado,
            $usuario,
            "Actualizó el acceso de {$usuario->name}"
                .($cambio ? ": pasó de {$this->nombreDe($antes)} a {$this->nombreDe($componente)}" : ''),
            $cambio ? ['componente' => ['antes' => $antes?->nombre, 'despues' => $componente?->nombre]] : [],
        );

        // Nada de la ficha viaja a Authentik (DOC-22): ni nombre, ni correo,
        // ni documento. Solo el acceso: activa, en el grupo (idempotente, y
        // sirve de reintento); inactiva, fuera del grupo. La cuenta de
        // Authentik nunca se desactiva: perdería las otras apps.
        $enAuthentik = $usuario->activo
            ? $this->authentik->darAcceso($usuario)
            : $this->authentik->quitarAcceso($usuario);

        $redireccion = redirect()
            ->route('admin.usuarios.index')
            ->with('exito', 'Usuario actualizado.');

        if (! $enAuthentik) {
            $redireccion->with('error', $usuario->activo
                ? $this->avisoDeAuthentik()
                : 'No se pudo sacar a esta persona del grupo de Documenta en Authentik: todavía puede entrar.'
                    .' Mira el detalle en la auditoría y vuelve a guardar esta ficha para reintentarlo.');
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

        // Sin ninguna dependencia ya no entra a Documenta: fuera del grupo.
        if (! $usuario->activo || $usuario->dependencias()->doesntExist()) {
            $this->authentik->quitarAcceso($usuario);
        }

        return back()->with('exito', 'Acceso revocado.');
    }

    /**
     * Por qué la persona no quedó en el grupo de Documenta en Authentik, en
     * palabras para quien administra.
     *
     * Sin esto el aprovisionamiento falla en silencio: la pantalla dice
     * «Usuario creado», la persona no puede entrar, y nadie sabe por qué
     * hasta que alguien se acuerda de mirar un log.
     */
    protected function avisoDeAuthentik(): string
    {
        if (! $this->authentik->configurado()) {
            return 'No se le dio acceso en Authentik, que no está configurado en este servidor.'
                .' Mientras tanto, esa persona no puede ingresar.';
        }

        return 'No se pudo dar acceso en Authentik. Mira el detalle en la auditoría'
            .' y vuelve a guardar esta ficha para reintentarlo.';
    }

    protected function autorizar(): void
    {
        abort_unless($this->contexto->puedeGestionar(), 403, 'No tienes permiso para gestionar usuarios.');
    }

    /**
     * Es de esta dependencia y, para quien no es Administración, de su
     * componente (DOC-25): Coordinación de Calle no ve a nadie de Básica.
     */
    protected function verificarPertenencia(User $usuario): void
    {
        $dependencia = $this->contexto->id();
        $actor = auth()->user();

        abort_unless($usuario->perteneceA($dependencia), 404);

        // Las cuentas de Administración (sin componente) las rechaza con 403
        // verificarRango; aquí no se adelanta un 404 que lo esconda.
        if ($actor->puedeAdministrarEn($dependencia) || $usuario->rolEn($dependencia) === RolDependencia::Administracion) {
            return;
        }

        abort_unless($usuario->componenteEn($dependencia) === ($actor->componenteEn($dependencia) ?? 0), 404);
    }

    /**
     * Nadie administra a quien está por encima suyo.
     *
     * Sin esto, Coordinación podría abrir la ficha de un administrador y
     * desactivarlo, o bajarle el rol: dos formas de quedarse con
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

        // DOC-25 (PA-03, P-02 del plan): Administración la da solo el
        // superadministrador, y solo él gestiona esas cuentas (bajarlas incluido).
        // Su propia ficha sí la edita (Edwar 2026-11-24): sin tocar su acceso,
        // que lo fija GuardarUsuarioRequest.
        abort_if(
            $suyo === RolDependencia::Administracion->nivel() && ! $actor->es_superadmin && ! $usuario->is($actor),
            403,
            'Las cuentas de Administración solo las gestiona el superadministrador.',
        );
    }

    /** Los roles que este usuario puede repartir, nunca por encima del suyo. */
    protected function rolesAsignables(Request $request): array
    {
        return $request->user()->rolesAsignablesEn($this->contexto->id());
    }

    /** DOC-25: el componente del formulario (ya validado); Administración, ninguno. */
    protected function componenteElegido(Request $request, RolDependencia $rol): ?Componente
    {
        return $rol === RolDependencia::Administracion ? null : $this->componenteDeId($request->integer('componente_id'));
    }

    /** Sin el scope de componente: Administración asigna cualquiera de la dependencia. */
    protected function componenteDeId(?int $id): ?Componente
    {
        return $id ? Componente::withoutGlobalScope(ComponenteScope::class)->find($id) : null;
    }

    protected function nombreDe(?Componente $componente): string
    {
        return $componente?->nombre ?? 'ningún componente';
    }
}
