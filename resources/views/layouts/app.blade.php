<!DOCTYPE html>
{{--
    El tema elegido viaja en un atributo y no interpolado dentro del bloque
    de JavaScript de abajo:
    así el bloque de abajo es JavaScript válido tal cual y el editor deja de
    marcarlo como error. Es además como el resto del proyecto le pasa datos al
    JS (data-maximo, data-copiar, data-base).
--}}
<html lang="es" class="h-full" data-tema="{{ auth()->user()?->tema?->value ?? 'sistema' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{--
        Solo el nombre: la pestaña se lee de un vistazo aunque haya diez
        abiertas. La sección en la que se está ya la dice el menú lateral, y
        repetirla aquí solo servía para que el nombre quedara cortado.
    --}}
    <title>{{ config('app.name') }}</title>

    {{--
        El SVG manda donde se entiende: es nítido en cualquier tamaño y pesa
        menos de 1 KB. El PNG queda de respaldo para lo que no lo soporta
        —Safari viejo— y como icono de pantalla de inicio en iOS, que solo
        acepta mapa de bits.
    --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/favicon.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-cem.png') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('img/favicon-cem.png') }}">

    {{--
        Va antes del CSS a propósito: si esto se resolviera al final del body el
        navegador ya habría pintado, y se vería un destello blanco al cargar en
        modo oscuro y el menú apareciendo para luego esconderse.
    --}}
    <script>
        (function () {
            const raiz = document.documentElement;

            try {
                if (localStorage.getItem('menu-oculto') === '1') {
                    raiz.classList.add('menu-oculto');
                }
            } catch (e) {
                // Navegación privada o almacenamiento bloqueado: el menú se muestra.
            }

            const oscuroDelSistema = window.matchMedia('(prefers-color-scheme: dark)');
            let tema = raiz.dataset.tema || 'sistema';

            const pintar = () => raiz.classList.toggle(
                'dark',
                tema === 'oscuro' || (tema === 'sistema' && oscuroDelSistema.matches)
            );

            // Con «sistema» la app sigue en vivo el tema de Windows. El listener
            // queda puesto siempre: pintar() deja de mirar al sistema en cuanto
            // el tema pasa a ser explícito.
            oscuroDelSistema.addEventListener('change', pintar);

            // Lo usa el interruptor de la barra superior para cambiar de tema
            // sin recargar la página.
            window.aplicarTema = (valor) => {
                tema = valor;
                pintar();
            };

            pintar();
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/css/loader.css', 'resources/js/app.js'])

    {{-- Lo que una página necesita solo para ella (p. ej. los estilos de Filament). --}}
    @stack('estilos')
</head>
<body class="h-full antialiased">

@include('partials.cargando')

<div class="flex min-h-full">

    <x-sidebar />

    <div class="flex min-w-0 flex-1 flex-col">

        <x-navbar />

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
            @include('partials.alertas')
            @yield('contenido')
        </main>
    </div>
</div>

<script>
(function () {
    const raiz = document.documentElement;
    const boton = document.getElementById('menu-boton');
    const botonTexto = document.getElementById('menu-boton-texto');
    const menu = document.getElementById('menu-lateral');
    const fondo = document.getElementById('menu-fondo');
    const escritorio = window.matchMedia('(min-width: 768px)');

    // El mismo botón hace dos cosas: en escritorio esconde y muestra el menú
    // recordando la elección; en móvil abre y cierra el cajón deslizante.
    function visible() {
        return escritorio.matches
            ? !raiz.classList.contains('menu-oculto')
            : !menu.classList.contains('-translate-x-full');
    }

    function rotular() {
        const texto = visible() ? 'Ocultar menú' : 'Mostrar menú';

        boton.setAttribute('aria-expanded', visible() ? 'true' : 'false');
        boton.title = texto;
        botonTexto.textContent = texto;
    }

    function mostrar(si) {
        if (escritorio.matches) {
            raiz.classList.toggle('menu-oculto', !si);

            try {
                localStorage.setItem('menu-oculto', si ? '0' : '1');
            } catch (e) {
                // Sin almacenamiento la preferencia dura lo que dure la página.
            }
        } else {
            menu.classList.toggle('-translate-x-full', !si);
            fondo.classList.toggle('hidden', !si);
            document.body.classList.toggle('overflow-hidden', si);
        }

        rotular();

        if (si && !escritorio.matches) {
            menu.querySelector('a, button, select, input')?.focus();
        } else if (!si && menu.contains(document.activeElement)) {
            boton.focus();
        }
    }

    boton.addEventListener('click', () => mostrar(!visible()));

    // En móvil, tocar fuera del cajón también lo cierra.
    fondo.addEventListener('click', () => mostrar(false));

    // El menú de la cuenta se abre solo, por ser un <details>. Esto añade
    // únicamente lo que el navegador no da: cerrarlo al tocar fuera o con
    // Escape. Si este script no llega a correr, el menú sigue funcionando.
    const cuenta = document.getElementById('menu-cuenta');

    if (cuenta) {
        document.addEventListener('click', (e) => {
            if (cuenta.open && !cuenta.contains(e.target)) {
                cuenta.open = false;
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && cuenta.open) {
                cuenta.open = false;
                cuenta.querySelector('summary')?.focus();
            }
        });
    }

    // Escape solo cierra el cajón de móvil: en escritorio esconder la navegación
    // con una tecla suelta sería desconcertante.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !escritorio.matches && visible()) {
            mostrar(false);
        }
    });

    // Al cruzar el breakpoint el cajón deja de tener sentido: se reinicia para
    // que el fondo y el bloqueo de scroll no queden pegados.
    escritorio.addEventListener('change', (e) => {
        if (e.matches) {
            menu.classList.add('-translate-x-full');
            fondo.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        rotular();
    });

    rotular();

    // El interruptor de tema decide a partir de lo que se ve ahora, no de la
    // preferencia guardada: si está en «sistema», el clic fija lo contrario de
    // lo que el sistema esté mostrando en este momento.
    const interruptorTema = document.getElementById('interruptor-tema');

    interruptorTema.addEventListener('submit', (e) => {
        e.preventDefault();

        const valor = raiz.classList.contains('dark') ? 'claro' : 'oscuro';

        window.aplicarTema(valor);
        interruptorTema.elements.tema.value = valor;

        fetch(interruptorTema.action, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ tema: valor }),
        }).catch(() => {
            // Si no se pudo guardar, el cambio vale para esta pantalla y la
            // siguiente carga vuelve a la preferencia que sí está en la cuenta.
        });
    });

    const selector = document.getElementById('selector-dependencia');

    if (selector) {
        selector.addEventListener('change', () => {
            const formulario = document.getElementById('form-dependencia');
            formulario.action = formulario.dataset.base + '/' + selector.value;
            formulario.submit();
        });
    }
})();
</script>

