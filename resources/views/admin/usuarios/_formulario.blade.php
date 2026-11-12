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
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">

        {{-- 1. La persona --}}
        <x-seccion-ficha icono="persona" titulo="Datos de la persona" descripcion="El documento es el usuario con el que entra, aquí y en Authentik.">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="{{ $etiqueta }}">Nombre completo *</label>
                    <input id="name" name="name" type="text" required value="{{ old('name', $u?->name) }}" class="{{ $campo }}" autocomplete="off">
                    @error('name')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="documento" class="{{ $etiqueta }}">Número de documento *</label>
                    <input id="documento" name="documento" type="text" inputmode="numeric" required maxlength="30"
                           value="{{ old('documento', $u?->documento) }}" class="{{ $campo }}" autocomplete="off">
                    @error('documento')
                        <p class="{{ $error }}">{{ $message }}</p>
                    @else
                        <p class="{{ $ayuda }}">Sin puntos ni espacios.{{ $esNuevo ? ' Si ya tiene cuenta en otra dependencia, se le suma esta.' : '' }}</p>
                    @enderror
                </div>

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
        <x-seccion-ficha icono="escudo" :titulo="'Rol en '.$dependenciaActual->nombre.' *'" descripcion="Solo para esta dependencia. Puedes dar hasta tu propio rol.">
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

        {{--
            3. Contraseña. La que se ponga aquí es la misma con la que la
            persona entrará por Authentik: Documenta la crea allá tal cual.
        --}}
        <x-seccion-ficha icono="candado" :titulo="'Contraseña'.($esNuevo ? ' *' : '')"
            :descripcion="($esNuevo ? 'Con esta entra, aquí y en Authentik.' : 'Déjala vacía para no cambiarla.').' Sin largo mínimo (puede ser el documento menos el último dígito). Entrégasela por un medio seguro.'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="{{ $etiqueta }}">{{ $esNuevo ? 'Contraseña' : 'Nueva contraseña' }}</label>
                    <input id="password" name="password" type="password" {{ $esNuevo ? 'required' : '' }} class="{{ $campo }}" autocomplete="new-password">
                    @error('password')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="{{ $etiqueta }}">Confirmar contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="{{ $campo }}" autocomplete="new-password">
                </div>
            </div>
        </x-seccion-ficha>
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
                            {{ blank($u->authentik_id) ? 'Falta: ponle contraseña' : 'Conectado' }}
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

            <label class="mt-4 flex cursor-pointer items-center justify-between gap-3 border-t border-[var(--border)] pt-4">
                <span>
                    <span class="block text-sm font-medium text-[var(--text)]">Cuenta activa</span>
                    <span class="block text-xs text-[var(--muted)]">Apagada, no puede entrar.</span>
                </span>
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $u?->activo ?? true)) class="peer sr-only">
                <span class="relative h-6 w-11 shrink-0 rounded-full bg-[var(--border)] transition peer-checked:bg-[var(--success)]
                             after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-[var(--card)] after:shadow after:transition peer-checked:after:translate-x-5"></span>
            </label>
        </div>

        <div class="{{ $tarjeta }} space-y-2">
            <button class="liquid-button-primary w-full rounded-lg px-4 py-2.5 text-sm font-semibold">{{ $boton }}</button>
            <a href="{{ route('admin.usuarios.index') }}"
               class="block w-full rounded-lg px-4 py-2 text-center text-sm text-[var(--muted)] ring-1 ring-[var(--border)] hover:text-[var(--text)]">Cancelar</a>
        </div>
    </aside>
</div>
