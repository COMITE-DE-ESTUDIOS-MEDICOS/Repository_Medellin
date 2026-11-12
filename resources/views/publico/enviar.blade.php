@extends('publico._base')
@section('titulo', 'Enviar documentos')

@php
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    // Campos altos: en un teléfono se toca con el pulgar, no con un ratón.
    $campo = 'liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm';
@endphp

@section('contenido')

<h1 class="text-lg font-semibold text-[var(--text)]">Enviar documentos</h1>

@if($proposito)
    <p class="mt-1 text-sm text-[var(--muted)]">{{ $proposito }}</p>
@endif

@if($errors->any())
    <div class="liquid-alert liquid-alert-error mt-4 text-sm">
        <ul class="list-inside list-disc space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Más de 50 archivos se envían por tandas (carga-tandas.js). --}}
<form method="POST" action="{{ route('envio.recibir', ['token' => $token]) }}"
          data-tandas data-por-tanda="{{ \App\Services\CargaPorTandas::porTanda() }}" data-mb-por-tanda="{{ config('repositorio.carga.mb_por_tanda') }}"
      enctype="multipart/form-data" class="mt-6 space-y-6">
    @csrf

    {{--
        La identidad la escribe quien envía, no quien generó el enlace. Es lo
        que queda como cadena de custodia junto a cada documento.
    --}}
    @if($componente)
        {{-- Persona de campo: la cédula basta; sus datos se llenan una sola vez. --}}
        <x-campos.cedula :consulta="route('envio.cedula', ['token' => $token])" :componente="$componente" />
    @else
    <fieldset class="space-y-4">
        <legend class="text-sm font-semibold text-[var(--text)]">Quién envía</legend>

        <div>
            <label for="remitente_nombre" class="{{ $etiqueta }}">Nombre completo *</label>
            <input id="remitente_nombre" name="remitente_nombre" type="text" required maxlength="255"
                   value="{{ old('remitente_nombre') }}" autocomplete="name"
                   class="{{ $campo }}">
        </div>

        <div>
            <label for="remitente_email" class="{{ $etiqueta }}">Correo *</label>
            <input id="remitente_email" name="remitente_email" type="email" required maxlength="255"
                   value="{{ old('remitente_email') }}" autocomplete="email"
                   inputmode="email" class="{{ $campo }}">
        </div>

        <div>
            <label for="remitente_entidad" class="{{ $etiqueta }}">Entidad o empresa *</label>
            <input id="remitente_entidad" name="remitente_entidad" type="text" required maxlength="255"
                   value="{{ old('remitente_entidad') }}" autocomplete="organization"
                   class="{{ $campo }}">
        </div>

        <div>
            <label for="nodo" class="{{ $etiqueta }}">Nodo *</label>
            <select id="nodo" name="nodo" required class="{{ $campo }}">
                <option value="">Selecciona…</option>
                @foreach(range(1, 6) as $n)
                    <option value="{{ $n }}" @selected(old('nodo') == $n)>Nodo {{ $n }}</option>
                @endforeach
            </select>
        </div>
    </fieldset>
    @endif

    <fieldset>
        <legend class="text-sm font-semibold text-[var(--text)]">Qué envías</legend>
        <p class="mt-1 text-xs text-[var(--muted)]">
            Hasta {{ $maximoArchivos }} archivos; si son muchos, se envían por pasos. PDF o fotos, máximo
            {{ round(config('repositorio.tamano_maximo_kb') / 1024) }} MB cada uno.
            Cada uno se guarda por separado, con el nombre que le pongas.
        </p>

        <x-campo-archivo requerido varios con-nombres camara formatos="publico" :maximo="$maximoArchivos" />
    </fieldset>

    <div>
        <label for="mensaje" class="{{ $etiqueta }}">
            Mensaje <span class="font-normal text-[var(--muted)]">(opcional)</span>
        </label>
        <textarea id="mensaje" name="mensaje" rows="3" maxlength="1000"
                  placeholder="Ej: actas de la sesión del 12 de agosto"
                  class="{{ $campo }}">{{ old('mensaje') }}</textarea>
    </div>

    <button class="liquid-button-primary w-full rounded-lg px-4 py-3.5 text-base font-medium sm:text-sm">
        Enviar
    </button>
</form>

@endsection
