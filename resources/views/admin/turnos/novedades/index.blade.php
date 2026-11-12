@extends('layouts.app')
@section('titulo', 'Novedades de turno')

@section('contenido')

@php
    $campo = 'liquid-input text-sm';
@endphp

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Novedades de turno</h1>
        <p class="text-sm text-[var(--muted)]">
            Turnos que se salieron de lo esperado. Apruébalos o recházalos: las marcaciones no cambian, la decisión queda aquí.
        </p>
    </div>

    <form method="GET" class="ml-auto flex flex-wrap gap-2">
        <select name="estado" class="{{ $campo }}" onchange="this.form.submit()" aria-label="Estado">
            @foreach(['pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'corregida' => 'Corregidas', 'todas' => 'Todas'] as $valor => $texto)
                <option value="{{ $valor }}" @selected($estado === $valor)>{{ $texto }}</option>
            @endforeach
        </select>
        <select name="tipo" class="{{ $campo }}" onchange="this.form.submit()" aria-label="Tipo">
            <option value="">Todos los tipos</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->value }}" @selected(request('tipo') === $tipo->value)>{{ $tipo->etiqueta() }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('admin.turnos.novedades.exportar', request()->query()) }}" download
       class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Descargar Excel</a>
</div>

<div class="space-y-3">
    @forelse($novedades as $novedad)
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm">
                        <span class="rounded bg-warning-soft px-1.5 py-0.5 text-xs font-medium text-[var(--warning)]">{{ $novedad->tipo->etiqueta() }}</span>
                        @can('view', $novedad->colaborador)<a href="{{ route('admin.turnos.colaboradores.show', $novedad->colaborador) }}" class="ml-1 font-medium text-[var(--text)] hover:text-[var(--primary)]">{{ $novedad->colaborador->nombre }}</a>@else<span class="ml-1 font-medium text-[var(--text)]">{{ $novedad->colaborador->nombre }}</span>@endcan
                        <span class="text-xs text-[var(--muted)]">CC {{ $novedad->colaborador->documento }}</span>
                    </p>
                    <p class="mt-1 text-sm text-[var(--text)]">{{ $novedad->detalle }}</p>
                    <p class="mt-1 text-xs text-[var(--muted)]">
                        {{ $novedad->componente->nombre }}{{ $novedad->marcacion?->nodo ? ' · '.$novedad->marcacion->nodo->nombre : '' }}
                        · {{ $novedad->fecha ? 'del '.$novedad->fecha->format('d/m/Y').' · ' : '' }}{{ $novedad->created_at->fechaHora() }}
                    </p>
                </div>

                @if($novedad->estaPendiente() && ! auth()->user()->puedeAprobarNovedadesEn($novedad->dependencia_id))
                    <p class="text-right text-xs text-[var(--muted)]">Pendiente: la aprueba Coordinación o quien tenga «Aprueba novedades».</p>
                @elseif($novedad->estaPendiente())
                    <div class="flex w-full flex-col items-end gap-2 sm:w-auto">
                    <form method="POST" action="{{ route('admin.turnos.novedades.revisar', $novedad) }}"
                          class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        @csrf @method('PATCH')
                        <input name="comentario" maxlength="1000" placeholder="Comentario (obligatorio si rechazas)"
                               class="{{ $campo }} min-w-0 flex-1 sm:w-64">
                        <button name="estado" value="aprobada"
                                class="rounded-md bg-[var(--success)] px-3 py-2 text-sm font-medium text-white hover:brightness-90">Aprobar</button>
                        <button name="estado" value="rechazada"
                                class="rounded-md bg-[var(--danger)] px-3 py-2 text-sm font-medium text-white hover:brightness-90">Rechazar</button>
                    </form>
                    @if($novedad->tipo === \App\Enums\TipoNovedad::SinEntrada)
                        {{-- No vino porque estaba en D, VAC, I…: se justifica y ese día queda registrado. --}}
                        @if(($ausenciasPorComponente[$novedad->componente_id] ?? collect())->isNotEmpty())
                            <form method="POST" action="{{ route('admin.turnos.novedades.justificar', $novedad) }}" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                                @csrf
                                <select name="tipo_turno_id" required class="{{ $campo }}" aria-label="Tipo de ausencia">
                                    @foreach($ausenciasPorComponente[$novedad->componente_id] as $tipo)
                                        <option value="{{ $tipo->id }}">{{ $tipo->codigo }} · {{ $tipo->nombre }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded-md bg-[var(--card-soft)] px-3 py-2 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:brightness-95">Justificar</button>
                            </form>
                        @endif
                        @can('update', $novedad->colaborador)
                            <a href="{{ route('admin.turnos.colaboradores.show', $novedad->colaborador) }}#agregar-marca" class="text-sm text-[var(--primary)] hover:underline">
                                Sí vino: agregar la entrada que faltó
                            </a>
                        @endcan
                    @else
                    {{-- Si la hora estaba mal, se corrige y el turno se vuelve a juzgar. --}}
                    <div class="flex gap-4">
                        @if($novedad->entrada && ! $novedad->entrada->estaAnulada())
                            @include('admin.turnos._corregir', ['marcacion' => $novedad->entrada, 'texto' => 'Corregir hora de entrada'])
                        @endif
                        @if($novedad->marcacion && ! $novedad->marcacion->estaAnulada())
                            @include('admin.turnos._corregir', ['marcacion' => $novedad->marcacion, 'texto' => 'Corregir hora de '.mb_strtolower($novedad->marcacion->tipo->etiqueta())])
                        @endif
                    </div>
                    @endif
                    </div>
                @else
                    <div class="text-right text-xs">
                        <span class="font-medium {{ match ($novedad->estado) {
                            \App\Enums\EstadoNovedad::Aprobada => 'text-[var(--success)]',
                            \App\Enums\EstadoNovedad::Corregida => 'text-[var(--primary)]',
                            default => 'text-[var(--danger)]',
                        } }}">
                            {{ $novedad->estado->etiqueta() }}
                        </span>
                        <span class="block text-[var(--muted)]">{{ $novedad->revisor?->name }} · {{ $novedad->revisada_at?->fechaHora() }}</span>
                        @if($novedad->comentario)
                            <span class="mt-1 block max-w-64 text-[var(--text)]">«{{ $novedad->comentario }}»</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p class="rounded-lg bg-[var(--card)] px-4 py-10 text-center text-sm text-[var(--muted)] ring-1 ring-[var(--border)]">
            {{ $estado === 'pendiente' ? 'No hay novedades pendientes.' : 'No hay novedades con estos filtros.' }}
        </p>
    @endforelse
</div>

<div class="mt-4">{{ $novedades->links() }}</div>

@endsection