<script>
(function () {
    // Como aquí no hay SPA, cada clic o envío recarga la página entera: este
    // overlay solo cubre el hueco entre que el usuario actúa y la siguiente
    // página termina de llegar. No hace falta ocultarlo "a mano" al terminar
    // porque esa página siguiente nunca lo trae consigo.
    const overlay = document.getElementById('cargando-pagina');

    function mostrarCargando() {
        overlay.classList.remove('hidden');
    }

    // Enlaces que navegan de verdad: mismo origen, sin modificador de teclado,
    // sin apuntar a otra pestaña, sin ser un ancla dentro de la misma página.
    document.addEventListener('click', (evento) => {
        const enlace = evento.target.closest('a[href]');

        if (!enlace || evento.defaultPrevented) {
            return;
        }

        const esOtraPestana = enlace.target === '_blank';
        const esDescarga = enlace.hasAttribute('download');
        const esAncla = enlace.getAttribute('href').startsWith('#');
        const esOtroOrigen = enlace.origin !== window.location.origin;
        const tieneModificador = evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey || evento.button !== 0;

        if (!esOtraPestana && !esDescarga && !esAncla && !esOtroOrigen && !tieneModificador) {
            mostrarCargando();
        }
    });

    // Bubbling: si un formulario ya manejó su propio envío por AJAX y llamó a
    // preventDefault() (como el interruptor de tema), defaultPrevented llega
    // en true aquí y este overlay no se muestra para algo que no navega.
    // Tampoco para un botón con data-descarga: la respuesta es un archivo y
    // la página se queda, así que nadie lo ocultaría.
    document.addEventListener('submit', (evento) => {
        if (!evento.defaultPrevented && !evento.submitter?.hasAttribute('data-descarga')) {
            mostrarCargando();
        }
    });

    // El navegador puede restaurar la página desde su caché de atrás/adelante
    // con el overlay todavía visible de una navegación anterior.
    window.addEventListener('pageshow', (evento) => {
        if (evento.persisted) {
            overlay.classList.add('hidden');
        }
    });
})();
</script>

@stack('scripts')

</body>
</html>