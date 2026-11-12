@extends('layouts.app')
@section('titulo', 'Editar carpeta')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
@endphp

<div class="mx-auto max-w-xl space-y-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Editar «{{ $carpeta->nombre }}»</h1>

    <form method="POST" action="{{ route('carpetas.update', $carpeta) }}"
          class="space-y-4 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf @method('PUT')

        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre *</label>
            <input id="nombre" name="nombre" type="text" required maxlength="150"
                   value="{{ old('nombre', $carpeta->nombre) }}"
                   class="{{ $campo }}">
        </div>

        <div>
            <label for="carpeta_id" class="{{ $etiqueta }}">Dentro de</label>
            <select id="carpeta_id" name="carpeta_id" data-buscable class="{{ $campo }}">
                <option value="">Raíz de la dependencia</option>
                <x-opciones-carpeta :carpetas="$carpetas" :seleccionada="old('carpeta_id', $carpeta->carpeta_id)" />
            </select>
        </div>

        {{--
            Custodia: solo Coordinación en adelante la asigna. Quien no puede,
            solo ve si la carpeta está custodiada (y por quién).
        --}}
        @if($grupos->isNotEmpty())
            <div>
                <label for="grupo_custodia_id" class="{{ $etiqueta }}">Custodia</label>
                <select id="grupo_custodia_id" name="grupo_custodia_id" class="{{ $campo }}">
                    <option value="">Sin custodia: la ve toda la dependencia</option>
                    @foreach($grupos as $grupo)
                        <option value="{{ $grupo->id }}" @selected(old('grupo_custodia_id', $carpeta->grupo_custodia_id) == $grupo->id)>{{ $grupo->nombre }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-[var(--muted)]">
                    Bajo custodia, solo los miembros del grupo (y Coordinación y Administración) ven y trabajan esta carpeta
                    y todo lo que tiene dentro.
                    @if($custodia && ! $carpeta->grupo_custodia_id)
                        Ahora la custodia «{{ $custodia->nombre }}» desde una carpeta superior.
                    @endif
                </p>
            </div>
        @elseif($custodia)
            <p class="rounded-md bg-[var(--card-soft)] px-3 py-2 text-sm text-[var(--text)]">
                Bajo custodia de <strong>{{ $custodia->nombre }}</strong>.
            </p>
        @endif

        <div>
            <label for="descripcion" class="{{ $etiqueta }}">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="500"
                      class="{{ $campo }}">{{ old('descripcion', $carpeta->descripcion) }}</textarea>
        </div>

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('documentos.index', ['carpeta' => $carpeta->uuid]) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Guardar</button>
        </div>
    </form>

    @if($carpeta->activa)
        <form method="POST" action="{{ route('carpetas.copiar', $carpeta) }}"
              class="space-y-3 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            @csrf
            <label for="copiar_carpeta" class="{{ $etiqueta }}">Copiar esta carpeta a</label>
            <p class="text-xs text-[var(--muted)]">
                Crea una copia con sus subcarpetas y documentos activos. La original no cambia.
                Para moverla, usa «Dentro de» arriba.
            </p>
            <div class="flex gap-2">
                <select id="copiar_carpeta" name="carpeta_id" data-buscable class="liquid-input min-w-0 flex-1 text-sm">
                    <option value="">Raíz de la dependencia</option>
                    <x-opciones-carpeta :carpetas="$carpetas" />
                </select>
                <button class="rounded-md bg-[var(--card)] px-4 py-2 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                    Copiar
                </button>
            </div>
            @error('carpeta_id')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
        </form>

        @can('inactivar', $carpeta)
            <form method="POST" action="{{ route('carpetas.inactivar', $carpeta) }}"
                  class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                @csrf @method('PATCH')
                <p class="mb-3 text-xs text-[var(--muted)]">
                    Inactivar la carpeta la oculta a lectores y editores. Su contenido se conserva.
                </p>
                <button class="rounded-md bg-[var(--danger)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                    Inactivar carpeta
                </button>
            </form>
        @endcan
    @else
        <div class="liquid-alert liquid-alert-error text-sm">
            <div>
                <p class="font-medium">Carpeta inactiva</p>
                <p class="mt-1">
                    Solo la ve el rol de administración. Su contenido sigue intacto.
                </p>
            </div>
        </div>

        @can('reactivar', $carpeta)
            <form method="POST" action="{{ route('carpetas.reactivar', $carpeta) }}"
                  class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                @csrf @method('PATCH')
                <p class="mb-3 text-xs text-[var(--muted)]">
                    Reactivarla vuelve a mostrarla a lectores y editores.
                </p>
                <button class="rounded-md bg-[var(--success)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                    Reactivar carpeta
                </button>
            </form>
        @endcan
    @endif
</div>

@endsection
