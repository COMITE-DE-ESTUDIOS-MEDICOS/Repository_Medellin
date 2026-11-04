@extends('layouts.app')
@section('titulo', $carpetaActual?->nombre ?? 'Documentos')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <nav class="flex items-center gap-1 text-sm text-[var(--muted)]">
        <a href="{{ route('documentos.index') }}" class="hover:text-[var(--text)]">Inicio</a>
        @foreach($migas as $miga)
            <span>/</span>
            <a href="{{ route('documentos.index', ['carpeta' => $miga->uuid]) }}"
               class="{{ $loop->last ? 'font-medium text-[var(--text)]' : 'hover:text-[var(--text)]' }}">
                {{ $miga->nombre }}
            </a>
        @endforeach
    </nav>

    <div class="ml-auto flex flex-wrap gap-2">
        @if($carpetaActual)
            <a href="{{ route('carpetas.descargar', $carpetaActual) }}" download
               class="rounded-md bg-[var(--card)] px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                Descargar carpeta
            </a>
        @endif

        {{--
            Solo dentro de una carpeta: un enlace de carga tiene que decir a
            dónde entrega, y en la raíz no hay carpeta que poner.
        --}}
        @if($carpetaActual && auth()->user()?->can('create', App\Models\EnlaceCarga::class))
            <a href="{{ route('admin.enlaces.create', ['carpeta' => $carpetaActual->uuid]) }}"
               class="flex items-center gap-1.5 rounded-md bg-[var(--card)] px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                <svg class="size-4 text-[var(--muted)]" fill="none" stroke="currentColor" stroke-width="1.8"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                </svg>
                Enlace de carga
            </a>
        @endif

        @if($rolActual?->puedeEditar())
            <a href="{{ route('carpetas.create', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="rounded-md bg-[var(--card)] px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                Nueva carpeta
            </a>
            <a href="{{ route('documentos.create', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">
                Subir documento
            </a>
        @endif
    </div>
</div>

@if($busqueda)
    <p class="mb-4 text-sm text-[var(--muted)]">
        Resultados para <span class="font-medium text-[var(--text)]">«{{ $busqueda }}»</span>
        · <a href="{{ route('documentos.index') }}" class="text-[var(--primary)] hover:underline">limpiar búsqueda</a>
    </p>
@endif

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    @if($busqueda)<input type="hidden" name="q" value="{{ $busqueda }}">@endif
    @if($carpetaActual)<input type="hidden" name="carpeta" value="{{ $carpetaActual->uuid }}">@endif

    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Tipo</label>
        <select name="tipo" class="liquid-input mt-1 text-sm">
            <option value="">Todos</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->id }}" @selected(request('tipo') == $tipo->id)>{{ $tipo->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Desde</label>
        <input type="date" name="desde" value="{{ request('desde') }}" class="liquid-input mt-1 text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Hasta</label>
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="liquid-input mt-1 text-sm">
    </div>
    <button class="liquid-button-primary rounded-md px-3 py-1.5 text-sm">Filtrar</button>
</form>

