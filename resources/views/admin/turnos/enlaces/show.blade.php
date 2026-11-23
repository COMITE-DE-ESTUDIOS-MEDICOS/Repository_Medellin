@extends('layouts.app')
@section('titulo', 'Enlace por cédula')

@section('contenido')

<div class="mb-4 text-sm text-[var(--muted)]">
    <a href="{{ route('admin.turnos.enlaces.index') }}" class="hover:text-[var(--text)]">Enlaces por cédula</a>
    <span>/</span>
    <span class="font-medium text-[var(--text)]">{{ $donde }}</span>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="space-y-4">
        <div class="rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
            <h1 class="text-lg font-semibold text-[var(--text)]">{{ $donde }}</h1>
            @if($enlace->nombre)
                <p class="text-sm text-[var(--muted)]">{{ $enlace->nombre }}</p>
            @endif
            <p class="mt-1 text-xs font-medium text-[var(--primary)]">{{ $enlace->etiquetaTipo() }}</p>

            <p class="mt-4 text-sm font-medium text-[var(--text)]">Link para compartir por WhatsApp</p>
            <div class="mt-2 flex items-center gap-2">
                <input id="url-turno" type="text" readonly value="{{ $enlace->url() }}"
                       class="liquid-input min-w-0 flex-1 text-xs" onclick="this.select()">
                <button type="button" data-copiar="#url-turno" aria-live="polite"
                        class="liquid-button-primary flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium">
                    Copiar
                </button>
            </div>

            <dl class="mt-6 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Estado</dt>
                    <dd class="text-right">
                        @if(! $enlace->activo)
                            <span class="text-[var(--danger)]">Revocado</span>
                        @elseif($enlace->haExpirado())
                            <span class="text-[var(--danger)]">Vencido</span>
                        @else
                            <span class="text-[var(--success)]">Activo</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Vence</dt>
                    <dd class="text-right text-[var(--text)]">{{ $enlace->expira_at?->format('d/m/Y') ?? 'Hasta que se revoque' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Creado</dt>
                    <dd class="text-right text-[var(--text)]">
                        {{ $enlace->created_at->fechaHora() }} · {{ $enlace->creador?->name ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        @can('revocar', $enlace)
            <form method="POST" action="{{ route('admin.turnos.enlaces.revocar', $enlace) }}"
                  class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                @csrf @method('PATCH')
                <p class="mb-3 text-xs text-[var(--muted)]">
                    Revocarlo apaga el link y el QR impreso al instante. Lo registrado con él se conserva.
                </p>
                <button class="rounded-md bg-[var(--danger)] px-4 py-2 text-sm font-medium text-white hover:brightness-90"
                        onclick="return confirm('¿Revocar este enlace? El QR impreso dejará de funcionar.')">
                    Revocar enlace
                </button>
            </form>
        @endcan
    </div>

    <div class="rounded-lg bg-[var(--card)] p-6 text-center ring-1 ring-[var(--border)]">
        <p class="text-sm font-medium text-[var(--text)]">QR para imprimir</p>
        {{-- Fondo blanco siempre, también en tema oscuro: un QR invertido no lo leen todos los celulares. --}}
        <div data-qr="{{ $enlace->url() }}" class="mx-auto mt-4 max-w-72 rounded-lg bg-white p-2"></div>
        <a href="{{ route('admin.turnos.enlaces.imprimir', $enlace) }}" target="_blank" rel="noopener"
           class="liquid-button-primary mt-4 inline-block rounded-md px-4 py-2 text-sm font-medium">
            Imprimir QR
        </a>
    </div>
</div>

@endsection
