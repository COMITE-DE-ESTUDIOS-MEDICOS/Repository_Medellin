{{--
    Barra superior: el control del menú, las notificaciones, el interruptor de
    tema y la cuenta.

    Como el sidebar, lee las variables que comparte EstablecerDependencia
    (rolActual, dependenciaActual) sin recibirlas como props. El
    comportamiento vive en los scripts del layout, que alcanzan estos
    elementos por su id.
--}}
@php
    $notificacionesSinLeer = auth()->user()?->unreadNotifications()->count() ?? 0;
@endphp

<header class="sticky top-0 z-20 flex items-center gap-3 border-b border-[var(--border)] bg-[var(--card)] px-4 py-2">
    {{--
        El único control del menú. Hace las dos cosas con el mismo clic: en
        escritorio lo esconde y lo saca, recordando la elección; en móvil abre
        y cierra el cajón. Vive en la cabecera y no dentro del menú, que es lo
        que le permite seguir alcanzable cuando el menú está escondido.
    --}}
    <button type="button" id="menu-boton" aria-controls="menu-lateral" aria-expanded="false"
            class="foco -ml-1 shrink-0 rounded-md p-2 text-[var(--muted)] hover:bg-[var(--card-soft)]">
        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
        </svg>
        <span class="sr-only" id="menu-boton-texto">Mostrar menú</span>
    </button>

    {{--
        La campana se mantiene al día sola: resources/js/notificaciones.js
        pregunta cada tanto cuántas hay sin leer y, si suben, suena y
        actualiza la insignia. Por eso la insignia existe siempre, aunque
        esté en cero: el script solo la enciende.
    --}}
    <a href="{{ route('notificaciones.index') }}" title="Notificaciones"
       data-notificaciones="{{ route('notificaciones.contador') }}" data-sin-leer="{{ $notificacionesSinLeer }}"
       class="foco relative ml-auto flex shrink-0 rounded-md p-2 text-[var(--muted)] hover:bg-[var(--card-soft)]">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
        </svg>
        <span data-insignia
              class="{{ $notificacionesSinLeer > 0 ? 'flex' : 'hidden' }} absolute -right-0.5 -top-0.5 min-w-[1.1rem] items-center justify-center rounded-full bg-[var(--danger)] px-1 text-[10px] font-bold leading-tight text-white">
            {{ $notificacionesSinLeer > 99 ? '99+' : $notificacionesSinLeer }}
        </span>
        <span class="sr-only" data-insignia-texto>Notificaciones{{ $notificacionesSinLeer > 0 ? " ({$notificacionesSinLeer} sin leer)" : '' }}</span>
    </a>

    {{--
        Sin JS es un envío normal que recarga; con JS se intercepta y el tema
        cambia al instante. Los dos iconos y las dos etiquetas viven en el HTML
        y los alterna el CSS con la misma clase .dark, así que no hay parpadeo
        ni riesgo de que se desincronicen.
    --}}
    <form method="POST" action="{{ route('perfil.tema') }}" id="interruptor-tema" class="shrink-0">
        @csrf @method('PUT')
        <input type="hidden" name="tema" value="{{ auth()->user()?->tema === \App\Enums\TemaInterfaz::Oscuro ? 'claro' : 'oscuro' }}">

        <button type="submit" class="foco flex rounded-md p-2 text-[var(--muted)] hover:bg-[var(--card-soft)]">
            <svg class="size-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/>
            </svg>
            <svg class="hidden size-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
            </svg>
            <span class="sr-only dark:hidden">Cambiar a tema oscuro</span>
            <span class="hidden sr-only dark:inline">Cambiar a tema claro</span>
        </button>
    </form>

    {{--
        La cuenta, solo en escritorio. En móvil sigue en el pie del menú
        lateral: aquí arriba competiría por sitio con el hamburguesa en una
        pantalla estrecha.

        Es un <details> y no un menú montado a mano con JavaScript: así se abre
        igual si el script falla o todavía no ha cargado. Para llegar a «cerrar
        sesión» eso no es un lujo.
    --}}
    <details id="menu-cuenta" class="relative hidden shrink-0 md:block">
        {{--
            Arriba solo el avatar y la flecha: el nombre completo y el rol
            ocupaban media cabecera y se truncaban igual. Quien quiera saber
            con qué cuenta entró, la despliega.
        --}}
        <summary class="foco flex cursor-pointer list-none items-center gap-1 rounded-md p-1 hover:bg-[var(--card-soft)]
                        [&::-webkit-details-marker]:hidden"
                 title="{{ auth()->user()->name }}">
            <x-avatar :rol="$rolActual" tamano="sm" />
            <svg class="size-4 shrink-0 text-[var(--muted)]" fill="none" stroke="currentColor"
                 stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
            <span class="sr-only">Abrir el menú de la cuenta de {{ auth()->user()->name }}</span>
        </summary>

        <div class="absolute right-0 z-30 mt-2 w-64 overflow-hidden rounded-md bg-[var(--card)] py-1 shadow-lg ring-1 ring-[var(--border)]">
            <div class="flex items-center gap-3 border-b border-[var(--border)] px-4 py-3">
                <x-avatar :rol="$rolActual" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-[var(--text)]">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-[var(--muted)]">{{ $rolActual?->etiqueta() }}</span>
                    <span class="block truncate text-xs text-[var(--muted)]">{{ $dependenciaActual?->nombre }}</span>
                </span>
            </div>

            <a href="{{ route('perfil.edit') }}"
               class="block px-4 py-2 text-sm text-[var(--text)] hover:bg-[var(--card-soft)]">
                Mi perfil
            </a>

            <a href="{{ route('manual') }}"
               class="block px-4 py-2 text-sm text-[var(--text)] hover:bg-[var(--card-soft)]">
                Manual de usuario
            </a>

            <form method="POST" action="{{ route('logout') }}" data-sonido-al-enviar="salida">
                @csrf
                <button class="block w-full px-4 py-2 text-left text-sm text-[var(--text)] hover:bg-[var(--card-soft)]">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </details>
</header>
