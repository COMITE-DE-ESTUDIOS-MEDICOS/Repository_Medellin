/**
 * La pantalla de marcar turno (/t/{token}).
 *
 * - Pide la ubicación al entrar, no al tocar el botón: así ya está lista.
 * - Cambia el texto del botón según la cédula: «Registrar entrada» o
 *   «Registrar salida». Lo que vale es lo que decida el servidor; esto solo
 *   le dice a la persona qué va a pasar.
 * - Al enviar, espera la ubicación (con tope) y la pone en campos ocultos.
 *   Sin señal se envía igual: el servidor lo anota como novedad.
 */
import { COMO_ACTIVAR_PRECISA, datosDeUbicacion, pedirUbicacionAhora, prepararUbicacion } from './foto-georreferencial';

const TEXTOS = {
    entrada: 'Registrar entrada',
    salida: 'Registrar salida',
    cerrado: 'Ya registraste tu turno de hoy',
    // DOC-17: el primer intento sí llegó; otro botón aquí solo invita al error.
    reciente: 'Tu marca ya quedó registrada ✓',
};

function iniciar(formulario) {
    const boton = formulario.querySelector('[data-turno-boton]');
    const textoBase = boton?.textContent.trim() ?? '';
    let enviando = false;
    // DOC-15: con ubicación aproximada, el primer toque avisa y el segundo envía igual.
    let imprecisaConfirmada = false;
    const precisionMinima = Number(formulario.dataset.precisionMinima || 0);
    const ubicacionObligatoria = formulario.dataset.ubicacionObligatoria === '1';
    const avisoUbicacion = (texto) => {
        const aviso = formulario.querySelector('[data-turno-ubicacion]');

        if (aviso && texto) {
            aviso.textContent = texto;
            aviso.classList.remove('hidden');
        }
    };

    // El último motivo de no tener ubicación, tal cual, para armar el aviso sin repetirlo.
    let ultimoMotivo = '';

    prepararUbicacion((motivo) => {
        ultimoMotivo = motivo;
        const aviso = formulario.querySelector('[data-turno-ubicacion]');

        if (aviso) {
            aviso.textContent = motivo;
            aviso.classList.toggle('hidden', motivo === '');
        }
    });

    // DOC-12: la pregunta de los EPP solo en la entrada. Oculta, no se exige.
    const epp = formulario.querySelector('[data-turno-epp]');
    const mostrarEpp = (visible) => {
        epp?.classList.toggle('hidden', !visible);
        epp?.querySelectorAll('input').forEach((radio) => { radio.required = visible; });
    };

    formulario.addEventListener('cedula:consultada', (evento) => {
        mostrarEpp(evento.detail?.accion === 'entrada');

        if (boton) {
            const accion = evento.detail?.accion;
            boton.textContent = TEXTOS[accion] ?? textoBase;
            boton.dataset.textoAccion = boton.textContent;
            // Con el turno del día cerrado no hay nada que marcar: si la hora
            // está mal, la corrige Coordinación, no un segundo envío.
            boton.disabled = accion === 'cerrado' || accion === 'reciente';
        }
    });

    formulario.addEventListener('submit', async (evento) => {
        if (enviando) {
            return;
        }

        evento.preventDefault();

        if (!formulario.reportValidity()) {
            return;
        }

        enviando = true;

        if (boton) {
            boton.disabled = true;
            boton.textContent = 'Ubicando…';
        }

        // Tope propio de 10 s: más allá de eso se marca sin ubicación antes
        // que dejar a la persona mirando un botón que no responde.
        await Promise.race([pedirUbicacionAhora(), new Promise((r) => setTimeout(r, 10000))]);

        const ubicacion = datosDeUbicacion();

        // No se envía para que el servidor lo rechace: así no se pierde la foto ya tomada.
        const detenerse = (textoBoton) => {
            enviando = false;

            if (boton) {
                boton.disabled = false;
                boton.textContent = textoBoton;
            }
        };

        if (!ubicacion && ubicacionObligatoria) {
            // La foto ya tomada se conserva: solo hay que volver a tocar el botón.
            const motivo = ultimoMotivo.replace(/\s*Luego vuelve a tomar la foto\.?$/, '') || 'Activa la ubicación (GPS) y permite el acceso.';
            avisoUbicacion(`Este turno exige tu ubicación y el teléfono no la está entregando. ${motivo} Luego vuelve a tocar el botón.`);
            detenerse(boton?.dataset.textoAccion || textoBase);
            return;
        }

        if (ubicacion && precisionMinima && ubicacion.precision > precisionMinima && !imprecisaConfirmada) {
            const km = (ubicacion.precision / 1000).toLocaleString('es-CO', { maximumFractionDigits: 1 });
            avisoUbicacion(`Tu teléfono está dando una ubicación aproximada (±${km} km). Activa la ubicación precisa: ${COMO_ACTIVAR_PRECISA} Luego vuelve a tocar el botón.`);
            imprecisaConfirmada = true;
            detenerse('Registrar de todos modos');
            return;
        }

        const poner = (nombre, valor) => {
            const campo = formulario.querySelector(`[name="${nombre}"]`);

            if (campo) {
                campo.value = valor ?? '';
            }
        };

        poner('lat', ubicacion?.lat);
        poner('lng', ubicacion?.lng);
        poner('precision_m', ubicacion?.precision);
        poner('municipio', ubicacion?.municipio);

        if (boton) {
            boton.textContent = 'Registrando…';
        }

        formulario.submit();
    });
}

document.querySelectorAll('[data-turno]').forEach(iniciar);
