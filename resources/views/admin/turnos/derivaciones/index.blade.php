@extends('layouts.app')
@section('titulo', 'Derivaciones y respuesta territorial')

@section('contenido')

@php($campo = 'liquid-input mt-1 text-sm')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Derivaciones y respuesta territorial</h1>
        <p class="text-sm text-[var(--muted)]">
            Llegan pendientes por el enlace por cédula. Un líder las responde aquí antes de {{ intdiv(\App\Models\Derivacion::META_MINUTOS, 60) }} horas; mientras tanto, el aviso llega cada {{ \App\Models\Derivacion::AVISO_CADA_MIN }} minutos.
        </p>
    </div>
    <div class="ml-auto flex flex-wrap gap-2">
        <a href="{{ route('admin.turnos.derivaciones.exportar', request()->query()) }}" download
           class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Descargar Excel</a>
        <a href="{{ route('admin.turnos.derivaciones.fotos', request()->query()) }}" download
           class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
            Excel + fotos (ZIP)
        </a>
    </div>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Buscar</label>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, CC, entidad…" class="{{ $campo }}">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Nodo</label>
        <select name="nodo" class="{{ $campo }}">
            <option value="">Todos</option>
            @foreach($nodos as $nodo)
                <option value="{{ $nodo->id }}" @selected(request('nodo') == $nodo->id)>{{ $nodo->componente->nombre }} · {{ $nodo->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Estado</label>
        <select name="estado" class="{{ $campo }}">
            <option value="">Todos</option>
            <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendientes</option>
            <option value="solucionada" @selected(request('estado') === 'solucionada')>Solucionadas</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Cumplimiento</label>
        <select name="cumplio" class="{{ $campo }}">
            <option value="">Todos</option>
            <option value="si" @selected(request('cumplio') === 'si')>Cumplió</option>
            <option value="no" @selected(request('cumplio') === 'no')>No cumplió</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Desde</label>
        <input type="date" name="desde" value="{{ request('desde') }}" class="{{ $campo }}">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Hasta</label>
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="{{ $campo }}">
    </div>
    <button class="liquid-button-primary rounded-md px-3 py-1.5 text-sm">Filtrar</button>
    <a href="{{ route('admin.turnos.derivaciones.index') }}" class="text-sm text-[var(--muted)] hover:text-[var(--text)]">Limpiar</a>
</form>

{{-- Trazabilidad: quién ha respondido y quién no, con el tiempo desde la solicitud. --}}
@php($tarjeta = 'rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]')
<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <a href="{{ route('admin.turnos.derivaciones.index', ['estado' => 'pendiente'] + request()->except('estado', 'page')) }}" class="{{ $tarjeta }} hover:bg-[var(--card-soft)]">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Sin respuesta</p>
        <p class="mt-1 text-2xl font-semibold {{ $traza['pendientes'] ? 'text-[var(--warning)]' : 'text-[var(--text)]' }}">{{ $traza['pendientes'] }}</p>
        <p class="text-xs {{ $traza['vencidas'] ? 'font-semibold text-[var(--danger)]' : 'text-[var(--muted)]' }}">{{ $traza['vencidas'] }} {{ $traza['vencidas'] === 1 ? 'vencida' : 'vencidas' }} (más de {{ intdiv(\App\Models\Derivacion::META_MINUTOS, 60) }} h)</p>
    </a>
    <div class="{{ $tarjeta }}">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Respondidas</p>
        <p class="mt-1 text-2xl font-semibold text-[var(--text)]">{{ $traza['solucionadas'] }}</p>
        <p class="text-xs text-[var(--muted)]">{{ $traza['a_tiempo'] }} a tiempo · {{ $traza['solucionadas'] - $traza['a_tiempo'] }} fuera de tiempo</p>
    </div>
    <div class="{{ $tarjeta }}">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Cumplimiento</p>
        <p class="mt-1 text-2xl font-semibold text-[var(--text)]">{{ $traza['solucionadas'] ? round(100 * $traza['a_tiempo'] / $traza['solucionadas']) : 0 }} %</p>
        <p class="text-xs text-[var(--muted)]">Tiempo promedio: {{ $traza['promedio'] ?? '—' }}</p>
    </div>
    <div class="{{ $tarjeta }}">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Nodos con casos sin respuesta</p>
        <ul class="mt-1 space-y-0.5 text-sm">
            @forelse($traza['sin_respuesta'] as $nodo => $fila)
                <li class="flex justify-between gap-2">
                    <span class="truncate text-[var(--text)]">{{ $nodo }}</span>
                    <span class="{{ $fila['vencidas'] ? 'font-semibold text-[var(--danger)]' : 'text-[var(--warning)]' }}">{{ $fila['casos'] }} · {{ \App\Models\Derivacion::duracion($fila['mas_antigua']) }}</span>
                </li>
            @empty
                <li class="text-[var(--success)]">Ninguno: todo respondido.</li>
            @endforelse
        </ul>
    </div>
</div>

@if($traza['respondedores']->isNotEmpty())
    <details class="mb-4 rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-[var(--text)]">Quién ha respondido ({{ $traza['respondedores']->count() }})</summary>
        <table class="min-w-full divide-y divide-[var(--border)] border-t border-[var(--border)] text-sm">
            <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
                <tr>
                    <th class="px-4 py-2 font-medium">Líder</th>
                    <th class="px-4 py-2 font-medium">Respondidas</th>
                    <th class="px-4 py-2 font-medium">A tiempo</th>
                    <th class="px-4 py-2 font-medium">Tiempo promedio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border)]">
                @foreach($traza['respondedores'] as $r)
                    <tr>
                        <td class="px-4 py-2 text-[var(--text)]">{{ $r->nombre }}</td>
                        <td class="px-4 py-2 text-[var(--text)]">{{ $r->total }}</td>
                        <td class="px-4 py-2 {{ $r->a_tiempo < $r->total ? 'text-[var(--danger)]' : 'text-[var(--success)]' }}">{{ $r->a_tiempo }} de {{ $r->total }}</td>
                        <td class="px-4 py-2 text-[var(--muted)]">{{ \App\Models\Derivacion::duracion((int) round($r->promedio)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
@endif

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">#</th>
                <th class="px-4 py-3 font-medium">Requerimiento</th>
                <th class="px-4 py-3 font-medium">Quién escala</th>
                <th class="px-4 py-3 font-medium">Nodo</th>
                <th class="px-4 py-3 font-medium">Entidad</th>
                <th class="px-4 py-3 font-medium">Respuesta</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($derivaciones as $derivacion)
                <tr>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $derivacion->id }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-[var(--text)]">
                        {{ $derivacion->fecha_requerimiento->format('d/m/Y') }} · {{ substr($derivacion->hora_requerimiento, 0, 5) }}
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $derivacion->nombre }}
                        <span class="block text-xs text-[var(--muted)]">CC {{ $derivacion->cedula }}{{ $derivacion->cargo ? ' · '.$derivacion->cargo : '' }}</span>
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $derivacion->nodo?->nombre }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $derivacion->entidad }}</td>
                    <td class="whitespace-nowrap px-4 py-3">
                        @include('admin.turnos.derivaciones._estado')
                        @if($derivacion->estaPendiente())
                            <span class="mt-1 block text-xs text-[var(--muted)]">
                                lleva <span class="font-semibold" data-contador-desde="{{ $derivacion->requeridaAt()->toIso8601String() }}" data-meta-minutos="{{ \App\Models\Derivacion::META_MINUTOS }}">{{ $derivacion->tiempoLegible() }}</span>
                            </span>
                        @else
                            <span class="mt-1 block text-xs {{ $derivacion->cumplio() ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}">
                                {{ $derivacion->tiempoLegible() }} · a las {{ substr($derivacion->hora_respuesta, 0, 5) }}
                            </span>
                            @if($derivacion->respondedor)
                                <span class="block text-xs text-[var(--muted)]">por {{ $derivacion->respondedor->name }}</span>
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.turnos.derivaciones.show', $derivacion) }}" class="text-[var(--primary)] hover:underline">{{ $derivacion->estaPendiente() ? 'Responder' : 'Ver' }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-[var(--muted)]">
                        No hay derivaciones con este filtro. Se reportan con un enlace por cédula de tipo «Derivaciones».
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $derivaciones->links() }}</div>

@endsection
