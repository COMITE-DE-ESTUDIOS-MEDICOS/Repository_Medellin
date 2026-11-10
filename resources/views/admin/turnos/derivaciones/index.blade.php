@extends('layouts.app')
@section('titulo', 'Derivaciones y respuesta territorial')

@section('contenido')

@php($campo = 'liquid-input mt-1 text-sm')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Derivaciones y respuesta territorial</h1>
        <p class="text-sm text-[var(--muted)]">
            La oficina crea el caso y la persona de campo lo atiende con su enlace antes de {{ intdiv(\App\Models\Derivacion::META_MINUTOS, 60) }} horas. Si vence sin atención, llega un aviso a la campana.
        </p>
    </div>
    <div class="ml-auto flex flex-wrap gap-2">
        @can('create', \App\Models\Derivacion::class)
            <a href="{{ route('admin.turnos.derivaciones.create') }}"
               class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Nuevo caso</a>
        @endcan
        <a href="{{ route('admin.turnos.derivaciones.exportar', request()->query()) }}" download
           class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Descargar Excel</a>
        <a href="{{ route('admin.turnos.derivaciones.fotos', request()->query()) }}" download
           class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
            Excel + fotos (ZIP)
        </a>
    </div>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Buscar</label>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="DC-12, nombre, CC, entidad…" class="{{ $campo }}">
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
            <option value="pendiente" @selected(request('estado') === 'pendiente')>Abiertos / pendientes</option>
            <option value="solucionada" @selected(request('estado') === 'solucionada')>Atendidos / solucionadas</option>
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

{{-- Trazabilidad: quién ha atendido y quién no, con el tiempo desde la creación. --}}
@php($tarjeta = 'rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]')
<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <a href="{{ route('admin.turnos.derivaciones.index', ['estado' => 'pendiente'] + request()->except('estado', 'page')) }}" class="{{ $tarjeta }} hover:bg-[var(--card-soft)]">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Sin atender</p>
        <p class="mt-1 text-2xl font-semibold {{ $traza['pendientes'] ? 'text-[var(--warning)]' : 'text-[var(--text)]' }}">{{ $traza['pendientes'] }}</p>
        <p class="text-xs {{ $traza['vencidas'] ? 'font-semibold text-[var(--danger)]' : 'text-[var(--muted)]' }}">{{ $traza['vencidas'] }} {{ $traza['vencidas'] === 1 ? 'vencida' : 'vencidas' }} (más de {{ intdiv(\App\Models\Derivacion::META_MINUTOS, 60) }} h)</p>
    </a>
    <div class="{{ $tarjeta }}">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Atendidos</p>
        <p class="mt-1 text-2xl font-semibold text-[var(--text)]">{{ $traza['solucionadas'] }}</p>
        <p class="text-xs text-[var(--muted)]">{{ $traza['a_tiempo'] }} a tiempo · {{ $traza['solucionadas'] - $traza['a_tiempo'] }} fuera de tiempo</p>
    </div>
    <div class="{{ $tarjeta }}">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Cumplimiento</p>
        <p class="mt-1 text-2xl font-semibold text-[var(--text)]">{{ $traza['solucionadas'] ? round(100 * $traza['a_tiempo'] / $traza['solucionadas']) : 0 }} %</p>
        <p class="text-xs text-[var(--muted)]">Tiempo promedio: {{ $traza['promedio'] ?? '—' }}</p>
    </div>
    <div class="{{ $tarjeta }}">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Nodos con casos sin atender</p>
        <ul class="mt-1 space-y-0.5 text-sm">
            @forelse($traza['sin_respuesta'] as $nodo => $fila)
                <li class="flex justify-between gap-2">
                    <span class="truncate text-[var(--text)]">{{ $nodo }}</span>
                    <span class="{{ $fila['vencidas'] ? 'font-semibold text-[var(--danger)]' : 'text-[var(--warning)]' }}">{{ $fila['casos'] }} · {{ \App\Models\Derivacion::duracion($fila['mas_antigua']) }}</span>
                </li>
            @empty
                <li class="text-[var(--success)]">Ninguno: todo atendido.</li>
            @endforelse
        </ul>
    </div>
