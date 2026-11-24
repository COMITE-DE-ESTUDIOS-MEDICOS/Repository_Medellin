{{--
    Ficha de un usuario, para dar acceso y para editar. Va en tarjetas por
    tema (persona, rol, contraseña) con un panel al lado que resume la cuenta
    y lleva los botones. Los nombres de los campos son los que espera
    GuardarUsuarioRequest.
--}}
@php
    $u = $usuario ?? null;
    $esNuevo = $u === null;
    $rolSeleccionado = old('rol', ($rolActualUsuario ?? null)?->value);
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $error = 'mt-1 text-xs font-medium text-[var(--danger)]';
    $tarjeta = 'rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)] sm:p-6';
    $iniciales = $u ? collect(explode(' ', $u->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join('') : '+';

    // DOC-25 (ACC-08): el componente. Lo valida GuardarUsuarioRequest; aquí solo se presenta.
    $actor = auth()->user();
    $componentes ??= collect();
    $componenteSeleccionado = (int) old('componente_id', $componenteActualUsuario ?? null);
    // En su propia ficha nadie cambia su acceso: rol y componente se muestran, no se eligen.
    $fichaPropia = $u?->is($actor) && ! $actor->es_superadmin;
    // Coordinación pone a las personas solo en su componente: va fijo.
    $componenteFijo = ! $actor->puedeAdministrarEn($dependenciaActual->id);
    // Su componente aunque esté inactivo (la lista de asignables solo trae activos).
    $componenteDeLaFicha = ($componenteActualUsuario ?? null)
        ? \App\Models\Componente::withoutGlobalScope(\App\Models\Scopes\ComponenteScope::class)->find($componenteActualUsuario)
        : null;
    $suComponenteInactivo = $componenteDeLaFicha && ! $componentes->contains('id', $componenteDeLaFicha->id);
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">

        {{-- 1. La persona --}}
        <x-seccion-ficha icono="persona" titulo="Datos de la persona" descripcion="El usuario es con el que entra por Authentik. Corregirlo aquí no lo cambia allá.">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="{{ $etiqueta }}">Nombre completo *</label>
                    <input id="name" name="name" type="text" required value="{{ old('name', $u?->name) }}" class="{{ $campo }}" autocomplete="off">
                    @error('name')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="documento" class="{{ $etiqueta }}">Usuario *</label>
                    <input id="documento" name="documento" type="text" required maxlength="30"
                           value="{{ old('documento', $u?->documento) }}" class="{{ $campo }}" autocomplete="off">
                    @error('documento')
                        <p class="{{ $error }}">{{ $message }}</p>
                    @else
                        <p class="{{ $ayuda }}">{{ ($usuario ?? null)?->es_superadmin ? 'El usuario del superadministrador en Authentik (p. ej. adminDocumenta): letras y números, sin espacios, tal cual.' : 'Por norma interna, la cédula de la persona: solo números, sin puntos ni espacios. Obligatorio con acceso web.' }}{{ $esNuevo ? ' Si ya tiene cuenta en otra dependencia, se le suma esta.' : '' }}</p>
                    @enderror
                </div>

                @if($u?->es_superadmin)
                    <div>
                        <label for="usuario" class="{{ $etiqueta }}">Alias de emergencia (opcional)</label>
                        <input id="usuario" name="usuario" type="text" maxlength="50"
                               value="{{ old('usuario', $u->usuario) }}" class="{{ $campo }}" autocomplete="off">
                        @error('usuario')
                            <p class="{{ $error }}">{{ $message }}</p>
                        @else
                            <p class="{{ $ayuda }}">Solo para entrar por el acceso de emergencia (administración). Las demás personas entran con la cuenta institucional.</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <label for="cargo" class="{{ $etiqueta }}">Cargo</label>
                    <input id="cargo" name="cargo" type="text" value="{{ old('cargo', $u?->cargo) }}" class="{{ $campo }}" placeholder="Ej: Líder de nodo">
                    @error('cargo')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="email" class="{{ $etiqueta }}">Correo <span class="font-normal text-[var(--muted)]">(opcional)</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $u?->email) }}" class="{{ $campo }}" placeholder="nombre@comitedeestudiosmedicos.com">
                    @error('email')
                        <p class="{{ $error }}">{{ $message }}</p>
                    @else
                        <p class="{{ $ayuda }}">Dato de contacto. No hace falta para entrar.</p>
                    @enderror
                </div>
            </div>
        </x-seccion-ficha>

        {{-- 2. Qué puede hacer --}}
        <x-seccion-ficha icono="escudo" :titulo="'Rol en '.$dependenciaActual->nombre.' *'"
            :descripcion="$fichaPropia
                ? 'Tu rol, tu componente y el estado de tu cuenta los cambia otra persona: Coordinación o Administración, y en las cuentas de Administración, el superadministrador.'
                : 'Solo para esta dependencia. Nunca un rol por encima del tuyo; Administración solo la da el superadministrador.'">
          <div class="group/rol">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($roles as $rol)
                    <label class="relative flex cursor-pointer flex-col gap-1 rounded-lg p-4 ring-1 ring-[var(--border)] transition hover:bg-[var(--card-soft)]
                                  has-[:checked]:bg-[var(--card-soft)] has-[:checked]:ring-2 has-[:checked]:ring-[var(--primary)]">
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-[var(--text)]">{{ $rol->etiqueta() }}</span>
                            <input type="radio" name="rol" value="{{ $rol->value }}" required class="accent-[var(--primary)]"
                                   @checked($rolSeleccionado === $rol->value)>
                        </span>
                        <span class="text-xs leading-relaxed text-[var(--muted)]">{{ $rol->descripcion() }}</span>
                    </label>
                @endforeach
            </div>
            @error('rol')<p class="{{ $error }}">{{ $message }}</p>@enderror

            {{-- DOC-25: el componente en que trabaja. Administración no tiene: trabaja en todos. --}}
            <div class="mt-4" data-campo-componente>
                @if($fichaPropia)
                    <p class="{{ $etiqueta }}">Componente</p>
                    <p class="mt-1 text-sm text-[var(--text)]">
                        @if($rolActualUsuario === \App\Enums\RolDependencia::Administracion)
                            Todos: Administración no tiene componente.
                        @elseif($componenteDeLaFicha)
                            {{ $componenteDeLaFicha->nombre }}{{ $suComponenteInactivo ? ' (inactivo)' : '' }}
                        @else
                            Sin componente asignado
                        @endif
                    </p>
                @else
                    {{-- Con el rol Administración marcado, el campo no aplica (el servidor no lo guarda). --}}
                    <p class="hidden text-sm text-[var(--muted)] group-has-[[value=administracion]:checked]/rol:block" data-componente-administracion>
                        <span class="font-medium text-[var(--text)]">Componente:</span> ninguno. Administración no tiene componente: trabaja en todos los de la dependencia.
                    </p>

                    <div class="group-has-[[value=administracion]:checked]/rol:hidden">
                        @if($componentes->isEmpty())
                            <p class="{{ $etiqueta }}">Componente *</p>
                            <p class="mt-1 text-sm text-[var(--warning)]">
                                {{ $componenteFijo ? 'No tienes un componente activo a cargo: pide a Administración que revise el tuyo.' : 'Esta dependencia no tiene componentes activos todavía.' }}
                            </p>
                        @elseif($componenteFijo)
                            <p class="{{ $etiqueta }}">Componente</p>
                            <p class="mt-1 text-sm text-[var(--text)]">{{ $componentes->first()->nombre }}</p>
                            <input type="hidden" name="componente_id" value="{{ $componentes->first()->id }}">
                            <p class="{{ $ayuda }}">Las personas que gestionas quedan en tu componente.</p>
                        @else
                            <label for="componente_id" class="{{ $etiqueta }}">Componente *</label>
                            <select id="componente_id" name="componente_id" class="{{ $campo }}">
                                <option value="">Elige el componente…</option>
                                @foreach($componentes as $componente)
                                    <option value="{{ $componente->id }}" @selected($componenteSeleccionado === $componente->id)>{{ $componente->nombre }}</option>
                                @endforeach
                            </select>
                            @if($suComponenteInactivo)
                                <p class="mt-1 text-xs font-medium text-[var(--warning)]" data-componente-inactivo>
                                    Su componente ({{ $componenteDeLaFicha->nombre }}) está inactivo: elige otro para guardar.
                                </p>
                            @endif
                            @unless($errors->has('componente_id'))
                                <p class="{{ $ayuda }}">La persona ve y trabaja solo en este componente: sus módulos, sus personas de campo y sus registros.</p>
                            @endunless
                        @endif
                        @error('componente_id')<p class="{{ $error }}">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>
          </div>

            <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-lg bg-[var(--card-soft)] p-4 ring-1 ring-[var(--border)]">
                <input type="hidden" name="aprueba_novedades" value="0">
                <input type="checkbox" name="aprueba_novedades" value="1" class="mt-0.5 size-4 accent-[var(--primary)]"
                       @checked(old('aprueba_novedades', $apruebaNovedades ?? false))>
                <span>
                    <span class="block text-sm font-medium text-[var(--text)]">Aprueba novedades de turno</span>
                    <span class="block text-xs text-[var(--muted)]">
                        Para Edición: aprueba, rechaza y justifica novedades, y le llegan por la campana.
                        Coordinación y Administración siempre pueden.
                    </span>
                </span>
            </label>
        </x-seccion-ficha>

        {{-- 3. Contraseña: la gestiona Authentik; Documenta no la toca (DOC-22). --}}
        <x-seccion-ficha icono="candado" titulo="Contraseña"
            descripcion="La contraseña la define la persona en Authentik. Para una persona nueva, crea en Authentik (Directorio → Usuarios → la persona) un «enlace de recuperación» y entrégaselo." />
    </div>

    {{-- Panel lateral: la cuenta de un vistazo y los botones, siempre a la mano. --}}
    <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
        <div class="{{ $tarjeta }}">
            <div class="flex items-center gap-3">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[var(--primary)] text-base font-semibold uppercase text-white">{{ $iniciales }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-[var(--text)]">{{ $u?->name ?? 'Nueva persona' }}</p>
                    <p class="truncate text-xs text-[var(--muted)]">{{ $u?->documento ?? 'Se crea al guardar' }}</p>
                </div>
            </div>

            @unless($esNuevo)
                <dl class="mt-4 space-y-2 border-t border-[var(--border)] pt-4 text-xs">
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Authentik</dt>
                        <dd class="{{ blank($u->authentik_id) ? 'font-semibold text-[var(--warning)]' : 'text-[var(--success)]' }}">
                            {{ blank($u->authentik_id) ? 'Falta: guarda de nuevo' : 'Conectado' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Último acceso</dt>
                        <dd class="text-[var(--text)]">{{ $u->ultimo_acceso_at?->fechaHora() ?? 'Nunca' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Cuenta creada</dt>
                        <dd class="text-[var(--text)]">{{ $u->created_at?->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            @endunless

            @if($fichaPropia)
                {{-- DOC-25 (P-02): nadie se desactiva a sí mismo; el servidor lo deja activo. --}}
                <div class="mt-4 border-t border-[var(--border)] pt-4" data-estado-propio>
                    <span class="block text-sm font-medium text-[var(--text)]">Cuenta activa</span>
                    <span class="block text-xs text-[var(--muted)]">Es tu propia cuenta: solo otra persona puede desactivarla.</span>
                </div>
            @else
            <label class="mt-4 flex cursor-pointer items-center justify-between gap-3 border-t border-[var(--border)] pt-4">
                <span>
                    <span class="block text-sm font-medium text-[var(--text)]">Cuenta activa</span>
                    <span class="block text-xs text-[var(--muted)]">Apagada, no puede entrar: sale del grupo de Documenta en Authentik. Su cuenta sigue activa para las demás aplicaciones.</span>
                </span>
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $u?->activo ?? true)) class="peer sr-only">
                <span class="relative h-6 w-11 shrink-0 rounded-full bg-[var(--border)] transition peer-checked:bg-[var(--success)]
                             after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-[var(--card)] after:shadow after:transition peer-checked:after:translate-x-5"></span>
            </label>
            @endif
        </div>

        <div class="{{ $tarjeta }} space-y-2">
            <button class="liquid-button-primary w-full rounded-lg px-4 py-2.5 text-sm font-semibold">{{ $boton }}</button>
            <a href="{{ route('admin.usuarios.index') }}"
               class="block w-full rounded-lg px-4 py-2 text-center text-sm text-[var(--muted)] ring-1 ring-[var(--border)] hover:text-[var(--text)]">Cancelar</a>
        </div>
    </aside>
</div>
