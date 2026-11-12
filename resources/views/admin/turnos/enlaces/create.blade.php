@extends('layouts.app')
@section('titulo', 'Nuevo enlace')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $opcional = '<span class="font-normal text-[var(--muted)]">(opcional)</span>';
@endphp

<div class="mx-auto max-w-xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Nuevo enlace</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        Quien lo abra se identifica con su cédula. El enlace no lleva el nombre de nadie.
    </p>

    <form method="POST" action="{{ route('admin.turnos.enlaces.store') }}"
          class="space-y-4 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf

        <fieldset>
            <legend class="{{ $etiqueta }}">Para qué es *</legend>
            <div class="mt-2 space-y-2">
                @foreach(\App\Models\EnlaceTurno::TIPOS as $valor => $texto)
                    <label class="flex items-center gap-2 text-sm text-[var(--text)]">
                        <input type="radio" name="tipo" value="{{ $valor }}" required
                               @checked(old('tipo', request('tipo', 'turno')) === $valor)>
                        {{ $texto }}
                    </label>
                @endforeach
            </div>
            @can('create', \App\Models\EnlaceCarga::class)
                <p class="{{ $ayuda }}">
                    ¿Para que alguien suba documentos a una carpeta?
                    <a href="{{ route('admin.enlaces.create') }}" class="text-[var(--primary)] underline">Crea un enlace de carga</a>.
                </p>
            @endcan
        </fieldset>

        <div>
            <label for="componente_id" class="{{ $etiqueta }}">Componente *</label>
            <select id="componente_id" name="componente_id" required class="{{ $campo }}">
                @foreach($componentes as $componente)
                    <option value="{{ $componente->id }}" @selected(old('componente_id') == $componente->id)>{{ $componente->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="nodo_id" class="{{ $etiqueta }}">Nodo {!! $opcional !!}</label>
            <select id="nodo_id" name="nodo_id" data-depende-de="#componente_id" class="{{ $campo }}">
                <option value="">Todo el componente</option>
                @foreach($componentes as $componente)
                    @foreach($componente->nodos as $nodo)
                        <option value="{{ $nodo->id }}" data-componente="{{ $componente->id }}" @selected(old('nodo_id') == $nodo->id)>{{ $nodo->nombre }}</option>
                    @endforeach
                @endforeach
            </select>
            <p class="{{ $ayuda }}">
                Con un nodo, el QR es para pegarlo en ese lugar o en ese vehículo, y el nodo ya va elegido al marcar.
            </p>
        </div>

        <div>
            <label for="punto_critico_id" class="{{ $etiqueta }}">Punto crítico {!! $opcional !!}</label>
            <select id="punto_critico_id" name="punto_critico_id" data-depende-de="#componente_id" class="{{ $campo }}">
                <option value="">El habitual de cada persona</option>
                @foreach($componentes as $componente)
                    @foreach($componente->puntosCriticos as $punto)
                        <option value="{{ $punto->id }}" data-componente="{{ $componente->id }}" @selected(old('punto_critico_id') == $punto->id)>{{ $punto->nombre }}</option>
                    @endforeach
                @endforeach
            </select>
            <p class="{{ $ayuda }}">
                Para pegar el QR en un punto: quien marque con él queda en ese punto ese día, aunque su habitual sea otro.
            </p>
        </div>

        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre {!! $opcional !!}</label>
            <input id="nombre" name="nombre" type="text" maxlength="255" value="{{ old('nombre') }}"
                   placeholder="Ej: Microbús placa ABC123" class="{{ $campo }}">
            <p class="{{ $ayuda }}">Para reconocerlo en la lista. Sale también en el QR impreso.</p>
        </div>

        <div>
            <label for="expira_at" class="{{ $etiqueta }}">Vence {!! $opcional !!}</label>
            <input id="expira_at" name="expira_at" type="date" min="{{ now()->toDateString() }}"
                   value="{{ old('expira_at') }}" class="{{ $campo }}">
            <p class="{{ $ayuda }}">Sin fecha, sirve hasta que lo revoques.</p>
        </div>

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.turnos.enlaces.index') }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Crear enlace</button>
        </div>
    </form>
</div>

@endsection
