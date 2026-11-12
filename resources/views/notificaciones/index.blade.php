@extends('layouts.app')
@section('titulo', 'Notificaciones')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <h1 class="text-lg font-semibold text-[var(--text)]">Notificaciones</h1>

    @if($notificaciones->contains(fn ($n) => $n->read_at === null))
        <form method="POST" action="{{ route('notificaciones.leidas') }}" class="ml-auto">
            @csrf
            <button class="text-sm text-[var(--primary)] hover:underline">Marcar todas como leídas</button>
        </form>
    @endif
</div>

@php
    $tipos = \App\Http\Controllers\NotificacionController::TIPOS;
    $colores = [
        'derivaciones' => 'text-[var(--danger)] ring-[var(--danger)]',
        'supervision' => 'text-[var(--warning)] ring-[var(--warning)]',
        'turnos' => 'text-[var(--warning)] ring-[var(--warning)]',
        'documentos' => 'text-[var(--primary)] ring-[var(--primary)]',
        'avisos' => 'text-[var(--success)] ring-[var(--success)]',
    ];
    $pestana = 'rounded-full px-3 py-1 text-sm font-medium ring-1';
@endphp

{{-- Separadas por tipo: una derivación por responder no se pierde entre novedades de turno. --}}
<nav class="mb-4 flex flex-wrap gap-2" aria-label="Tipo de notificación">
    <a href="{{ route('notificaciones.index') }}"
       class="{{ $pestana }} {{ $tipo === null ? 'bg-[var(--primary)] text-white ring-[var(--primary)]' : 'text-[var(--text)] ring-[var(--border)] hover:bg-[var(--card-soft)]' }}">Todas</a>
    @foreach($tipos as $clave => [$etiqueta])
        <a href="{{ route('notificaciones.index', ['tipo' => $clave]) }}"
           class="{{ $pestana }} {{ $tipo === $clave ? 'bg-[var(--primary)] text-white ring-[var(--primary)]' : 'text-[var(--text)] ring-[var(--border)] hover:bg-[var(--card-soft)]' }}">
            {{ $etiqueta }}
            @if($sinLeer[$clave] > 0)
                <span class="ml-1 rounded-full bg-[var(--danger)] px-1.5 text-xs font-bold text-white">{{ $sinLeer[$clave] }}</span>
            @endif
        </a>
    @endforeach
</nav>

<div class="space-y-2">
    @forelse($notificaciones as $n)
        @php($clave = \App\Http\Controllers\NotificacionController::tipoDe($n->type))
        <div class="flex items-start gap-3 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)] {{ $n->read_at ? 'opacity-60' : '' }}">
            <div class="min-w-0 flex-1">
                @if($clave)
                    <span class="mb-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $colores[$clave] }}">{{ $tipos[$clave][0] }}</span>
                @endif
                <p class="text-sm text-[var(--text)]">
                    @if($n->data['url'] ?? null)
                        <a href="{{ url($n->data['url']) }}" class="hover:underline">{{ $n->data['mensaje'] }}</a>
                    @elseif($n->data['carpeta_uuid'] ?? null)
                        <a href="{{ route('documentos.index', ['carpeta' => $n->data['carpeta_uuid']]) }}" class="hover:underline">
                            {{ $n->data['mensaje'] }}
                        </a>
                    @else
                        {{ $n->data['mensaje'] }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-[var(--muted)]">{{ $n->created_at->diffForHumans() }}</p>
            </div>

            @unless($n->read_at)
                <form method="POST" action="{{ route('notificaciones.leida', $n) }}">
                    @csrf @method('PATCH')
                    <button title="Marcar como leída" class="shrink-0 rounded-md p-1 text-[var(--muted)] hover:text-[var(--text)]">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                        <span class="sr-only">Marcar como leída</span>
                    </button>
                </form>
            @endunless
        </div>
    @empty
        <p class="text-sm text-[var(--muted)]">No tienes notificaciones.</p>
    @endforelse
</div>

@endsection
