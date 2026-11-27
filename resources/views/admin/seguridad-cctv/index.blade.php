@extends('layouts.app')
@section('titulo', 'Seguridad CCTV')

{{-- Solo en esta página: los estilos y scripts de las piezas de Filament. --}}
@push('estilos')
    @filamentStyles
    @vite('resources/css/filament.css')
@endpush

@push('scripts')
    @filamentScripts
    @livewire('notifications')
@endpush

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Seguridad CCTV</h1>
        <p class="text-sm text-[var(--muted)]">
            Los requerimientos de seguridad que la oficina pasa a una persona de campo, con el tiempo que tardó en atenderlos.
        </p>
    </div>
    <div class="ml-auto flex flex-wrap gap-2">
        @can('create', \App\Models\CasoCctv::class)
            <a href="{{ route('admin.turnos.cctv.create') }}"
               class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Nuevo caso</a>
        @endcan
        @can('compartirEnlace', \App\Models\CasoCctv::class)
            <a href="{{ route('admin.turnos.enlaces.create', ['tipo' => 'seguridad_cctv']) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Enlace para atender</a>
        @endcan
        <a href="{{ route('admin.turnos.cctv.reporte') }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Reporte</a>
    </div>
</div>

@livewire('seguridad-cctv.casos')

@endsection
