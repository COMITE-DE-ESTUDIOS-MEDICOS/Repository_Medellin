/**
 * Sonidos de confirmación. Viven en public/media y se nombran aquí por lo que
 * significan, no por el archivo: si mañana se cambia un sonido, se cambia en
 * esta lista y en ningún otro sitio.
 *
 * Un sonido nunca es la única señal: siempre acompaña a algo que ya se ve (el
 * toast, la pantalla de recibido). Quien tiene el volumen apagado, o un
 * navegador que no lo deja sonar, no se pierde nada.
 */
/*
 * 'exito' es el de los toasts de la app (guardar, cargar, inactivar…).
 * 'envio' es el de entregar algo por un enlace público: enviar evidencias y
 * marcar entrada o salida suenan igual porque son la misma clase de acción.
 */
const SONIDOS = {
    exito: '/media/terminalCommandSucceeded.mp3',
    envio: '/media/taskCompleted.mp3',
    error: '/media/error.mp3',
    salida: '/media/responseReceived1.mp3',
    notificacion: '/media/codeActionApplied.mp3',
};

const VOLUMEN = 0.6;

/**
 * Reproduce un sonido y devuelve el audio, o null si no sonó.
 *
 * Los navegadores no dejan sonar a una página que la persona todavía no ha
 * tocado. Safari en iPhone es el más estricto. Si lo bloquea, se sigue en
 * silencio: un sonido de confirmación no puede romper nada.
 */
export function reproducir(nombre) {
    const audio = obtener(nombre);

    if (!audio) {
        return Promise.resolve(null);
    }

    pedidos.add(nombre);
    audio.muted = false;
    audio.currentTime = 0;

    return audio.play().then(() => audio).catch(() => null);
}

/*
 * Un elemento por sonido, siempre el mismo. Hace falta para el desbloqueo de
 * abajo: en iPhone, lo que se desbloquea es ese elemento concreto, no la
 * página. Un Audio nuevo en cada llamada volvería a nacer bloqueado.
 */
const elementos = {};

// Los que ya se pidieron de verdad. Si el primer toque es justo «Cerrar
// sesión», el desbloqueo de abajo no debe pausar el sonido que acaba de pedirse.
const pedidos = new Set();

function obtener(nombre) {
    if (!SONIDOS[nombre]) {
        return null;
    }

    if (!elementos[nombre]) {
        elementos[nombre] = new Audio(SONIDOS[nombre]);
        elementos[nombre].volume = VOLUMEN;
    }

    return elementos[nombre];
}

/*
 * El sonido de una notificación lo dispara un temporizador, no un toque, y
 * Safari en iPhone no deja sonar a nada que no venga de un toque. Al primer
 * toque en la página se reproduce cada sonido en silencio y se detiene: desde
 * ese momento iPhone los deja sonar aunque los dispare el temporizador.
 */
document.addEventListener('pointerdown', () => {
    Object.keys(SONIDOS).forEach((nombre) => {
        const audio = obtener(nombre);

        if (pedidos.has(nombre)) {
            return;
        }

        audio.muted = true;
        audio.play().then(() => {
            if (!pedidos.has(nombre)) {
                audio.pause();
                audio.currentTime = 0;
            }

            audio.muted = false;
        }, () => {
            audio.muted = false;
        });
    });
}, { once: true, capture: true });

/** Como reproducir, pero espera a que termine, con un tope para no dejar a nadie esperando. */
async function reproducirYEsperar(nombre, tope = 1500) {
    const audio = await reproducir(nombre);

    if (!audio) {
        return;
    }

    await Promise.race([
        new Promise((resolve) => audio.addEventListener('ended', resolve, { once: true })),
        new Promise((resolve) => setTimeout(resolve, tope)),
    ]);
}

/*
 * Formularios con data-sonido-al-enviar (cerrar sesión): el envío espera a que
 * el sonido termine. Si se enviara en el acto, la página siguiente cortaría el
 * sonido antes de oírse.
 *
 * form.submit() no vuelve a disparar el evento 'submit', así que no hay bucle.
 */
document.addEventListener('submit', async (evento) => {
    const formulario = evento.target;
    const nombre = formulario.dataset?.sonidoAlEnviar;

    if (!nombre || formulario.dataset.enviando) {
        return;
    }

    evento.preventDefault();
    formulario.dataset.enviando = '1';

    await reproducirYEsperar(nombre);
    formulario.submit();
});

/*
 * Páginas que suenan al abrirse (la de documentos recibidos). Va en la página
 * de llegada y no al pulsar «Enviar»: así solo suena si el envío salió bien, y
 * no cuando el servidor lo rechaza.
 */
function sonarAlCargar() {
    const nombre = document.querySelector('[data-sonido-al-cargar]')?.dataset.sonidoAlCargar;

    if (nombre) {
        reproducir(nombre);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sonarAlCargar);
} else {
    sonarAlCargar();
}
