@props([
    'nombre' => 'archivo',
    'requerido' => false,
    'autocompletar' => null,
    'compacto' => false,
    'varios' => false,
    'conNombres' => false,
    'camara' => false,
    // Con cámara, si también se puede elegir de la galería. Sin galería (turnos,
    // DOC-12) la foto solo se toma en el momento.
    'galeria' => true,
    'maximo' => null,

    // Lo que dice el sello de la foto debajo de la hora: «Evidencia», «Turno».
    'sello' => 'Evidencia',

    // De qué conjunto de config/repositorio.php salen los formatos: el de
    // dentro ('aplicacion', que incluye hojas de cálculo) o el de la puerta
    // pública ('publico', solo PDF y fotos). El mismo campo sirve a los dos
    // formularios, así que la lista tiene que venirle dada.
    'formatos' => 'aplicacion',
])

@php
    $extensiones = collect(config('repositorio.formatos.'.$formatos.'.extensiones'));
    $acepta = $extensiones->map(fn ($e) => '.'.$e)->join(',');
    $maximoMb = round(config('repositorio.tamano_maximo_kb') / 1024);

    // Con varios, el campo viaja como lista: archivo[].
    $nombreCampo = $varios ? $nombre.'[]' : $nombre;

    $botonSelector = 'flex flex-col items-center justify-center gap-1.5 rounded-lg border border-[var(--border)]
                      px-3 py-5 text-sm font-medium text-[var(--text)] hover:bg-[var(--card-soft)]
                      active:bg-[var(--card-soft)]';
@endphp

<div data-campo-archivo class="min-w-0"
     @if($autocompletar) data-autocompletar="{{ $autocompletar }}" @endif
     @if($maximo) data-maximo="{{ $maximo }}" @endif
     @if($camara) data-lado-maximo="{{ config('repositorio.imagen.lado_maximo') }}" data-sello="{{ $sello }}" @endif>

    {{--
        El campo nativo. Nunca se quita del HTML: si el script no corre, esto
        sigue siendo un formulario de archivo que funciona.
    --}}
    <input id="{{ $nombre }}" name="{{ $nombreCampo }}" type="file" data-entrada
           @required($requerido) @if($varios) multiple @endif
           @if($camara && ! $galeria) accept="image/*" capture="environment" @else accept="{{ $acepta }}" @endif
           class="mt-1 block w-full text-sm text-[var(--muted)] file:mr-3 file:rounded-md file:border-0
                  file:bg-[var(--card-soft)] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-[var(--text)]">

    @if($camara)
        {{--
            En el móvil no se arrastra nada: lo que hace falta son dos botones
            grandes. 'capture' lo pone y lo quita el script justo antes de
            abrir el campo, porque los dos no caben en un mismo input.
        --}}
        <div data-selectores class="mt-2 hidden {{ $galeria ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
            <button type="button" data-camara class="{{ $botonSelector }}">
                <svg class="size-7 text-[var(--muted)]" fill="none" stroke="currentColor"
                     stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                </svg>
                Tomar foto
            </button>

            @if($galeria)
            <button type="button" data-galeria class="{{ $botonSelector }}">
                <svg class="size-7 text-[var(--muted)]" fill="none" stroke="currentColor"
                     stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/>
                </svg>
                Elegir archivo
            </button>
            @endif
        </div>

        {{-- El motivo de que las fotos salgan sin coordenadas, y cómo arreglarlo. --}}
        <p data-aviso-ubicacion class="mt-2 hidden text-xs font-medium text-[var(--warning)]"></p>
    @else
        {{-- Zona para soltar. Oculta de entrada: la enseña el script. --}}
        <div data-zona
             class="liquid-dropzone mt-1 hidden cursor-pointer flex-col items-center justify-center text-center
                    {{ $compacto ? 'px-4 py-4' : 'px-6 py-10' }}">
            <svg class="{{ $compacto ? 'size-6' : 'size-10' }} text-[var(--muted)]" fill="none"
                 stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
            </svg>

            <p class="mt-2 text-sm font-medium text-[var(--text)]">
                @if($varios)
                    Arrastra los archivos aquí o <span class="text-[var(--primary)] underline">búscalos en tu equipo</span>
                @else
                    Arrastra el archivo aquí o <span class="text-[var(--primary)] underline">búscalo en tu equipo</span>
                @endif
            </p>

            @unless($compacto)
                <p class="mt-1 text-xs text-[var(--muted)]">
                    Se aceptan {{ $extensiones->map(fn ($e) => strtoupper($e))->join(', ') }} ·
                    máximo {{ $maximoMb }} MB cada uno
                    @if($maximo) · hasta {{ $maximo }} archivos @endif
                </p>
            @endunless
        </div>
    @endif

    {{-- Lo adjuntado, una tarjeta por archivo. --}}
    <div data-lista class="mt-3 hidden min-w-0 flex-col gap-2"></div>

    <p data-resumen class="mt-2 hidden text-xs text-[var(--muted)]"></p>

    {{-- Aviso al pasarse del tope. Lo llena y lo enseña el script. --}}
    <p data-aviso class="mt-2 hidden text-xs font-medium text-[var(--warning)]"></p>

    {{--
        El molde de cada tarjeta. Vive en un <template> para que el script
        no tenga que construir el HTML a mano concatenando cadenas: así el
        marcado y sus clases se leen aquí, junto al resto de la vista.
    --}}
    <template data-plantilla>
        <div class="relative flex min-w-0 max-w-full items-center gap-3 rounded-lg bg-[var(--card-soft)] p-2.5 pr-11 ring-1 ring-[var(--border)]">
            <img data-miniatura alt="" class="hidden size-14 shrink-0 rounded-md object-cover">

            <span data-icono class="hidden size-14 shrink-0 items-center justify-center rounded-md bg-[var(--card)] text-[var(--muted)]">
                <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
            </span>

            @if($camara)
                {{--
                    Cuándo se tomó la foto, según el reloj del teléfono que la
                    estampó. Otra lista paralela a archivo[], vacía para lo que
                    no viene de la cámara.
                --}}
                <input type="hidden" name="tomadas[]" data-tomada-campo value="">
            @endif

            <span class="min-w-0 flex-1">
                @if($conNombres)
                    {{--
                        El nombre del documento, uno por archivo. Viaja como
                        lista paralela a archivo[]: el orden de las tarjetas
                        es el mismo, así que en el servidor casan por posición.
                    --}}
                    {{-- Textarea que crece con el texto: un nombre largo se lee entero. --}}
                    <textarea name="nombres[]" data-nombre-campo rows="1"
                              placeholder="Nombre del documento"
                              class="liquid-input w-full min-w-0 max-w-full resize-none py-2 text-sm [field-sizing:content] [overflow-wrap:anywhere]"></textarea>
                    <span data-archivo class="mt-1 block text-xs text-[var(--muted)] [overflow-wrap:anywhere]"></span>
                @else
                    <span data-archivo class="block text-sm font-medium text-[var(--text)] [overflow-wrap:anywhere]"></span>
                    <span data-peso class="block text-xs text-[var(--muted)]"></span>
                @endif
            </span>

            {{-- Arriba a la derecha: por si se adjuntó el equivocado. --}}
            <button type="button" data-quitar title="Quitar el archivo"
                    class="absolute right-1.5 top-1.5 rounded-md p-2 text-[var(--muted)]
                           hover:bg-[var(--card)] hover:text-[var(--text)]">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
                <span class="sr-only">Quitar el archivo</span>
            </button>
        </div>
    </template>
</div>
