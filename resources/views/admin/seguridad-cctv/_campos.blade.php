{{-- DOC-33: campos del registro, comunes a «Nuevo caso» y «Corregir». $caso es null al registrar. --}}
@php
    $campo = 'liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $entidad = old('entidad', $caso?->entidad);
    $requerido = old('requerido_at', ($caso?->requerido_at ?? now())->format('Y-m-d\TH:i'));
@endphp

<div>
    <label for="entidad" class="{{ $etiqueta }}">Entidad que solicita *</label>
    <select id="entidad" name="entidad" required class="{{ $campo }}"
            onchange="var o = this.value === 'otras', c = document.getElementById('bloque-otra'); c.hidden = !o; c.querySelector('input').required = o;">
        <option value="">Elige la entidad</option>
        @foreach(\App\Models\CasoCctv::ENTIDADES as $valor => $nombre)
            <option value="{{ $valor }}" @selected($entidad === $valor)>{{ $nombre }}</option>
        @endforeach
    </select>
</div>

<div id="bloque-otra" @unless($entidad === 'otras') hidden @endunless>
    <label for="entidad_otra" class="{{ $etiqueta }}">¿Cuál? *</label>
    <input id="entidad_otra" name="entidad_otra" type="text" maxlength="255" @required($entidad === 'otras')
           value="{{ old('entidad_otra', $caso?->entidad_otra) }}" class="{{ $campo }}">
</div>

<div>
    <label for="requerido_at" class="{{ $etiqueta }}">Fecha y hora del requerimiento *</label>
    <input id="requerido_at" name="requerido_at" type="datetime-local" required
           max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ $requerido }}" class="{{ $campo }}">
    <p class="{{ $ayuda }}">Cuándo llegó el requerimiento. No puede ser una fecha futura.</p>
</div>

<div>
    <label for="colaborador_asignado_id" class="{{ $etiqueta }}">A quién se le pasa la novedad *</label>
    <select id="colaborador_asignado_id" name="colaborador_asignado_id" required class="{{ $campo }}">
        <option value="">Elige la persona de campo</option>
        @foreach($personas as $persona)
            <option value="{{ $persona->id }}" @selected((string) old('colaborador_asignado_id', $caso?->colaborador_asignado_id) === (string) $persona->id)>{{ $persona->nombre }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="nodo_id" class="{{ $etiqueta }}">Nodo que atiende el requerimiento *</label>
    <select id="nodo_id" name="nodo_id" required class="{{ $campo }}">
        <option value="">Elige el nodo</option>
        @foreach($nodos as $nodo)
            <option value="{{ $nodo->id }}" @selected((string) old('nodo_id', $caso?->nodo_id) === (string) $nodo->id)>{{ $nodo->nombre }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="descripcion" class="{{ $etiqueta }}">Descripción del caso *</label>
    <textarea id="descripcion" name="descripcion" rows="5" required maxlength="5000" class="{{ $campo }}">{{ old('descripcion', $caso?->descripcion) }}</textarea>
</div>

<div>
    <label for="ubicacion" class="{{ $etiqueta }}">Ubicación del requerimiento *</label>
    <input id="ubicacion" name="ubicacion" type="text" required maxlength="500" value="{{ old('ubicacion', $caso?->ubicacion) }}" class="{{ $campo }}">
    <p class="{{ $ayuda }}">Dirección, barrio o un punto de referencia.</p>
</div>

<div>
    <label for="comuna" class="{{ $etiqueta }}">Comuna *</label>
    <select id="comuna" name="comuna" required class="{{ $campo }}">
        <option value="">Elige la comuna o el corregimiento</option>
        @foreach(config('repositorio.comunas') as $numero => $nombre)
            <option value="{{ $numero }}" @selected((string) old('comuna', $caso?->comuna) === (string) $numero)>{{ $numero }} · {{ $nombre }}</option>
        @endforeach
    </select>
</div>
