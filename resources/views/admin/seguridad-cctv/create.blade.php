@extends('layouts.app')
@section('titulo', 'Nuevo caso de Seguridad CCTV')

@section('contenido')

@php($creado = session('caso.creado'))

<div class="mx-auto max-w-xl">

@if($creado)
    {{-- El número en grande, para dictárselo a la persona de campo. --}}
    <div class="rounded-xl bg-[var(--card)] p-6 text-center ring-1 ring-[var(--border)]" role="status">
        <p class="text-sm text-[var(--muted)]">Caso registrado. Díctale este número a la persona de campo:</p>
        <p class="mt-3 font-mono text-5xl font-bold tracking-wide text-[var(--primary)] sm:text-6xl">{{ $creado['codigo'] }}</p>
        <p class="mt-4 text-sm text-[var(--text)]">{{ $creado['ubicacion'] }}</p>
        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <a href="{{ route('admin.turnos.cctv.create') }}"
               class="liquid-button-primary rounded-md px-4 py-3 text-sm font-medium">Registrar otro</a>
            <a href="{{ route('admin.turnos.cctv.show', $creado['id']) }}"
               class="rounded-md px-4 py-3 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Ver el caso</a>
            <a href="{{ route('admin.turnos.cctv.index') }}"
               class="rounded-md px-4 py-3 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Ver la lista</a>
        </div>
    </div>
@elseif($personas->isEmpty() || $nodos->isEmpty())
    <div class="liquid-alert liquid-alert-warning text-sm">
        Para registrar casos, este componente necesita al menos una persona de campo activa y un nodo activo.
    </div>
@else
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Nuevo caso de Seguridad CCTV</h1>
    <p class="mb-5 text-sm text-[var(--muted)]">Al guardar, el caso recibe su número (CCTV-001, CCTV-002…).</p>

    @if($errors->any())
        <div class="liquid-alert liquid-alert-error mb-4 text-sm">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.turnos.cctv.store') }}" enctype="multipart/form-data"
          class="space-y-5 rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)]">
        @csrf
        {{-- Clave de un solo uso: el doble toque devuelve el mismo caso. --}}
        <input type="hidden" name="clave_formulario" value="{{ old('clave_formulario', $clave) }}">

        @include('admin.seguridad-cctv._campos', ['caso' => null])

        <div>
            <p class="block text-sm font-medium text-[var(--text)]">Soporte del requerimiento (opcional)</p>
            <p class="mt-1 text-xs text-[var(--muted)]">Una foto con la cámara o una imagen del dispositivo.</p>
            <x-campo-archivo nombre="soporte" camara formatos="imagenes" sello="Seguridad CCTV" />
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-[var(--border)] pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.turnos.cctv.index') }}"
               class="rounded-md px-4 py-3 text-center text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-3 text-sm font-medium">Guardar caso</button>
        </div>
    </form>
@endif

</div>

@endsection
