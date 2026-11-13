@extends('layouts.app')
@section('titulo', 'Corregir '.$derivacion->codigo())

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $atendida = ! $derivacion->estaPendiente();
@endphp

<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Corregir {{ $derivacion->codigo() }}</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        @if($derivacion->nombre){{ $derivacion->nombre }} · CC {{ $derivacion->cedula }}. @endif
        Quién lo atendió, sus archivos y las horas no se cambian aquí. La corrección queda en la auditoría.
    </p>

    @if($errors->any())
        <div class="liquid-alert liquid-alert-error mb-4 text-sm">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.turnos.derivaciones.update', $derivacion) }}"
          class="space-y-4 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf @method('PUT')

        @if($derivacion->esCaso())
            <div>
                <label for="descripcion" class="{{ $etiqueta }}">Mensaje para la atención *</label>
                <textarea id="descripcion" name="descripcion" rows="4" required maxlength="5000" class="{{ $campo }}">{{ old('descripcion', $derivacion->descripcion) }}</textarea>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="remitente" class="{{ $etiqueta }}">Quién lo envía *</label>
                    <select id="remitente" name="remitente" required class="{{ $campo }}">
                        @foreach(\App\Models\Derivacion::REMITENTES as $clave => $texto)
                            <option value="{{ $clave }}" @selected(old('remitente', $derivacion->remitente) === $clave)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="remitente_otra" class="{{ $etiqueta }}">Si es Otra, ¿cuál?</label>
                    <input id="remitente_otra" name="remitente_otra" type="text" maxlength="255" class="{{ $campo }}"
                           value="{{ old('remitente_otra', $derivacion->remitente === 'otra' ? $derivacion->entidad : '') }}">
                </div>
            </div>
        @else
            <div>
                <label for="nodo_id" class="{{ $etiqueta }}">Nodo que atiende *</label>
                <select id="nodo_id" name="nodo_id" required class="{{ $campo }}">
                    @foreach($nodos as $nodo)
                        <option value="{{ $nodo->id }}" @selected(old('nodo_id', $derivacion->nodo_id) == $nodo->id)>{{ $nodo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="entidad" class="{{ $etiqueta }}">Entidad que solicita *</label>
                <input id="entidad" name="entidad" type="text" required maxlength="255" value="{{ old('entidad', $derivacion->entidad) }}" class="{{ $campo }}">
            </div>
            <div>
                <label for="descripcion" class="{{ $etiqueta }}">Descripción del caso *</label>
                <textarea id="descripcion" name="descripcion" rows="4" required maxlength="5000" class="{{ $campo }}">{{ old('descripcion', $derivacion->descripcion) }}</textarea>
            </div>
        @endif

        <div>
            <label for="ubicacion" class="{{ $etiqueta }}">Ubicación *</label>
            <input id="ubicacion" name="ubicacion" type="text" required maxlength="500" value="{{ old('ubicacion', $derivacion->ubicacion) }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="comuna" class="{{ $etiqueta }}">Comuna</label>
            <select id="comuna" name="comuna" class="{{ $campo }}">
                <option value="">Sin comuna</option>
                @foreach(config('repositorio.comunas') as $numero => $nombre)
                    <option value="{{ $numero }}" @selected((string) old('comuna', $derivacion->comuna) === (string) $numero)>{{ $numero }} · {{ $nombre }}</option>
                @endforeach
            </select>
        </div>

        @if($atendida)
            @if($derivacion->esCaso())
                <div>
                    <label for="gestion_realizada" class="{{ $etiqueta }}">Atención o gestión realizada{{ $derivacion->gestion_realizada === null ? '' : ' *' }}</label>
                    <textarea id="gestion_realizada" name="gestion_realizada" rows="3" minlength="10" maxlength="2000" @required($derivacion->gestion_realizada !== null) class="{{ $campo }}">{{ old('gestion_realizada', $derivacion->gestion_realizada) }}</textarea>
                </div>
                <div>
                    <label for="observacion" class="{{ $etiqueta }}">Observación</label>
                    <textarea id="observacion" name="observacion" rows="3" maxlength="5000" class="{{ $campo }}">{{ old('observacion', $derivacion->observacion) }}</textarea>
                </div>
            @else
                <div>
                    <label for="respuesta" class="{{ $etiqueta }}">Respuesta</label>
                    <textarea id="respuesta" name="respuesta" rows="3" maxlength="5000" class="{{ $campo }}">{{ old('respuesta', $derivacion->respuesta) }}</textarea>
                </div>
            @endif
            <div>
                <label for="justificacion" class="{{ $etiqueta }}">Justificación en caso de no cumplir</label>
                <textarea id="justificacion" name="justificacion" rows="3" maxlength="5000" class="{{ $campo }}">{{ old('justificacion', $derivacion->justificacion) }}</textarea>
            </div>
        @endif

        @if($horas)
            <fieldset class="space-y-3 rounded-md p-4 ring-1 ring-[var(--warning)]">
                <legend class="px-1 text-sm font-semibold text-[var(--text)]">Horas y cumplimiento (contingencia)</legend>
                <p class="text-xs text-[var(--muted)]">Solo el superadministrador. Si cambias algo aquí, escribe el motivo: queda en la auditoría con el valor anterior y el nuevo.</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="fecha_requerimiento" class="{{ $etiqueta }}">Fecha de inicio *</label>
                        <input id="fecha_requerimiento" name="fecha_requerimiento" type="date" required
                               value="{{ old('fecha_requerimiento', $derivacion->fecha_requerimiento->toDateString()) }}" class="{{ $campo }}">
                    </div>
                    <div>
                        <label for="hora_requerimiento" class="{{ $etiqueta }}">Hora de inicio *</label>
                        <input id="hora_requerimiento" name="hora_requerimiento" type="time" required
                               value="{{ old('hora_requerimiento', substr($derivacion->hora_requerimiento, 0, 5)) }}" class="{{ $campo }}">
                    </div>
                </div>
                @if($atendida && $derivacion->esCaso())
                    <div>
                        <label for="atendida_at" class="{{ $etiqueta }}">Fecha y hora de cierre *</label>
                        <input id="atendida_at" name="atendida_at" type="datetime-local" required
                               value="{{ old('atendida_at', $derivacion->atendida_at?->format('Y-m-d\TH:i')) }}" class="{{ $campo }}">
                    </div>
                    <fieldset>
                        <legend class="{{ $etiqueta }}">¿Se cumplió el requerimiento? *</legend>
                        @php($cumplio = old('cumplio_requerimiento', $derivacion->cumplio_requerimiento === null ? null : (int) $derivacion->cumplio_requerimiento))
                        <div class="mt-1 flex gap-4 text-sm text-[var(--text)]">
                            <label><input type="radio" name="cumplio_requerimiento" value="1" required @checked((string) $cumplio === '1')> Sí</label>
                            <label><input type="radio" name="cumplio_requerimiento" value="0" @checked((string) $cumplio === '0')> No</label>
                        </div>
                    </fieldset>
                @elseif($atendida)
                    <div>
                        <label for="hora_respuesta" class="{{ $etiqueta }}">Hora de respuesta *</label>
                        <input id="hora_respuesta" name="hora_respuesta" type="time" required
                               value="{{ old('hora_respuesta', substr((string) $derivacion->hora_respuesta, 0, 5)) }}" class="{{ $campo }}">
                    </div>
                @endif
                <div>
                    <label for="motivo" class="{{ $etiqueta }}">Motivo de la corrección de horas</label>
                    <textarea id="motivo" name="motivo" rows="2" maxlength="1000" class="{{ $campo }}">{{ old('motivo') }}</textarea>
                </div>
            </fieldset>
        @endif

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.turnos.derivaciones.show', $derivacion) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Guardar corrección</button>
        </div>
    </form>
</div>

@endsection
