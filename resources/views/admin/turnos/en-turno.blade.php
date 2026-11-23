@extends('layouts.app')
@section('titulo', 'En turno ahora')

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
        <h1 class="text-lg font-semibold text-[var(--text)]">En turno ahora</h1>
        <p class="text-sm text-[var(--muted)]">
            {{ $total }} {{ $total === 1 ? 'persona marcó entrada' : 'personas marcaron entrada' }} y aún no su salida.
            Actualizado a las {{ now()->hora() }}.
        </p>
    </div>

    @can('viewAny', App\Models\PuntoCritico::class)
        <a href="{{ route('admin.turnos.puntos-criticos') }}" class="ml-auto rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Puntos críticos</a>
    @endcan
</div>

@livewire('turnos.en-turno')

@endsection