@if($carpetas->isNotEmpty())
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($carpetas as $carpeta)
            @php
                $seleccionable = $destinos && $carpeta->activa;
                $editable = (bool) $rolActual?->puedeEditar();
                $lote = "lote-carpeta-{$carpeta->uuid}";
            @endphp
            {{--
                Marcada para copiar o mover, la tarjeta lo dice sola: anillo,
                tinte y la marca sobre el icono, todo con has-[:checked] sobre
                el check escondido. Con su menú abierto sube por encima de las
                vecinas, que si no lo taparían.
            --}}
            <div class="main-card group/carpeta relative p-4 ring-1 ring-[var(--border)] hover:ring-[var(--primary)]
                        has-[:checked]:border-[var(--primary)] has-[:checked]:ring-2 has-[:checked]:ring-[var(--primary)]
                        has-[:checked]:[background-color:color-mix(in_srgb,var(--primary)_8%,var(--card))]
                        has-[details[open]]:z-20">
                {{--
                    El check del lote sigue aquí, pero sin verse: lo marca
                    «Seleccionar» en el menú ⋮ y lo envía #form-lote como
                    siempre (lote-explorador.js).
                --}}
                @if($seleccionable)
                    <input type="checkbox" name="carpetas[]" value="{{ $carpeta->uuid }}" form="form-lote" data-lote-item
                           id="{{ $lote }}" hidden>
                @endif
                <a href="{{ route('documentos.index', ['carpeta' => $carpeta->uuid]) }}"
                   class="flex items-start gap-3 {{ $carpeta->activa ? '' : 'opacity-60' }} {{ $seleccionable || $editable ? 'pr-8' : '' }}">
                    <span class="relative shrink-0">
                        <svg class="size-8 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2 6a2 2 0 0 1 2-2h5.17a2 2 0 0 1 1.41.59L12 6h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6Z"/>
                        </svg>
                        @if($seleccionable)
                            <span aria-hidden="true"
                                  class="absolute -bottom-1 -right-1.5 hidden size-5 items-center justify-center rounded-full bg-[var(--primary)] text-[var(--card)] ring-2 ring-[var(--card)] group-has-[:checked]/carpeta:flex">
                                <svg class="size-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                          d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"/>
                                </svg>
                            </span>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="flex items-center gap-1 truncate font-medium text-[var(--text)]">
                            @if(isset($custodiadas[$carpeta->id]))
                                <svg class="size-3.5 shrink-0 text-[var(--primary)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <title>Bajo custodia</title>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                                </svg>
                            @endif
                            <span class="truncate">{{ $carpeta->nombre }}</span>
                        </p>
                        <p class="text-xs text-[var(--muted)]">
                            {{ $carpeta->documentos_count }} {{ Str::plural('documento', $carpeta->documentos_count) }}
                            @unless($carpeta->activa) · inactiva @endunless
                        </p>
                        {{-- Lo llena lote-explorador.js; describe también al botón ⋮. --}}
                        @if($seleccionable)
                            <span id="estado-{{ $lote }}" data-lote-estado="{{ $lote }}" class="sr-only"></span>
                        @endif
                    </div>
                </a>

                {{--
                    Un <details>, como el menú de la cuenta: se abre aunque el
                    JavaScript falle. menu-opciones.js le pone lo de un menú
                    (flechas, Escape, uno abierto a la vez). Va al lado del
                    enlace y no dentro, así que pulsarlo no abre la carpeta.
                --}}
                @if($seleccionable || $editable)
                    <details data-menu-opciones class="absolute right-1 top-1">
                        <summary aria-haspopup="menu" aria-expanded="false" aria-label="Opciones de la carpeta {{ $carpeta->nombre }}"
                                 @if($seleccionable) aria-describedby="estado-{{ $lote }}" @endif
                                 class="foco flex size-11 cursor-pointer list-none items-center justify-center rounded-full text-[var(--muted)]
                                        hover:bg-[var(--card-soft)] hover:text-[var(--text)] [&::-webkit-details-marker]:hidden">
                            <svg class="size-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="5" r="1.75"/>
                                <circle cx="12" cy="12" r="1.75"/>
                                <circle cx="12" cy="19" r="1.75"/>
                            </svg>
                        </summary>

                        <div role="menu" aria-label="Opciones de la carpeta {{ $carpeta->nombre }}"
                             class="absolute right-0 top-full z-30 mt-1 w-48 max-w-[calc(100vw-2rem)] overflow-hidden rounded-md bg-[var(--card)] py-1 shadow-lg ring-1 ring-[var(--border)]">
                            @if($editable)
                                <a href="{{ route('carpetas.edit', $carpeta) }}" role="menuitem" tabindex="-1"
                                   class="flex min-h-11 items-center gap-2.5 px-4 text-sm text-[var(--text)] hover:bg-[var(--card-soft)]
                                          focus-visible:bg-[var(--card-soft)] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--primary)]">
                                    <svg class="size-4 shrink-0 text-[var(--muted)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                                    </svg>
                                    Editar
                                </a>
                            @endif
                            {{-- El texto cambia con el check (has-[:checked]): no hay que tenerlo al día a mano. --}}
                            @if($seleccionable)
                                <button type="button" role="menuitem" tabindex="-1" data-lote-alternar="{{ $lote }}"
                                        class="flex min-h-11 w-full items-center gap-2.5 px-4 text-left text-sm text-[var(--text)] hover:bg-[var(--card-soft)]
                                               focus-visible:bg-[var(--card-soft)] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--primary)]">
                                    <svg class="size-4 shrink-0 text-[var(--muted)] group-has-[:checked]/carpeta:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                    <svg class="hidden size-4 shrink-0 text-[var(--muted)] group-has-[:checked]/carpeta:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                    <span class="group-has-[:checked]/carpeta:hidden">Seleccionar</span>
                                    <span class="hidden group-has-[:checked]/carpeta:inline">Quitar selección</span>
                                </button>
                            @endif
                        </div>
                    </details>
                @endif

                @unless($carpeta->activa)
                    @can('reactivar', $carpeta)
                        <form method="POST" action="{{ route('carpetas.reactivar', $carpeta) }}" class="mt-3 opacity-60">
                            @csrf @method('PATCH')
                            <button class="w-full rounded-md bg-[var(--success)] px-3 py-1.5 text-xs font-medium text-white hover:brightness-90">
                                Reactivar carpeta
                            </button>
                        </form>
                    @endcan
                @endunless
            </div>
        @endforeach
    </div>
