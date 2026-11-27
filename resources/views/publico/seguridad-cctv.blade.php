@extends('publico._base')

@section('contenido')

@php
    $atendido = session('cctv.atendido');
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $seccion = 'space-y-5 rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)]';
    $tituloSeccion = 'text-base font-semibold text-[var(--text)]';

    // Con errores del servidor, se abre el primer paso que tiene uno.
    $pasoDe = ['cedula' => 0, 'numero' => 1, 'justificacion' => 2, 'evidencia1' => 2, 'evidencia2' => 2, 'evidencia3' => 2];
    $pasoInicial = collect($errors->keys())->map(fn ($clave) => $pasoDe[$clave] ?? 0)->min() ?? 0;
@endphp

<div class="text-center">
    <h1 class="text-xl font-semibold text-[var(--text)]">Atender caso</h1>
    <p class="mt-1 text-sm text-[var(--muted)]">
        Seguridad CCTV · {{ $componente->nombre }}{{ $enlace->nombre ? ' · '.$enlace->nombre : '' }}
    </p>
</div>

@if($atendido)
    <div class="mt-6 rounded-lg bg-success-soft p-4 text-center" role="status" data-sonido-al-cargar="envio">
        <p class="text-lg font-semibold text-[var(--success)]">Caso {{ $atendido['codigo'] }} atendido</p>
        <p class="mt-1 text-sm text-[var(--text)]">{{ $atendido['saludo'] }} · tiempo de atención: {{ $atendido['tiempo'] }}</p>
    </div>
@endif

@if($errors->any())
    <div class="liquid-alert liquid-alert-error mt-6 text-sm">
        <ul class="list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Nada del caso se guarda en el navegador: se pide al servidor por POST y solo vive en la página. --}}
