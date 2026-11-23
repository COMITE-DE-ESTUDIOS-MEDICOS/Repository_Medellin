@extends('layouts.app')
@section('titulo', 'Enlaces de carga')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Enlaces de carga</h1>
        <p class="text-sm text-[var(--muted)]">
            Cada enlace deja subir archivos sin cuenta, directo a una carpeta. Quien lo recibe
            se identifica al enviar.
        </p>
    </div>

    <a href="{{ route('admin.enlaces.create') }}" class="liquid-button-primary ml-auto rounded-md px-3 py-1.5 text-sm font-medium">
        Nuevo enlace
    </a>
</div>

@if(session('enlace_url'))
    <div class="liquid-alert liquid-alert-info mb-4 text-sm">
        <div class="w-full">
            <p class="font-medium">Copia esta URL ahora — no volverá a mostrarse completa:</p>
            <div class="mt-2 flex items-center gap-2">
                <input id="url-enlace" type="text" readonly value="{{ session('enlace_url') }}"
                       class="liquid-input min-w-0 flex-1 text-xs" onclick="this.select()">
                {{--
                    El comportamiento vive en resources/js/copiar.js: copia,
                    confirma en el propio botón, y si el navegador no deja
                    —pasa en cualquier sitio servido por http— deja la URL
                    seleccionada y lo dice, en vez de fallar en silencio.
                --}}
                <button type="button" data-copiar="#url-enlace" aria-live="polite"
                        class="liquid-button-primary flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium">
                    Copiar
                </button>
            </div>
        </div>
    </div>
@endif

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Carpeta de destino</th>
                <th class="px-4 py-3 font-medium">Creado por</th>
                <th class="px-4 py-3 font-medium">Usos</th>
                <th class="px-4 py-3 font-medium">Enviado</th>
                <th class="px-4 py-3 font-medium">Vence</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($enlaces as $enlace)
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-medium text-[var(--text)]">{{ $enlace->carpeta?->nombre ?? 'Sin carpeta' }}</p>
                        @if($enlace->proposito)
                            <p class="text-xs text-[var(--muted)]">{{ $enlace->proposito }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">{{ $enlace->creador?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $enlace->usos }}{{ $enlace->max_usos ? ' / '.$enlace->max_usos : '' }}
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $enlace->enviado_at?->fechaHora() ?? '—' }}

                        @can('corregirEnvio', $enlace)
                            {{--
                                Un <details> y no un modal montado a mano: se
                                abre solo, sin JavaScript, igual que el menú de
                                la cuenta. Corregir esto es raro, así que no
                                merece ocupar sitio hasta que hace falta.
                            --}}
                            <details class="mt-1">
                                <summary class="cursor-pointer text-xs text-[var(--muted)] hover:text-[var(--text)]">
                                    Corregir fecha de envío
                                </summary>

                                <form method="POST" action="{{ route('admin.enlaces.fecha-envio', $enlace) }}"
                                      class="mt-2 flex flex-wrap items-center gap-2">
                                    @csrf @method('PATCH')

                                    <input type="datetime-local" name="enviado_at" required
                                           value="{{ $enlace->enviado_at?->format('Y-m-d\TH:i') }}"
                                           min="{{ $enlace->created_at->format('Y-m-d\TH:i') }}"
                                           max="{{ now()->format('Y-m-d\TH:i') }}"
                                           class="liquid-input py-1 text-xs">

                                    <button class="liquid-button-primary rounded-md px-2.5 py-1 text-xs font-medium">
                                        Guardar
                                    </button>
                                </form>

                                <p class="mt-1 text-xs text-[var(--muted)]">
                                    Úsalo solo si el enlace se generó un día y se entregó otro.
                                </p>
                            </details>
                        @endcan
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $enlace->expira_at?->fechaHora() ?? 'Sin vencimiento' }}
                    </td>
                    <td class="px-4 py-3">
                        @if($enlace->estaVigente())
                            <span class="text-[var(--success)]">Vigente</span>
                        @elseif(! $enlace->activo)
                            <span class="text-[var(--danger)]">Revocado</span>
                        @else
                            <span class="text-[var(--muted)]">Vencido / agotado</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @can('revocar', $enlace)
                            @if($enlace->activo)
                                <form method="POST" action="{{ route('admin.enlaces.revocar', $enlace) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="text-[var(--danger)] hover:underline"
                                            onclick="return confirm('¿Revocar este enlace? Dejará de aceptar cargas de inmediato.')">
                                        Revocar
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-[var(--muted)]">Aún no hay enlaces de carga.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $enlaces->links() }}</div>

@endsection
