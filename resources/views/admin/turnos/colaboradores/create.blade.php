@extends('layouts.app')
@section('titulo', 'Registrar persona')

@section('contenido')

@php($tarjeta = 'rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)] sm:p-6')

<div class="mx-auto max-w-6xl">
    <div class="mb-4 flex flex-wrap items-center gap-x-1 text-sm text-[var(--muted)]">
        <a href="{{ route('admin.turnos.colaboradores.index') }}" class="hover:text-[var(--text)]">Personas de campo</a>
        <span>/</span>
        <span class="font-medium text-[var(--text)]">Registrar persona</span>
    </div>
    <h1 class="mb-6 text-lg font-semibold text-[var(--text)]">Registrar persona de campo</h1>

    <div class="grid gap-6 lg:grid-cols-3">
        <form id="ficha-persona" method="POST" action="{{ route('admin.turnos.colaboradores.store') }}" class="lg:col-span-2">
            @csrf
            @include('admin.turnos.colaboradores._formulario')
        </form>

        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <div class="{{ $tarjeta }}">
                <div class="flex items-center gap-3">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[var(--primary)] text-xl font-semibold text-white">+</span>
                    <div>
                        <p class="text-sm font-semibold text-[var(--text)]">Nueva persona</p>
                        <p class="text-xs text-[var(--muted)]">Queda verificada desde el principio.</p>
                    </div>
                </div>
                <p class="mt-4 border-t border-[var(--border)] pt-4 text-xs text-[var(--muted)]">
                    No hace falta registrar a todos: quien use un enlace por primera vez se registra solo y queda por verificar.
                    Para muchas a la vez, usa la carga masiva desde la lista.
                </p>
            </div>

            <div class="{{ $tarjeta }} space-y-2">
                <button form="ficha-persona" class="liquid-button-primary w-full rounded-lg px-4 py-2.5 text-sm font-semibold">Registrar persona</button>
                <a href="{{ route('admin.turnos.colaboradores.index') }}"
                   class="block w-full rounded-lg px-4 py-2 text-center text-sm text-[var(--muted)] ring-1 ring-[var(--border)] hover:text-[var(--text)]">Cancelar</a>
            </div>
        </aside>
    </div>
</div>

@endsection
