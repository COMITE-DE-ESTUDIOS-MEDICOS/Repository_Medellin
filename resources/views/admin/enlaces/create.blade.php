@extends('layouts.app')
@section('titulo', 'Nuevo enlace de carga')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
@endphp

<div class="mx-auto max-w-xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Nuevo enlace de carga</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        Genera una URL para que alguien de fuera suba archivos sin tener cuenta. Lo que envíe
        entra directo a la carpeta que elijas, y esa persona se identifica al enviarlo.
    </p>

    <form method="POST" action="{{ route('admin.enlaces.store') }}"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf

        <div>
            <label for="carpeta_id" class="{{ $etiqueta }}">Carpeta de destino *</label>
            <select id="carpeta_id" name="carpeta_id" required data-buscable class="{{ $campo }}">
                <option value="">Selecciona…</option>
                <x-opciones-carpeta :carpetas="$carpetas" :seleccionada="old('carpeta_id', $carpetaPorDefecto)" />
            </select>
            <p class="{{ $ayuda }}">
                @if($esAdministrador)
                    Todo lo que entre por este enlace se guardará aquí.
                @else
                    Solo puedes generar enlaces hacia las carpetas que lideras.
                @endif
            </p>
        </div>

        @if($componentes->isNotEmpty())
            <div>
                <label for="componente_id" class="{{ $etiqueta }}">
                    Componente <span class="font-normal text-[var(--muted)]">(si es para personas de campo)</span>
                </label>
                <select id="componente_id" name="componente_id" class="{{ $campo }}">
                    <option value="">Ninguno: es para alguien de fuera</option>
                    @foreach($componentes as $componente)
                        <option value="{{ $componente->id }}" @selected(old('componente_id') == $componente->id)>{{ $componente->nombre }}</option>
                    @endforeach
                </select>
                <p class="{{ $ayuda }}">
                    Con un componente, el formulario pide solo la cédula y toma los datos de la persona.
                    Sin componente, pide nombre, correo y entidad.
                </p>
            </div>
        @endif

        <div>
            <label for="proposito" class="{{ $etiqueta }}">Propósito</label>
            <input id="proposito" name="proposito" type="text" value="{{ old('proposito') }}"
                   placeholder="Actas del comité 2026" class="{{ $campo }}">
            <p class="{{ $ayuda }}">Es lo único que ve quien recibe el enlace, además del formulario.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="expira_at" class="{{ $etiqueta }}">Vence</label>
                <input id="expira_at" name="expira_at" type="date"
                       min="{{ now()->toDateString() }}"
                       value="{{ \Illuminate\Support\Str::substr(old('expira_at', ''), 0, 10) }}"
                       class="{{ $campo }}">
                <p class="{{ $ayuda }}">Vacío = sin vencimiento. Sirve todo el día elegido.</p>
            </div>
            <div>
                <label for="max_usos" class="{{ $etiqueta }}">Usos máximos</label>
                <input id="max_usos" name="max_usos" type="number" min="1" value="{{ old('max_usos') }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Vacío = sin límite de usos.</p>
            </div>
        </div>

        {{--
            El enlace no lleva identidad: cualquiera a quien se lo reenvíen
            puede subir. El vencimiento y el número de usos son el único
            control, así que conviene decirlo aquí y no en un manual.
        --}}
        <div class="liquid-alert liquid-alert-warning text-sm">
            <p>
                Quien tenga esta URL podrá subir a esa carpeta hasta que venza o se agote.
                Ponle un vencimiento corto si la vas a enviar por correo o WhatsApp.
            </p>
        </div>

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.enlaces.index') }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Crear enlace</button>
        </div>
    </form>
</div>

@endsection
