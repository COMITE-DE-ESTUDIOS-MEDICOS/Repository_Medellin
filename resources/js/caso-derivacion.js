/**
 * «Atender caso» en el enlace de derivaciones (DOC-20).
 *
 * Con la cédula ya reconocida, se pide el caso por su código exacto (POST,
 * nunca en la URL). El caso solo vive en la página: nada va a localStorage
 * ni a sessionStorage. El servidor vuelve a validar todo al enviar.
 */

function iniciar(form) {
    const paso = [...form.querySelectorAll('[data-paso]')].findIndex((p) => p.hasAttribute('data-caso-paso'));
    const numero = form.querySelector('[data-caso-numero]');
    const buscarBoton = form.querySelector('[data-caso-buscar]');
    const aviso = form.querySelector('[data-caso-aviso]');
    const detalle = form.querySelector('[data-caso-detalle]');
    const soporte = form.querySelector('[data-caso-soporte]');
    const imagen = form.querySelector('[data-caso-soporte-imagen]');
    const pdf = form.querySelector('[data-caso-soporte-pdf]');
    const nodo = form.querySelector('[data-caso-nodo]');
    const observacion = form.querySelector('#observacion');
    const justificacion = form.querySelector('#justificacion');
    const bloqueJustificacion = form.querySelector('[data-caso-justificacion]');
    const cedula = form.querySelector('[data-cedula-entrada]');

    if (paso === -1 || !numero || !buscarBoton) {
        return;
    }

    let turno = 0;
    let nodoElegido = false;

    const bloquear = (motivo) => {
        form.dispatchEvent(new CustomEvent('pasos:bloquear', { detail: { paso, motivo } }));
    };

    const avisar = (texto) => {
        aviso.textContent = texto;
        aviso.classList.toggle('hidden', texto === '');
    };

    const vencido = (si) => {
        justificacion.required = si;
        bloqueJustificacion.classList.toggle('hidden', !si && justificacion.value.trim() === '');
    };

    const limpiar = () => {
        turno++;
        detalle.classList.add('hidden');
        detalle.querySelectorAll('[data-caso-codigo], [data-caso-mensaje], [data-caso-entidad], [data-caso-ubicacion], [data-caso-comuna]')
            .forEach((el) => { el.textContent = ''; });
        imagen.removeAttribute('src');
        pdf.removeAttribute('href');
        avisar('');
        vencido(false);
        bloquear('Escribe el código del caso y pulsa «Ver caso».');
    };

    const mostrar = (caso) => {
        // Solo texto: lo que escribió la oficina nunca se interpreta como HTML.
        detalle.querySelector('[data-caso-codigo]').textContent = caso.codigo;
        detalle.querySelector('[data-caso-mensaje]').textContent = caso.mensaje || '';
        detalle.querySelector('[data-caso-entidad]').textContent = caso.entidad || '';
        detalle.querySelector('[data-caso-ubicacion]').textContent = caso.ubicacion || '';
        detalle.querySelector('[data-caso-comuna]').textContent = caso.comuna || '—';

        soporte.classList.toggle('hidden', !caso.soporte);
        imagen.classList.toggle('hidden', caso.soporte?.tipo !== 'imagen');
        pdf.classList.toggle('hidden', !caso.soporte || caso.soporte.tipo === 'imagen');

        if (caso.soporte?.tipo === 'imagen') {
            imagen.src = caso.soporte.url;
        } else if (caso.soporte) {
            pdf.href = caso.soporte.url;
        }

        if (!nodoElegido && caso.nodo_id && nodo.querySelector(`option[value="${Number(caso.nodo_id)}"]`)) {
            nodo.value = String(caso.nodo_id);
        }

        vencido(Boolean(caso.vencido));
        detalle.classList.remove('hidden');
        bloquear('');
    };

    const buscar = async () => {
        const valor = numero.value.trim();
        limpiar();

        if (!/^[0-9]{1,9}$/.test(valor)) {
            avisar('Escribe solo los números del código, sin «DC-».');
            return;
        }

        const este = turno;
        let estado = 0;
        let datos = null;

        buscarBoton.disabled = true;

        try {
            const respuesta = await fetch(form.dataset.consulta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': form.dataset.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ cedula: cedula?.value.trim() || '', numero: valor }),
                cache: 'no-store',
            });

            estado = respuesta.status;
            datos = await respuesta.json().catch(() => null);
        } catch {
            // Sin red: se dice abajo.
        }

        buscarBoton.disabled = false;

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

    buscarBoton.addEventListener('click', buscar);

    // Enter en el código busca el caso (pasos.js avanzaría sin él).
    numero.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            buscar();
        }
    });

    numero.addEventListener('input', () => {
        numero.value = numero.value.replace(/\D/g, '');
        limpiar();
    });

    nodo.addEventListener('change', () => { nodoElegido = true; });

    // Con «No», la observación es obligatoria.
    form.querySelectorAll('[data-caso-cumplio]').forEach((radio) => {
        radio.addEventListener('change', () => {
            observacion.required = form.querySelector('[data-caso-cumplio]:checked')?.value === '0';
        });
    });
    observacion.required = form.querySelector('[data-caso-cumplio]:checked')?.value === '0';

    // Otra cédula: el caso consultado ya no aplica. Si volvió del servidor con
    // el código escrito (un error al enviar), se vuelve a pedir.
    form.addEventListener('cedula:consultada', (e) => {
        limpiar();

        if (e.detail?.existe && numero.value.trim() !== '') {
            buscar();
        }
    });

    limpiar();
}

function iniciarTodos() {
    document.querySelectorAll('form[data-caso-derivacion]').forEach(iniciar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarTodos);
} else {
    iniciarTodos();
}
