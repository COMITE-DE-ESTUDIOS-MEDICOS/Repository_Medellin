@extends('layouts.app')
@section('titulo', 'Enlaces por cédula')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Enlaces por cédula</h1>
        <p class="text-sm text-[var(--muted)]">
            Turnos, derivaciones y supervisión: quien lo abre se identifica con su cédula. Compártelo por WhatsApp o imprime su QR.
        </p>
    </div>

    <a href="{{ route('admin.turnos.enlaces.create') }}"
       class="liquid-button-primary ml-auto rounded-md px-3 py-1.5 text-sm font-medium">
        Nuevo enlace
    </a>
</div>

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Dónde</th>
                <th class="px-4 py-3 font-medium">Tipo</th>
                <th class="px-4 py-3 font-medium">Registros</th>
                <th class="px-4 py-3 font-medium">Creado</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($enlaces as $enlace)
                <tr class="{{ $enlace->estaVigente() ? '' : 'opacity-60' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.turnos.enlaces.show', $enlace) }}"
                           class="font-medium text-[var(--text)] hover:text-[var(--primary)]">
                            {{ $enlace->componente->nombre }}{{ $enlace->nodo ? ' · '.$enlace->nodo->nombre : '' }}
                        </a>
                        @if($enlace->nombre)
                            <p class="text-xs text-[var(--muted)]">{{ $enlace->nombre }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">{{ $enlace->etiquetaTipo() }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $enlace->esDerivacion() ? $enlace->derivaciones_count : ($enlace->esSupervision() ? $enlace->supervisiones_count : $enlace->marcaciones_count) }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">
                        {{ $enlace->created_at->format('d/m/Y') }}
                        <span class="block text-xs">{{ $enlace->creador?->name }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs">
                        @if(! $enlace->activo)
                            <span class="text-[var(--danger)]">Revocado</span>
                        @elseif($enlace->haExpirado())
                            <span class="text-[var(--danger)]">Venció el {{ $enlace->expira_at->format('d/m/Y') }}</span>
                        @else
                            <span class="text-[var(--success)]">Activo</span>
                            @if($enlace->expira_at)
                                <span class="block text-[var(--muted)]">hasta el {{ $enlace->expira_at->format('d/m/Y') }}</span>
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.turnos.enlaces.show', $enlace) }}"
                           class="text-[var(--primary)] hover:underline">Ver link y QR</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-[var(--muted)]">
                        Aún no hay enlaces. Crea uno por componente, o uno por nodo para pegarlo en cada lugar.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $enlaces->links() }}</div>

@endsection
