@props([
    // Las carpetas que se ofrecen (ya filtradas por quién puede verlas).
    'carpetas',
    'seleccionada' => null,
])

{{--
    Las <option> de un selector de carpetas, con la ruta completa
    («Calle / Nodo 2 / Octubre») y en ese orden: dos carpetas que se llaman
    igual se distinguen por dónde están. El buscador lo pone select-buscable.js.
--}}
@php
    $rutas = \App\Models\Carpeta::destinosLegibles();
    $ordenadas = collect($carpetas)->sortBy(fn ($c) => $rutas[$c->id] ?? $c->nombre, SORT_NATURAL | SORT_FLAG_CASE);
@endphp

@foreach($ordenadas as $carpeta)
    <option value="{{ $carpeta->id }}" @selected((string) $seleccionada === (string) $carpeta->id)>{{ $rutas[$carpeta->id] ?? $carpeta->nombre }}</option>
@endforeach