<form method="POST" action="{{ route('cctv.atender', ['token' => $token]) }}" enctype="multipart/form-data"
      data-pasos data-paso-inicial="{{ $pasoInicial }}" autocomplete="off"
      data-cctv-caso data-consulta="{{ route('cctv.caso', ['token' => $token]) }}" data-csrf="{{ csrf_token() }}"
      class="mt-6 space-y-5 scroll-mt-4">
    @csrf

    <div data-pasos-cabecera class="hidden space-y-3">
        <div class="h-2 overflow-hidden rounded-full bg-[var(--card-soft)] ring-1 ring-[var(--border)]">
            <div data-pasos-barra class="h-full rounded-full bg-[var(--primary)] transition-all duration-300" style="width: 33%"></div>
        </div>
        <ol data-pasos-puntos class="flex items-start justify-between gap-1"></ol>
        <p data-pasos-texto class="text-center text-sm font-medium text-[var(--muted)]"></p>
    </div>

    <section data-paso data-titulo="Quién atiende" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">¿Quién atiende el caso?</h2>
        <x-campos.cedula :consulta="route('cctv.cedula', ['token' => $token])" :registro="false" />
    </section>

    <section data-paso data-titulo="Caso" class="{{ $seccion }}" data-caso-paso>
        <div>
            <label for="numero" class="{{ $etiqueta }}">Número del caso *</label>
            <div class="mt-1.5 flex items-stretch gap-2">
                <span class="flex items-center rounded-lg bg-[var(--card-soft)] px-3 text-base font-semibold text-[var(--text)] ring-1 ring-[var(--border)]">CCTV-</span>
                <input id="numero" name="numero" type="text" inputmode="numeric" pattern="[0-9]{1,9}" maxlength="9" required
                       autocomplete="off" value="{{ old('numero') }}" data-caso-numero
                       class="liquid-input w-full py-2.5 text-base sm:text-sm">
                <button type="button" data-caso-buscar class="liquid-button-primary shrink-0 rounded-lg px-4 text-base font-semibold">
                    Ver caso
                </button>
            </div>
            <p class="{{ $ayuda }}">Solo los números del código que te dio la oficina.</p>
        </div>

        <p data-caso-aviso role="alert" aria-live="polite" class="hidden text-sm font-medium text-[var(--warning)]"></p>

        <div data-caso-detalle class="hidden space-y-3 rounded-lg bg-[var(--card-soft)] p-4 ring-1 ring-[var(--border)]">
            <p data-caso-codigo class="text-lg font-semibold text-[var(--text)]"></p>
            <div>
                <p class="text-xs font-medium text-[var(--muted)]">Descripción</p>
                <p data-caso-descripcion class="whitespace-pre-line text-sm text-[var(--text)]"></p>
            </div>
            <div>
                <p class="text-xs font-medium text-[var(--muted)]">Ubicación</p>
                <p data-caso-ubicacion class="text-sm text-[var(--text)]"></p>
            </div>
            <div>
                <p class="text-xs font-medium text-[var(--muted)]">Comuna</p>
                <p data-caso-comuna class="text-sm text-[var(--text)]"></p>
            </div>
            <div data-caso-soporte class="hidden">
                <p class="text-xs font-medium text-[var(--muted)]">Soporte</p>
                <img data-caso-soporte-imagen alt="Soporte del caso" class="mt-1 hidden max-h-96 w-full rounded-lg object-contain ring-1 ring-[var(--border)]">
                <a data-caso-soporte-pdf target="_blank" rel="noopener noreferrer"
                   class="mt-1 hidden text-sm font-medium text-[var(--primary)] underline">Abrir el soporte (PDF)</a>
                <p class="{{ $ayuda }}">El soporte se puede ver durante 10 minutos; después, vuelve a pulsar «Ver caso».</p>
            </div>
        </div>
    </section>

    <section data-paso data-titulo="Evidencias" class="{{ $seccion }}">
        <div>
            <h2 class="{{ $tituloSeccion }}">Evidencias fotográficas</h2>
            <p class="{{ $ayuda }}">Se les estampa la hora y el lugar. Tómalas en el sitio. La #1 es obligatoria.</p>
        </div>

        @foreach([1, 2, 3] as $n)
            <div>
                <p class="{{ $etiqueta }}">Evidencia #{{ $n }}{{ $n === 1 ? ' *' : ' (opcional)' }}</p>
                <x-campo-archivo nombre="evidencia{{ $n }}" :requerido="$n === 1" camara formatos="imagenes" sello="Seguridad CCTV" />
            </div>
        @endforeach

        <div>
            <label for="justificacion" class="{{ $etiqueta }}">Justificación en caso de no cumplir (opcional)</label>
            <textarea id="justificacion" name="justificacion" rows="3" maxlength="5000" autocomplete="off"
                      class="liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm">{{ old('justificacion') }}</textarea>
        </div>
    </section>

    <p data-pasos-aviso role="alert" class="hidden rounded-lg bg-[var(--card-soft)] px-3 py-2 text-sm font-medium text-[var(--warning)] ring-1 ring-[var(--border)]"></p>

    <div data-pasos-botones class="hidden flex items-center gap-3">
        <button type="button" data-pasos-atras
                class="rounded-lg px-4 py-3 text-base font-medium text-[var(--muted)] ring-1 ring-[var(--border)] hover:text-[var(--text)]">
            Atrás
        </button>
        <button type="button" data-pasos-siguiente class="liquid-button-primary flex-1 rounded-lg px-4 py-3 text-base font-semibold">
            Siguiente
        </button>
        <button data-pasos-enviar class="liquid-button-primary hidden flex-1 rounded-lg px-4 py-3 text-base font-semibold">
            Enviar atención
        </button>
    </div>

    {{-- Sin script: un solo botón al final, con todo a la vista. --}}
    <button data-pasos-sin-script class="liquid-button-primary w-full rounded-lg px-4 py-4 text-lg font-semibold">
        Enviar atención
    </button>
