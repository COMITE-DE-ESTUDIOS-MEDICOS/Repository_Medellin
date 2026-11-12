@extends('layouts.app')
@section('titulo', 'Auditoría')

@section('contenido')

@php($campo = 'liquid-input mt-1 text-sm')

<div class="mb-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Auditoría</h1>
    <p class="text-sm text-[var(--muted)]">
        Registro de quién hizo qué y cuándo. Es lo que respalda la trazabilidad de las inactivaciones.
    </p>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Archivo o persona</label>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre del archivo…" class="{{ $campo }}">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Usuario</label>
        <select name="usuario" class="{{ $campo }}">
            <option value="">Todos</option>
            @foreach($usuarios as $usuario)
                <option value="{{ $usuario->id }}" @selected(request('usuario') == $usuario->id)>{{ $usuario->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Acción</label>
        <select name="accion" class="{{ $campo }}">
            <option value="">Todas</option>
            @foreach(\App\Enums\AccionAuditoria::cases() as $accion)
                <option value="{{ $accion->value }}" @selected(request('accion') === $accion->value)>
                    {{ $accion->etiqueta() }}
                </option>
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
    <a href="{{ route('auditoria.index') }}" class="text-sm text-[var(--muted)] hover:text-[var(--text)]">Limpiar</a>
</form>

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Usuario</th>
                <th class="px-4 py-3 font-medium">Acción</th>
                <th class="px-4 py-3 font-medium">Detalle</th>
                <th class="px-4 py-3 font-medium">Origen · Carpeta</th>
                <th class="px-4 py-3 font-medium">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($registros as $registro)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 text-[var(--muted)]">
                        {{ $registro->created_at->fechaHora() }}
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">{{ $registro->usuario?->name ?? 'Sistema' }}</td>
                    <td class="px-4 py-3 text-[var(--text)]">{{ $registro->etiqueta_accion }}</td>
                    @php($datos = collect($registro->datos)->except(['origen', 'carpeta']))
                    <td class="px-4 py-3 text-[var(--muted)]">
                        {{ $registro->descripcion }}
                        @if($datos->isNotEmpty())
                            <span class="block text-xs text-[var(--muted)]">
                                {{ $datos->map(fn ($v, $k) => $k.': '.(is_array($v) ? implode(', ', $v) : $v))->join(' · ') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        @if($registro->datos['carpeta'] ?? null)
                            <span class="block text-xs text-[var(--muted)]">{{ $registro->datos['origen'] ?? '' }}</span>
                            {{ $registro->datos['carpeta'] }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-[var(--muted)]">{{ $registro->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-[var(--muted)]">Sin registros para este filtro.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $registros->links() }}</div>

@endsection
