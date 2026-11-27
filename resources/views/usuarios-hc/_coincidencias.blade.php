{{-- DOC-26 (UHC-07): el aviso de coincidencias. Solo identificación, nunca caracterización ni historial (CA-21). --}}
@if($fichas->isNotEmpty())
    <div class="rounded-lg p-3 text-sm ring-1 ring-[var(--border)]" role="status">
        <p class="font-medium text-[var(--text)]">Ya hay fichas parecidas. Revisa que no sea la misma persona; igual puedes crear la ficha.</p>
        <ul class="mt-2 space-y-1 text-[var(--muted)]">
            @foreach($fichas as $f)
                <li>
                    <span class="text-[var(--text)]">{{ $f->nombre_completo }}</span>
                    · {{ $f->documentoLegible() }}
                    @if($f->numero_ficha) · ficha {{ $f->numero_ficha }} @endif
                    · registrada en {{ $f->componenteRegistro?->nombre ?? '—' }}
                </li>
            @endforeach
        </ul>
    </div>
@endif
