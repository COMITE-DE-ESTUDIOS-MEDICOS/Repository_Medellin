@extends('layouts.app')
@section('titulo', 'Manual de usuario')

@push('estilos')
    @include('manual._estilos')
@endpush

@section('contenido')

<div class="manual mx-auto max-w-4xl overflow-hidden rounded-xl shadow-sm ring-1 ring-[var(--border)]">
    {{-- Encabezado con la imagen del Comité. --}}
    <header style="background:var(--carbon);color:#fff" class="flex flex-wrap items-center gap-6 px-8 py-8">
        <img src="{{ asset('img/logo-cem-oscuro.png') }}" alt="Comité de Estudios Médicos" class="h-14 w-auto">
        <div class="min-w-0 flex-1">
            <p class="marca" style="color:var(--bruma);letter-spacing:.24em;font-size:12px;font-weight:600;margin:0">DOCUMENTA · {{ mb_strtoupper($dependenciaActual?->nombre ?? 'Inclusión Social') }}</p>
            <h1 style="color:#fff;font-size:30px;font-weight:600;margin:2px 0 0;letter-spacing:.02em">Manual de usuario</h1>
            <p style="color:var(--bruma);margin:4px 0 0;font-size:13px">Versión {{ config('app.version') }} · actualizado {{ now()->translatedFormat('F \\d\\e Y') }}</p>
        </div>
        <a href="{{ route('manual.pdf') }}" target="_blank" rel="noopener"
           style="background:var(--bruma);color:var(--carbon);font-family:'Quicksand',sans-serif"
           class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold hover:brightness-95">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            Descargar PDF
        </a>
    </header>
    <div style="height:6px;background:var(--bruma)"></div>

    <div class="px-6 py-6 sm:px-10">
        @include('manual._contenido')
    </div>
</div>

@endsection
