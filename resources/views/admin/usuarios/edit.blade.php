@extends('layouts.app')
@section('titulo', 'Editar usuario')

@section('contenido')

<div class="mx-auto max-w-6xl">
    <div class="mb-4 flex flex-wrap items-center gap-x-1 text-sm text-[var(--muted)]">
        <a href="{{ route('admin.usuarios.index') }}" class="hover:text-[var(--text)]">Usuarios</a>
        <span>/</span>
        <span class="font-medium text-[var(--text)]">{{ $usuario->name }}</span>
    </div>
    <h1 class="mb-6 text-lg font-semibold text-[var(--text)]">Editar a {{ $usuario->name }}</h1>

    <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}">
        @csrf @method('PUT')
        @include('admin.usuarios._formulario', ['boton' => 'Guardar cambios'])
    </form>
</div>

@endsection
