/**
 * Formulario por pasos: una sección a la vez, con barra de progreso, y no
 * se avanza con algo obligatorio vacío. Sin el script, todas las secciones
 * se ven seguidas y el formulario funciona igual.
 *
 * Marcado: <form data-pasos data-paso-inicial="N"> con <section data-paso
 * data-titulo="..."> dentro, [data-pasos-barra], [data-pasos-texto],
 * [data-pasos-puntos], [data-pasos-aviso], y botones [data-pasos-atras],
 * [data-pasos-siguiente], [data-pasos-enviar].
 */

function visible(control) {
    return control.offsetParent !== null;
}

function nombreDe(control) {
    const etiqueta = control.id && control.form?.querySelector(`label[for="${control.id}"]`);
    const texto = etiqueta?.textContent || control.closest('div')?.querySelector('p, label')?.textContent || 'un campo obligatorio';

    return texto.replace('*', '').replace(/\s+/g, ' ').trim();
}

function iniciar(form) {
    const pasos = [...form.querySelectorAll('[data-paso]')];
    const barra = form.querySelector('[data-pasos-barra]');
    const texto = form.querySelector('[data-pasos-texto]');
    const puntos = form.querySelector('[data-pasos-puntos]');
    const aviso = form.querySelector('[data-pasos-aviso]');
    const atras = form.querySelector('[data-pasos-atras]');
    const siguiente = form.querySelector('[data-pasos-siguiente]');
    const enviar = form.querySelector('[data-pasos-enviar]');

    if (pasos.length < 2 || !siguiente || !enviar) {
        return;
    }

    let actual = Math.min(Number(form.dataset.pasoInicial) || 0, pasos.length - 1);

    // Un bloqueo propio del paso, además de lo nativo (p. ej. «cédula no registrada»).
    const bloqueos = new Map();
    form.addEventListener('pasos:bloquear', (e) => {
        bloqueos.set(e.detail.paso, e.detail.motivo || '');
    });

    const avisar = (mensaje) => {
        aviso.textContent = mensaje;
        aviso.classList.toggle('hidden', mensaje === '');
    };

    puntos.innerHTML = '';
    pasos.forEach((paso, i) => {
        const punto = document.createElement('li');
        punto.className = 'flex flex-1 flex-col items-center gap-1 text-center';
        punto.innerHTML = `<span class="flex size-7 items-center justify-center rounded-full text-xs font-semibold ring-1 transition-colors"></span>
            <span class="hidden text-[11px] leading-tight sm:block"></span>`;
        punto.lastElementChild.textContent = paso.dataset.titulo;
        puntos.appendChild(punto);
    });

    const pintar = () => {
        pasos.forEach((paso, i) => paso.classList.toggle('hidden', i !== actual));

        [...puntos.children].forEach((punto, i) => {
            const circulo = punto.firstElementChild;
            const hecho = i < actual;
            circulo.textContent = hecho ? '✓' : String(i + 1);
            circulo.className = 'flex size-7 items-center justify-center rounded-full text-xs font-semibold ring-1 transition-colors '
                + (i === actual
                    ? 'bg-[var(--primary)] text-white ring-[var(--primary)]'
                    : hecho
                        ? 'bg-[var(--success)] text-white ring-[var(--success)]'
                        : 'bg-[var(--card)] text-[var(--muted)] ring-[var(--border)]');
            punto.lastElementChild.className = 'hidden text-[11px] leading-tight sm:block '
                + (i === actual ? 'font-semibold text-[var(--text)]' : 'text-[var(--muted)]');
        });

        barra.style.width = `${((actual + 1) / pasos.length) * 100}%`;
        texto.textContent = `Paso ${actual + 1} de ${pasos.length} · ${pasos[actual].dataset.titulo}`;
        atras.classList.toggle('hidden', actual === 0);
        siguiente.classList.toggle('hidden', actual === pasos.length - 1);
        enviar.classList.toggle('hidden', actual !== pasos.length - 1);
        avisar('');
    };

    /** ¿Se puede salir de este paso? Si no, dice qué falta y se para ahí. */
    const pasoValido = (i) => {
        if (bloqueos.get(i)) {
            avisar(bloqueos.get(i));
            return false;
        }

        const invalido = [...pasos[i].querySelectorAll('input, select, textarea')]
            .find((control) => !control.disabled && !control.checkValidity());

        if (!invalido) {
            return true;
        }

        if (visible(invalido)) {
            invalido.reportValidity();
            invalido.focus();
        } else {
            // El campo de archivo nativo va oculto tras los botones de cámara.
            avisar(`Falta: ${nombreDe(invalido)}.`);
        }

        return false;
    };

    const ir = (i) => {
        actual = i;
        pintar();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    siguiente.addEventListener('click', () => {
        if (pasoValido(actual)) {
            ir(actual + 1);
        }
    });

    atras.addEventListener('click', () => ir(Math.max(0, actual - 1)));

    // Enter en un campo de texto avanza en vez de enviar a medias.
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && actual < pasos.length - 1) {
            e.preventDefault();
            siguiente.click();
        }
    });

    form.addEventListener('submit', (e) => {
        const malo = pasos.findIndex((_, i) => i <= actual && !pasoValido(i));

        if (malo !== -1) {
            e.preventDefault();
            ir(malo);
            pasoValido(malo);
            return;
        }

        enviar.disabled = true;
        enviar.textContent = 'Enviando…';
    });

    form.querySelector('[data-pasos-cabecera]')?.classList.remove('hidden');
    form.querySelector('[data-pasos-botones]')?.classList.remove('hidden');
    form.querySelector('[data-pasos-sin-script]')?.remove();
    pintar();
}

/* En el paso de la cédula: no se sigue con una que no está registrada. */
function iniciarCedula(form) {
    const paso = [...form.querySelectorAll('[data-paso]')].findIndex((p) => p.querySelector('[data-campo-cedula]'));

    if (paso === -1) {
        return;
    }

    form.addEventListener('cedula:consultada', (e) => {
        const motivo = e.detail && !e.detail.existe
            ? (e.detail.aviso || 'Esta identificación no está registrada. Pide a Coordinación que te registre.')
            : '';
        form.dispatchEvent(new CustomEvent('pasos:bloquear', { detail: { paso, motivo } }));
    });
}

function iniciarTodos() {
    document.querySelectorAll('form[data-pasos]').forEach((form) => {
        iniciarCedula(form);
        iniciar(form);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarTodos);
} else {
    iniciarTodos();
}
