<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        Aquí todavía no hay usuario, así que no hay preferencia guardada en la
        cuenta: se respeta primero una elección manual guardada en este
        navegador (botón de la cabecera) y si no existe, la del sistema.
        Va antes del CSS para que no haya destello blanco.
    --}}
    <script>
        (function () {
            const raiz = document.documentElement;
            const oscuroDelSistema = window.matchMedia('(prefers-color-scheme: dark)');

            const preferenciaGuardada = () => {
                try {
                    return localStorage.getItem('login-tema');
                } catch (e) {
                    return null;
                }
            };

            const aplicarTema = () => {
                const guardada = preferenciaGuardada();
                raiz.classList.toggle('dark', guardada ? guardada === 'oscuro' : oscuroDelSistema.matches);
            };

            aplicarTema();
            oscuroDelSistema.addEventListener('change', aplicarTema);

            window.alternarTemaLogin = () => {
                const nuevoTema = raiz.classList.contains('dark') ? 'claro' : 'oscuro';

                try {
                    localStorage.setItem('login-tema', nuevoTema);
                } catch (e) {
                    // Sin almacenamiento el cambio vale solo para esta pantalla.
                }

                aplicarTema();
            };
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/css/loader.css', 'resources/js/app.js'])
</head>
<body class="flex h-dvh w-full flex-col overflow-hidden">

@include('partials.cargando')

<header class="flex shrink-0 items-center justify-between gap-3 border-b px-4 py-2.5 sm:px-8"
        style="border-color: var(--border); background-color: color-mix(in srgb, var(--card) 80%, transparent);">
    {{--
        Dos archivos y no uno con filtro CSS: el logotipo es texto fino, y
        teñirlo por filtro emborrona los bordes suavizados. Cada tema carga
        el suyo, ya del color que le toca.
    --}}
    <img src="{{ asset('img/logo-cem-claro.png') }}" alt="Comité de Estudios Médicos"
         class="h-9 w-auto dark:hidden">
    <img src="{{ asset('img/logo-cem-oscuro.png') }}" alt="Comité de Estudios Médicos"
         class="hidden h-9 w-auto dark:block">

    <button type="button" onclick="window.alternarTemaLogin()" title="Cambiar de tema"
            class="rounded-xl border p-2 transition-colors" style="border-color: var(--border); color: var(--text);">
        <svg class="size-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/>
        </svg>
        <svg class="hidden size-4 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
        </svg>
        <span class="sr-only">Cambiar de tema</span>
    </button>
</header>

{{--
    overflow-y-auto es solo la red de seguridad para una ventana absurdamente
    baja: con los tamaños en clamp() de aquí abajo, la tarjeta se encoge sola
    y en cualquier pantalla real no debería activarse nunca.
--}}
<main class="flex min-h-0 flex-1 items-center justify-center overflow-y-auto p-[clamp(0.5rem,2vh,2rem)]">
    <div class="grid w-full max-w-5xl grid-cols-1 items-center gap-8 lg:grid-cols-12">

        {{-- Columna informativa: solo en pantallas anchas, para que el login
             quepa sin scroll en móvil sin sacrificar el mensaje institucional. --}}
        <div class="hidden space-y-6 lg:col-span-6 lg:block lg:text-left">
            <div class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-bold"
                 style="background-color: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary); border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);">
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3.75 21h16.5M4.5 3.75h15M5.25 3.75v17.25m13.5-17.25v17.25M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                </svg>
                <span>Gestión documental institucional</span>
            </div>

            <h1 class="text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl" style="color: var(--text);">
                {{--
                    El nombre entero sale de APP_NAME, sin una sola palabra
                    escrita aquí: la primera va en el color del texto y el
                    resto en el de acento, que es lo único que pone la vista.
                --}}
                @php([$primera, $resto] = array_pad(explode(' ', config('app.name'), 2), 2, null))
                {{ $primera }}@if($resto) <span style="color: var(--primary);">{{ $resto }}</span>@endif
            </h1>

            <p class="mx-auto max-w-xl text-sm leading-relaxed sm:text-base lg:mx-0" style="color: var(--muted);">
                Plataforma institucional de archivo, custodia y control de versiones para los documentos de las
                dependencias del Comité de Estudios Médicos.
            </p>


        </div>

        {{-- Tarjeta de ingreso --}}
        <div class="flex justify-center lg:col-span-6">
            <div class="liquid-card w-full max-w-md rounded-3xl p-[clamp(0.9rem,3.5vh,2rem)]">
                <div class="text-center" style="margin-bottom: clamp(0.5rem, 2.5vh, 1.5rem);">
                    <div class="mx-auto flex items-center justify-center rounded-2xl text-white shadow-md"
                         style="background-color: var(--primary); width: clamp(2rem, 6vh, 3rem); height: clamp(2rem, 6vh, 3rem); margin-bottom: clamp(0.25rem, 1.2vh, 0.75rem);">
                        <svg style="width: clamp(1rem, 3vh, 1.5rem); height: clamp(1rem, 3vh, 1.5rem);" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/>
                        </svg>
                    </div>
                    <h2 class="font-black tracking-tight" style="color: var(--text); font-size: clamp(1rem, 2.5vh, 1.25rem);">Iniciar sesión</h2>
                    <p class="mt-1 text-xs" style="color: var(--muted);">Te llevamos a Authentik para validar tu identidad</p>
                </div>

                @if($errors->any())
                    <div class="liquid-alert liquid-alert-error mb-3 text-xs">
                        <svg class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 9v3.75m0 3.75h.007v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div>
                            <span class="block font-semibold">Error de autenticación</span>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    </div>
                @endif

                {{--
                    La identidad la valida Authentik: es la cuenta institucional,
                    la misma de MedTech. Este botón es la entrada de todos.
                --}}
                <a href="{{ route('authentik.redirect') }}" id="boton-login"
                   class="liquid-button-primary flex w-full items-center justify-center gap-2 rounded-xl text-sm font-bold shadow-md"
                   style="padding-top: clamp(0.5rem, 1.6vh, 0.75rem); padding-bottom: clamp(0.5rem, 1.6vh, 0.75rem);">
                    <span id="texto-boton-login">Entrar con la cuenta institucional</span>
                    <svg id="icono-boton-login" class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                    </svg>
                </a>

                {{--
                    DOC-22 (PA-01): el formulario propio queda solo para el
                    superadministrador, como entrada de emergencia si Authentik
                    no responde. Va plegado y discreto para que nadie lo tome
                    por la entrada de todos los días.
                --}}
                <details class="mt-3 text-xs" style="color: var(--muted);" @if(old('identificador')) open @endif>
                    <summary class="cursor-pointer text-center">Acceso de emergencia (administración)</summary>

                    <form method="POST" action="{{ route('login') }}" class="mt-3 space-y-2">
                        @csrf
                        <label class="block font-bold" style="color: var(--text);" for="identificador">Usuario o alias de emergencia</label>
                        <input id="identificador" name="identificador" type="text" value="{{ old('identificador') }}" required
                               autocomplete="username" maxlength="255" class="liquid-input w-full px-3 text-sm">

                        <label class="block font-bold" style="color: var(--text);" for="password">Contraseña</label>
                        <input id="password" name="password" type="password" required
                               autocomplete="current-password" class="liquid-input w-full px-3 text-sm">

                        <button type="submit" class="w-full rounded-xl border py-2 text-xs font-bold"
                                style="border-color: var(--border); color: var(--text);">
                            Ingresar
                        </button>
                    </form>
                </details>

                <div class="border-t text-center" style="border-color: var(--border); margin-top: clamp(0.5rem, 2vh, 1.5rem); padding-top: clamp(0.4rem, 1.5vh, 1rem);">
                    <p class="text-[11px] leading-relaxed" style="color: var(--muted);">
                        El acceso lo habilita un administrador de la dependencia. Cada inicio de sesión queda
                        registrado en la auditoría del sistema.
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="shrink-0 border-t px-4 py-2 text-center text-xs" style="border-color: var(--border); color: var(--muted);">
    © {{ date('Y') }} {{ config('app.name') }} • Comité de Estudios Médicos
</footer>

<script>
    (function () {
        // El ingreso ya no envía un formulario: sale del sitio hacia Authentik.
        // Se bloquea el botón y se enciende el overlay mientras el navegador
        // hace el salto, que en una conexión lenta no es instantáneo.
        const boton = document.getElementById('boton-login');

        boton.addEventListener('click', () => {
            boton.classList.add('pointer-events-none', 'opacity-75');
            document.getElementById('texto-boton-login').textContent = 'Redirigiendo…';
            document.getElementById('icono-boton-login').classList.add('hidden');
            document.getElementById('cargando-pagina').classList.remove('hidden');
        });

        // Si el navegador restaura esta página desde el historial (back/forward)
        // con el overlay visible de un intento anterior, hay que esconderlo:
        // ya no hay ninguna redirección en curso.
        window.addEventListener('pageshow', (evento) => {
            if (evento.persisted) {
                document.getElementById('cargando-pagina').classList.add('hidden');
                boton.classList.remove('pointer-events-none', 'opacity-75');
                document.getElementById('texto-boton-login').textContent = 'Entrar con la cuenta institucional';
                document.getElementById('icono-boton-login').classList.remove('hidden');
            }
        });
    })();
</script>

</body>
</html>
