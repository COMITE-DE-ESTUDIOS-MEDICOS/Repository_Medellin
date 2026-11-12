{{--
    «Corregir hora» de una marca. Para quien revisa es editar; por debajo, la
    original queda anulada con el motivo y la reemplaza la nueva
    (Marcador::corregir). Se usa en Marcaciones, Novedades y la ficha.

    Parámetros: $marcacion y, opcional, $texto para el botón.
--}}
@can('corregir', $marcacion)
<details class="inline-block text-left">
    <summary class="cursor-pointer list-none text-sm text-[var(--primary)] hover:underline [&::-webkit-details-marker]:hidden">
        {{ $texto ?? 'Corregir hora' }}
    </summary>
    <form method="POST" action="{{ route('admin.turnos.marcaciones.corregir', $marcacion) }}"
          class="mt-2 flex w-72 flex-col gap-2 rounded-lg bg-[var(--card-soft)] p-3 ring-1 ring-[var(--border)]">
        @csrf @method('PATCH')
        <p class="text-xs text-[var(--muted)]">
            {{ $marcacion->tipo->etiqueta() }} marcada el {{ $marcacion->marcada_at->fechaHora() }}.
            La original queda en el historial, anulada.
        </p>
        <input name="marcada_at" type="datetime-local" required max="{{ now()->format('Y-m-d\TH:i') }}"
               value="{{ $marcacion->marcada_at->format('Y-m-d\TH:i') }}" class="liquid-input text-sm" aria-label="Hora correcta">
        <input name="motivo" required minlength="5" maxlength="500" placeholder="Motivo: ej. marcó al llegar a la base"
               class="liquid-input text-sm" aria-label="Motivo">
        <button class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Guardar corrección</button>
    </form>
    {{-- DOC-18: para un turno invertido o una marca de más. Coordinación y Administración. --}}
    @can('anular', $marcacion)
        <form method="POST" class="mt-2 flex w-72 flex-col gap-2 rounded-lg bg-[var(--card-soft)] p-3 ring-1 ring-[var(--border)]">
            @csrf @method('PATCH')
            <p class="text-xs text-[var(--muted)]">Si la marca no es de este tipo o sobra:</p>
            <input name="motivo" required minlength="5" maxlength="500" placeholder="Motivo: ej. reintentó y quedó de más"
                   class="liquid-input text-sm" aria-label="Motivo">
            <div class="flex gap-2">
                <button formaction="{{ route('admin.turnos.marcaciones.tipo', $marcacion) }}"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium ring-1 ring-[var(--border)] hover:bg-[var(--card)]">
                    Cambiar a {{ $marcacion->esEntrada() ? 'salida' : 'entrada' }}
                </button>
                <button formaction="{{ route('admin.turnos.marcaciones.anular', $marcacion) }}"
                        onclick="return confirm('¿Anular esta marca? Deja de contar y queda tachada en el historial.')"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium text-[var(--danger)] ring-1 ring-[var(--border)] hover:bg-[var(--card)]">
                    Anular marca
                </button>
            </div>
        </form>
    @endcan
</details>
@endcan
