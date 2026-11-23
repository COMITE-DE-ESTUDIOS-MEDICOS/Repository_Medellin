@extends('layouts.app')
@section('titulo', $documento->nombre)

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-x-1 gap-y-2 text-sm text-[var(--muted)]">
    <a href="{{ route('documentos.index') }}" class="hover:text-[var(--text)]">Inicio</a>
    @foreach($migas as $miga)
        <span>/</span>
        <a href="{{ route('documentos.index', ['carpeta' => $miga->uuid]) }}"
           class="hover:text-[var(--text)]">{{ $miga->nombre }}</a>
    @endforeach

    {{-- Pasar al archivo de al lado sin volver a la lista (también con ← y →). --}}
    @if($vecinos['total'] > 1)
        <nav class="ml-auto flex items-center gap-2" aria-label="Archivos de esta carpeta">
            @if($vecinos['anterior'])
                <a href="{{ route('documentos.show', $vecinos['anterior']) }}" data-navegar="anterior" rel="prev"
                   title="Anterior (←)" class="flex size-9 items-center justify-center rounded-full bg-[var(--card)] text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                    <span class="sr-only">Archivo anterior</span>
                </a>
            @endif
            <span class="tabular-nums">{{ $vecinos['posicion'] }} de {{ $vecinos['total'] }}</span>
            @if($vecinos['siguiente'])
                <a href="{{ route('documentos.show', $vecinos['siguiente']) }}" data-navegar="siguiente" rel="next"
                   title="Siguiente (→)" class="flex size-9 items-center justify-center rounded-full bg-[var(--card)] text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    <span class="sr-only">Archivo siguiente</span>
                </a>
            @endif
        </nav>
    @endif
</div>

@unless($documento->activo)
    <div class="liquid-alert liquid-alert-error mb-4 text-sm">
        <div>
            <p class="font-medium">Documento inactivo</p>
            <p class="mt-1">
                Motivo: {{ $documento->motivo_inactivacion }} ·
                {{ $documento->inactivador?->name }} ·
                {{ $documento->inactivado_at?->fechaHora() }}
            </p>
            <p class="mt-1">Solo lo ve el rol de administración. El registro se conserva completo.</p>
        </div>
    </div>
@endunless

