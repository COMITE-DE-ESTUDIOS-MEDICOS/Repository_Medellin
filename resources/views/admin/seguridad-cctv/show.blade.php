@extends('layouts.app')
@section('titulo', 'Caso '.$caso->codigo())

@section('contenido')

@php
    $fila = 'grid gap-1 py-2 sm:grid-cols-3';
    $dt = 'text-xs font-medium uppercase tracking-wide text-[var(--muted)]';
    $dd = 'text-sm text-[var(--text)] sm:col-span-2 whitespace-pre-line';
    $color = ['abierto' => 'var(--warning)', 'atendido' => 'var(--success)', 'cerrado' => 'var(--muted)', 'anulado' => 'var(--danger)'][$caso->estado];
    $nombres = ['soporte' => 'Soporte', 'evidencia1' => 'Evidencia 1', 'evidencia2' => 'Evidencia 2', 'evidencia3' => 'Evidencia 3'];
@endphp

<div class="mb-4 flex flex-wrap items-center gap-3 text-sm text-[var(--muted)]">
    <a href="{{ route('admin.turnos.cctv.index') }}" class="hover:text-[var(--text)]">Seguridad CCTV</a>
    <span>/</span>
    <span class="font-medium text-[var(--text)]">{{ $caso->codigo() }}</span>
    <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" style="color: {{ $color }}; --tw-ring-color: {{ $color }}">{{ ucfirst($caso->estado) }}</span>

    <div class="ml-auto flex flex-wrap gap-2">
        @can('update', $caso)
            <a href="{{ route('admin.turnos.cctv.edit', $caso) }}" class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Corregir</a>
        @endcan
        @can('cerrar', $caso)
            <form method="POST" action="{{ route('admin.turnos.cctv.cerrar', $caso) }}"
                  onsubmit="return confirm('¿Cerrar {{ $caso->codigo() }}? Ya no se podrá corregir.')">
                @csrf @method('PATCH')
                <button class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)]">Cerrar caso</button>
            </form>
        @endcan
    </div>
</div>

@if(session('exito'))
    <div class="liquid-alert liquid-alert-success mb-4 text-sm">{{ session('exito') }}</div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <dl class="divide-y divide-[var(--border)] rounded-lg bg-[var(--card)] px-4 py-2 ring-1 ring-[var(--border)] sm:px-6 lg:col-span-2">
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Registrado por</dt><dd class="{{ $dd }}">{{ $caso->creador?->name ?? '—' }} · {{ $caso->created_at->fechaHora() }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Quién lo pide</dt><dd class="{{ $dd }}">{{ $caso->entidadLegible() }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Inicio del tiempo</dt><dd class="{{ $dd }}">{{ $caso->requerido_at->fechaHora() }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Descripción</dt><dd class="{{ $dd }}">{{ $caso->descripcion }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Ubicación</dt><dd class="{{ $dd }}">{{ $caso->ubicacion }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Comuna</dt><dd class="{{ $dd }}">{{ $caso->nombreComuna() }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Se le pasó a</dt><dd class="{{ $dd }}">{{ $caso->asignado?->nombre ?? '—' }}</dd></div>
        <div class="{{ $fila }}">
            <dt class="{{ $dt }}">Quién lo atendió</dt>
            <dd class="{{ $dd }}">
                @if($caso->atendido_at)
                    {{ $caso->nombre }} · CC {{ $caso->cedula }}{{ $caso->cargo ? ' · '.$caso->cargo : '' }}
                    <span class="block text-xs text-[var(--muted)]">{{ $caso->nodo?->nombre }}</span>
                @else
                    <span class="text-[var(--muted)]">Todavía nadie: el caso está abierto.</span>
                @endif
            </dd>
        </div>
        <div class="{{ $fila }}">
            <dt class="{{ $dt }}">Tiempo de respuesta</dt>
            <dd class="{{ $dd }}">
                @if($caso->atendido_at)
                    Atendido el {{ $caso->atendido_at->fechaHora() }} ·
                @else
                    Lleva
                @endif
                <span class="font-semibold">{{ $caso->tiempoLegible() }}</span>
            </dd>
        </div>
        @if($caso->justificacion)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Justificación</dt><dd class="{{ $dd }}">{{ $caso->justificacion }}</dd></div>
        @endif
        @if($caso->cerrado_at)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Cerrado por</dt><dd class="{{ $dd }}">{{ $caso->cerrador?->name ?? '—' }} · {{ $caso->cerrado_at->fechaHora() }}</dd></div>
        @endif
        @if($caso->anulado_at)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Anulado por</dt><dd class="{{ $dd }}">{{ $caso->anulador?->name ?? '—' }} · {{ $caso->anulado_at->fechaHora() }}<span class="mt-1 block text-xs text-[var(--muted)]">Motivo: {{ $caso->motivo_anulacion }}</span></dd></div>
        @endif
    </dl>

    <div class="space-y-4">
        @can('verImagenes', $caso)
            <section class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                <h2 class="text-sm font-semibold text-[var(--text)]">Imágenes</h2>
                @forelse($archivos as $campo => $documento)
                    <a href="{{ route('admin.turnos.cctv.archivo', [$caso, $campo]) }}" target="_blank" rel="noopener"
                       class="mt-2 block text-sm text-[var(--primary)] hover:underline">{{ $nombres[$campo] }}</a>
                @empty
                    <p class="mt-2 text-xs text-[var(--muted)]">Este caso no tiene imágenes.</p>
                @endforelse
            </section>
        @endcan

        @can('anular', $caso)
            <details class="rounded-lg bg-[var(--card)] ring-1 ring-[var(--danger)]" @if($errors->has('motivo')) open @endif>
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-[var(--danger)]">Anular el caso</summary>
                <form method="POST" action="{{ route('admin.turnos.cctv.anular', $caso) }}" class="space-y-3 border-t border-[var(--border)] p-4">
                    @csrf @method('PATCH')
                    <p class="text-xs text-[var(--muted)]">El caso no se borra: queda anulado, sale de los reportes y la acción queda en la auditoría.</p>
                    <label for="motivo" class="block text-sm font-medium text-[var(--text)]">Motivo *</label>
                    <textarea id="motivo" name="motivo" rows="3" required maxlength="500" class="liquid-input w-full text-sm">{{ old('motivo') }}</textarea>
                    @error('motivo')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                    <button class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--danger)] ring-1 ring-[var(--danger)]">Anular</button>
                </form>
            </details>
        @endcan
    </div>
</div>
@endsection
