@extends('layouts.app')
@section('titulo', 'Dar acceso')

@section('contenido')

<div class="mx-auto max-w-6xl">
    <div class="mb-4 flex flex-wrap items-center gap-x-1 text-sm text-[var(--muted)]">
        <a href="{{ route('admin.usuarios.index') }}" class="hover:text-[var(--text)]">Usuarios</a>
        <span>/</span>
        <span class="font-medium text-[var(--text)]">Dar acceso</span>
    </div>
    <h1 class="mb-6 text-lg font-semibold text-[var(--text)]">Dar acceso a {{ $dependenciaActual->nombre }}</h1>

    <form method="POST" action="{{ route('admin.usuarios.store') }}">
        @csrf
        @include('admin.usuarios._formulario', ['boton' => 'Habilitar usuario'])
    </form>
</div>

@endsection
