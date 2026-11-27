{{-- DOC-34: tarjeta de soporte/evidencia de una ficha. `accion` solo llega si el usuario puede cargar (y es evidencia, no soporte). --}}
@props(['nombre', 'documento' => null, 'n' => null, 'accion' => null, 'ver' => null, 'sello' => ''])

@php($campo = $n ? "evidencia{$n}" : null)

<div class="rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">{{ $nombre }}</p>

    @if($documento)
        @php($version = $documento->versionActual)
        <p class="mt-1 break-words text-sm text-[var(--text)]">{{ $documento->nombre }}</p>
        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            @if($ver)
                <a href="{{ $ver }}" target="_blank" rel="noopener" class="text-[var(--primary)] hover:underline">Ver imagen</a>
            @endif
            @can('view', $documento)
                <a href="{{ route('documentos.show', $documento) }}" class="text-[var(--primary)] hover:underline">Abrir en el repositorio</a>
            @endcan
        </div>
        {{-- Las evidencias del enlace y de la carga única no guardan creador; las de la ficha sí (DOC-34). --}}
        @if($campo && $documento->creado_por)
            <p class="mt-1 text-xs text-[var(--muted)]">Cargada desde la ficha por {{ $documento->creador?->name ?? '—' }} el {{ $documento->created_at->fechaHora() }}</p>
        @endif
        @if($version && $version->numero > 1)
            <p class="mt-1 text-xs text-[var(--muted)]">Versión {{ $version->numero }} · cambiada el {{ $version->created_at->fechaHora() }} por {{ $version->autor?->name ?? '—' }}</p>
        @endif
    @else
        <p class="mt-1 text-sm text-[var(--muted)]">Sin archivo</p>
        @if($accion && $campo)
            <details class="mt-2" @if($errors->has($campo)) open @endif>
                <summary class="cursor-pointer text-sm font-medium text-[var(--primary)]">Cargar evidencia</summary>
                <form method="POST" action="{{ $accion }}" enctype="multipart/form-data" class="mt-2 space-y-3">
                    @csrf
                    <x-campo-archivo :nombre="$campo" requerido camara formatos="imagenes" :sello="$sello" />
                    @error($campo)<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                    <button class="liquid-button-primary w-full rounded-md px-4 py-2.5 text-sm font-medium sm:w-auto">Guardar</button>
                </form>
            </details>
        @endif
    @endif
</div>
