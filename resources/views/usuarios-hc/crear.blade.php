{{-- DOC-26 (UHC-07): formulario mínimo para probar el servidor. UHC-08 lo completa (aviso de coincidencias, número que se oculta con «Sin documento», foto, estilos). --}}
@extends('layouts.app')
@section('titulo', 'Nueva ficha')

@section('contenido')
@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $error = 'mt-1 text-xs font-medium text-[var(--danger)]';
@endphp

<div class="mx-auto max-w-2xl">
    <h1 class="mb-4 text-lg font-semibold text-[var(--text)]">Nueva ficha de Usuario HC</h1>

    <form method="POST" action="{{ route('usuarios-hc.store') }}" class="space-y-4 rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)]">
        @csrf

        <div>
            <label for="tipo_documento" class="{{ $etiqueta }}">Tipo de documento</label>
            <select id="tipo_documento" name="tipo_documento" class="{{ $campo }}" required>
                @foreach($tipos as $codigo => $texto)
                    <option value="{{ $codigo }}" @selected(old('tipo_documento') === (string) $codigo)>{{ $texto }}</option>
                @endforeach
            </select>
            @error('tipo_documento')<p class="{{ $error }}">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="numero_documento" class="{{ $etiqueta }}">Número de documento</label>
            <input id="numero_documento" name="numero_documento" value="{{ old('numero_documento') }}" maxlength="20" class="{{ $campo }}">
            @error('numero_documento')<p class="{{ $error }}">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="numero_ficha" class="{{ $etiqueta }}">Número de ficha (opcional)</label>
            <input id="numero_ficha" name="numero_ficha" value="{{ old('numero_ficha') }}" maxlength="30" class="{{ $campo }}">
            @error('numero_ficha')<p class="{{ $error }}">{{ $message }}</p>@enderror
        </div>

        @foreach(['primer_nombre' => 'Primer nombre', 'segundo_nombre' => 'Segundo nombre', 'primer_apellido' => 'Primer apellido', 'segundo_apellido' => 'Segundo apellido', 'alias' => 'Alias'] as $nombre => $texto)
            <div>
                <label for="{{ $nombre }}" class="{{ $etiqueta }}">{{ $texto }}</label>
                <input id="{{ $nombre }}" name="{{ $nombre }}" value="{{ old($nombre) }}" maxlength="100" class="{{ $campo }}" @required($nombre === 'primer_nombre')>
                @error($nombre)<p class="{{ $error }}">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <div class="flex gap-3">
            <button type="submit" class="liquid-button-primary rounded-lg px-4 py-2 text-sm font-semibold">Crear ficha</button>
            <a href="{{ route('usuarios-hc.index') }}" class="rounded-lg px-4 py-2 text-sm text-[var(--muted)]">Cancelar</a>
        </div>
    </form>
</div>
@endsection
