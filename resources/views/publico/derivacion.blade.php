@extends('publico._base')

@section('contenido')

@php
    $atendida = session('derivacion.atendida');
    $campo = 'liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $horas = intdiv(\App\Models\Derivacion::META_MINUTOS, 60);

    // Con errores del servidor, se abre el primer paso que tiene uno.
    $pasoDe = ['cedula' => 0, 'numero' => 1, 'nodo_id' => 2, 'cumplio_requerimiento' => 2, 'observacion' => 2,
        'justificacion' => 2, 'evidencia1' => 3, 'evidencia2' => 3, 'evidencia3' => 3];
    $pasoInicial = collect($errors->keys())->map(fn ($campo) => $pasoDe[$campo] ?? 0)->min() ?? 0;
    $seccion = 'space-y-5 rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)]';
    $tituloSeccion = 'text-base font-semibold text-[var(--text)]';
@endphp

<div class="text-center">
    <h1 class="text-xl font-semibold text-[var(--text)]">Atender caso</h1>
    <p class="mt-1 text-sm text-[var(--muted)]">
        Derivaciones y respuesta territorial · {{ $componente->nombre }}{{ $enlace->nombre ? ' · '.$enlace->nombre : '' }}
    </p>
</div>

@if($atendida)
    <div class="mt-6 rounded-lg bg-success-soft p-4 text-center" role="status" data-sonido-al-cargar="envio">
        <p class="text-lg font-semibold text-[var(--success)]">Caso {{ $atendida['codigo'] }} atendido</p>
        <p class="mt-1 text-sm text-[var(--text)]">{{ $atendida['saludo'] }} · tiempo de atención: {{ $atendida['tiempo'] }}</p>
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

