@extends('layouts.app')
@section('titulo', ($derivacion->esCaso() ? 'Caso ' : 'Derivación ').$derivacion->codigo())

@section('contenido')

@php
    $fila = 'grid gap-1 py-2 sm:grid-cols-3';
    $dt = 'text-xs font-medium uppercase tracking-wide text-[var(--muted)]';
    $dd = 'text-sm text-[var(--text)] sm:col-span-2 whitespace-pre-line';
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $abierto = $derivacion->esCaso() && $derivacion->estaPendiente();
    $meta = intdiv(\App\Models\Derivacion::META_MINUTOS, 60);
@endphp

<div class="mb-4 flex flex-wrap items-center gap-3 text-sm text-[var(--muted)]">
    <a href="{{ route('admin.turnos.derivaciones.index') }}" class="hover:text-[var(--text)]">Derivaciones</a>
    <span>/</span>
    <span class="font-medium text-[var(--text)]">{{ $derivacion->codigo() }}</span>
    @include('admin.turnos.derivaciones._estado')

    <div class="ml-auto flex gap-2">
        @can('update', $derivacion)
            <a href="{{ route('admin.turnos.derivaciones.edit', $derivacion) }}"
               class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Corregir</a>
        @endcan
        @can('delete', $derivacion)
            <form method="POST" action="{{ route('admin.turnos.derivaciones.destroy', $derivacion) }}"
                  onsubmit="return confirm('¿Eliminar {{ $derivacion->codigo() }}? Sale del reporte; sus fotos siguen en el repositorio.')">
                @csrf @method('DELETE')
                <button class="rounded-md px-3 py-1.5 text-sm text-[var(--danger)] ring-1 ring-[var(--border)]">Eliminar</button>
            </form>
        @endcan
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <dl class="divide-y divide-[var(--border)] rounded-lg bg-[var(--card)] px-6 py-2 ring-1 ring-[var(--border)] lg:col-span-2">
        @if($derivacion->esCaso())
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Creado por</dt><dd class="{{ $dd }}">{{ $derivacion->creador?->name ?? '—' }} · {{ $derivacion->created_at->fechaHora() }}</dd></div>
        @else
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Marca temporal</dt><dd class="{{ $dd }}">{{ $derivacion->created_at->fechaHora(true) }} · registro anterior</dd></div>
        @endif
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Inicio del tiempo</dt><dd class="{{ $dd }}">{{ $derivacion->requeridaAt()->fechaHora() }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Quién lo envía</dt><dd class="{{ $dd }}">{{ $derivacion->entidad }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">{{ $derivacion->esCaso() ? 'Mensaje para la atención' : 'Descripción del caso' }}</dt><dd class="{{ $dd }}">{{ $derivacion->descripcion }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Ubicación</dt><dd class="{{ $dd }}">{{ $derivacion->ubicacion }}</dd></div>
        <div class="{{ $fila }}">
            <dt class="{{ $dt }}">{{ $derivacion->esCaso() ? 'Quién lo atendió' : 'Quién escala' }}</dt>
            <dd class="{{ $dd }}">
                @if($abierto)
                    <span class="text-[var(--muted)]">Todavía nadie: el caso está abierto.</span>
                @else
                    {{ $derivacion->nombre }} · CC {{ $derivacion->cedula }}{{ $derivacion->cargo ? ' · '.$derivacion->cargo : '' }}
                @endif
            </dd>
        </div>
        @unless($abierto)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Nodo que atiende</dt><dd class="{{ $dd }}">{{ $derivacion->componente->nombre }} · {{ $derivacion->nodo?->nombre }}</dd></div>
        @endunless
        <div class="{{ $fila }}">
            <dt class="{{ $dt }}">Tiempo</dt>
            <dd class="{{ $dd }}">
                @if($derivacion->estaPendiente())
                    Lleva <span class="font-semibold" data-contador-desde="{{ $derivacion->requeridaAt()->toIso8601String() }}" data-meta-minutos="{{ \App\Models\Derivacion::META_MINUTOS }}">{{ $derivacion->tiempoLegible() }}</span>.
                    Meta: antes de las {{ $derivacion->requeridaAt()->addMinutes(\App\Models\Derivacion::META_MINUTOS)->hora() }}.
                @else
                    {{ $derivacion->atendida_at ? 'Atendido el '.$derivacion->atendida_at->fechaHora() : 'Respondida a las '.\Illuminate\Support\Carbon::parse($derivacion->hora_respuesta)->hora() }} ·
                    <span class="{{ $derivacion->cumplio() ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}">
                        {{ $derivacion->tiempoLegible() }} {{ $derivacion->cumplio() ? "(cumplió la meta de {$meta} h)" : "(no cumplió la meta de {$meta} h)" }}
                    </span>
                @endif
            </dd>
        </div>
        @if($derivacion->cumplio_requerimiento !== null)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">¿Se cumplió el requerimiento?</dt><dd class="{{ $dd }}">{{ $derivacion->cumplio_requerimiento ? 'Sí' : 'No' }}</dd></div>
        @endif
        @if($derivacion->observacion)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Observación</dt><dd class="{{ $dd }}">{{ $derivacion->observacion }}</dd></div>
        @endif
        @if($derivacion->respuesta)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Respuesta</dt>
                <dd class="{{ $dd }}">{{ $derivacion->respuesta }}<span class="mt-1 block text-xs text-[var(--muted)]">{{ $derivacion->respondedor?->name ?? 'Registrada con la derivación' }}</span></dd>
            </div>
        @endif
        @if($derivacion->justificacion)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Justificación</dt><dd class="{{ $dd }}">{{ $derivacion->justificacion }}</dd></div>
        @endif
    </dl>

    <div class="space-y-3">
        @if($contingencia)
            <details class="rounded-lg bg-[var(--card)] ring-1 ring-[var(--warning)]" @if($errors->any()) open @endif>
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-[var(--text)]">Cerrar a mano (contingencia)</summary>
                <form method="POST" action="{{ route('admin.turnos.derivaciones.contingencia', $derivacion) }}" class="space-y-3 border-t border-[var(--border)] p-4">
                    @csrf @method('PATCH')
                    <p class="text-xs text-[var(--muted)]">Solo si el caso se atendió y no se pudo enviar por el enlace. Queda en la auditoría con el motivo.</p>
                    @if($errors->any())
                        <div class="liquid-alert liquid-alert-error text-sm">
                            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <div>
                        <label for="colaborador_id" class="{{ $etiqueta }}">Persona de campo *</label>
                        <select id="colaborador_id" name="colaborador_id" required class="{{ $campo }}">
                            <option value="">Elige…</option>
                            @foreach($colaboradores as $c)
                                <option value="{{ $c->id }}" @selected(old('colaborador_id') == $c->id)>{{ $c->nombre }} · CC {{ $c->documento }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="nodo_id" class="{{ $etiqueta }}">Nodo que atiende *</label>
                        <select id="nodo_id" name="nodo_id" required class="{{ $campo }}">
                            <option value="">Elige…</option>
                            @foreach($nodosActivos as $nodo)
                                <option value="{{ $nodo->id }}" @selected(old('nodo_id') == $nodo->id)>{{ $nodo->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="atendida_at" class="{{ $etiqueta }}">Hora de cierre *</label>
                        <input id="atendida_at" name="atendida_at" type="datetime-local" required
                               min="{{ $derivacion->requeridaAt()->format('Y-m-d\TH:i') }}" max="{{ now()->format('Y-m-d\TH:i') }}"
                               value="{{ old('atendida_at') }}" class="{{ $campo }}">
                    </div>
                    <fieldset>
                        <legend class="{{ $etiqueta }}">¿Se cumplió el requerimiento? *</legend>
                        <div class="mt-1 flex gap-4 text-sm text-[var(--text)]">
                            <label><input type="radio" name="cumplio_requerimiento" value="1" required @checked(old('cumplio_requerimiento') === '1')> Sí</label>
                            <label><input type="radio" name="cumplio_requerimiento" value="0" @checked(old('cumplio_requerimiento') === '0')> No</label>
                        </div>
                    </fieldset>
                    <div>
                        <label for="observacion" class="{{ $etiqueta }}">Observación (obligatoria si es No)</label>
                        <textarea id="observacion" name="observacion" rows="2" maxlength="5000" class="{{ $campo }}">{{ old('observacion') }}</textarea>
                    </div>
                    <div>
                        <label for="justificacion" class="{{ $etiqueta }}">Justificación si pasó la meta de {{ $meta }} h</label>
                        <textarea id="justificacion" name="justificacion" rows="2" maxlength="5000" class="{{ $campo }}">{{ old('justificacion') }}</textarea>
                    </div>
                    <div>
                        <label for="motivo" class="{{ $etiqueta }}">Motivo del cierre a mano *</label>
                        <textarea id="motivo" name="motivo" rows="2" required maxlength="1000" class="{{ $campo }}">{{ old('motivo') }}</textarea>
                    </div>
                    <button class="liquid-button-primary w-full rounded-md px-4 py-2 text-sm font-medium">Cerrar el caso</button>
                </form>
            </details>
        @endif

        @foreach($derivacion->archivos() as $nombre => $documento)
            <div class="rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
                <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">{{ $nombre }}</p>
                @if($documento)
                    <a href="{{ route('documentos.show', $documento) }}" class="mt-1 block text-sm text-[var(--primary)] hover:underline">
                        {{ $documento->nombre }}
                    </a>
                @else
                    <p class="mt-1 text-sm text-[var(--muted)]">Sin archivo</p>
                @endif
            </div>
        @endforeach
    </div>
</div>

@endsection
