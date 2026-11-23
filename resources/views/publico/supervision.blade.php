@extends('publico._base')

@section('contenido')

@php
    $registrada = session('supervision.registrada');
    $campo = 'liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $opcional = '<span class="font-normal text-[var(--muted)]">(opcional)</span>';
    $seccion = 'space-y-5 rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)]';
    $tituloSeccion = 'text-base font-semibold text-[var(--text)]';
    // Botones de opción como pastillas: se tocan fácil con el pulgar.
    $pastilla = 'flex cursor-pointer items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-[var(--text)] ring-1 ring-[var(--border)] transition
                 has-[:checked]:bg-[var(--primary)] has-[:checked]:text-white has-[:checked]:ring-[var(--primary)]';

    // Con errores del servidor, se abre el primer paso que tiene uno.
    $pasoDe = ['cedula' => 0, 'punto_critico_id' => 1, 'punto_critico_otro' => 1, 'nodo_id' => 1, 'fecha_visita' => 1, 'hora_visita' => 1,
        'dinamizador1' => 1, 'dinamizador2' => 1, 'tipo_turno_id' => 1, 'descripcion_punto' => 1,
        'calificacion_global' => 2, 'incidencia' => 3, 'casos_escalados' => 3, 'casos_escalados_cual' => 3, 'casos_prioritarios' => 3,
        'recomendaciones' => 3, 'observaciones' => 3, 'evidencia1' => 4, 'evidencia2' => 4]
        + array_fill_keys(array_keys(\App\Models\Supervision::CRITERIOS), 2);
    $pasoInicial = collect($errors->keys())->map(fn ($c) => $pasoDe[$c] ?? 0)->min() ?? 0;
@endphp

<div class="text-center">
    <h1 class="text-xl font-semibold text-[var(--text)]">Registro de supervisión</h1>
    <p class="mt-1 text-sm text-[var(--muted)]">
        {{ $componente->nombre }}{{ $enlace->nombre ? ' · '.$enlace->nombre : '' }}
    </p>
</div>

@if($registrada)
    <div class="mt-6 rounded-lg bg-success-soft p-4 text-center" role="status" data-sonido-al-cargar="envio">
        <p class="text-lg font-semibold text-[var(--success)]">Supervisión #{{ $registrada['numero'] }} registrada</p>
        <p class="mt-1 text-sm text-[var(--text)]">Gracias, {{ $registrada['saludo'] }}.</p>
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