{{--
    Nada del caso se guarda en el navegador (DOC-20, condición 8): el caso se
    pide al servidor por POST (caso-derivacion.js) y solo vive en la página.
--}}
<form method="POST" action="{{ route('derivacion.atender', ['token' => $token]) }}" enctype="multipart/form-data"
      data-pasos data-paso-inicial="{{ $pasoInicial }}" autocomplete="off"
      data-caso-derivacion data-consulta="{{ route('derivacion.caso', ['token' => $token]) }}" data-csrf="{{ csrf_token() }}"
      class="mt-6 space-y-5 scroll-mt-4">
    @csrf

    {{-- La cabecera de progreso: la enseña el script (pasos.js). --}}
    <div data-pasos-cabecera class="hidden space-y-3">
        <div class="h-2 overflow-hidden rounded-full bg-[var(--card-soft)] ring-1 ring-[var(--border)]">
            <div data-pasos-barra class="h-full rounded-full bg-[var(--primary)] transition-all duration-300" style="width: 25%"></div>
        </div>
        <ol data-pasos-puntos class="flex items-start justify-between gap-1"></ol>
        <p data-pasos-texto class="text-center text-sm font-medium text-[var(--muted)]"></p>
    </div>

    <section data-paso data-titulo="Quién atiende" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">¿Quién atiende el caso?</h2>
        <x-campos.cedula :consulta="route('derivacion.cedula', ['token' => $token])" :registro="false" />
    </section>

    <section data-paso data-titulo="Caso" class="{{ $seccion }}" data-caso-paso>
        <div>
            <label for="numero" class="{{ $etiqueta }}">Código del caso *</label>
            <div class="mt-1.5 flex items-stretch gap-2">
                <span class="flex items-center rounded-lg bg-[var(--card-soft)] px-3 text-base font-semibold text-[var(--text)] ring-1 ring-[var(--border)]">DC-</span>
                <input id="numero" name="numero" type="text" inputmode="numeric" pattern="[0-9]{1,9}" maxlength="9" required
                       autocomplete="off" value="{{ old('numero') }}" data-caso-numero
                       class="liquid-input w-full py-2.5 text-base sm:text-sm">
                <button type="button" data-caso-buscar class="liquid-button-primary shrink-0 rounded-lg px-4 text-base font-semibold">
                    Ver caso
                </button>
            </div>
            <p class="{{ $ayuda }}">Solo los números del código que te dio la oficina.</p>
        </div>

        <p data-caso-aviso role="alert" aria-live="polite" class="hidden text-sm font-medium text-[var(--warning)]"></p>

        <div data-caso-detalle class="hidden space-y-3 rounded-lg bg-[var(--card-soft)] p-4 ring-1 ring-[var(--border)]">
            <p data-caso-codigo class="text-lg font-semibold text-[var(--text)]"></p>
            <div>
                <p class="text-xs font-medium text-[var(--muted)]">Mensaje</p>
                <p data-caso-mensaje class="whitespace-pre-line text-sm text-[var(--text)]"></p>
            </div>
            <div>
                <p class="text-xs font-medium text-[var(--muted)]">Quién lo envía</p>
                <p data-caso-entidad class="text-sm text-[var(--text)]"></p>
            </div>
            <div>
                <p class="text-xs font-medium text-[var(--muted)]">Ubicación</p>
                <p data-caso-ubicacion class="text-sm text-[var(--text)]"></p>
            </div>
            <div data-caso-soporte class="hidden">
                <p class="text-xs font-medium text-[var(--muted)]">Soporte</p>
                <img data-caso-soporte-imagen alt="Soporte del caso" class="mt-1 hidden max-h-96 w-full rounded-lg object-contain ring-1 ring-[var(--border)]">
                <a data-caso-soporte-pdf target="_blank" rel="noopener noreferrer"
                   class="mt-1 hidden text-sm font-medium text-[var(--primary)] underline">Abrir el soporte (PDF)</a>
                <p class="{{ $ayuda }}">El soporte se puede ver durante 10 minutos; después, vuelve a pulsar «Ver caso».</p>
            </div>
        </div>
    </section>

    <section data-paso data-titulo="Atención" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">La atención</h2>

        <div>
            <label for="nodo_id" class="{{ $etiqueta }}">Nodo que atiende *</label>
            <select id="nodo_id" name="nodo_id" required class="{{ $campo }}" data-caso-nodo>
                <option value="">Elige el nodo</option>
                @foreach($componente->nodos as $nodo)
                    <option value="{{ $nodo->id }}" @selected(old('nodo_id', $enlace->nodo_id) == $nodo->id)>{{ $nodo->nombre }}</option>
                @endforeach
            </select>
        </div>

        <fieldset>
            <legend class="{{ $etiqueta }}">¿Se cumplió con el requerimiento? *</legend>
            <div class="mt-1.5 grid grid-cols-2 gap-3">
                @foreach(['1' => 'Sí', '0' => 'No'] as $valor => $texto)
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-3 text-base font-medium text-[var(--text)] ring-1 ring-[var(--border)] has-[:checked]:bg-[var(--card-soft)] has-[:checked]:ring-[var(--primary)]">
                        <input type="radio" name="cumplio_requerimiento" value="{{ $valor }}" required data-caso-cumplio
                               @checked(old('cumplio_requerimiento') === (string) $valor)>
                        {{ $texto }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="observacion" class="{{ $etiqueta }}">Observación <span data-caso-observacion-marca>(obligatoria si la respuesta es No)</span></label>
            <textarea id="observacion" name="observacion" rows="3" maxlength="5000" autocomplete="off"
                      class="{{ $campo }}">{{ old('observacion') }}</textarea>
        </div>

        <div data-caso-justificacion>
            <label for="justificacion" class="{{ $etiqueta }}">Justificación <span data-caso-justificacion-marca>(obligatoria si pasaron más de {{ $horas }} h desde que se creó el caso)</span></label>
            <textarea id="justificacion" name="justificacion" rows="3" maxlength="5000" autocomplete="off"
                      class="{{ $campo }}">{{ old('justificacion') }}</textarea>
        </div>
    </section>

    <section data-paso data-titulo="Evidencias" class="{{ $seccion }}">
        <div>
            <h2 class="{{ $tituloSeccion }}">Evidencias fotográficas</h2>
            <p class="{{ $ayuda }}">Se les estampa la hora y el lugar. Tómalas en el sitio.</p>
        </div>

        @foreach([1, 2, 3] as $n)
            <div>
                <p class="{{ $etiqueta }}">Evidencia #{{ $n }} *</p>
                <x-campo-archivo nombre="evidencia{{ $n }}" requerido camara formatos="imagenes" sello="Derivación" />
            </div>
        @endforeach
    </section>

    <p data-pasos-aviso role="alert" class="hidden rounded-lg bg-[var(--card-soft)] px-3 py-2 text-sm font-medium text-[var(--warning)] ring-1 ring-[var(--border)]"></p>

    <div data-pasos-botones class="hidden flex items-center gap-3">
        <button type="button" data-pasos-atras
                class="rounded-lg px-4 py-3 text-base font-medium text-[var(--muted)] ring-1 ring-[var(--border)] hover:text-[var(--text)]">
            Atrás
        </button>
        <button type="button" data-pasos-siguiente class="liquid-button-primary flex-1 rounded-lg px-4 py-3 text-base font-semibold">
            Siguiente
        </button>
        <button data-pasos-enviar class="liquid-button-primary hidden flex-1 rounded-lg px-4 py-3 text-base font-semibold">
            Enviar atención
        </button>
    </div>

    {{-- Sin script: un solo botón al final, con todo a la vista. --}}
    <button data-pasos-sin-script class="liquid-button-primary w-full rounded-lg px-4 py-4 text-lg font-semibold">
        Enviar atención
    </button>
</form>

@endsection