</form>

{{--
    «Ver caso». El JS de derivaciones no sirve: este formulario no tiene nodo ni cumplimiento.
    Solo texto: lo que escribió la oficina nunca se interpreta como HTML.
    ponytail: si el frontend prefiere, pasarlo a resources/js.
--}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form[data-cctv-caso]');
        const q = (s) => form.querySelector(s);
        const paso = [...form.querySelectorAll('[data-paso]')].findIndex((p) => p.hasAttribute('data-caso-paso'));
        const numero = q('[data-caso-numero]');
        const boton = q('[data-caso-buscar]');
        const aviso = q('[data-caso-aviso]');
        const detalle = q('[data-caso-detalle]');
        const cedula = q('[data-cedula-entrada]');
        let turno = 0;

        const bloquear = (motivo) => form.dispatchEvent(new CustomEvent('pasos:bloquear', { detail: { paso, motivo } }));
        const avisar = (texto) => { aviso.textContent = texto; aviso.classList.toggle('hidden', texto === ''); };

        const limpiar = () => {
            turno++;
            detalle.classList.add('hidden');
            q('[data-caso-soporte-imagen]').removeAttribute('src');
            q('[data-caso-soporte-pdf]').removeAttribute('href');
            avisar('');
            bloquear('Escribe el número del caso y pulsa «Ver caso».');
        };

        const mostrar = (caso) => {
            q('[data-caso-codigo]').textContent = caso.codigo;
            q('[data-caso-descripcion]').textContent = caso.descripcion || '';
            q('[data-caso-ubicacion]').textContent = caso.ubicacion || '';
            q('[data-caso-comuna]').textContent = caso.comuna || '—';
            q('[data-caso-soporte]').classList.toggle('hidden', !caso.soporte);
            q('[data-caso-soporte-imagen]').classList.toggle('hidden', caso.soporte?.tipo !== 'imagen');
            q('[data-caso-soporte-pdf]').classList.toggle('hidden', !caso.soporte || caso.soporte.tipo === 'imagen');

            if (caso.soporte?.tipo === 'imagen') {
                q('[data-caso-soporte-imagen]').src = caso.soporte.url;
            } else if (caso.soporte) {
                q('[data-caso-soporte-pdf]').href = caso.soporte.url;
            }

            detalle.classList.remove('hidden');
            bloquear('');
        };

        const buscar = async () => {
            const valor = numero.value.trim();
            limpiar();

            if (!/^[0-9]{1,9}$/.test(valor)) {
                avisar('Escribe solo los números del código, sin «CCTV-».');
                return;
            }

            const este = turno;
            let estado = 0;
            let datos = null;
            boton.disabled = true;

            try {
                const respuesta = await fetch(form.dataset.consulta, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ cedula: cedula?.value.trim() || '', numero: valor }),
                    cache: 'no-store',
                });
                estado = respuesta.status;
                datos = await respuesta.json().catch(() => null);
            } catch {
                // Sin red: se dice abajo.
            }

            boton.disabled = false;

            if (este !== turno) {
                return;
            }

            if (estado === 200 && datos) {
                mostrar(datos);
            } else if (estado === 422) {
                avisar(datos?.message || 'No hay un caso abierto con ese número.');
            } else if (estado === 429) {
                avisar('Demasiados intentos seguidos. Espera unos minutos y vuelve a intentarlo.');
            } else {
                avisar('No pudimos consultar el caso. Revisa la conexión y vuelve a intentarlo.');
            }
        };

        boton.addEventListener('click', buscar);
        numero.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); buscar(); }
        });
        numero.addEventListener('input', () => { numero.value = numero.value.replace(/\D/g, ''); limpiar(); });
        form.addEventListener('cedula:consultada', (e) => {
            limpiar();
            if (e.detail?.existe && numero.value.trim() !== '') { buscar(); }
        });

        limpiar();
    });
</script>

@endsection
