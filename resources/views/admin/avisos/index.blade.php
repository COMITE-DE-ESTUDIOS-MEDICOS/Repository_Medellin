@extends('layouts.app')
@section('titulo', 'Avisos')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('admin.avisos.store') }}"
          class="space-y-4 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf
        <div>
            <h1 class="text-lg font-semibold text-[var(--text)]">Enviar un aviso</h1>
            <p class="text-sm text-[var(--muted)]">
                Llega por la campana a los usuarios de {{ $dependenciaActual->nombre }}.
                Los cambios de cada versión se anuncian solos.
            </p>
        </div>

        <div>
            <label for="titulo" class="{{ $etiqueta }}">Título *</label>
            <input id="titulo" name="titulo" required maxlength="120" value="{{ old('titulo') }}"
                   placeholder="Ej: Mantenimiento programado" class="{{ $campo }}">
        </div>

        <div>
            <label for="mensaje" class="{{ $etiqueta }}">Mensaje *</label>
            <textarea id="mensaje" name="mensaje" rows="4" required maxlength="1000"
                      placeholder="Ej: El sábado de 8 a 10 a. m. Documenta no estará disponible."
                      class="{{ $campo }}">{{ old('mensaje') }}</textarea>
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-[var(--text)]">Para quién</legend>
            <p class="text-xs text-[var(--muted)]">Sin marcar ninguno, va para todos.</p>
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach($roles as $rol)
                    <label class="flex items-center gap-1.5 text-sm text-[var(--text)]">
                        <input type="checkbox" name="roles[]" value="{{ $rol->value }}" class="accent-[var(--primary)]"
                               @checked(in_array($rol->value, old('roles', []), true))>
                        {{ $rol->etiqueta() }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex justify-end border-t border-[var(--border)] pt-4">
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Enviar aviso</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
        <h2 class="border-b border-[var(--border)] px-4 py-3 text-sm font-medium text-[var(--text)]">Enviados</h2>
        <ul class="divide-y divide-[var(--border)] text-sm">
            @forelse($avisos as $aviso)
                <li class="px-4 py-3">
                    <p class="font-medium text-[var(--text)]">{{ $aviso->titulo }}</p>
                    <p class="mt-0.5 break-words text-[var(--muted)]">{{ $aviso->mensaje }}</p>
                    <p class="mt-1 text-xs text-[var(--muted)]">
                        {{ $aviso->created_at->fechaHora() }}
                        · {{ $aviso->version ? 'Automático de versión' : ($aviso->autor?->name ?? '—') }}
                        · {{ $aviso->destinatarios }} {{ $aviso->destinatarios === 1 ? 'persona' : 'personas' }}
                        @if($aviso->roles)
                            · {{ collect($aviso->roles)->map(fn ($r) => \App\Enums\RolDependencia::tryFrom($r)?->etiqueta())->filter()->join(', ') }}
                        @endif
                    </p>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-[var(--muted)]">Aún no se ha enviado ningún aviso.</li>
            @endforelse
        </ul>
    </div>
</div>

@endsection
