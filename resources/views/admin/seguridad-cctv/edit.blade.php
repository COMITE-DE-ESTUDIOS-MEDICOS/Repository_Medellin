@extends('layouts.app')
@section('titulo', 'Corregir '.$caso->codigo())

@section('contenido')

@php
    $campo = 'liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
@endphp

<div class="mx-auto max-w-xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Corregir {{ $caso->codigo() }}</h1>
    <p class="mb-5 text-sm text-[var(--muted)]">
        La hora de respuesta y las evidencias no se cambian aquí. Cada corrección queda en la auditoría.
        @if($caso->atendido_at) Fue atendido el {{ $caso->atendido_at->fechaHora() }}. @endif
    </p>

    @if($errors->any())
        <div class="liquid-alert liquid-alert-error mb-4 text-sm">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.turnos.cctv.update', $caso) }}"
          class="space-y-5 rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)]">
        @csrf @method('PUT')

        @include('admin.seguridad-cctv._campos')

        <div>
            <label for="motivo" class="{{ $etiqueta }}">Motivo del cambio de fecha y hora</label>
            <input id="motivo" name="motivo" type="text" maxlength="1000" value="{{ old('motivo') }}" class="{{ $campo }}">
            <p class="mt-1 text-xs text-[var(--muted)]">Obligatorio si cambias la fecha y hora del requerimiento: el tiempo de respuesta se recalcula.</p>
        </div>

        @if($caso->atendido_at)
            <div>
                <label for="justificacion" class="{{ $etiqueta }}">Justificación (opcional)</label>
                <textarea id="justificacion" name="justificacion" rows="3" maxlength="5000" class="{{ $campo }}">{{ old('justificacion', $caso->justificacion) }}</textarea>
            </div>
        @endif

        <div class="flex flex-col-reverse gap-3 border-t border-[var(--border)] pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.turnos.cctv.show', $caso) }}"
               class="rounded-md px-4 py-3 text-center text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-3 text-sm font-medium">Guardar cambios</button>
        </div>
    </form>
</div>

@endsection
