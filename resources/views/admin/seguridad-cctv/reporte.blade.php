@extends('layouts.app')
@section('titulo', 'Reporte de Seguridad CCTV')

@section('contenido')

@php($campo = 'liquid-input mt-1 text-sm')
@php($tabla = 'w-full text-sm')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Reporte de Seguridad CCTV</h1>
        <p class="text-sm text-[var(--muted)]">Los casos cuyo requerimiento cae en el rango. Los anulados no cuentan.</p>
    </div>
    <div class="ml-auto flex flex-wrap gap-2">
        <a href="{{ route('admin.turnos.cctv.exportar', ['desde' => $desde, 'hasta' => $hasta]) }}" download
           class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Descargar Excel</a>
        <a href="{{ route('admin.turnos.cctv.index') }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Ver los casos</a>
    </div>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <div>
        <label for="desde" class="block text-xs font-medium text-[var(--muted)]">Desde</label>
        <input type="date" id="desde" name="desde" value="{{ $desde }}" class="{{ $campo }}">
    </div>
    <div>
        <label for="hasta" class="block text-xs font-medium text-[var(--muted)]">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="{{ $hasta }}" class="{{ $campo }}">
    </div>
    <button class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Ver</button>
</form>

@error('hasta')<p class="mb-3 text-sm text-red-600">La fecha «Hasta» no puede ser anterior a «Desde».</p>@enderror

<div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
    <div class="rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
        <p class="text-xs text-[var(--muted)]">Casos del rango</p>
        <p class="text-2xl font-semibold text-[var(--text)]">{{ $resumen['total'] }}</p>
    </div>
    @foreach($resumen['estados'] as $nombre => $cantidad)
        <div class="rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
            <p class="text-xs text-[var(--muted)]">{{ $nombre }}</p>
            <p class="text-2xl font-semibold text-[var(--text)]">{{ $cantidad }}</p>
        </div>
    @endforeach
</div>

<div class="mb-4 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
    <h2 class="mb-2 text-sm font-semibold text-[var(--text)]">Tiempo de respuesta</h2>
    @if($resumen['tiempos'])
        <table class="{{ $tabla }}">
            @foreach($resumen['tiempos'] as $nombre => $valor)
                <tr class="border-t border-[var(--border)] first:border-0">
                    <td class="py-1.5 text-[var(--muted)]">{{ $nombre }}</td>
                    <td class="py-1.5 text-right font-medium text-[var(--text)]">{{ $valor }}</td>
                </tr>
            @endforeach
        </table>
        <p class="mt-2 text-xs text-[var(--muted)]">Solo cuentan los casos ya atendidos; un caso abierto sigue corriendo.</p>
    @else
        <p class="text-sm text-[var(--muted)]">Ningún caso del rango se ha atendido todavía.</p>
    @endif
</div>

<div class="grid gap-4 md:grid-cols-2">
    @foreach(['Por entidad' => $resumen['entidades'], 'Por comuna' => $resumen['comunas']] as $titulo => $cifras)
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h2 class="mb-2 text-sm font-semibold text-[var(--text)]">{{ $titulo }}</h2>
            <table class="{{ $tabla }}">
                @forelse($cifras as $nombre => $cantidad)
                    <tr class="border-t border-[var(--border)] first:border-0">
                        <td class="py-1.5 text-[var(--text)]">{{ $nombre }}</td>
                        <td class="py-1.5 text-right font-medium text-[var(--text)]">{{ $cantidad }}</td>
                    </tr>
                @empty
                    <tr><td class="py-1.5 text-[var(--muted)]">Sin casos en el rango.</td></tr>
                @endforelse
            </table>
        </div>
    @endforeach
</div>

@endsection
