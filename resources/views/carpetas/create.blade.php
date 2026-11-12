@extends('layouts.app')
@section('titulo', 'Nueva carpeta')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
@endphp

<div class="mx-auto max-w-xl">
    <h1 class="mb-6 text-lg font-semibold text-[var(--text)]">Nueva carpeta</h1>

    <form method="POST" action="{{ route('carpetas.store') }}"
          class="space-y-4 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf

        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre *</label>
            <input id="nombre" name="nombre" type="text" required maxlength="150" value="{{ old('nombre') }}" autofocus
                   class="{{ $campo }}">
        </div>

        <div>
            <label for="carpeta_id" class="{{ $etiqueta }}">Dentro de</label>
            <select id="carpeta_id" name="carpeta_id" data-buscable class="{{ $campo }}">
                <option value="">Raíz de la dependencia</option>
                <x-opciones-carpeta :carpetas="$carpetas" :seleccionada="old('carpeta_id', $carpetaActual?->id)" />
            </select>
        </div>

        <div>
            <label for="descripcion" class="{{ $etiqueta }}">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="500"
                      class="{{ $campo }}">{{ old('descripcion') }}</textarea>
        </div>

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('documentos.index', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Crear carpeta</button>
        </div>
    </form>
</div>

@endsection
