@extends('layouts.app')
@section('titulo', 'Personas de campo')

@section('contenido')

@php($campo = 'liquid-input text-sm')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Personas de campo</h1>
        <p class="text-sm text-[var(--muted)]">Marcan turno y envían evidencias con su cédula, sin cuenta en Documenta.</p>
    </div>

    <div class="ml-auto flex flex-wrap gap-2">
        @can('viewAny', App\Models\TipoTurno::class)
            <a href="{{ route('admin.turnos.tipos-turno') }}" class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Tipos de turno</a>
        @endcan
        @can('create', App\Models\Colaborador::class)
            {{-- Carga masiva (temporal): la plantilla y el botón que la lee. --}}
            <a href="{{ route('admin.turnos.colaboradores.carga.plantilla') }}" download class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">Descargar Excel de carga</a>
            <form method="POST" action="{{ route('admin.turnos.colaboradores.carga.importar') }}" enctype="multipart/form-data" data-carga-masiva>
                @csrf
                <label class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)] inline-block">
                    Carga masiva
                    <input type="file" name="archivo" accept=".xlsx" class="sr-only" required>
                </label>
            </form>
        @endcan
        <a href="{{ route('admin.turnos.colaboradores.create') }}"
           class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">
            Registrar persona
        </a>
    </div>
</div>

@if(session('carga_errores'))
    <div class="mb-4 rounded-lg bg-[var(--card)] p-4 text-sm ring-1 ring-[var(--danger)]">
        <p class="font-medium text-[var(--danger)]">La carga masiva no se hizo. Corrige estas filas del Excel y súbelo de nuevo:</p>
        <ul class="mt-2 list-disc space-y-0.5 pl-5 text-[var(--text)]">
            @foreach(session('carga_errores') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($pendientes > 0 && request('verificacion') !== 'pendientes')
    <a href="{{ route('admin.turnos.colaboradores.index', ['verificacion' => 'pendientes']) }}"
       class="mb-4 flex items-center gap-2 rounded-lg bg-warning-soft px-4 py-3 text-sm text-[var(--warning)] hover:brightness-95">
        <span class="font-medium">{{ $pendientes }} {{ $pendientes === 1 ? 'persona se registró' : 'personas se registraron' }} por el enlace</span>
        y {{ $pendientes === 1 ? 'espera' : 'esperan' }} verificación →
    </a>
@endif

{{-- Un solo GET: buscar y filtrar se combinan, y el enlace se puede compartir. --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <div class="min-w-56 flex-1">
        <label for="q" class="block text-xs font-medium text-[var(--muted)]">Cédula o nombre</label>
        <input id="q" type="search" name="q" value="{{ request('q') }}" autofocus
               placeholder="Ej: 1.017.234 o María" class="{{ $campo }} mt-1 w-full">
    </div>

    <div>
        <label for="componente" class="block text-xs font-medium text-[var(--muted)]">Componente</label>
        <select id="componente" name="componente" class="{{ $campo }} mt-1">
            <option value="">Todos</option>
            @foreach($componentes as $componente)
                <option value="{{ $componente->id }}" @selected(request('componente') == $componente->id)>{{ $componente->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="nodo" class="block text-xs font-medium text-[var(--muted)]">Nodo</label>
        <select id="nodo" name="nodo" data-depende-de="#componente" class="{{ $campo }} mt-1">
            <option value="">Todos</option>
            @foreach($componentes as $componente)
                @foreach($componente->nodos as $nodo)
                    <option value="{{ $nodo->id }}" data-componente="{{ $componente->id }}" @selected(request('nodo') == $nodo->id)>{{ $nodo->nombre }}</option>
                @endforeach
            @endforeach
        </select>
    </div>

    <div>
        <label for="verificacion" class="block text-xs font-medium text-[var(--muted)]">Verificación</label>
        <select id="verificacion" name="verificacion" class="{{ $campo }} mt-1">
            <option value="">Todas</option>
            <option value="pendientes" @selected(request('verificacion') === 'pendientes')>Pendientes</option>
        </select>
    </div>

    <div>
        <label for="estado" class="block text-xs font-medium text-[var(--muted)]">Estado</label>
        <select id="estado" name="estado" class="{{ $campo }} mt-1">
            <option value="activos" @selected(request('estado', 'activos') === 'activos')>Activas</option>
            <option value="inactivos" @selected(request('estado') === 'inactivos')>Desactivadas</option>
            <option value="todos" @selected(request('estado') === 'todos')>Todas</option>
        </select>
    </div>

    <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Buscar</button>
    @if(request()->hasAny(['q', 'componente', 'nodo', 'verificacion', 'estado']))
        <a href="{{ route('admin.turnos.colaboradores.index') }}" class="py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Limpiar</a>
    @endif
</form>

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Persona</th>
                <th class="px-4 py-3 font-medium">Componente · nodo</th>
                <th class="px-4 py-3 font-medium">Contacto</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($colaboradores as $colaborador)
                <tr class="{{ $colaborador->activo ? '' : 'opacity-60' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.turnos.colaboradores.show', $colaborador) }}"
                           class="font-medium text-[var(--text)] hover:text-[var(--primary)]">{{ $colaborador->nombre }}</a>
                        <p class="text-xs text-[var(--muted)]">
                            CC {{ $colaborador->documento }}{{ $colaborador->cargo ? ' · '.$colaborador->cargo : '' }}
                        </p>
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">
                        {{ $colaborador->componente?->nombre ?? '—' }}{{ $colaborador->nodo ? ' · '.$colaborador->nodo->nombre : '' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-[var(--muted)]">
                        {{ $colaborador->telefono ?? '' }}
                        @if($colaborador->correo)<span class="block">{{ $colaborador->correo }}</span>@endif
                    </td>
                    <td class="px-4 py-3 text-xs">
                        @if(! $colaborador->activo)
                            <span class="text-[var(--danger)]">Desactivada</span>
                        @elseif(! $colaborador->estaVerificado())
                            <span class="rounded bg-warning-soft px-1.5 py-0.5 font-medium text-[var(--warning)]">Por verificar</span>
                        @else
                            <span class="text-[var(--success)]">Verificada</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @can('verificar', $colaborador)
                            <form method="POST" action="{{ route('admin.turnos.colaboradores.verificar', $colaborador) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="text-[var(--success)] hover:underline">Verificar</button>
                            </form>
                        @endcan
                        <a href="{{ route('admin.turnos.colaboradores.edit', $colaborador) }}"
                           class="ml-3 text-[var(--primary)] hover:underline">Editar</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-[var(--muted)]">
                        @if(request('q'))
                            Nadie coincide con «{{ request('q') }}».
                        @else
                            Aún no hay personas de campo. Se registran solas la primera vez que usan un enlace, o desde «Registrar persona».
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $colaboradores->links() }}</div>

@push('scripts')
<script>
    // Elegir el archivo ya lo envía: un clic menos.
    document.querySelector('[data-carga-masiva] input[type=file]')?.addEventListener('change', (e) => {
        if (e.target.files.length) e.target.form.submit();
    });
</script>
@endpush

@endsection
