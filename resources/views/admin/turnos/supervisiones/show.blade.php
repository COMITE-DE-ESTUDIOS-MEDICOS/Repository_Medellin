@extends('layouts.app')
@section('titulo', 'Supervisión #'.$supervision->id)

@section('contenido')

@php
    $s = $supervision;
    $fila = 'grid gap-1 py-2 sm:grid-cols-3';
    $dt = 'text-xs font-medium uppercase tracking-wide text-[var(--muted)]';
    $dd = 'text-sm text-[var(--text)] sm:col-span-2 whitespace-pre-line';
    $color = fn ($n) => $n >= 4 ? 'bg-[var(--success)]' : ($n >= 3 ? 'bg-[var(--warning)]' : 'bg-[var(--danger)]');
    $persona = fn ($c) => $c ? "{$c->nombre} · CC {$c->documento}" : '—';
@endphp

<div class="mb-4 flex flex-wrap items-center gap-x-1 text-sm text-[var(--muted)]">
    <a href="{{ route('admin.turnos.supervisiones.index') }}" class="hover:text-[var(--text)]">Supervisión territorial</a>
    <span>/</span>
    <span class="font-medium text-[var(--text)]">#{{ $s->id }}</span>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-seccion-ficha icono="ubicacion" titulo="La visita" :descripcion="$s->visita_at->format('d/m/Y').' a las '.$s->visita_at->hora()">
            <dl class="divide-y divide-[var(--border)]">
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Supervisó</dt><dd class="{{ $dd }}">{{ $s->supervisor_nombre }} · CC {{ $s->supervisor_cedula }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Dinamizador 1</dt><dd class="{{ $dd }}">{{ $persona($s->dinamizador1) }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Dinamizador 2</dt><dd class="{{ $dd }}">{{ $persona($s->dinamizador2) }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Punto crítico</dt><dd class="{{ $dd }}">{{ $s->puntoNombre() }} · {{ $s->componente->nombre }} · {{ $s->nodo?->nombre }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Turno</dt><dd class="{{ $dd }}">{{ $s->tipoTurno ? $s->tipoTurno->codigo.' · '.$s->tipoTurno->nombre : '—' }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Ubicación</dt>
                    <dd class="text-sm text-[var(--text)] sm:col-span-2">
                        @if($s->mapa())
                            <a href="{{ $s->mapa() }}" target="_blank" rel="noopener" class="text-[var(--primary)] hover:underline">{{ $s->municipio ?? 'Ver en el mapa' }}</a>
                        @else
                            <span class="text-[var(--warning)]">Sin ubicación</span>
                        @endif
                    </dd>
                </div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Descripción del punto</dt><dd class="{{ $dd }}">{{ $s->descripcion_punto }}</dd></div>
            </dl>
        </x-seccion-ficha>

        <x-seccion-ficha icono="escudo" titulo="Calificación" :descripcion="'Promedio '.number_format($s->promedio(), 1, ',').' · global de la dupla '.$s->calificacion_global">
            <div class="space-y-3">
                @foreach(\App\Models\Supervision::CRITERIOS as $criterio => $texto)
                    <div>
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="text-[var(--text)]">{{ $texto }}</span>
                            <span class="font-semibold text-[var(--text)]">{{ $s->{$criterio} }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-[var(--card-soft)] ring-1 ring-[var(--border)]">
                            <div class="h-full rounded-full {{ $color($s->{$criterio}) }}" style="width: {{ $s->{$criterio} * 20 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-seccion-ficha>

        <x-seccion-ficha icono="contacto" titulo="Hallazgos">
            <dl class="divide-y divide-[var(--border)]">
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Incidencias</dt><dd class="{{ $dd }}">{{ \App\Models\Supervision::INCIDENCIAS[$s->incidencia] ?? $s->incidencia }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Casos escalados</dt><dd class="{{ $dd }}">{{ $s->casos_escalados ? 'Sí: '.$s->casos_escalados_cual : 'No' }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Casos prioritarios</dt><dd class="{{ $dd }}">{{ $s->casos_prioritarios ?: '—' }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Recomendaciones</dt><dd class="{{ $dd }}">{{ $s->recomendaciones }}</dd></div>
                <div class="{{ $fila }}"><dt class="{{ $dt }}">Observaciones</dt><dd class="{{ $dd }}">{{ $s->observaciones ?: '—' }}</dd></div>
            </dl>
        </x-seccion-ficha>
    </div>

    <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
        @foreach(['Evidencia 1' => $s->evidencia1, 'Evidencia 2' => $s->evidencia2] as $etiqueta => $documento)
            <div class="rounded-xl bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">{{ $etiqueta }}</p>
                @if($documento)
                    <a href="{{ route('documentos.show', $documento) }}" class="mt-1 block text-sm text-[var(--primary)] hover:underline">{{ $documento->nombre }}</a>
                @else
                    <p class="mt-1 text-sm text-[var(--muted)]">Sin foto</p>
                @endif
            </div>
        @endforeach
        <div class="rounded-xl bg-[var(--card)] p-4 text-xs text-[var(--muted)] ring-1 ring-[var(--border)]">
            Abrió el formulario {{ $s->inicio_at?->hora() ?? '—' }} · lo envió {{ $s->created_at->fechaHora() }}
        </div>
    </aside>
</div>

@endsection
