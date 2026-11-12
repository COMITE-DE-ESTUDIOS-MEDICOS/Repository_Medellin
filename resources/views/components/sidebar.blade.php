{{--
    Menú lateral: logotipo, selector de dependencia, navegación y la cuenta
    (esta última solo en móvil; en escritorio vive en la barra superior).

    Las variables dependenciaActual, dependenciasDisponibles y rolActual no se
    pasan como props: las comparte EstablecerDependencia con View::share, y
    llegan solas a cualquier vista, componentes incluidos.

    El comportamiento —abrir, cerrar, recordar si está escondido— vive en el
    script del layout, que es quien coordina este menú con su botón de la
    barra superior.
--}}
@php
/*
| El menú va por grupos, y cada grupo se pliega (menu-grupos.js recuerda
| cómo lo dejó cada quien). Un grupo vacío para ese rol no se pinta.
| Para agregar un módulo: una línea en su grupo, con su permiso.
*/
$puedeEditar = $rolActual?->puedeEditar();
$puedeGestionar = $rolActual?->puedeGestionar();
$puedeAdministrar = $rolActual?->puedeAdministrar();

$grupos = [
    [
        'clave' => 'principal',
        'titulo' => null,
        'enlaces' => array_filter([
            'documentos.index' => ['texto' => 'Documentos', 'icono' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
            // Administración ve todos los enlaces de su dependencia; un líder de
            // carpeta ve y crea los suyos. La visibilidad la decide EnlaceCargaPolicy.
            'admin.enlaces.index' => auth()->user()?->can('viewAny', App\Models\EnlaceCarga::class) ? ['texto' => 'Enlaces de carga', 'icono' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244'] : null,
        ]),
    ],
    [
        'clave' => 'turnos',
        'titulo' => 'Turnos',
        // En el orden del trabajo: repartir el QR, registrar a la gente, seguir
        // el día, validar y reportar (desde Edición: los editores son los
        // líderes de cada componente o nodo). Los catálogos de la malla (tipos
        // de turno, puntos críticos) se abren desde Personas de campo y En
        // turno ahora, para no llenar el menú.
        'enlaces' => array_filter([
            'admin.turnos.enlaces.index' => $puedeEditar ? ['texto' => 'Enlaces por cédula', 'icono' => 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM13.5 14.25h.75v.75h-.75v-.75ZM17.25 14.25h.75v.75h-.75v-.75ZM13.5 18h.75v.75h-.75V18ZM17.25 18h.75v.75h-.75V18ZM15.375 16.125h.75v.75h-.75v-.75Z'] : null,
            // El contador: las que se registraron solas por el enlace y nadie ha verificado.
            'admin.turnos.colaboradores.index' => $puedeEditar ? ['texto' => 'Personas de campo', 'icono' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z', 'contador' => App\Models\Colaborador::activos()->whereNull('verificado_at')->count()] : null,
            'admin.turnos.en-turno' => $puedeEditar ? ['texto' => 'En turno ahora', 'icono' => 'M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z'] : null,
            'admin.turnos.novedades.index' => $puedeEditar ? ['texto' => 'Novedades', 'icono' => 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z', 'contador' => App\Models\NovedadTurno::pendientes()->count()] : null,
            'admin.turnos.marcaciones.index' => $puedeEditar ? ['texto' => 'Marcaciones y reporte', 'icono' => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z'] : null,
        ]),
    ],
    [
        'clave' => 'territorio',
        'titulo' => 'Atención territorial',
        'enlaces' => array_filter([
            'admin.turnos.derivaciones.index' => $puedeEditar ? ['texto' => 'Derivaciones', 'icono' => 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5'] : null,
            'admin.turnos.supervisiones.index' => $puedeEditar ? ['texto' => 'Supervisión', 'icono' => 'M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z'] : null,
        ]),
    ],
    [
        'clave' => 'administracion',
        'titulo' => 'Administración',
        // La estructura (componentes y nodos) → quién entra y con qué rol →
        // cómo se clasifica → avisar → revisar.
        'enlaces' => array_filter([
            'admin.turnos.componentes.index' => $puedeGestionar ? ['texto' => 'Componentes y nodos', 'icono' => 'M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3'] : null,
            'admin.usuarios.index' => $puedeGestionar ? ['texto' => 'Usuarios', 'icono' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'] : null,
            'admin.tipos.index' => $puedeGestionar ? ['texto' => 'Tipos de documento', 'icono' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'] : null,
            'admin.custodia.index' => $puedeGestionar ? ['texto' => 'Grupos de custodia', 'icono' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z'] : null,
            'admin.avisos.index' => $puedeAdministrar ? ['texto' => 'Enviar aviso', 'icono' => 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46'] : null,
            'auditoria.index' => $puedeGestionar ? ['texto' => 'Auditoría', 'icono' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'] : null,
            // El manual, con descarga en PDF. Los demás roles lo abren desde su cuenta.
            'manual' => $puedeGestionar ? ['texto' => 'Manual de usuario', 'icono' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25'] : null,
        ]),
    ],
];

$nameTitle = 'Documenta';
$slogan = 'Inclusión Social';

// Activo también en las pantallas hijas: la ficha o la edición de una persona
// marcan «Personas de campo», no solo su listado.
$esActivo = fn (string $ruta) => request()->routeIs($ruta, str_ends_with($ruta, '.index') ? substr($ruta, 0, -6).'.*' : $ruta);
@endphp

{{-- Solo en móvil: atenúa el contenido y captura el clic fuera del menú. --}}
<div id="menu-fondo" class="fixed inset-0 z-30 hidden bg-slate-900/60 md:hidden"></div>

{{--
    La barra lateral siempre se ve «oscura», sin importar el tema de la
    página: por eso lleva su propia clase .dark, que aquí no alterna nada
    por JS, solo fija los valores oscuros de las variables para todo lo
    que está dentro de este <aside>.
--}}
<aside id="menu-lateral" aria-label="Menú principal"
    class="dark fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-[var(--border)] bg-[var(--card)] text-[var(--text)]
              transition-transform duration-200 ease-out
              md:sticky md:top-0 md:h-screen md:shrink-0 md:translate-x-0 md:transition-none">

    <div class="flex items-center gap-2 px-4 py-4">
        {{--
            El menú lateral es oscuro en los dos temas —lleva su propia
            clase .dark—, así que aquí siempre va el logotipo blanco.
        --}}
        <a href="{{ route('documentos.index') }}" class="foco flex min-w-0 flex-1 items-center rounded-md">
            <img src="{{ asset('img/logo-cem-oscuro.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
        </a>
    </div>

    <div class="px-4 pb-4">
        @if($dependenciasDisponibles?->count() > 1)
        <label for="selector-dependencia" class="mb-1 block text-xs font-medium text-[var(--muted)]">Dependencia</label>

        {{--
                La base de la URL viaja en un atributo y no interpolada dentro
                del script: el destino lleva el slug elegido al final y lo arma
                el JS. Con {{ }} dentro del JavaScript, las comillas anidadas
        rompen el analizador del editor.
        --}}
        <form method="POST" action="#" id="form-dependencia" data-base="{{ url('dependencia') }}">
            @csrf @method('PUT')
            <select id="selector-dependencia"
                class="w-full rounded-md border-0 bg-[var(--card-soft)] py-1.5 pl-3 pr-8 text-sm text-[var(--text)] focus:ring-2 focus:ring-[var(--primary)]">
                @foreach($dependenciasDisponibles as $dep)
                <option value="{{ $dep->slug }}" @selected($dep->id === $dependenciaActual?->id)>
                    {{ $dep->nombre }}
                </option>
                @endforeach
            </select>
        </form>
        @elseif($dependenciaActual)
        <p class="rounded-md bg-[var(--card-soft)] px-2.5 py-1.5 text-sm text-[var(--muted)]">
            {{ $dependenciaActual->nombre }}
        </p>
        @endif
    </div>

    {{-- Con scroll pero sin la barra visible (.sin-barra, en app.css). --}}
    <nav class="sin-barra flex-1 space-y-3 overflow-y-auto px-2 pb-2">
        @foreach($grupos as $grupo)
        @continue(empty($grupo['enlaces']))
        @php
            $abierto = collect($grupo['enlaces'])->keys()->contains(fn ($ruta) => $esActivo($ruta));
            $pendientes = collect($grupo['enlaces'])->sum(fn ($enlace) => $enlace['contador'] ?? 0);
        @endphp

        {{--
            Los grupos con título se pliegan; el de la página actual viene
            abierto siempre. Cerrado, su contador sube al título para que
            una novedad pendiente no quede escondida.
        --}}
        @if($grupo['titulo'])
        <details data-grupo-menu="{{ $grupo['clave'] }}" @if($abierto) open data-grupo-activo @endif class="group/grupo">
            <summary class="foco flex cursor-pointer list-none items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-[var(--muted)] hover:text-[var(--text)] [&::-webkit-details-marker]:hidden">
                <svg class="size-3.5 shrink-0 transition-transform group-open/grupo:rotate-90" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span>{{ $grupo['titulo'] }}</span>
                @if($pendientes > 0)
                <span class="ml-auto rounded-full bg-[var(--primary)] px-2 py-0.5 text-[10px] font-semibold text-white group-open/grupo:hidden"
                    aria-label="{{ $pendientes }} pendientes">{{ $pendientes > 99 ? '99+' : $pendientes }}</span>
                @endif
            </summary>
        @else
        {{-- Sin título: lo de todos los días, siempre a la vista y sin plegar. --}}
        <div data-grupo-menu="{{ $grupo['clave'] }}">
        @endif

            <div class="mt-1 space-y-1">
                @foreach($grupo['enlaces'] as $ruta => $enlace)
                @php
                    $activo = $esActivo($ruta);
                @endphp
                <a href="{{ route($ruta) }}" @if($activo) aria-current="page" @endif
                    class="foco flex items-center gap-3 rounded-md px-3 py-2 text-sm
                              {{ $activo
                                  ? 'bg-[var(--card-soft)] font-medium text-[var(--text)]'
                                  : 'text-[var(--muted)] hover:bg-[var(--card-soft)] hover:text-[var(--text)]' }}">
                    <svg class="size-5 shrink-0 {{ $activo ? 'text-[var(--primary)]' : 'text-[var(--muted)]' }}"
                        fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $enlace['icono'] }}" />
                    </svg>
                    <span class="truncate">{{ $enlace['texto'] }}</span>

                    {{-- El contador es lo que hace que la gente entre a mirar. --}}
                    @if(($enlace['contador'] ?? 0) > 0)
                    <span class="ml-auto shrink-0 rounded-full bg-[var(--primary)] px-2 py-0.5 text-xs font-semibold text-white"
                        aria-label="{{ $enlace['contador'] }} sin revisar">
                        {{ $enlace['contador'] > 99 ? '99+' : $enlace['contador'] }}
                    </span>
                    @endif
                </a>
                @endforeach
            </div>
        @if($grupo['titulo'])
        </details>
        @else
        </div>
        @endif
        @endforeach
    </nav>

    <div class="border-t border-[var(--border)] p-3">
        {{-- Solo en móvil: en escritorio la cuenta vive arriba a la derecha. --}}
        <div class="flex items-center gap-2 md:hidden">
            <a href="{{ route('perfil.edit') }}"
                class="foco flex min-w-0 flex-1 items-center gap-2 rounded-md p-1 hover:bg-[var(--card-soft)]">
                <x-avatar :rol="$rolActual" tamano="sm" />
                <span class="min-w-0">
                    <span class="block truncate text-sm">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-[var(--muted)]">{{ $rolActual?->etiqueta() }}</span>
                </span>
            </a>

            <a href="{{ route('manual') }}" title="Manual de usuario"
               class="foco rounded-md p-2 text-[var(--muted)] hover:bg-[var(--card-soft)] hover:text-[var(--text)]">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                <span class="sr-only">Manual de usuario</span>
            </a>

            <form method="POST" action="{{ route('logout') }}" data-sonido-al-enviar="salida">
                @csrf
                <button class="foco rounded-md p-2 text-[var(--muted)] hover:bg-[var(--card-soft)] hover:text-[var(--text)]" title="Salir">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                    </svg>
                    <span class="sr-only">Salir</span>
                </button>
            </form>
        </div>

        {{--
            El nombre del producto y la dependencia en la que se está parado,
            cada uno de su fuente: APP_NAME no lleva dependencia dentro, porque
            la misma instalación atiende a varias y esta línea tiene que
            cambiar al cambiar de dependencia.
        --}}
        <p class="mt-2 px-1 text-center text-xs text-[var(--muted)]">
            {{ $nameTitle }} · {{ $slogan }}
            {{-- La pone el despliegue (bin/deploy.sh): sube sola con cada fusión. --}}
            <span class="block opacity-80">{{ config('app.version') }}</span>
        </p>
    </div>
</aside>