@extends('publico._base')

@section('contenido')

@php
    $marcado = session('turno.marcado');
    $fotoObligatoria = (bool) $componente->regla('foto_obligatoria');
    $nodoFijo = $enlace->nodo;
@endphp

<div class="text-center">
    <h1 class="text-xl font-semibold text-[var(--text)]">Marca tu turno</h1>
    <p class="mt-1 text-sm text-[var(--muted)]">
        {{ $componente->nombre }}{{ $nodoFijo ? ' · '.$nodoFijo->nombre : '' }}{{ $enlace->nombre ? ' · '.$enlace->nombre : '' }}
    </p>
</div>

@if($marcado)
    {{-- Lo primero que se ve al volver: qué quedó registrado y a qué hora. --}}
    {{-- Suena igual que toda confirmación (sonidos.js), salvo un doble toque, que no registró nada. --}}
    <div class="mt-6 rounded-lg bg-success-soft p-4 text-center" role="status"
         @unless($marcado['repetida']) data-sonido-al-cargar="envio" @endunless>
        <p class="text-lg font-semibold text-[var(--success)]">
            {{ $marcado['repetida'] ? 'Ya estaba registrada tu' : $marcado['tipo'].' registrada' }}
            {{ $marcado['repetida'] ? mb_strtolower($marcado['tipo']) : '' }}
        </p>
        <p class="mt-1 text-sm text-[var(--text)]">{{ $marcado['saludo'] }} · {{ $marcado['hora'] }}</p>
        @if($marcado['sin_ubicacion'])
            <p class="mt-2 text-xs text-[var(--warning)]">Se registró sin ubicación: Coordinación lo revisará.</p>
        @endif
    </div>
@endif

@if($errors->any())
    <div class="liquid-alert liquid-alert-error mt-6 text-sm">
        <ul class="list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('turno.marcar', ['token' => $token]) }}" enctype="multipart/form-data"
      data-turno data-precision-minima="{{ (int) $componente->regla('precision_minima_m') }}"
      data-ubicacion-obligatoria="{{ $componente->regla('ubicacion_obligatoria') ? '1' : '0' }}" class="mt-6 space-y-6">
    @csrf

    <x-campos.cedula :consulta="route('turno.cedula', ['token' => $token])" :componente="$componente" />

    {{--
        El nodo no se pregunta aquí: es el del QR si el QR es de un nodo, o el
        habitual de la persona (el que eligió al registrarse). Quien trabaje
        en otro nodo usa el QR de ese nodo.
    --}}

    {{-- DOC-12: solo en la entrada; turno.js la muestra según lo que toque. --}}
    <fieldset data-turno-epp>
        <legend class="text-sm font-medium text-[var(--text)]">¿Recibiste los elementos de protección personal (EPP)? *</legend>
        <div class="mt-2 grid grid-cols-2 gap-3">
            @foreach(['si' => 'Sí', 'no' => 'No'] as $valor => $texto)
                <label class="flex cursor-pointer items-center justify-center rounded-lg px-4 py-3 text-base font-semibold text-[var(--text)] ring-1 ring-[var(--border)] transition
                              has-[:checked]:bg-[var(--primary)] has-[:checked]:text-white has-[:checked]:ring-[var(--primary)]">
                    <input type="radio" name="epp" value="{{ $valor }}" required class="sr-only" @checked(old('epp') === $valor)>
                    {{ $texto }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <div>
        <p class="text-sm font-medium text-[var(--text)]">
            Foto {!! $fotoObligatoria ? '*' : '<span class="font-normal text-[var(--muted)]">(opcional)</span>' !!}
        </p>
        <p class="mt-1 text-xs text-[var(--muted)]">Se le estampa la hora y el lugar. Tómala en el sitio.</p>
        <x-campo-archivo nombre="foto" :requerido="$fotoObligatoria" camara :galeria="false" formatos="publico" sello="Turno" />
    </div>

    {{-- Las llena turno.js justo antes de enviar. --}}
    <input type="hidden" name="lat">
    <input type="hidden" name="lng">
    <input type="hidden" name="precision_m">
    <input type="hidden" name="municipio">
    <p data-turno-ubicacion aria-live="polite" class="hidden text-xs font-medium text-[var(--warning)]"></p>

    <button data-turno-boton class="liquid-button-primary w-full rounded-lg px-4 py-4 text-lg font-semibold">
        Registrar marca
    </button>
</form>

@endsection
