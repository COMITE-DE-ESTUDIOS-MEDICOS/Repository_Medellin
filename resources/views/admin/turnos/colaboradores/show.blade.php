@extends('layouts.app')
@section('titulo', $colaborador->nombre)

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <nav class="text-sm text-[var(--muted)]">
        <a href="{{ route('admin.turnos.colaboradores.index') }}" class="hover:text-[var(--text)]">Personas de campo</a>
        <span>/</span>
        <span class="font-medium text-[var(--text)]">{{ $colaborador->nombre }}</span>
    </nav>

    <div class="ml-auto flex gap-2">
        @can('verificar', $colaborador)
            <form method="POST" action="{{ route('admin.turnos.colaboradores.verificar', $colaborador) }}">
                @csrf @method('PATCH')
                <button class="rounded-md bg-[var(--success)] px-3 py-1.5 text-sm font-medium text-white hover:brightness-90">
                    Verificar
                </button>
            </form>
        @endcan
        <a href="{{ route('admin.turnos.colaboradores.edit', $colaborador) }}"
           class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Editar</a>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <aside class="min-w-0">
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h1 class="text-lg font-semibold text-[var(--text)]">{{ $colaborador->nombre }}</h1>
            <p class="text-sm text-[var(--muted)]">CC {{ $colaborador->documento }}</p>

            <dl class="mt-4 space-y-2 text-sm">
                @foreach([
                    'Componente' => $colaborador->componente?->nombre,
                    'Nodo habitual' => $colaborador->nodo?->nombre,
                    'Cargo' => $colaborador->cargo,
                    'Entidad' => $colaborador->entidad,
                    'Teléfono' => $colaborador->telefono,
                    'Correo' => $colaborador->correo,
                ] as $dato => $valor)
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">{{ $dato }}</dt>
                        <dd class="min-w-0 break-words text-right text-[var(--text)]">{{ $valor ?? '—' }}</dd>
                    </div>
                @endforeach
                <div class="flex justify-between gap-3 border-t border-[var(--border)] pt-2">
                    <dt class="text-[var(--muted)]">Registro</dt>
                    <dd class="text-right text-[var(--text)]">
                        {{ $colaborador->origen->etiqueta() }}
                        <span class="block text-xs text-[var(--muted)]">{{ $colaborador->created_at->fechaHora() }}</span>
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Verificación</dt>
                    <dd class="text-right">
                        @if($colaborador->estaVerificado())
                            <span class="text-[var(--success)]">Verificada</span>
                            <span class="block text-xs text-[var(--muted)]">
                                {{ $colaborador->verificador?->name ?? '—' }} · {{ $colaborador->verificado_at->format('d/m/Y') }}
                            </span>
                        @else
                            <span class="text-[var(--warning)]">Pendiente</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Estado</dt>
                    <dd class="text-right {{ $colaborador->activo ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}">
                        {{ $colaborador->activo ? 'Activa' : 'Desactivada' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Lo habitual y las ausencias: con esto se sabe a quién esperar cada día. --}}
        <div id="turno" class="mt-6 scroll-mt-4 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h2 class="text-sm font-medium text-[var(--text)]">Turno y ausencias</h2>

            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Turno habitual</dt>
                    <dd class="text-right text-[var(--text)]">
                        @if($colaborador->tipoTurno)
                            <span class="font-medium">{{ $colaborador->tipoTurno->codigo }}</span> · {{ $colaborador->tipoTurno->horario() }}
                            <span class="block text-xs text-[var(--muted)]">{{ $colaborador->tipoTurno->diasLegibles() }}</span>
                        @else
                            <span class="text-[var(--muted)]">Sin turno fijo</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Punto crítico</dt>
                    <dd class="min-w-0 break-words text-right text-[var(--text)]">{{ $colaborador->puntoCritico?->nombre ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Descansa</dt>
                    <dd class="text-right text-[var(--text)]">
                        {{ collect($colaborador->dias_descanso ?? [])->map(fn ($d) => App\Models\TipoTurno::DIAS[(int) $d] ?? null)->filter()->join(', ') ?: '—' }}
                    </dd>
                </div>
            </dl>

            @if($ausencias->isNotEmpty())
                <ul class="mt-4 divide-y divide-[var(--border)] border-t border-[var(--border)] text-sm">
                    @foreach($ausencias as $ausencia)
                        <li class="flex items-start gap-2 py-2">
                            <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-semibold text-[var(--text)]">{{ $ausencia->tipo->codigo }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="text-[var(--text)]">{{ $ausencia->rango() }}</span>
                                @if($ausencia->motivo)
                                    <span class="block text-xs text-[var(--muted)]">{{ $ausencia->motivo }}</span>
                                @endif
                            </span>
                            @can('update', $colaborador)
                                <form method="POST" action="{{ route('admin.turnos.ausencias.destroy', $ausencia) }}"
                                      onsubmit="return confirm('¿Quitar esta ausencia? Esos días se volverá a esperar a la persona.')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-[var(--muted)] hover:text-[var(--danger)]">Quitar</button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif

            @can('update', $colaborador)
                @if($tiposAusencia->isNotEmpty())
                    <form method="POST" action="{{ route('admin.turnos.ausencias.store', $colaborador) }}"
                          class="mt-4 space-y-2 border-t border-[var(--border)] pt-4">
                        @csrf
                        <p class="text-xs font-medium text-[var(--muted)]">Registrar ausencia</p>
                        <select name="tipo_turno_id" required class="liquid-input w-full text-sm" aria-label="Tipo de ausencia">
                            @foreach($tiposAusencia as $tipo)
                                <option value="{{ $tipo->id }}" @selected(old('tipo_turno_id') == $tipo->id)>{{ $tipo->codigo }} · {{ $tipo->nombre }}</option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-[var(--muted)]">Desde
                                <input type="date" name="desde" required value="{{ old('desde', now()->toDateString()) }}" class="liquid-input mt-1 w-full text-sm">
                            </label>
                            <label class="text-xs text-[var(--muted)]">Hasta
                                <input type="date" name="hasta" required value="{{ old('hasta', now()->toDateString()) }}" class="liquid-input mt-1 w-full text-sm">
                            </label>
                        </div>
                        <input type="text" name="motivo" maxlength="500" value="{{ old('motivo') }}" placeholder="Motivo (opcional)" class="liquid-input w-full text-sm">
                        @if($errors->hasAny(['tipo_turno_id', 'desde', 'hasta']))
                            <p class="text-xs text-[var(--danger)]">{{ $errors->first('tipo_turno_id') ?: $errors->first('desde') ?: $errors->first('hasta') }}</p>
                        @endif
                        <button class="w-full rounded-md bg-[var(--card-soft)] px-3 py-2 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:brightness-95">
                            Registrar
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </aside>

    <div class="min-w-0 space-y-6 lg:col-span-2">
        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            {{-- Un turno por fila y por quincena: la ficha no crece con los meses. --}}
            <div class="flex flex-wrap items-center gap-2 border-b border-[var(--border)] px-4 py-3">
                <h2 class="text-sm font-medium text-[var(--text)]">Turnos</h2>
                <nav class="ml-auto flex items-center gap-1 text-sm" aria-label="Quincena">
                    <a href="?q={{ $desde->copy()->subDay()->toDateString() }}" class="rounded-md px-2 py-1 text-[var(--muted)] hover:bg-[var(--card-soft)] hover:text-[var(--text)]" aria-label="Quincena anterior">‹</a>
                    <span class="min-w-32 text-center font-medium text-[var(--text)]">{{ $desde->day }} – {{ $hasta->day }} {{ $desde->locale('es')->isoFormat('MMM YYYY') }}</span>
                    @if($hasta->isPast())
                        <a href="?q={{ $hasta->copy()->addDay()->toDateString() }}" class="rounded-md px-2 py-1 text-[var(--muted)] hover:bg-[var(--card-soft)] hover:text-[var(--text)]" aria-label="Quincena siguiente">›</a>
                    @else
                        <span class="px-2 py-1 text-[var(--muted)] opacity-40">›</span>
                    @endif
                </nav>
            </div>

            @if(! $haMarcado)
                <p class="px-4 py-6 text-center text-sm text-[var(--muted)]">Todavía no ha marcado turno.</p>
            @else
                @php($pendientes = $turnos->sum(fn ($t) => $t['novedades']->where('estado', \App\Enums\EstadoNovedad::Pendiente)->count()))
                <p class="border-b border-[var(--border)] bg-[var(--card-soft)] px-4 py-2 text-xs text-[var(--muted)]">
                    <strong class="text-[var(--text)]">{{ $turnos->count() }}</strong> {{ $turnos->count() === 1 ? 'turno' : 'turnos' }}
                    · <strong class="text-[var(--text)]">{{ number_format($turnos->sum('trabajado') / 60, 1, ',', '.') }} h</strong> trabajadas
                    · <span class="{{ $pendientes ? 'text-[var(--warning)]' : '' }}">{{ $pendientes }} {{ $pendientes === 1 ? 'novedad pendiente' : 'novedades pendientes' }}</span>
                    @if($anuladas->isNotEmpty())
                        · {{ $anuladas->count() }} {{ $anuladas->count() === 1 ? 'corrección' : 'correcciones' }}
                    @endif
                </p>

                @if($turnos->isEmpty() && $sueltas->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-[var(--muted)]">Sin turnos en esta quincena.</p>
                @endif

                <ul class="divide-y divide-[var(--border)] text-sm">
                    {{-- Una marca sin pareja (una salida sin entrada): casi siempre una marca de más o del tipo equivocado. --}}
                    @foreach($sueltas as $marca)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-warning-soft px-4 py-2.5">
                            <span class="w-20 text-[var(--muted)]">{{ ucfirst($marca->marcada_at->locale('es')->isoFormat('ddd DD/MM')) }}</span>
                            <span class="font-medium text-[var(--warning)]">{{ $marca->tipo->etiqueta() }} sin pareja</span>
                            @include('admin.turnos.colaboradores._hora', ['marca' => $marca])
                            <span class="ml-auto text-xs text-[var(--muted)]">Revísala: puede sobrar o ser del otro tipo</span>
                        </li>
                    @endforeach

                    @foreach($turnos as $turno)
                        @php($entrada = $turno['entrada'])
                        @php($salida = $turno['salida'])
                        @php($raro = $turno['novedades']->contains('estado', \App\Enums\EstadoNovedad::Pendiente))
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 {{ $raro ? 'border-l-2 border-[var(--warning)]' : '' }}">
                            <span class="w-20 text-[var(--muted)]">{{ ucfirst($entrada->marcada_at->locale('es')->isoFormat('ddd DD/MM')) }}</span>
                            {{-- El turno de la malla que corresponde a la hora de entrada (el mismo del reporte). --}}
                            @if($turno['tipo'])
                                <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-semibold text-[var(--text)]" title="{{ $turno['tipo']->nombre }} · {{ $turno['tipo']->horario() }}">{{ $turno['tipo']->codigo }}</span>
                            @endif
                            <span class="flex flex-wrap items-center gap-1.5">
                                @include('admin.turnos.colaboradores._hora', ['marca' => $entrada])
                                <span class="text-[var(--muted)]">→</span>
                                @if($salida)
                                    @include('admin.turnos.colaboradores._hora', ['marca' => $salida, 'delDia' => $entrada->marcada_at])
                                    {{-- DOC-21 (CA-06): hasta que Coordinación corrija la salida o revise la novedad. --}}
                                    @if($salida->origen === \App\Enums\OrigenMarcacion::CierreAutomatico
                                        && $turno['novedades']->contains(fn ($n) => $n->tipo === \App\Enums\TipoNovedad::SinSalida && $n->estado === \App\Enums\EstadoNovedad::Pendiente))
                                        <span class="rounded bg-warning-soft px-1.5 py-0.5 text-xs font-medium text-[var(--warning)]" title="{{ $salida->motivo }}">Cierre automático · por corregir</span>
                                    @endif
                                @elseif($entrada->id === $ultimaId)
                                    <span class="font-medium text-[var(--success)]">en turno</span>
                                @else
                                    <span class="font-medium text-[var(--warning)]">sin salida</span>
                                @endif
                            </span>
                            @if($turno['trabajado'] !== null)
                                <span class="font-medium tabular-nums text-[var(--text)]" title="En el sitio {{ intdiv($turno['minutos'], 60) }} h {{ str_pad($turno['minutos'] % 60, 2, '0', STR_PAD_LEFT) }}; trabajadas, sin el descanso">
                                    {{ intdiv($turno['trabajado'], 60) }} h {{ str_pad($turno['trabajado'] % 60, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @endif
                            <span class="text-[var(--muted)]">{{ $entrada->nodo?->nombre }}</span>
                            @if($entrada->epp_recibidos === false)
                                <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-medium text-[var(--danger)] ring-1 ring-[var(--danger)]">Sin EPP</span>
                            @elseif($entrada->epp_recibidos === true)
                                <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-medium text-[var(--success)] ring-1 ring-[var(--success)]" title="Confirmó que recibió los elementos de protección personal">EPP ✓</span>
                            @endif
                            @foreach($turno['novedades'] as $novedad)
                                <a href="{{ route('admin.turnos.novedades.index', ['estado' => 'todas']) }}"
                                   class="rounded px-1.5 py-0.5 text-xs font-medium {{ match ($novedad->estado) {
                                       \App\Enums\EstadoNovedad::Aprobada => 'bg-[var(--card-soft)] text-[var(--success)]',
                                       \App\Enums\EstadoNovedad::Rechazada => 'bg-[var(--card-soft)] text-[var(--danger)]',
                                       default => 'bg-warning-soft text-[var(--warning)]',
                                   } }}" title="{{ $novedad->estado->etiqueta() }} · en la {{ $novedad->marcacion_id === $entrada->id ? 'entrada' : 'salida' }}">{{ match ($novedad->estado) {
                                       \App\Enums\EstadoNovedad::Aprobada => '✓',
                                       \App\Enums\EstadoNovedad::Rechazada => '✕',
                                       default => '⚠',
                                   } }} {{ $novedad->tipo->etiqueta() }}</a>
                            @endforeach
                        </li>
                    @endforeach
                </ul>

                @if($anuladas->isNotEmpty())
                    <details class="border-t border-[var(--border)]">
                        <summary class="cursor-pointer list-none px-4 py-2.5 text-xs text-[var(--muted)] hover:text-[var(--text)] [&::-webkit-details-marker]:hidden">
                            Historial de correcciones de la quincena ({{ $anuladas->count() }}) ▾
                        </summary>
                        <ul class="divide-y divide-[var(--border)] text-xs">
                            @foreach($anuladas as $marca)
                                <li class="flex flex-wrap gap-x-3 gap-y-0.5 px-4 py-2 text-[var(--muted)]">
                                    <span class="w-16">{{ $marca->tipo->etiqueta() }}</span>
                                    <span class="line-through">{{ $marca->marcada_at->fechaHora() }}</span>
                                    <span>{{ $marca->reemplazada_por_id ? 'Corregida' : 'Anulada' }} por {{ $marca->anulador?->name ?? '—' }} · {{ $marca->anulada_at->format('d/m').' '.$marca->anulada_at->hora() }}</span>
                                    <span class="w-full text-[var(--text)] sm:w-auto">«{{ $marca->motivo_anulacion }}»</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endif
        </div>

        @can('update', $colaborador)
            {{-- Corrección: una marca que faltó. Fila nueva con motivo y autor; nada se edita. --}}
            <form id="agregar-marca" method="POST" action="{{ route('admin.turnos.marcaciones.store', $colaborador) }}"
                  class="scroll-mt-4 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                @csrf
                <h2 class="text-sm font-medium text-[var(--text)]">Agregar una marca que faltó</h2>
                <p class="mb-3 text-xs text-[var(--muted)]">Queda como corrección, con tu nombre y el motivo. Las marcas existentes no se tocan.</p>
                <div class="grid gap-2 sm:grid-cols-4">
                    <select name="tipo" required class="liquid-input text-sm" aria-label="Tipo">
                        <option value="entrada" @selected(old('tipo') === 'entrada')>Entrada</option>
                        <option value="salida" @selected(old('tipo', 'salida') === 'salida')>Salida</option>
                    </select>
                    <input name="marcada_at" type="datetime-local" required max="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ old('marcada_at') }}" class="liquid-input text-sm" aria-label="Fecha y hora">
                    <input name="motivo" required minlength="5" maxlength="500" value="{{ old('motivo') }}"
                           placeholder="Motivo: ej. olvidó marcar la salida" class="liquid-input text-sm sm:col-span-2">
                </div>
                <div class="mt-2 flex justify-end">
                    <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Agregar marca</button>
                </div>
            </form>
        @endcan

        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <h2 class="border-b border-[var(--border)] px-4 py-3 text-sm font-medium text-[var(--text)]">Últimas evidencias enviadas</h2>
            @if($recepciones->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-[var(--muted)]">Todavía no ha enviado evidencias.</p>
            @else
                <ul class="divide-y divide-[var(--border)] text-sm">
                    @foreach($recepciones as $recepcion)
                        <li class="flex flex-wrap items-center gap-x-3 px-4 py-2.5">
                            <span class="text-[var(--muted)]">{{ $recepcion->created_at->fechaHora() }}</span>
                            @if($recepcion->documento)
                                <a href="{{ route('documentos.show', $recepcion->documento) }}"
                                   class="min-w-0 break-words text-[var(--primary)] hover:underline">{{ $recepcion->documento->nombre }}</a>
                            @else
                                <span class="text-[var(--text)]">{{ $recepcion->nombre_original }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

@endsection