{{--
    min-w-0 en las dos columnas: por defecto una celda de grid no se encoge
    por debajo de su contenido, así que la tabla de versiones estiraba la
    columna y con ella la página entera, y el teléfono acababa con scroll
    horizontal. Con esto la tabla se queda con su propio scroll, dentro de su
    tarjeta, que es donde estorba a nadie.
--}}
<div class="grid gap-6 lg:grid-cols-3">

    <div class="min-w-0 lg:col-span-2 space-y-6">

        <div class="rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
            <div class="flex flex-wrap items-start gap-4">
                <div class="min-w-0 flex-1">
                    <h1 class="break-words text-lg font-semibold text-[var(--text)]">{{ $documento->nombre }}</h1>
                    @if($documento->descripcion)
                        <p class="mt-2 whitespace-pre-line text-sm text-[var(--muted)]">{{ $documento->descripcion }}</p>
                    @endif
                    @if($documento->etiquetas->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach($documento->etiquetas as $etiqueta)
                                <a href="{{ route('documentos.index', ['q' => $etiqueta->nombre]) }}"
                                   class="rounded bg-[var(--card-soft)] px-2 py-0.5 text-xs text-[var(--muted)] hover:text-[var(--text)]">
                                    {{ $etiqueta->nombre }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex shrink-0 flex-col gap-2">
                    <a href="{{ route('documentos.descargar', $documento) }}" download
                       class="liquid-button-primary rounded-md px-4 py-2 text-center text-sm font-medium">
                        Descargar
                    </a>
                    @can('update', $documento)
                        <a href="{{ route('documentos.edit', $documento) }}"
                           class="rounded-md bg-[var(--card)] px-4 py-2 text-center text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                            Editar datos
                        </a>
                    @endcan
                </div>
            </div>

            @can('update', $documento)
                <form method="POST" action="{{ route('documentos.copiar', $documento) }}"
                      class="mt-4 flex flex-wrap items-center gap-2 border-t border-[var(--border)] pt-4">
                    @csrf
                    <label for="copiar_carpeta" class="text-sm text-[var(--muted)]">Copiar a</label>
                    <select id="copiar_carpeta" name="carpeta_id" data-buscable class="liquid-input min-w-0 flex-1 text-sm">
                        <option value="">Raíz</option>
                        <x-opciones-carpeta :carpetas="$carpetas" :seleccionada="$documento->carpeta_id" />
                    </select>
                    <button class="rounded-md bg-[var(--card)] px-4 py-2 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                        Copiar
                    </button>
                    @error('carpeta_id')<p class="w-full text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                </form>
            @endcan
        </div>

        @if($documento->esPrevisualizable())
            <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
                <div class="border-b border-[var(--border)] px-4 py-2 text-sm font-medium text-[var(--text)]">Vista previa</div>
                @if(str_starts_with($documento->versionActual->mime, 'image/'))
                    {{-- Fondo neutro detrás de la imagen: sin él, un PNG con transparencia
                         se mezcla con la tarjeta y no se distingue dónde acaba la foto. --}}
                    <div class="bg-[var(--card-soft)]">
                        <img src="{{ route('documentos.previsualizar', $documento) }}" alt="{{ $documento->nombre }}"
                             class="mx-auto max-h-[70vh]">
                    </div>
                @else
                    <iframe src="{{ route('documentos.previsualizar', $documento) }}"
                            class="h-[70vh] w-full" title="Vista previa de {{ $documento->nombre }}"></iframe>
                @endif
            </div>
        @endif

        <div id="comentarios" class="scroll-mt-4 rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <h2 class="border-b border-[var(--border)] px-4 py-2 text-sm font-medium text-[var(--text)]">
                Comentarios <span class="text-[var(--muted)]">({{ $documento->comentarios->count() }})</span>
            </h2>

            <ul class="divide-y divide-[var(--border)]">
                @forelse($documento->comentarios as $comentario)
                    <li id="comentario-{{ $comentario->id }}" class="scroll-mt-4 px-4 py-3 target:bg-[var(--card-soft)]">
                        <div class="flex items-baseline gap-2">
                            <p class="text-sm font-medium text-[var(--text)]">{{ $comentario->autor?->name ?? 'Usuario retirado' }}</p>
                            <p class="text-xs text-[var(--muted)]" title="{{ $comentario->created_at->fechaHora() }}">
                                {{ $comentario->created_at->diffForHumans() }}
                            </p>
                            @if($comentario->user_id === auth()->id() || auth()->user()->puedeGestionarEn($documento->dependencia_id))
                                <form method="POST" action="{{ route('documentos.comentarios.destroy', [$documento, $comentario]) }}" class="ml-auto"
                                      onsubmit="return confirm('¿Borrar este comentario?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-[var(--muted)] hover:text-[var(--danger)]">Borrar</button>
                                </form>
                            @endif
                        </div>
                        <p class="mt-1 whitespace-pre-line text-sm text-[var(--text)]">{{ $comentario->texto }}</p>
                    </li>
                @empty
                    <li class="px-4 py-3 text-sm text-[var(--muted)]">
                        Sin comentarios. Si la foto está mal tomada o no cumple el estándar, dilo aquí: quien la subió se entera por la campana.
                    </li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('documentos.comentarios.store', $documento) }}" class="space-y-2 border-t border-[var(--border)] p-4">
                @csrf
                <label for="texto" class="sr-only">Escribe un comentario</label>
                <textarea id="texto" name="texto" rows="2" required maxlength="2000"
                          placeholder="Ej: La foto está desenfocada, no se lee el documento. Tómala de nuevo."
                          class="liquid-input w-full resize-y text-sm [field-sizing:content]">{{ old('texto') }}</textarea>
                @error('texto')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                <div class="flex justify-end">
                    <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Comentar</button>
                </div>
            </form>
        </div>

        {{--
            Para quien sube, el archivo se reemplaza. Por debajo cada reemplazo
            es una versión: lo anterior no se borra, y el historial lo ve
            Coordinación para auditoría.
        --}}
        @canany(['subirVersion', 'verVersiones'], $documento)
        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            @can('subirVersion', $documento)
                <form method="POST" action="{{ route('documentos.versiones.store', $documento) }}"
                      enctype="multipart/form-data"
                      class="flex flex-wrap items-end gap-3 px-4 py-3">
                    @csrf
                    <h2 class="w-full text-sm font-medium text-[var(--text)]">Reemplazar archivo</h2>
                    <div class="flex-1 min-w-48">
                        <label for="archivo" class="block text-xs font-medium text-[var(--muted)]">Archivo nuevo</label>
                        <x-campo-archivo requerido compacto />
                    </div>
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-[var(--muted)]">Motivo del reemplazo (opcional)</label>
                        <input type="text" name="comentario" maxlength="255" placeholder="Ej: la foto no era la correcta"
                               class="liquid-input mt-1 w-full text-sm">
                    </div>
                    <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">
                        Reemplazar archivo
                    </button>
                </form>
            @endcan

            @can('verVersiones', $documento)
                <div class="border-t border-[var(--border)] px-4 py-3">
                    <h2 class="text-sm font-medium text-[var(--text)]">
                        Historial de archivos ({{ $documento->versiones->count() }})
                    </h2>
                    <p class="text-xs text-[var(--muted)]">Los archivos reemplazados se conservan para auditoría.</p>
                </div>

                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                    <tbody class="divide-y divide-[var(--border)]">
                        @foreach($documento->versiones as $version)
                            <tr class="{{ $loop->first ? 'bg-primary-soft' : '' }}">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-[var(--text)]">v{{ $version->numero }}</span>
                                    @if($loop->first)
                                        <span class="bg-primary-soft ml-1 rounded px-1.5 py-0.5 text-xs text-[var(--primary)]">actual</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[var(--muted)]">
                                    <p class="truncate">{{ $version->nombre_original }}</p>
                                    @if($version->comentario)
                                        <p class="text-xs text-[var(--muted)]">{{ $version->comentario }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[var(--muted)]">{{ $version->tamano_legible }}</td>
                                <td class="px-4 py-3 text-[var(--muted)]">
                                    {{ $version->autor?->name ?? 'Usuario eliminado' }}<br>
                                    <span class="text-xs">{{ $version->created_at->fechaHora() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('documentos.versiones.descargar', [$documento, $version]) }}" download
                                       class="text-sm text-[var(--primary)] hover:underline">Descargar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @endcan
        </div>
        @endcanany
    </div>

    <aside class="min-w-0 space-y-6">
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h2 class="mb-3 text-sm font-medium text-[var(--text)]">Datos</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Tipo</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->tipoDocumento?->nombre ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Formato</dt>
                    <dd class="text-right text-[var(--text)]">
                        {{ $documento->versionActual?->extension ? strtoupper($documento->versionActual->extension) : '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Fecha del documento</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->fecha_documento?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Carpeta</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->carpeta?->nombre ?? 'Raíz' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Cargado por</dt>
                    {{--
                        Lo que entra por un enlace no tiene autor de dentro: lo
                        subió quien lo envió, y su nombre es el único que
                        responde a «quién puso esto aquí».
                    --}}
                    <dd class="text-right text-[var(--text)]">
                        @if($documento->creador)
                            {{ $documento->creador->name }}
                        @elseif($documento->recepcion)
                            {{ $documento->recepcion->remitente_nombre }}
                            <span class="mt-0.5 block text-xs text-[var(--muted)]">Desde fuera, por enlace</span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Cargado el</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->created_at->fechaHora() }}</dd>
                </div>
            </dl>
        </div>

        {{--
            Cadena de custodia. Solo aparece si el documento entró por un
            enlace de carga: es lo que el documento por sí solo no puede
            contar, porque nadie de dentro lo subió.
        --}}
        @if($documento->recepcion)
            @php($recepcion = $documento->recepcion)

            <div id="origen" class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                <h2 class="mb-1 text-sm font-medium text-[var(--text)]">
                    <span class="rounded bg-success-soft px-1.5 py-0.5 text-xs text-[var(--success)]">Recibido de fuera</span>
                </h2>
                <p class="mb-3 text-xs text-[var(--muted)]">
                    Llegó por un enlace de carga. Estos datos los declaró quien lo envió.
                </p>

                @if($recepcion->estado_escaneo->requiereAdvertencia())
                    <div class="mb-3 rounded-md bg-warning-soft px-3 py-2 text-xs text-[var(--warning)]">
                        {{ $recepcion->estado_escaneo->etiqueta() }}: trátalo con precaución.
                    </div>
                @endif

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">Lo envió</dt>
                        <dd class="text-right text-[var(--text)]">{{ $recepcion->remitente_nombre }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">Correo</dt>
                        <dd class="text-right text-[var(--text)]">{{ $recepcion->remitente_email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">Entidad</dt>
                        <dd class="text-right text-[var(--text)]">{{ $recepcion->remitente_entidad ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">Nodo</dt>
                        <dd class="text-right text-[var(--text)]">
                            {{ $recepcion->nodo ? 'Nodo '.$recepcion->nodo : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">Recibido el</dt>
                        <dd class="text-right text-[var(--text)]">
                            {{ $recepcion->created_at->fechaHora() }}

                            {{--
                                El juicio de horario es el que se hizo ese día,
                                no el que daría la configuración de hoy: por eso
                                se lee de la fila y no se recalcula aquí.
                            --}}
                            @if($recepcion->fuera_de_horario)
                                <span class="mt-0.5 block text-xs text-[var(--warning)]">Fuera del horario hábil</span>
                            @endif
                        </dd>
                    </div>

                    {{--
                        Cuánto tardó en responder desde que se le entregó el
                        enlace. Puede no haberlo: el enlace se borró, o es
                        anterior a que se registrara la fecha de entrega.
                    --}}
                    @php($minutos = $recepcion->minutosDesdeEnvio())
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">Tardó en responder</dt>
                        <dd class="text-right text-[var(--text)]">
                            @if($minutos === null)
                                —
                            @elseif($minutos < 60)
                                {{ $minutos }} min
                            @elseif($minutos < 60 * 24)
                                {{ intdiv($minutos, 60) }} h {{ $minutos % 60 }} min
                            @else
                                {{ intdiv($minutos, 60 * 24) }} d {{ intdiv($minutos % (60 * 24), 60) }} h
                            @endif
                        </dd>
                    </div>
                    @if($recepcion->tomada_at)
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--muted)]">Foto tomada el</dt>
                            <dd class="text-right text-[var(--text)]">
                                {{ $recepcion->tomada_at->fechaHora(true) }}
                                <span class="mt-0.5 block text-xs text-[var(--muted)]">Según el reloj del teléfono</span>
                            </dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">IP de origen</dt>
                        <dd class="text-right text-[var(--text)]">{{ $recepcion->ip_remitente ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">Archivo original</dt>
                        <dd class="text-right text-[var(--text)]">{{ $recepcion->nombre_original }}</dd>
                    </div>
                    <div>
                        <dt class="text-[var(--muted)]">Huella SHA-256</dt>
                        <dd class="mt-1 break-all font-mono text-xs text-[var(--text)]">{{ $recepcion->hash ?? '—' }}</dd>
                    </div>
                    @if($recepcion->agente)
                        <div>
                            <dt class="text-[var(--muted)]">Navegador</dt>
                            <dd class="mt-1 break-all text-xs text-[var(--text)]">{{ $recepcion->agente }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        @canany(['inactivar', 'reactivar'], $documento)
            <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                <h2 class="mb-1 text-sm font-medium text-[var(--text)]">Estado</h2>
                <p class="mb-3 text-xs text-[var(--muted)]">
                    Inactivar oculta el documento a lectores y editores. No se borra nada: queda para auditoría.
                </p>

                @if($documento->activo)
                    @can('inactivar', $documento)
                    <form method="POST" action="{{ route('documentos.inactivar', $documento) }}" class="space-y-2">
                        @csrf @method('PATCH')
                        <input type="text" name="motivo" required maxlength="255" placeholder="Motivo de la inactivación"
                               class="liquid-input w-full text-sm">
                        <button class="w-full rounded-md bg-[var(--danger)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                            Inactivar documento
                        </button>
                    </form>
                    @endcan
                @elseif(auth()->user()->can('reactivar', $documento))
                    <form method="POST" action="{{ route('documentos.reactivar', $documento) }}">
                        @csrf @method('PATCH')
                        <button class="w-full rounded-md bg-[var(--success)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                            Reactivar documento
                        </button>
                    </form>
                @endif
            </div>
        @endcanany
    </aside>
</div>

@endsection
