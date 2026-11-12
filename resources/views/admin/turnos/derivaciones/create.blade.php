@extends('layouts.app')
@section('titulo', 'Nuevo caso')

@section('contenido')

@php
    $creado = session('caso.creado');
    $campo = 'liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $esOtra = old('remitente') === 'otra';
@endphp

<div class="mx-auto max-w-xl">

@if($creado)
    {{-- DOC-20: el código en grande, para dictárselo a la persona de campo. --}}
    <div class="rounded-xl bg-[var(--card)] p-6 text-center ring-1 ring-[var(--border)]" role="status">
        <p class="text-sm text-[var(--muted)]">Caso creado. Díctale este código a la persona de campo:</p>
        <p class="mt-3 font-mono text-5xl font-bold tracking-wide text-[var(--primary)] sm:text-6xl">{{ $creado['codigo'] }}</p>
        <p class="mt-4 text-sm text-[var(--text)]">{{ $creado['ubicacion'] }}</p>
        <p class="mt-1 text-sm text-[var(--muted)]">
            El tiempo empezó a las {{ $creado['hora'] }}. Debe atenderse antes de las {{ $creado['limite'] }}.
        </p>
        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <a href="{{ route('admin.turnos.derivaciones.create') }}"
               class="liquid-button-primary rounded-md px-4 py-3 text-sm font-medium">Crear otro caso</a>
            <a href="{{ route('admin.turnos.derivaciones.index') }}"
               class="rounded-md px-4 py-3 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Ver casos</a>
        </div>
    </div>
@elseif($componentes->isEmpty())
    <div class="liquid-alert liquid-alert-warning text-sm">
        Esta dependencia no tiene componentes activos. Pide a Administración que active uno para crear casos.
    </div>
@else
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Nuevo caso</h1>
    <p class="mb-5 text-sm text-[var(--muted)]">
        Al guardar, el caso recibe su código y el tiempo de atención empieza a correr con la hora actual.
    </p>

    @if($errors->any())
        <div class="liquid-alert {{ $errors->has('duplicado') ? 'liquid-alert-warning' : 'liquid-alert-error' }} mb-4 text-sm">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.turnos.derivaciones.store') }}" enctype="multipart/form-data"
          class="space-y-5 rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)]">
        @csrf
        {{-- Clave de un solo uso: el doble toque devuelve el mismo caso. --}}
        <input type="hidden" name="clave_formulario" value="{{ old('clave_formulario', $clave) }}">

        @if($componentes->count() === 1)
            <input type="hidden" name="componente_id" value="{{ $componentes->first()->id }}">
        @else
            <div>
                <label for="componente_id" class="{{ $etiqueta }}">Componente *</label>
                <select id="componente_id" name="componente_id" required class="{{ $campo }}">
                    <option value="">Elige el componente</option>
                    @foreach($componentes as $componente)
                        <option value="{{ $componente->id }}" @selected(old('componente_id') == $componente->id)>{{ $componente->nombre }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label for="descripcion" class="{{ $etiqueta }}">Mensaje para la atención *</label>
            <textarea id="descripcion" name="descripcion" rows="5" required maxlength="5000" class="{{ $campo }}">{{ old('descripcion') }}</textarea>
            <p class="{{ $ayuda }}">Lo que debe saber la persona de campo para atender el caso.</p>
        </div>

        <div>
            <label for="remitente" class="{{ $etiqueta }}">Quién lo envía *</label>
            <select id="remitente" name="remitente" required class="{{ $campo }}"
                    onchange="var o = this.value === 'otra', c = document.getElementById('bloque-otra'); c.hidden = !o; c.querySelector('input').required = o;">
                <option value="">Elige quién lo envía</option>
                @foreach(\App\Models\Derivacion::REMITENTES as $valor => $nombre)
                    <option value="{{ $valor }}" @selected(old('remitente') === $valor)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>

        <div id="bloque-otra" @unless($esOtra) hidden @endunless>
            <label for="remitente_otra" class="{{ $etiqueta }}">¿Cuál? *</label>
            <input id="remitente_otra" name="remitente_otra" type="text" maxlength="255" @required($esOtra)
                   value="{{ old('remitente_otra') }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="ubicacion" class="{{ $etiqueta }}">Ubicación *</label>
            <input id="ubicacion" name="ubicacion" type="text" required maxlength="500" value="{{ old('ubicacion') }}" class="{{ $campo }}">
            <p class="{{ $ayuda }}">Dirección, barrio o un punto de referencia.</p>
        </div>

        <div>
            <label for="comuna" class="{{ $etiqueta }}">Comuna *</label>
            <select id="comuna" name="comuna" required class="{{ $campo }}">
                <option value="">Elige la comuna o el corregimiento</option>
                @foreach(config('repositorio.comunas') as $numero => $nombre)
                    <option value="{{ $numero }}" @selected((string) old('comuna') === (string) $numero)>{{ $numero }} · {{ $nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <p class="{{ $etiqueta }}">Soporte del caso *</p>
            <p class="{{ $ayuda }}">Una foto con la cámara, o una imagen o un PDF del dispositivo (el oficio, el correo o el pantallazo).</p>
            <x-campo-archivo nombre="soporte" requerido camara formatos="publico" sello="Caso" />
        </div>

        @error('duplicado')
            <label class="flex items-start gap-2 rounded-lg p-3 text-sm text-[var(--text)] ring-1 ring-[var(--border)]">
                <input type="checkbox" name="confirmar_duplicado" value="1" class="mt-0.5">
                <span>Crear de todas formas: es un caso distinto.</span>
            </label>
        @enderror

        <div class="flex flex-col-reverse gap-3 border-t border-[var(--border)] pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.turnos.derivaciones.index') }}"
               class="rounded-md px-4 py-3 text-center text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-3 text-sm font-medium">Crear caso</button>
        </div>
    </form>
@endif

</div>

@endsection
