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

<div data-asignado data-consulta="{{ route('admin.turnos.cctv.persona') }}" data-csrf="{{ csrf_token() }}">
    <label for="cedula_asignado" class="{{ $etiqueta }}">Cédula de la persona a quien se le pasa la novedad (opcional)</label>
    <input id="cedula_asignado" name="cedula_asignado" type="text" inputmode="numeric" maxlength="20" autocomplete="off"
           value="{{ old('cedula_asignado', $caso?->asignado?->documento) }}" class="{{ $campo }}">
    <p data-asignado-nombre aria-live="polite" class="mt-1 text-sm font-medium text-[var(--text)]">{{ $caso?->asignado?->nombre }}</p>
    <p class="{{ $ayuda }}">Persona de campo activa de este componente. Si la dejas vacía, lo atiende cualquiera que esté en turno; si la escribes, solo ella.</p>
</div>

<div>
    <label for="nodo_id" class="{{ $etiqueta }}">Nodo que atiende el requerimiento (opcional)</label>
    <select id="nodo_id" name="nodo_id" class="{{ $campo }}">
        <option value="">Sin nodo: queda el de quien atienda</option>
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

{{-- Al escribir la cédula (o salir del campo) se muestra el nombre; el servidor revalida al guardar. --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const bloque = document.querySelector('[data-asignado]');
        const entrada = bloque.querySelector('input');
        const texto = bloque.querySelector('[data-asignado-nombre]');

        const buscar = async () => {
            const cedula = entrada.value.replace(/[^0-9A-Za-z]/g, '');
            texto.textContent = cedula === '' ? '' : 'Buscando…';
            if (cedula === '') { return; }

            try {
                const respuesta = await fetch(bloque.dataset.consulta, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': bloque.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ cedula }),
                });
                const datos = respuesta.ok ? await respuesta.json() : null;
                texto.textContent = datos === null ? 'No pudimos buscarla. Vuelve a intentarlo.'
                    : (datos.nombre ?? 'No hay una persona de campo activa de este componente con esa cédula.');
            } catch {
                texto.textContent = 'No pudimos buscarla. Revisa la conexión.';
            }
        };

        entrada.addEventListener('change', buscar);
        if (entrada.value !== '' && texto.textContent.trim() === '') { buscar(); }
    });
</script>
