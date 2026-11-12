{{-- DOC-14: la analítica del filtro. Verde ≥ 4, ámbar ≥ 3, rojo < 3. --}}
@php
    $a = $analitica;
    $tarjeta = 'rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]';
    $titulo = 'text-xs font-medium uppercase tracking-wide text-[var(--muted)]';
    // El color sigue al número que se ve (con un decimal): 2,96 se lee «3,0» y no puede salir en rojo.
    $texto = fn ($n) => $n === null ? 'text-[var(--muted)]' : (round($n, 1) >= 4 ? 'text-[var(--success)]' : (round($n, 1) >= 3 ? 'text-[var(--warning)]' : 'text-[var(--danger)]'));
    $barra = fn ($n) => round((float) $n, 1) >= 4 ? 'bg-[var(--success)]' : (round((float) $n, 1) >= 3 ? 'bg-[var(--warning)]' : 'bg-[var(--danger)]');
    $num = fn ($n) => $n === null ? '—' : number_format($n, 1, ',', '.');
    $th = 'px-4 py-2 font-medium';
    $td = 'px-4 py-2';
@endphp

<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="{{ $tarjeta }}">
        <p class="{{ $titulo }}">Supervisiones</p>
        <p class="mt-1 text-2xl font-semibold text-[var(--text)]">{{ number_format($a['total'], 0, ',', '.') }}</p>
        <p class="text-xs text-[var(--muted)]">{{ $a['sin_ubicacion'] }} sin ubicación</p>
    </div>
    <div class="{{ $tarjeta }}">
        <p class="{{ $titulo }}">Promedio de criterios</p>
        <p class="mt-1 text-2xl font-semibold {{ $texto($a['promedio']) }}">{{ $num($a['promedio']) }}</p>
        <p class="text-xs text-[var(--muted)]">De 1 a 5, sobre los nueve criterios</p>
    </div>
    <div class="{{ $tarjeta }}">
        <p class="{{ $titulo }}">Calificación global de las duplas</p>
        <p class="mt-1 text-2xl font-semibold {{ $texto($a['global']) }}">{{ $num($a['global']) }}</p>
        <p class="text-xs text-[var(--muted)]">Promedio de la nota global</p>
    </div>
    <div class="{{ $tarjeta }}">
        <p class="{{ $titulo }}">Incidencias relevantes o críticas</p>
        <p class="mt-1 text-2xl font-semibold {{ $a['relevantes'] ? 'text-[var(--warning)]' : 'text-[var(--text)]' }}">{{ $a['relevantes'] }}</p>
        <p class="text-xs {{ $a['criticas'] ? 'font-semibold text-[var(--danger)]' : 'text-[var(--muted)]' }}">{{ $a['criticas'] }} {{ $a['criticas'] === 1 ? 'crítica' : 'críticas' }} · {{ $a['escalados'] }} con casos escalados</p>
    </div>
</div>