<form method="POST" action="{{ route('supervision.registrar', ['token' => $token]) }}" enctype="multipart/form-data"
      data-pasos data-paso-inicial="{{ $pasoInicial }}" data-supervision
      data-consulta-dinamizador="{{ route('supervision.dinamizador', ['token' => $token]) }}"
      class="mt-6 space-y-5 scroll-mt-4">
    @csrf

    {{-- Cuándo se abrió el formulario (la «hora de inicio» del Forms) y la ubicación al enviar: los pone el script. --}}
    <input type="hidden" name="inicio" value="{{ old('inicio', now()->toIso8601String()) }}">
    <input type="hidden" name="lat">
    <input type="hidden" name="lng">
    <input type="hidden" name="precision_m">
    <input type="hidden" name="municipio">

    {{-- La cabecera de progreso: la enseña el script (pasos.js). --}}
    <div data-pasos-cabecera class="hidden space-y-3">
        <div class="h-2 overflow-hidden rounded-full bg-[var(--card-soft)] ring-1 ring-[var(--border)]">
            <div data-pasos-barra class="h-full rounded-full bg-[var(--primary)] transition-all duration-300" style="width: 20%"></div>
        </div>
        <ol data-pasos-puntos class="flex items-start justify-between gap-1"></ol>
        <p data-pasos-texto class="text-center text-sm font-medium text-[var(--muted)]"></p>
    </div>

    <section data-paso data-titulo="Quién supervisa" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">¿Quién supervisa?</h2>
        <x-campos.cedula :consulta="route('supervision.cedula', ['token' => $token])" :registro="false" />
    </section>

    <section data-paso data-titulo="La visita" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">La visita</h2>

        <div>
            <label for="punto_critico_id" class="{{ $etiqueta }}">Punto crítico *</label>
            <select id="punto_critico_id" name="punto_critico_id" class="{{ $campo }}" data-punto>
                <option value="">Elige el punto</option>
                @foreach($componente->puntosCriticos as $punto)
                    <option value="{{ $punto->id }}" @selected(old('punto_critico_id', $enlace->punto_critico_id) == $punto->id)>{{ $punto->nombre }}</option>
                @endforeach
                <option value="" data-otro @selected(filled(old('punto_critico_otro')))>Otro (escribirlo)</option>
            </select>
            <input name="punto_critico_otro" type="text" maxlength="255" value="{{ old('punto_critico_otro') }}" data-punto-otro
                   placeholder="¿Cuál punto?" class="{{ $campo }} {{ filled(old('punto_critico_otro')) ? '' : 'hidden' }}">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="nodo_id" class="{{ $etiqueta }}">Nodo *</label>
                <select id="nodo_id" name="nodo_id" required class="{{ $campo }}">
                    <option value="">Elige</option>
                    @foreach($componente->nodos as $nodo)
                        <option value="{{ $nodo->id }}" @selected(old('nodo_id', $enlace->nodo_id) == $nodo->id)>{{ $nodo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tipo_turno_id" class="{{ $etiqueta }}">Turno *</label>
                <select id="tipo_turno_id" name="tipo_turno_id" required class="{{ $campo }}">
                    <option value="">Elige</option>
                    @foreach($componente->tiposTurno as $tipo)
                        <option value="{{ $tipo->id }}" @selected(old('tipo_turno_id') == $tipo->id)>{{ $tipo->codigo }} · {{ $tipo->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="fecha_visita" class="{{ $etiqueta }}">Fecha *</label>
                <input id="fecha_visita" name="fecha_visita" type="date" required max="{{ now()->toDateString() }}"
                       value="{{ old('fecha_visita', now()->toDateString()) }}" class="{{ $campo }}">
            </div>
            <div>
                <label for="hora_visita" class="{{ $etiqueta }}">Hora de la visita *</label>
                <input id="hora_visita" name="hora_visita" type="time" required
                       value="{{ old('hora_visita', now()->hora()) }}" class="{{ $campo }}">
            </div>
        </div>

        @foreach([1 => true, 2 => false] as $n => $obligatorio)
            <div data-dinamizador>
                <label for="dinamizador{{ $n }}" class="{{ $etiqueta }}">Cédula del dinamizador #{{ $n }} {!! $obligatorio ? '*' : $opcional !!}</label>
                <input id="dinamizador{{ $n }}" name="dinamizador{{ $n }}" type="text" inputmode="numeric" maxlength="20" autocomplete="off"
                       @required($obligatorio) value="{{ old('dinamizador'.$n) }}" class="{{ $campo }}">
                <p data-dinamizador-saludo aria-live="polite" class="mt-1 hidden text-xs font-medium"></p>
            </div>
        @endforeach

        <div>
            <label for="descripcion_punto" class="{{ $etiqueta }}">Descripción del punto crítico *</label>
            <textarea id="descripcion_punto" name="descripcion_punto" rows="3" required maxlength="5000"
                      placeholder="Cómo está el punto, quiénes había, qué se observó" class="{{ $campo }}">{{ old('descripcion_punto') }}</textarea>
        </div>
    </section>

    <section data-paso data-titulo="Calificación" class="{{ $seccion }}">
        <div>
            <h2 class="{{ $tituloSeccion }}">Calificación de la dupla</h2>
            <p class="{{ $ayuda }}">De 1 (muy bajo) a 5 (excelente).</p>
        </div>

        @foreach(\App\Models\Supervision::CRITERIOS + ['calificacion_global' => 'Calificación global de la dupla'] as $criterio => $texto)
            <fieldset @class(['border-t border-[var(--border)] pt-4' => $criterio === 'calificacion_global'])>
                <legend class="{{ $etiqueta }}">{{ $texto }} *</legend>
                <div class="mt-2 grid grid-cols-5 gap-2">
                    @foreach(range(1, 5) as $valor)
                        <label class="{{ $pastilla }}">
                            <input type="radio" name="{{ $criterio }}" value="{{ $valor }}" required class="sr-only" @checked((string) old($criterio) === (string) $valor)>
                            {{ $valor }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </section>

    <section data-paso data-titulo="Hallazgos" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">Lo que se encontró</h2>

        <fieldset>
            <legend class="{{ $etiqueta }}">Incidencias detectadas en territorio *</legend>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach(\App\Models\Supervision::INCIDENCIAS as $valor => $texto)
                    <label class="{{ $pastilla }}">
                        <input type="radio" name="incidencia" value="{{ $valor }}" required class="sr-only" @checked(old('incidencia') === $valor)>
                        {{ $texto }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="{{ $etiqueta }}">¿Casos escalados al enlace de nodo? *</legend>
            <div class="mt-2 grid grid-cols-2 gap-2">
                @foreach(['si' => 'Sí', 'no' => 'No'] as $valor => $texto)
                    <label class="{{ $pastilla }}">
                        <input type="radio" name="casos_escalados" value="{{ $valor }}" required class="sr-only" data-escalados @checked(old('casos_escalados') === $valor)>
                        {{ $texto }}
                    </label>
                @endforeach
            </div>
            <div data-escalados-cual class="{{ old('casos_escalados') === 'si' ? '' : 'hidden' }}">
                <label for="casos_escalados_cual" class="{{ $etiqueta }} mt-3">¿Cuál? *</label>
                <textarea id="casos_escalados_cual" name="casos_escalados_cual" rows="2" maxlength="2000" class="{{ $campo }}">{{ old('casos_escalados_cual') }}</textarea>
            </div>
        </fieldset>

        <div>
            <label for="casos_prioritarios" class="{{ $etiqueta }}">Casos prioritarios del turno {!! $opcional !!}</label>
            <textarea id="casos_prioritarios" name="casos_prioritarios" rows="2" maxlength="2000" class="{{ $campo }}">{{ old('casos_prioritarios') }}</textarea>
        </div>

        <div>
            <label for="recomendaciones" class="{{ $etiqueta }}">Recomendaciones del apoyo territorial *</label>
            <textarea id="recomendaciones" name="recomendaciones" rows="3" required maxlength="5000" class="{{ $campo }}">{{ old('recomendaciones') }}</textarea>
        </div>

        <div>
            <label for="observaciones" class="{{ $etiqueta }}">Observaciones {!! $opcional !!}</label>
            <textarea id="observaciones" name="observaciones" rows="2" maxlength="5000" class="{{ $campo }}">{{ old('observaciones') }}</textarea>
        </div>
    </section>

    <section data-paso data-titulo="Evidencias" class="{{ $seccion }}">
        <div>
            <h2 class="{{ $tituloSeccion }}">Evidencias</h2>
            <p class="{{ $ayuda }}">Se toman con la cámara en el sitio; llevan la hora y el lugar.</p>
        </div>

        @foreach([1 => true, 2 => false] as $n => $obligatoria)
            <div>
                <p class="{{ $etiqueta }}">Evidencia #{{ $n }} {!! $obligatoria ? '*' : $opcional !!}</p>
                <x-campo-archivo nombre="evidencia{{ $n }}" :requerido="$obligatoria" camara :galeria="false" formatos="imagenes" sello="Apoyo Territorial" />
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
            Registrar supervisión
        </button>
    </div>

    {{-- Sin script: un solo botón al final, con todo a la vista. --}}
    <button data-pasos-sin-script class="liquid-button-primary w-full rounded-lg px-4 py-4 text-lg font-semibold">
        Registrar supervisión
    </button>
</form>

@endsection
