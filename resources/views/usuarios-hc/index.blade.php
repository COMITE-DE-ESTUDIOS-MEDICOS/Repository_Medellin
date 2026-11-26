{{-- DOC-26 (UHC-06): la lista de Usuarios HC, tabla de Filament dentro de Documenta. --}}
@extends('layouts.app')
@section('titulo', 'Usuarios HC')

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

<div class="mb-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Usuarios HC</h1>
    <p class="text-sm text-[var(--muted)]">Las personas atendidas en la dependencia, de todos los componentes. Búscala por nombre, alias, documento o número de ficha antes de registrarla, para no repetir su ficha.</p>
</div>

@livewire(\App\Livewire\UsuariosHc\Listado::class)

@endsection