@if($a['total'] > 0)
    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        <div class="{{ $tarjeta }}">
            <p class="{{ $titulo }}">Por criterio (del más bajo al más alto)</p>
            <div class="mt-3 space-y-2.5">
                @foreach($a['criterios'] as $c)
                    <div>
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="text-[var(--text)]">{{ $c['criterio'] }}</span>
                            <span class="font-semibold {{ $texto($c['promedio']) }}">{{ $num($c['promedio']) }}</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-[var(--card-soft)] ring-1 ring-[var(--border)]">
                            <div class="h-full rounded-full {{ $barra($c['promedio']) }}" style="width: {{ ($c['promedio'] ?? 0) * 20 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="{{ $tarjeta }}">
            <p class="{{ $titulo }}">Dinamizadores con promedio más bajo</p>
            <ul class="mt-3 divide-y divide-[var(--border)] text-sm">
                @foreach($a['dinamizadores']->take(8) as $d)
                    <li class="flex items-center justify-between gap-3 py-1.5">
                        <a href="{{ route('admin.turnos.supervisiones.index', ['dinamizador' => $d['documento']] + request()->except('dinamizador', 'page')) }}"
                           class="min-w-0 truncate text-[var(--text)] hover:text-[var(--primary)]">
                            {{ $d['nombre'] }} <span class="text-xs text-[var(--muted)]">· {{ $d['visitas'] }} {{ $d['visitas'] === 1 ? 'visita' : 'visitas' }}</span>
                        </a>
                        <span class="shrink-0 font-semibold {{ $texto($d['promedio']) }}">{{ $num($d['promedio']) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-[var(--muted)]">Toca un nombre para ver solo sus supervisiones. Todos están en «Por dinamizador», abajo y en el Excel.</p>
        </div>
    </div>

    <details class="mb-3 rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-[var(--text)]">Por dinamizador ({{ $a['dinamizadores']->count() }})</summary>
        <div class="overflow-x-auto border-t border-[var(--border)]">
            <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
                    <tr><th class="{{ $th }}">Dinamizador</th><th class="{{ $th }}">Visitas</th><th class="{{ $th }}">Promedio</th><th class="{{ $th }}">Global</th><th class="{{ $th }}">Última visita</th></tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @foreach($a['dinamizadores'] as $d)
                        <tr>
                            <td class="{{ $td }} text-[var(--text)]">{{ $d['nombre'] }} <span class="block text-xs text-[var(--muted)]">CC {{ $d['documento'] }}</span></td>
                            <td class="{{ $td }} text-[var(--text)]">{{ $d['visitas'] }}</td>
                            <td class="{{ $td }} font-semibold {{ $texto($d['promedio']) }}">{{ $num($d['promedio']) }}</td>
                            <td class="{{ $td }} font-semibold {{ $texto($d['global']) }}">{{ $num($d['global']) }}</td>
                            <td class="{{ $td }} text-[var(--muted)]">{{ \Illuminate\Support\Carbon::parse($d['ultima'])->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <div class="mb-4 grid gap-3 lg:grid-cols-2">
        <details class="rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-[var(--text)]">Por quien supervisa ({{ $a['supervisores']->count() }})</summary>
            <table class="min-w-full divide-y divide-[var(--border)] border-t border-[var(--border)] text-sm">
                <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
                    <tr><th class="{{ $th }}">Supervisor</th><th class="{{ $th }}">Visitas</th><th class="{{ $th }}">Promedio que da</th></tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @foreach($a['supervisores'] as $s)
                        <tr>
                            <td class="{{ $td }} text-[var(--text)]">{{ $s->nombre }}</td>
                            <td class="{{ $td }} text-[var(--text)]">{{ $s->visitas }}</td>
                            <td class="{{ $td }} font-semibold {{ $texto((float) $s->promedio) }}">{{ $num((float) $s->promedio) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>

        <details class="rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-[var(--text)]">Por nodo ({{ $a['nodos']->count() }})</summary>
            <table class="min-w-full divide-y divide-[var(--border)] border-t border-[var(--border)] text-sm">
                <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
                    <tr><th class="{{ $th }}">Nodo</th><th class="{{ $th }}">Visitas</th><th class="{{ $th }}">Promedio</th><th class="{{ $th }}">Relevantes o críticas</th></tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @foreach($a['nodos'] as $n)
                        <tr>
                            <td class="{{ $td }} text-[var(--text)]">{{ $n['nodo'] }}</td>
                            <td class="{{ $td }} text-[var(--text)]">{{ $n['visitas'] }}</td>
                            <td class="{{ $td }} font-semibold {{ $texto($n['promedio']) }}">{{ $num($n['promedio']) }}</td>
                            <td class="{{ $td }} {{ $n['relevantes'] ? 'font-semibold text-[var(--warning)]' : 'text-[var(--muted)]' }}">{{ $n['relevantes'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    </div>
@endif
