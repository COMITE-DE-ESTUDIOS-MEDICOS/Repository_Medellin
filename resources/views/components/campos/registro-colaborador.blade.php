@props([
    'componente' => null,
])

@php
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    // Campos altos: en un teléfono se toca con el pulgar, no con un ratón.
    $campo = 'liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm';
    $opcional = '<span class="font-normal text-[var(--muted)]">(opcional)</span>';
@endphp

{{--
    Los datos de quien llega por primera vez. Se piden una sola vez: desde
    ahí basta la identificación, que va en el campo de arriba y es la llave.

    Obligatorios por ahora: identificación, nombre y nodo. La lista
    definitiva sale del formulario de Forms (PLAN.md §7.1).
--}}
<fieldset class="space-y-4">
    <legend class="text-sm font-semibold text-[var(--text)]">Tus datos</legend>
    <p class="text-xs text-[var(--muted)]">Solo esta vez. La próxima basta con tu identificación.</p>

    <div>
        <label for="colaborador_nombre" class="{{ $etiqueta }}">Nombre completo *</label>
        <input id="colaborador_nombre" name="colaborador[nombre]" type="text" required maxlength="255"
               value="{{ old('colaborador.nombre') }}" autocomplete="name" class="{{ $campo }}">
    </div>

    <div>
        <label for="colaborador_correo" class="{{ $etiqueta }}">Correo {!! $opcional !!}</label>
        <input id="colaborador_correo" name="colaborador[correo]" type="email" maxlength="255"
               value="{{ old('colaborador.correo') }}" autocomplete="email" inputmode="email"
               class="{{ $campo }}">
    </div>

    <div>
        <label for="colaborador_telefono" class="{{ $etiqueta }}">Teléfono {!! $opcional !!}</label>
        <input id="colaborador_telefono" name="colaborador[telefono]" type="tel" maxlength="30"
               value="{{ old('colaborador.telefono') }}" autocomplete="tel" inputmode="tel"
               class="{{ $campo }}">
    </div>

    <div>
        <label for="colaborador_entidad" class="{{ $etiqueta }}">Entidad o empresa {!! $opcional !!}</label>
        <input id="colaborador_entidad" name="colaborador[entidad]" type="text" maxlength="255"
               value="{{ old('colaborador.entidad') }}" autocomplete="organization" class="{{ $campo }}">
    </div>

    {{--
        El cargo se elige de los del componente del enlace (Calle: conductores,
        operador terapéutico, monitor de ruta). Sin lista, se escribe.
    --}}
    @php
        $cargos = $componente?->cargos() ?? [];
    @endphp
    <div>
        <label for="colaborador_cargo" class="{{ $etiqueta }}">Cargo *</label>
        @if($cargos)
            <select id="colaborador_cargo" name="colaborador[cargo]" required class="{{ $campo }}">
                <option value="">Selecciona…</option>
                @foreach($cargos as $cargo)
                    <option value="{{ $cargo }}" @selected(old('colaborador.cargo') === $cargo)>{{ $cargo }}</option>
                @endforeach
            </select>
        @else
            <input id="colaborador_cargo" name="colaborador[cargo]" type="text" required maxlength="255"
                   value="{{ old('colaborador.cargo') }}" autocomplete="organization-title" class="{{ $campo }}">
        @endif
    </div>

    <x-campos.selector-nodo :componente="$componente" />
</fieldset>
