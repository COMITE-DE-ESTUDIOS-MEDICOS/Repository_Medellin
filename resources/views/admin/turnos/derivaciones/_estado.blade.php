{{-- Abierto / Pendiente (ámbar, o rojo si ya pasó la meta) o Atendido / Solucionada (verde). --}}
@if($derivacion->estaPendiente())
    <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $derivacion->cumplio() ? 'text-[var(--warning)] ring-[var(--warning)]' : 'text-[var(--danger)] ring-[var(--danger)]' }}">
        {{ $derivacion->estadoLegible() }}{{ ! $derivacion->esCaso() && ! $derivacion->cumplio() ? ' · vencida' : '' }}
    </span>
@else
    <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-[var(--success)] ring-1 ring-[var(--success)]">{{ $derivacion->estadoLegible() }}</span>
@endif
@unless($derivacion->esCaso())
    <span class="rounded-full px-2 py-0.5 text-xs text-[var(--muted)] ring-1 ring-[var(--border)]">registro anterior</span>
@endunless
