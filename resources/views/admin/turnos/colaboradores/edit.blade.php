@extends('layouts.app')
@section('titulo', 'Editar persona')

@section('contenido')

@php
    $tarjeta = 'rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)] sm:p-6';
    $iniciales = collect(explode(' ', $colaborador->nombre))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join('');
@endphp

<div class="mx-auto max-w-6xl">
    <div class="mb-4 flex flex-wrap items-center gap-x-1 text-sm text-[var(--muted)]">
        <a href="{{ route('admin.turnos.colaboradores.index') }}" class="hover:text-[var(--text)]">Personas de campo</a>
        <span>/</span>
        <a href="{{ route('admin.turnos.colaboradores.show', $colaborador) }}" class="hover:text-[var(--text)]">{{ $colaborador->nombre }}</a>
        <span>/</span>
        <span class="font-medium text-[var(--text)]">Editar</span>
    </div>
    <h1 class="mb-6 text-lg font-semibold text-[var(--text)]">Editar a {{ $colaborador->nombre }}</h1>

    <div class="grid gap-6 lg:grid-cols-3">
        <form id="ficha-persona" method="POST" action="{{ route('admin.turnos.colaboradores.update', $colaborador) }}" class="lg:col-span-2">
            @csrf @method('PUT')
            @include('admin.turnos.colaboradores._formulario')
        </form>

        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <div class="{{ $tarjeta }}">
                <div class="flex items-center gap-3">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[var(--primary)] text-base font-semibold uppercase text-white">{{ $iniciales }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-[var(--text)]">{{ $colaborador->nombre }}</p>
                        <p class="truncate text-xs text-[var(--muted)]">CC {{ $colaborador->documento }}</p>
                    </div>
                </div>

                <dl class="mt-4 space-y-2 border-t border-[var(--border)] pt-4 text-xs">
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Estado</dt>
                        <dd class="{{ $colaborador->activo ? 'text-[var(--success)]' : 'font-semibold text-[var(--danger)]' }}">{{ $colaborador->activo ? 'Activa' : 'Desactivada' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Verificación</dt>
                        <dd class="{{ $colaborador->estaVerificado() ? 'text-[var(--success)]' : 'font-semibold text-[var(--warning)]' }}">{{ $colaborador->estaVerificado() ? 'Verificada' : 'Por verificar' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Origen</dt>
                        <dd class="text-right text-[var(--text)]">{{ $colaborador->origen?->etiqueta() }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--muted)]">Registrada</dt>
                        <dd class="text-[var(--text)]">{{ $colaborador->created_at?->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="{{ $tarjeta }} space-y-2">
                <button form="ficha-persona" class="liquid-button-primary w-full rounded-lg px-4 py-2.5 text-sm font-semibold">Guardar cambios</button>
                <a href="{{ route('admin.turnos.colaboradores.show', $colaborador) }}"
                   class="block w-full rounded-lg px-4 py-2 text-center text-sm text-[var(--muted)] ring-1 ring-[var(--border)] hover:text-[var(--text)]">Cancelar</a>
            </div>

            {{-- Nunca se borra: sus marcaciones la referencian para siempre. --}}
            <form method="POST" action="{{ route('admin.turnos.colaboradores.estado', $colaborador) }}" class="{{ $tarjeta }}">
                @csrf @method('PATCH')
                @if($colaborador->activo)
                    <p class="text-sm font-semibold text-[var(--text)]">Desactivar</p>
                    <p class="mb-3 mt-1 text-xs text-[var(--muted)]">
                        Los enlaces dejan de reconocer su cédula. Sus marcaciones y evidencias se conservan.
                    </p>
                    <button class="w-full rounded-lg px-4 py-2 text-sm font-medium text-[var(--danger)] ring-1 ring-[var(--danger)] hover:bg-[var(--card-soft)]">
                        Desactivar
                    </button>
                @else
                    <p class="text-sm font-semibold text-[var(--text)]">Está desactivada</p>
                    <p class="mb-3 mt-1 text-xs text-[var(--muted)]">Los enlaces no reconocen su cédula.</p>
                    <button class="w-full rounded-lg bg-[var(--success)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                        Reactivar
                    </button>
                @endif
            </form>
        </aside>
    </div>
</div>

@endsection
