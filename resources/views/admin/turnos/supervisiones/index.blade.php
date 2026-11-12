@extends('layouts.app')
@section('titulo', 'Supervisión territorial')

@section('contenido')

@php($campo = 'liquid-input mt-1 text-sm')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Supervisión territorial</h1>
        <p class="text-sm text-[var(--muted)]">
            Lo registra el Apoyo al control territorial con su cédula, por un enlace de tipo «Supervisión territorial».
        </p>
    </div>
    <div class="ml-auto flex flex-wrap gap-2">
        <a href="{{ route('admin.turnos.supervisiones.exportar', request()->query()) }}" download
           class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Descargar Excel</a>
        @can('create', App\Models\EnlaceTurno::class)
            <a href="{{ route('admin.turnos.enlaces.create', ['tipo' => 'supervision']) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Crear enlace</a>
        @endcan
    </div>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Cédula del dinamizador</label>
        <input type="search" name="dinamizador" value="{{ request('dinamizador') }}" inputmode="numeric" placeholder="1.017.234" class="{{ $campo }}">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Quién supervisó</label>
        <input type="search" name="supervisor" value="{{ request('supervisor') }}" placeholder="Nombre" class="{{ $campo }}">
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
        <label class="block text-xs font-medium text-[var(--muted)]">Punto crítico</label>
        <select name="punto" class="{{ $campo }} max-w-56">
            <option value="">Todos</option>
            @foreach($puntos as $punto)
                <option value="{{ $punto->id }}" @selected(request('punto') == $punto->id)>{{ $punto->nombre }}</option>
            @endforeach
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
    <a href="{{ route('admin.turnos.supervisiones.index') }}" class="text-sm text-[var(--muted)] hover:text-[var(--text)]">Limpiar</a>
</form>

@include('admin.turnos.supervisiones._analitica')

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">#</th>
                <th class="px-4 py-3 font-medium">Visita</th>
                <th class="px-4 py-3 font-medium">Punto · nodo</th>
                <th class="px-4 py-3 font-medium">Supervisó</th>
                <th class="px-4 py-3 font-medium">Promedio</th>
                <th class="px-4 py-3 font-medium">Global</th>
                <th class="px-4 py-3 font-medium">Incidencia</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($supervisiones as $s)
                @php($color = fn ($n) => $n >= 4 ? 'text-[var(--success)]' : ($n >= 3 ? 'text-[var(--warning)]' : 'text-[var(--danger)]'))
                <tr>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $s->id }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-[var(--text)]">{{ $s->visita_at->format('d/m/Y').' · '.$s->visita_at->hora() }}</td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $s->puntoNombre() }}
                        <span class="block text-xs text-[var(--muted)]">{{ $s->nodo?->nombre }}{{ $s->lat === null ? ' · sin ubicación' : '' }}</span>
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $s->supervisor_nombre }}</td>
                    <td class="px-4 py-3 font-semibold {{ $color($s->promedio()) }}">{{ number_format($s->promedio(), 1, ',') }}</td>
                    <td class="px-4 py-3 font-semibold {{ $color($s->calificacion_global) }}">{{ $s->calificacion_global }}</td>
                    <td class="px-4 py-3">
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
                            'text-[var(--muted)] ring-[var(--border)]' => $s->incidencia === 'ninguna',
                            'text-[var(--warning)] ring-[var(--warning)]' => in_array($s->incidencia, ['leve', 'relevante'], true),
                            'text-[var(--danger)] ring-[var(--danger)]' => $s->incidencia === 'critica',
                        ])>{{ \App\Models\Supervision::INCIDENCIAS[$s->incidencia] ?? $s->incidencia }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.turnos.supervisiones.show', $s) }}" class="text-[var(--primary)] hover:underline">Ver</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-[var(--muted)]">
                        No hay supervisiones con este filtro. Se registran con un enlace por cédula de tipo «Supervisión territorial».
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $supervisiones->links() }}</div>

@endsection
