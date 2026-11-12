@php
    $doc = $documento ?? null;
    // Al subir, el nombre de cada documento se escribe en su propia tarjeta,
    // así que aquí sobra. Al editar, este es el único que hay.
    $conNombre = $conNombre ?? true;
    $carpetaPorDefecto = $doc?->carpeta_id ?? ($carpetaActual?->id ?? null);
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    @if($conNombre)
        <div class="sm:col-span-2">
            <label for="nombre" class="{{ $etiqueta }}">Nombre del documento *</label>
            <textarea id="nombre" name="nombre" required rows="1"
                      class="{{ $campo }} resize-none [field-sizing:content]">{{ old('nombre', $doc?->nombre) }}</textarea>
        </div>
    @endif

    <div>
        <label for="carpeta_id" class="{{ $etiqueta }}">Carpeta</label>
        <select id="carpeta_id" name="carpeta_id" data-buscable class="{{ $campo }}">
            <option value="">Raíz de la dependencia</option>
            <x-opciones-carpeta :carpetas="$carpetas" :seleccionada="old('carpeta_id', $carpetaPorDefecto)" />
        </select>
    </div>

    <div>
        <label for="tipo_documento_id" class="{{ $etiqueta }}">Tipo de documento</label>
        <select id="tipo_documento_id" name="tipo_documento_id" class="{{ $campo }}">
            <option value="">Sin clasificar</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->id }}" @selected(old('tipo_documento_id', $doc?->tipo_documento_id) == $tipo->id)>
                    {{ $tipo->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="fecha_documento" class="{{ $etiqueta }}">Fecha del documento</label>
        <input id="fecha_documento" name="fecha_documento" type="date"
               value="{{ old('fecha_documento', $doc?->fecha_documento?->format('Y-m-d')) }}"
               class="{{ $campo }}">
        <p class="{{ $ayuda }}">La fecha del acta o informe, no la de carga.</p>
    </div>

    <div>
        <label for="etiquetas" class="{{ $etiqueta }}">Etiquetas de búsqueda</label>
        <input id="etiquetas" name="etiquetas" type="text"
               value="{{ old('etiquetas', $doc?->etiquetas->pluck('nombre')->join(', ')) }}"
               placeholder="actas, 2026, comité"
               class="{{ $campo }}">
        <p class="{{ $ayuda }}">Separadas por coma. Son el atajo del buscador.</p>
    </div>

    <div class="sm:col-span-2">
        <label for="descripcion" class="{{ $etiqueta }}">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="3" maxlength="2000"
                  class="{{ $campo }}">{{ old('descripcion', $doc?->descripcion) }}</textarea>
    </div>
</div>
