@extends('layouts.app')
@section('titulo', 'En turno ahora')

@section('contenido')

@php
    $campo = 'liquid-input text-sm';
@endphp

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">En turno ahora</h1>
        <p class="text-sm text-[var(--muted)]">
            {{ $abiertas->count() }} {{ $abiertas->count() === 1 ? 'persona marcó entrada' : 'personas marcaron entrada' }} y aún no su salida.
            Actualizado a las {{ now()->hora() }}.
        </p>
    </div>

    <form method="GET" class="ml-auto flex flex-wrap items-end gap-2">
        @can('viewAny', App\Models\PuntoCritico::class)
            <a href="{{ route('admin.turnos.puntos-criticos') }}" class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Puntos críticos</a>
        @endcan
        <select id="filtro-componente" name="componente" class="{{ $campo }}" onchange="this.form.submit()" aria-label="Componente">
            <option value="">Todos los componentes</option>
            @foreach($componentes as $componente)
                <option value="{{ $componente->id }}" @selected(request('componente') == $componente->id)>{{ $componente->nombre }}</option>
            @endforeach
        </select>
        <select name="nodo" data-depende-de="#filtro-componente" class="{{ $campo }}" onchange="this.form.submit()" aria-label="Nodo">
            <option value="">Todos los nodos</option>
            @foreach($componentes as $componente)
                @foreach($componente->nodos as $nodo)
                    <option value="{{ $nodo->id }}" data-componente="{{ $componente->id }}" @selected(request('nodo') == $nodo->id)>{{ $nodo->nombre }}</option>
                @endforeach
            @endforeach
        </select>
    </form>
</div>

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Persona</th>
                <th class="px-4 py-3 font-medium">Dónde</th>
                <th class="px-4 py-3 font-medium">Turno</th>
                <th class="px-4 py-3 font-medium">Entró</th>
                <th class="px-4 py-3 font-medium">Lleva</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($abiertas as $marcacion)
                @php
                    $minutos = $marcacion->minutosDesde();
                    // DOC-21: pasado su tope (cargo o turno), el cierre automático lo dará por «sin salida».
                    $excedido = app(\App\Services\Turnos\Marcador::class)->vencida($marcacion);
                @endphp
                <tr>
                    <td class="px-4 py-3">
                        @can('view', $marcacion->colaborador)<a href="{{ route('admin.turnos.colaboradores.show', $marcacion->colaborador) }}" class="font-medium text-[var(--text)] hover:text-[var(--primary)]">{{ $marcacion->colaborador->nombre }}</a>@else<span class="font-medium text-[var(--text)]">{{ $marcacion->colaborador->nombre }}</span>@endcan
                        <p class="text-xs text-[var(--muted)]">
                            CC {{ $marcacion->colaborador->documento }}{{ $marcacion->colaborador->cargo ? ' · '.$marcacion->colaborador->cargo : '' }}
                        </p>
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">
                        {{ $marcacion->componente->nombre }}{{ $marcacion->nodo ? ' · '.$marcacion->nodo->nombre : '' }}
                        @if($marcacion->puntoCritico)
                            <span class="block text-xs">
                                @if($marcacion->puntoCritico->urlMapa())
                                    <a href="{{ $marcacion->puntoCritico->urlMapa() }}" target="_blank" rel="noopener" class="text-[var(--primary)] hover:underline">{{ $marcacion->puntoCritico->nombre }}</a>
                                @else
                                    {{ $marcacion->puntoCritico->nombre }}
                                @endif
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($marcacion->tipoTurno)
                            <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-semibold text-[var(--text)]">{{ $marcacion->tipoTurno->codigo }}</span>
                            <span class="block text-xs text-[var(--muted)]">{{ $marcacion->tipoTurno->horario() }}</span>
                            @if($marcacion->minutos_tarde > $marcacion->componente->regla('tolerancia_min'))
                                <span class="block text-xs font-medium text-[var(--warning)]">{{ $marcacion->minutos_tarde }} min tarde</span>
                            @endif
                        @else
                            <span class="text-xs text-[var(--muted)]">Sin reconocer</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $marcacion->marcada_at->isToday() ? 'Hoy' : $marcacion->marcada_at->format('d/m') }}
                        {{ $marcacion->marcada_at->hora() }}
                        @if($marcacion->sin_ubicacion)
                            <span class="ml-1 rounded bg-warning-soft px-1.5 py-0.5 text-xs text-[var(--warning)]">Sin ubicación</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 {{ $excedido ? 'font-medium text-[var(--danger)]' : 'text-[var(--muted)]' }}">
                        {{ intdiv($minutos, 60) }} h {{ $minutos % 60 }} min
                        @if($excedido)
                            <span class="block text-xs">Pasó del máximo del turno</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-[var(--muted)]">Nadie está en turno ahora mismo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
