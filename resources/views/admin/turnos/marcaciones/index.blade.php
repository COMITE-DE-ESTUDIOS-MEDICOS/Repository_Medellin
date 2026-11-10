@extends('layouts.app')
@section('titulo', 'Marcaciones')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 text-sm';
@endphp

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Marcaciones</h1>
        <p class="text-sm text-[var(--muted)]">
            Cada entrada y salida tal como quedó. No se editan: si falta una, se agrega desde la ficha de la persona.
        </p>
    </div>

    {{-- Envía el formulario de filtros tal como está en pantalla, sin tener que pulsar «Filtrar» antes.
         data-descarga: el overlay de carga no se muestra, porque una descarga no cambia de página. --}}
    <button type="submit" form="filtros" formaction="{{ route('admin.turnos.marcaciones.exportar') }}" data-descarga
            class="liquid-button-primary ml-auto rounded-md px-3 py-1.5 text-sm font-medium">
        Descargar reporte (Excel)
    </button>
</div>

<form id="filtros" method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <div>
        <label for="cedula" class="block text-xs font-medium text-[var(--muted)]">Cédula</label>
        <input id="cedula" name="cedula" type="search" inputmode="numeric" value="{{ request('cedula') }}" class="{{ $campo }}">
    </div>
    <div>
        <label for="componente" class="block text-xs font-medium text-[var(--muted)]">Componente</label>
        <select id="componente" name="componente" class="{{ $campo }}">
            <option value="">Todos</option>
            @foreach($componentes as $componente)
                <option value="{{ $componente->id }}" @selected(request('componente') == $componente->id)>{{ $componente->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="nodo" class="block text-xs font-medium text-[var(--muted)]">Nodo</label>
        <select id="nodo" name="nodo" data-depende-de="#componente" class="{{ $campo }}">
            <option value="">Todos</option>
            @foreach($componentes as $componente)
                @foreach($componente->nodos as $nodo)
                    <option value="{{ $nodo->id }}" data-componente="{{ $componente->id }}" @selected(request('nodo') == $nodo->id)>{{ $nodo->nombre }}</option>
                @endforeach
            @endforeach
        </select>
    </div>
    <div>
        <label for="desde" class="block text-xs font-medium text-[var(--muted)]">Desde</label>
        <input id="desde" name="desde" type="date" value="{{ $desde->toDateString() }}" class="{{ $campo }}">
    </div>
    <div>
        <label for="hasta" class="block text-xs font-medium text-[var(--muted)]">Hasta</label>
        <input id="hasta" name="hasta" type="date" value="{{ $hasta->toDateString() }}" class="{{ $campo }}">
    </div>
    <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Filtrar</button>
</form>

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Cuándo</th>
                <th class="px-4 py-3 font-medium">Persona</th>
                <th class="px-4 py-3 font-medium">Marca</th>
                <th class="px-4 py-3 font-medium">Dónde</th>
                <th class="px-4 py-3 font-medium">Evidencia</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($marcaciones as $marcacion)
                <tr class="{{ $marcacion->estaAnulada() ? 'opacity-50' : '' }}">
                    <td class="px-4 py-3 whitespace-nowrap text-[var(--text)]">
                        <span class="{{ $marcacion->estaAnulada() ? 'line-through' : '' }}">{{ $marcacion->marcada_at->fechaHora() }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @can('view', $marcacion->colaborador)<a href="{{ route('admin.turnos.colaboradores.show', $marcacion->colaborador) }}" class="font-medium text-[var(--text)] hover:text-[var(--primary)]">{{ $marcacion->colaborador->nombre }}</a>@else<span class="font-medium text-[var(--text)]">{{ $marcacion->colaborador->nombre }}</span>@endcan
                        <p class="text-xs text-[var(--muted)]">CC {{ $marcacion->colaborador->documento }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-medium {{ $marcacion->esEntrada() ? 'text-[var(--success)]' : 'text-[var(--text)]' }}">
                            {{ $marcacion->tipo->etiqueta() }}
                        </span>
                        @if($marcacion->origen !== \App\Enums\OrigenMarcacion::Enlace)
                            <span class="mt-0.5 block text-xs text-[var(--muted)]"
                                  title="{{ $marcacion->motivo }}">{{ $marcacion->origen->etiqueta() }}{{ $marcacion->registrador ? ' · '.$marcacion->registrador->name : '' }}</span>
                        @endif
                        @if(isset($porCorregir[$marcacion->id]) && ! $marcacion->estaAnulada())
                            <span class="mt-0.5 inline-block rounded bg-warning-soft px-1.5 py-0.5 text-xs text-[var(--warning)]" title="{{ $marcacion->motivo }}">Cierre automático · por corregir</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">
                        {{ $marcacion->componente->nombre }}{{ $marcacion->nodo ? ' · '.$marcacion->nodo->nombre : '' }}
                        @if($marcacion->lat !== null)
                            <a href="https://www.openstreetmap.org/?mlat={{ $marcacion->lat }}&mlon={{ $marcacion->lng }}#map=17/{{ $marcacion->lat }}/{{ $marcacion->lng }}"
                               target="_blank" rel="noopener" class="mt-0.5 block text-xs text-[var(--primary)] hover:underline">
                                {{ $marcacion->municipio ?? 'Ver en el mapa' }}
                            </a>
                        @endif
                        @if($marcacion->ubicacion_imprecisa)
                            <span class="mt-0.5 inline-block rounded bg-warning-soft px-1.5 py-0.5 text-xs text-[var(--warning)]" title="El teléfono dio una ubicación aproximada (±{{ number_format(($marcacion->precision_m ?? 0) / 1000, 1, ',', '.') }} km)">Ubicación imprecisa</span>
                        @elseif($marcacion->fuera_de_zona)
                            <span class="mt-0.5 inline-block rounded bg-warning-soft px-1.5 py-0.5 text-xs text-[var(--warning)]">Fuera de zona</span>
                        @elseif($marcacion->sin_ubicacion && $marcacion->origen === \App\Enums\OrigenMarcacion::Enlace)
                            <span class="mt-0.5 inline-block rounded bg-warning-soft px-1.5 py-0.5 text-xs text-[var(--warning)]">Sin ubicación</span>
                        @endif
                        @if($marcacion->epp_recibidos === false)
                            <span class="mt-0.5 inline-block rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-medium text-[var(--danger)] ring-1 ring-[var(--danger)]">Sin EPP</span>
                        @elseif($marcacion->epp_recibidos === true)
                            <span class="mt-0.5 inline-block rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-medium text-[var(--success)] ring-1 ring-[var(--success)]" title="Confirmó que recibió los elementos de protección personal">EPP ✓</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($marcacion->foto)
                            <a href="{{ route('documentos.show', $marcacion->foto) }}" class="text-[var(--primary)] hover:underline">Ver foto</a>
                        @else
                            <span class="text-[var(--muted)]">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($marcacion->estaAnulada())
                            <span class="text-xs text-[var(--muted)]" title="{{ $marcacion->motivo_anulacion }}">
                                {{ $marcacion->reemplazada_por_id ? 'Corregida' : 'Anulada' }} por {{ $marcacion->anulador?->name ?? '—' }}
                            </span>
                        @else
                            @include('admin.turnos._corregir', ['marcacion' => $marcacion])
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-[var(--muted)]">No hay marcaciones en este rango.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $marcaciones->links() }}</div>

@endsection