@endif

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                @if($destinos)
                    <th class="w-10 py-3 pl-4">
                        <input type="checkbox" data-lote-todos aria-label="Marcar todos los documentos" class="size-4 cursor-pointer accent-[var(--primary)]">
                    </th>
                @endif
                <th class="px-4 py-3 font-medium">Documento</th>
                <th class="px-4 py-3 font-medium">Tipo</th>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Versión</th>
                <th class="px-4 py-3 font-medium">Tamaño</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($documentos as $documento)
                <tr class="{{ $documento->activo ? '' : 'bg-danger-soft' }}">
                    @if($destinos)
                        <td class="py-3 pl-4">
                            @if($documento->activo)
                                <input type="checkbox" name="documentos[]" value="{{ $documento->uuid }}" form="form-lote" data-lote-item data-lote-documento
                                       aria-label="Marcar {{ $documento->nombre }}" class="size-4 cursor-pointer accent-[var(--primary)]">
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-3">
                        <a href="{{ route('documentos.show', $documento) }}"
                           class="break-words font-medium text-[var(--text)] hover:text-[var(--primary)]">
                            {{ $documento->nombre }}
                        </a>
                        @unless($documento->activo)
                            <span class="ml-2 rounded bg-danger-soft px-1.5 py-0.5 text-xs font-medium text-[var(--danger)]">Inactivo</span>
                        @endunless
                        @if($documento->etiquetas->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach($documento->etiquetas as $etiqueta)
                                    <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs text-[var(--muted)]">{{ $etiqueta->nombre }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $documento->tipoDocumento?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $documento->fecha_documento?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">v{{ $documento->versionActual?->numero ?? 1 }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $documento->versionActual?->tamano_legible ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-3">
                            {{--
                                Solo si llegó de fuera: lleva al documento, donde
                                está su cadena de custodia —quién dijo ser, desde
                                qué IP y con qué huella entró—.
                            --}}
                            @if($documento->recepcion)
                                <a href="{{ route('documentos.show', $documento) }}#origen"
                                   title="Llegó por un enlace de carga: ver de dónde"
                                   class="flex items-center gap-1 rounded-md bg-success-soft px-2 py-1 text-xs font-medium text-[var(--success)] hover:underline">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                         viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    </svg>
                                    Origen
                                </a>

                                {{--
                                    Marca discreta, sin texto: solo el reloj y
                                    su title. Llegar fuera de horario no es una
                                    falta de nadie —el remitente es de fuera—,
                                    así que no compite con el resto de la fila.
                                --}}
                                @if($documento->recepcion->fuera_de_horario)
                                    <span title="Llegó fuera del horario hábil" class="text-[var(--warning)]">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2"
                                             viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                        <span class="sr-only">Llegó fuera del horario hábil</span>
                                    </span>
                                @endif
                            @endif

                            <a href="{{ route('documentos.descargar', $documento) }}" download
                               class="text-sm font-medium text-[var(--primary)] hover:underline">Descargar</a>

                            @unless($documento->activo)
                                @can('reactivar', $documento)
                                    <form method="POST" action="{{ route('documentos.reactivar', $documento) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-sm font-medium text-[var(--success)] hover:underline">Reactivar</button>
                                    </form>
                                @endcan
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $destinos ? 7 : 6 }}" class="px-4 py-10 text-center text-[var(--muted)]">
                        @if($busqueda)
                            No hay documentos que coincidan con la búsqueda.
                        @else
                            Esta carpeta está vacía.
                            @if($rolActual?->puedeEditar())
                                <a href="{{ route('documentos.create', ['carpeta' => $carpetaActual?->uuid]) }}"
                                   class="text-[var(--primary)] hover:underline">Sube el primer documento.</a>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $documentos->links() }}</div>

{{--
    Copiar o mover lo marcado (LoteController). Los checks están repartidos por
    la página y apuntan aquí con form="form-lote". La barra aparece al marcar
    algo (lote-explorador.js) y queda fija abajo mientras se recorre la lista.
--}}
@if($destinos)
    <form id="form-lote" method="POST" action="{{ route('documentos.lote') }}" data-lote-barra hidden
          class="fixed inset-x-4 bottom-4 z-40 mx-auto flex max-w-3xl flex-wrap items-center gap-2 rounded-lg bg-[var(--card)] p-3 shadow-lg ring-1 ring-[var(--border)] md:left-72">
        @csrf
        <p class="text-sm font-medium text-[var(--text)]"><span data-lote-cuenta>0</span> marcados</p>
        <select name="destino" data-buscable class="liquid-input min-w-0 flex-1 text-sm" aria-label="Carpeta de destino">
            <option value="">Raíz de la dependencia</option>
            @foreach($destinos as $id => $ruta)
                <option value="{{ $id }}" @selected($id === $carpetaActual?->id)>{{ $ruta }}</option>
            @endforeach
        </select>
        <button name="accion" value="copiar" class="liquid-button-primary rounded-md px-3 py-2 text-sm font-medium">Copiar aquí</button>
        <button name="accion" value="mover" data-lote-mover
                class="rounded-md bg-[var(--card-soft)] px-3 py-2 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:brightness-95">
            Mover aquí
        </button>
        <button type="button" data-lote-limpiar class="px-2 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Quitar marcas</button>
    </form>
@endif

@endsection
