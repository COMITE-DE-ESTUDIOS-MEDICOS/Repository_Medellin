@extends('layouts.app')
@section('titulo', 'Grupos de custodia')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
@endphp

<div class="mb-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Grupos de custodia</h1>
    <p class="text-sm text-[var(--muted)]">
        Una carpeta bajo custodia (y todo lo que tiene dentro) solo la ven y la trabajan los miembros de su grupo,
        además de Coordinación y Administración. Así el líder de un nodo no toca lo de otro.
        Cada miembro hace lo que su rol le permite: con Lectura, revisa sin modificar.
        La custodia se asigna en <strong>Editar carpeta</strong>.
    </p>
</div>

{{-- Un miembro, una casilla. Reutilizado en crear y en cada grupo. --}}
@php
    $miembros = function ($grupo) use ($usuarios) {
        return $usuarios->map(fn ($u) => [
            'usuario' => $u,
            'marcado' => $grupo ? $grupo->usuarios->contains($u) : false,
        ]);
    };
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    @foreach($grupos as $grupo)
        <form method="POST" action="{{ route('admin.custodia.update', $grupo) }}"
              class="space-y-3 rounded-lg bg-[var(--card)] p-5 ring-1 ring-[var(--border)]">
            @csrf @method('PUT')
            <div class="flex items-center gap-2">
                <svg class="size-5 text-[var(--primary)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
                <input name="nombre" required maxlength="100" value="{{ $grupo->nombre }}" aria-label="Nombre del grupo"
                       class="liquid-input flex-1 text-sm font-medium">
            </div>
            <input name="descripcion" maxlength="255" value="{{ $grupo->descripcion }}" placeholder="Para qué es (opcional)"
                   class="{{ $campo }}">

            <fieldset>
                <legend class="text-xs font-medium text-[var(--muted)]">Miembros</legend>
                <div class="sin-barra mt-1 max-h-48 space-y-1 overflow-y-auto rounded-md border border-[var(--border)] p-2">
                    @foreach($miembros($grupo) as $m)
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-[var(--text)]">
                            <input type="checkbox" name="usuarios[]" value="{{ $m['usuario']->id }}" class="accent-[var(--primary)]" @checked($m['marcado'])>
                            {{ $m['usuario']->name }}
                            <span class="text-xs text-[var(--muted)]">{{ \App\Enums\RolDependencia::tryFrom($m['usuario']->pivot->rol)?->etiqueta() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <p class="text-xs text-[var(--muted)]">
                @if($grupo->carpetas->isEmpty())
                    Aún no custodia ninguna carpeta.
                @else
                    Custodia: {{ $grupo->carpetas->pluck('nombre')->join(', ') }}.
                @endif
            </p>

            <div class="flex justify-end">
                <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Guardar</button>
            </div>
        </form>
    @endforeach

    <form method="POST" action="{{ route('admin.custodia.store') }}"
          class="space-y-3 rounded-lg border-2 border-dashed border-[var(--border)] p-5">
        @csrf
        <h2 class="text-sm font-medium text-[var(--text)]">Nuevo grupo</h2>
        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre *</label>
            <input id="nombre" name="nombre" required maxlength="100" value="{{ old('nombre') }}" placeholder="Ej: Líderes Nodo 6" class="{{ $campo }}">
        </div>
        <input name="descripcion" maxlength="255" value="{{ old('descripcion') }}" placeholder="Para qué es (opcional)" class="{{ $campo }}">
        <fieldset>
            <legend class="text-xs font-medium text-[var(--muted)]">Miembros</legend>
            <div class="sin-barra mt-1 max-h-48 space-y-1 overflow-y-auto rounded-md border border-[var(--border)] p-2">
                @foreach($miembros(null) as $m)
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-[var(--text)]">
                        <input type="checkbox" name="usuarios[]" value="{{ $m['usuario']->id }}" class="accent-[var(--primary)]">
                        {{ $m['usuario']->name }}
                        <span class="text-xs text-[var(--muted)]">{{ \App\Enums\RolDependencia::tryFrom($m['usuario']->pivot->rol)?->etiqueta() }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <div class="flex justify-end">
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Crear grupo</button>
        </div>
    </form>
</div>

@endsection