</div>

@if($traza['personas']->isNotEmpty())
    <details class="mb-4 rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-[var(--text)]">Casos atendidos por persona de campo ({{ $traza['personas']->count() }})</summary>
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[var(--border)] border-t border-[var(--border)] text-sm">
            <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
                <tr>
                    <th class="px-4 py-2 font-medium">Persona de campo</th>
                    <th class="px-4 py-2 font-medium">Atendidos</th>
                    <th class="px-4 py-2 font-medium">A tiempo</th>
                    <th class="px-4 py-2 font-medium">Tiempo promedio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border)]">
                @foreach($traza['personas'] as $p)
                    <tr>
                        <td class="px-4 py-2 text-[var(--text)]">{{ $p->nombre ?? 'Sin nombre' }}<span class="block text-xs text-[var(--muted)]">{{ $p->cedula ? 'CC '.$p->cedula : '' }}</span></td>
                        <td class="px-4 py-2 text-[var(--text)]">{{ $p->total }}</td>
                        <td class="px-4 py-2 {{ $p->a_tiempo < $p->total ? 'text-[var(--danger)]' : 'text-[var(--success)]' }}">{{ $p->a_tiempo }} de {{ $p->total }}</td>
                        <td class="px-4 py-2 text-[var(--muted)]">{{ \App\Models\Derivacion::duracion((int) round($p->promedio)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </details>
@endif

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="w-full min-w-[56rem] divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Código</th>
                <th class="px-4 py-3 font-medium">Inicio</th>
                <th class="px-4 py-3 font-medium">Quién lo envía</th>
                <th class="px-4 py-3 font-medium">Persona de campo</th>
                <th class="px-4 py-3 font-medium">Nodo</th>
                <th class="px-4 py-3 font-medium">Estado y tiempo</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($derivaciones as $derivacion)
                @php($abierto = $derivacion->esCaso() && $derivacion->estaPendiente())
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 font-medium text-[var(--text)]">{{ $derivacion->codigo() }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-[var(--text)]">
                        {{ $derivacion->requeridaAt()->fechaHora() }}
                        @if($derivacion->creador)
                            <span class="block text-xs text-[var(--muted)]">creó {{ $derivacion->creador->name }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $derivacion->entidad }}</td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        @unless($abierto)
                            {{ $derivacion->nombre }}
                            @if($derivacion->cedula)
                                <span class="block text-xs text-[var(--muted)]">CC {{ $derivacion->cedula }}{{ $derivacion->cargo ? ' · '.$derivacion->cargo : '' }}</span>
                            @endif
                        @endunless
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $abierto ? '' : $derivacion->nodo?->nombre }}</td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <span class="flex flex-wrap gap-1">@include('admin.turnos.derivaciones._estado')</span>
                        @if($derivacion->estaPendiente())
                            <span class="mt-1 block text-xs text-[var(--muted)]">
                                lleva <span class="font-semibold" data-contador-desde="{{ $derivacion->requeridaAt()->toIso8601String() }}" data-meta-minutos="{{ \App\Models\Derivacion::META_MINUTOS }}">{{ $derivacion->tiempoLegible() }}</span>
                            </span>
                        @else
                            <span class="mt-1 block text-xs {{ $derivacion->cumplio() ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}">
                                {{ $derivacion->tiempoLegible() }} · a las {{ $derivacion->atendida_at?->hora() ?? \Illuminate\Support\Carbon::parse($derivacion->hora_respuesta)->hora() }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.turnos.derivaciones.show', $derivacion) }}" class="text-[var(--primary)] hover:underline">Ver</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-[var(--muted)]">
                        No hay casos con este filtro. La oficina los crea con «Nuevo caso».
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $derivaciones->links() }}</div>

@endsection
