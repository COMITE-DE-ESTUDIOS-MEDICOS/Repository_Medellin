{{--
    La hora de una marca dentro de un turno. Quien puede corregir la toca y se
    abre «Corregir hora» (y, en Coordinación, cambiar tipo o anular). Lo de la
    ubicación no va aquí: sale como novedad del turno.
    Parámetros: $marca y, opcional, $delDia (la entrada: la salida de otro día lleva su fecha).
--}}
@php($dias = isset($delDia) ? (int) $delDia->copy()->startOfDay()->diffInDays($marca->marcada_at->copy()->startOfDay()) : 0)
@php($texto = $marca->marcada_at->hora().match (true) { $dias === 1 => ' (+1)', $dias > 1 => ' ('.$marca->marcada_at->format('d/m').')', default => '' })
@can('corregir', $marca)
    @include('admin.turnos._corregir', ['marcacion' => $marca, 'texto' => $texto])
@else
    <span class="text-[var(--text)]">{{ $texto }}</span>
@endcan
@if($marca->origen !== \App\Enums\OrigenMarcacion::Enlace)
    <span class="text-xs text-[var(--muted)]" title="{{ $marca->origen->etiqueta() }}{{ $marca->motivo ? ': '.$marca->motivo : '' }}">✎</span>
@endif
