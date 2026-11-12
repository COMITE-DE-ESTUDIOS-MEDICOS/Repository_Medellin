@props([
    // A dónde se pregunta si la identificación ya está registrada. Lo pone
    // cada enlace: el de turno y el de evidencias tienen su propia ruta.
    'consulta',

    // Del componente salen los nodos del registro.
    'componente' => null,

    // Sin registro: solo entra quien ya está registrado (derivaciones).
    'registro' => true,
])

@php
    // Si la validación devolvió errores del registro, o si el registro llegó
    // lleno y falló otra cosa, vuelve abierto: ahí está lo que hay que ver.
    $registroAbierto = $errors->has('colaborador.*') || filled(old('colaborador'));
@endphp

{{--
    Identificarse por cédula en un enlace público. El script (cedula.js)
    pregunta al salir del campo; si la reconoce saluda y cierra el registro,
    y si no, lo deja abierto para llenarlo una sola vez.

    Sin el script, el registro queda a la vista y el servidor decide con lo
    que llegue: a quien ya está registrado no se le cambia nada.
--}}
<div data-campo-cedula
     data-consulta="{{ $consulta }}"
     data-csrf="{{ csrf_token() }}"
     @if($registroAbierto) data-registro-abierto @endif
     class="space-y-4">

    <div>
        <label for="cedula" class="block text-sm font-medium text-[var(--text)]">Número de identificación *</label>
        <input id="cedula" name="cedula" type="text" inputmode="numeric" required maxlength="20"
               autocomplete="off" value="{{ old('cedula') }}" data-cedula-entrada
               class="liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm">
        <p class="mt-1 text-xs text-[var(--muted)]">{{ $registro ? 'Es tu llave: con ella te reconocemos la próxima vez.' : 'Tiene que estar registrada en el componente.' }}</p>
    </div>

    {{-- Al reconocerla, solo el primer nombre recortado. Nada más llega al navegador. --}}
    <div data-cedula-saludo aria-live="polite"
         class="hidden items-center justify-between gap-3 rounded-lg bg-[var(--card-soft)] px-3 py-2.5 ring-1 ring-[var(--border)]">
        <p data-cedula-saludo-texto class="text-sm font-medium text-[var(--text)]"></p>
        <button type="button" data-cedula-no-soy-yo class="shrink-0 text-sm text-[var(--primary)] underline">
            No soy yo
        </button>
    </div>

    <p data-cedula-aviso aria-live="polite" class="hidden text-xs font-medium text-[var(--warning)]"></p>

    <div data-cedula-registro>
        @if($registro)
            <x-campos.registro-colaborador :componente="$componente" />
        @endif
    </div>
</div>
