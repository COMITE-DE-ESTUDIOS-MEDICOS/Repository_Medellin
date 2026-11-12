@extends('layouts.app')
@section('titulo', $titulo)

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

@isset($volver)
    <div class="mb-4 flex flex-wrap items-center gap-x-1 gap-y-0.5 text-sm text-[var(--muted)]">
        <a href="{{ route($volver[0]) }}" class="hover:text-[var(--text)]">{{ $volver[1] }}</a>
        <span>/</span>
        <span class="font-medium text-[var(--text)]">{{ $titulo }}</span>
    </div>
@endisset

<div class="mb-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">{{ $titulo }}</h1>
    <p class="text-sm text-[var(--muted)]">{{ $descripcion }}</p>
</div>

@livewire($componente)

@endsection
