{{--
    DOC-25 (plan D7): la pantalla de llegada cuando no hay ningún módulo que
    abrir. Si lo hay, la ruta «inicio» redirige ahí y esta vista no se pinta.

    Variables (acordadas con ACC-05): componenteActual y componentesDisponibles
    las comparte EstablecerDependencia; sinComponente la pasa el controlador.
--}}
@extends('layouts.app')
@section('titulo', 'Inicio')

@section('contenido')

@php
    $componenteActual ??= null;
    $componentesDisponibles ??= collect();
    $sinComponente = ($sinComponente ?? false) || $componenteActual === null;
    $puedeElegirOtro = ! $sinComponente && $componentesDisponibles->count() > 1;
@endphp

<div class="mx-auto max-w-lg py-6 sm:py-12">
    <div class="rounded-lg bg-[var(--card)] p-6 text-center ring-1 ring-[var(--border)] sm:p-8">
        <svg class="mx-auto size-12 text-[var(--primary)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            @if($sinComponente)
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            @else
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3" />
            @endif
        </svg>

        <h1 class="mt-4 text-lg font-semibold text-[var(--text)]">
            Hola, {{ str(auth()->user()?->name)->before(' ') }}
        </h1>

        @if($sinComponente)
            <p class="mt-2 text-sm text-[var(--text)]">Pide a tu coordinación que te asigne un componente.</p>
            <p class="mt-2 text-sm text-[var(--muted)]">
                Cuando lo tengas, aquí verás lo que te corresponde.
            </p>
        @else
            <p class="mt-2 text-sm text-[var(--text)]">Tu componente todavía no tiene módulos activos.</p>
            <p class="mt-2 text-sm text-[var(--muted)]">
                Estás en <strong class="font-medium text-[var(--text)]">{{ $componenteActual->nombre }}</strong>.
                @if($puedeElegirOtro)
                    Puedes elegir otro componente en el menú, debajo de la dependencia.
                @else
                    Cuando se activen, aparecerán en el menú.
                @endif
            </p>
        @endif

        {{-- Lo que sigue disponible para todos, con o sin módulos. --}}
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <a href="{{ route('manual') }}"
               class="foco rounded-md px-3 py-2 text-sm font-medium text-[var(--primary)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                Manual de usuario
            </a>
            <a href="{{ route('perfil.edit') }}"
               class="foco rounded-md px-3 py-2 text-sm font-medium text-[var(--primary)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                Mi perfil
            </a>
        </div>
    </div>
</div>

@endsection
