@extends('layouts.app')
@section('titulo', 'Usuarios')

@push('estilos')
    @filamentStyles
    @vite('resources/css/filament.css')
@endpush

@push('scripts')
    @filamentScripts
    @livewire('notifications')
@endpush

@section('contenido')

<div class="mb-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Usuarios de {{ $dependenciaActual->nombre }}</h1>
    <p class="text-sm text-[var(--muted)]">Los accesos se asignan por dependencia, no a todo el Comité. Busca, filtra por rol o estado, y revoca sin salir de la lista.</p>
</div>

@livewire('usuarios')

{{-- Qué puede hacer cada rol, a la vista al decidir cuál dar. --}}
<div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach($roles as $rol)
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <p class="text-sm font-semibold text-[var(--text)]">{{ $rol->etiqueta() }}</p>
            <p class="mt-1 text-xs text-[var(--muted)]">{{ $rol->descripcion() }}</p>
        </div>
    @endforeach
</div>

@endsection
