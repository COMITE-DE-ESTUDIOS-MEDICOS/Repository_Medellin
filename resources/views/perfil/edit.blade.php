@extends('layouts.app')
@section('titulo', 'Mi perfil')

@section('contenido')

@php
    $yo = auth()->user();
    $temaActual = $yo->tema ?? \App\Enums\TemaInterfaz::Sistema;
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $error = 'mt-1 text-xs font-medium text-[var(--danger)]';
    $tarjeta = 'rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)] sm:p-6';
    $boton = 'liquid-button-primary rounded-lg px-4 py-2 text-sm font-semibold';
    $iniciales = collect(explode(' ', $yo->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join('');
    // El dibujo de cada tema: sol, luna, pantalla.
    $iconoTema = [
        'claro' => 'M12 3v2.25m6.36.39-1.59 1.59M21 12h-2.25m-.39 6.36-1.59-1.59M12 18.75V21m-4.77-4.23-1.59 1.59M5.25 12H3m4.23-4.77L5.64 5.64M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z',
        'oscuro' => 'M21.75 15A9.72 9.72 0 0 1 18 15.75 9.75 9.75 0 0 1 8.25 6c0-1.33.27-2.6.75-3.75A9.75 9.75 0 0 0 3 11.25 9.75 9.75 0 0 0 12.75 21a9.75 9.75 0 0 0 9-6Z',
        'sistema' => 'M9 17.25v1.01a3 3 0 0 1-.88 2.12L7.5 21h9l-.62-.62A3 3 0 0 1 15 18.26v-1.01m5.25-13.5v10.5a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 6 3h12a2.25 2.25 0 0 1 2.25 2.25Z',
    ];
@endphp

<div class="mx-auto max-w-6xl">
    <h1 class="mb-6 text-lg font-semibold text-[var(--text)]">Mi perfil</h1>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- La cuenta de un vistazo. --}}
        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <div class="{{ $tarjeta }}">
                <div class="flex flex-col items-center text-center">
                    <span class="flex size-20 items-center justify-center rounded-full bg-[var(--primary)] text-2xl font-semibold uppercase text-white">{{ $iniciales }}</span>
                    <p class="mt-3 text-base font-semibold text-[var(--text)]">{{ $yo->name }}</p>
                    <p class="text-sm text-[var(--muted)]">{{ $yo->cargo ?: 'Sin cargo' }}</p>
                    @if($rolActual)
                        <span class="mt-2 rounded-full px-2.5 py-0.5 text-xs font-semibold text-[var(--primary)] ring-1 ring-[var(--primary)]">{{ $rolActual->etiqueta() }}</span>
                    @endif
                </div>

                <dl class="mt-5 space-y-2 border-t border-[var(--border)] pt-4 text-xs">
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Documento</dt>
                        <dd class="text-[var(--text)]">{{ $yo->documento ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Dependencia</dt>
                        <dd class="text-right text-[var(--text)]">{{ $dependenciaActual?->nombre ?? '—' }}</dd>
                    </div>
                    @if($yo->email)
                        <div class="flex justify-between gap-2">
                            <dt class="text-[var(--muted)]">Correo</dt>
                            <dd class="truncate text-[var(--text)]">{{ $yo->email }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Último acceso</dt>
                        <dd class="text-[var(--text)]">{{ $yo->ultimo_acceso_at?->fechaHora() ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-[var(--muted)]">El documento y el rol los cambia quien administra los usuarios.</p>
            </div>
        </aside>

        <div class="space-y-6 lg:col-span-2">

            <x-seccion-ficha icono="persona" titulo="Mis datos" descripcion="Cómo te ven los demás en Documenta: autoría de archivos, comentarios y auditoría.">
                <form method="POST" action="{{ route('perfil.update') }}">
                    @csrf @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="name" class="{{ $etiqueta }}">Nombre</label>
                            <input id="name" name="name" type="text" required value="{{ old('name', $yo->name) }}" class="{{ $campo }}">
                            @error('name')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="cargo" class="{{ $etiqueta }}">Cargo</label>
                            <input id="cargo" name="cargo" type="text" value="{{ old('cargo', $yo->cargo) }}" class="{{ $campo }}" placeholder="Ej: Líder de nodo">
                            @error('cargo')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end">
                        <button class="{{ $boton }}">Guardar</button>
                    </div>
                </form>
            </x-seccion-ficha>

            <x-seccion-ficha icono="paleta" titulo="Apariencia" descripcion="Se guarda en tu cuenta: te acompaña en cualquier equipo donde entres.">
                <form method="POST" action="{{ route('perfil.tema') }}">
                    @csrf @method('PUT')
                    <fieldset>
                        <legend class="sr-only">Tema de la interfaz</legend>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach(\App\Enums\TemaInterfaz::cases() as $tema)
                                <label class="flex cursor-pointer flex-col items-center gap-2 rounded-lg p-4 text-center ring-1 ring-[var(--border)] transition hover:bg-[var(--card-soft)]
                                              has-[:checked]:bg-[var(--card-soft)] has-[:checked]:ring-2 has-[:checked]:ring-[var(--primary)]">
                                    <input type="radio" name="tema" value="{{ $tema->value }}" required class="sr-only"
                                           @checked(old('tema', $temaActual->value) === $tema->value)>
                                    <svg class="size-7 text-[var(--primary)]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconoTema[$tema->value] ?? $iconoTema['sistema'] }}"/>
                                    </svg>
                                    <span class="text-sm font-semibold text-[var(--text)]">{{ $tema->etiqueta() }}</span>
                                    <span class="text-xs text-[var(--muted)]">{{ $tema->descripcion() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="mt-5 flex justify-end">
                        <button class="{{ $boton }}">Guardar apariencia</button>
                    </div>
                </form>
            </x-seccion-ficha>

            <x-seccion-ficha icono="candado" titulo="Cambiar contraseña" descripcion="Sin largo mínimo ni más reglas.">
                <form method="POST" action="{{ route('perfil.password') }}">
                    @csrf @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="password_actual" class="{{ $etiqueta }}">Contraseña actual</label>
                            <input id="password_actual" name="password_actual" type="password" required class="{{ $campo }} sm:w-1/2" autocomplete="current-password">
                            @error('password_actual')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password" class="{{ $etiqueta }}">Contraseña nueva</label>
                            <input id="password" name="password" type="password" required class="{{ $campo }}" autocomplete="new-password">
                            @error('password')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="{{ $etiqueta }}">Confirmar contraseña</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required class="{{ $campo }}" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end">
                        <button class="{{ $boton }}">Actualizar contraseña</button>
                    </div>
                </form>
            </x-seccion-ficha>
        </div>
    </div>
</div>

@endsection
