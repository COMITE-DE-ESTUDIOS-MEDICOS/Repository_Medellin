/**
 * Identificarse por cédula en los enlaces públicos (turno y evidencias).
 *
 * Al salir del campo se pregunta al servidor si la identificación ya está
 * registrada. La respuesta trae dos cosas y nada más —si existe, y un saludo
 * con el primer nombre recortado—: el enlace es compartido y cualquiera puede
 * escribir cédulas al azar.
 *
 * Si existe, se saluda y el registro se cierra. Si no, se abre, una sola vez:
 * la próxima, la misma cédula basta.
 */

const LLAVE = 'documenta:cedula';

/*
 * Recordar la cédula es una comodidad para no escribirla cada día, nunca un
 * requisito. localStorage puede no estar o lanzar al tocarlo —navegación
 * privada, datos del sitio bloqueados—, y entonces simplemente no se recuerda.
 */
function leerRecordada() {
    try {
        return window.localStorage.getItem(LLAVE) || '';
    } catch {
        return '';
    }
}

function recordar(cedula) {
    try {
        window.localStorage.setItem(LLAVE, cedula);
    } catch {
        // Sin memoria en este teléfono: se escribirá a mano la próxima vez.
    }
}

function olvidar() {
    try {
        window.localStorage.removeItem(LLAVE);
    } catch {
        // Nada que borrar.
    }
}

function iniciar(campo) {
    const entrada = campo.querySelector('[data-cedula-entrada]');
    const saludo = campo.querySelector('[data-cedula-saludo]');
    const saludoTexto = campo.querySelector('[data-cedula-saludo-texto]');
    const aviso = campo.querySelector('[data-cedula-aviso]');
    const registro = campo.querySelector('[data-cedula-registro]');

    if (!entrada || !saludo || !registro || !campo.dataset.consulta) {
        return;
    }

    const mostrarSaludo = (texto) => {
        saludoTexto.textContent = texto;
        saludo.classList.toggle('hidden', texto === '');
        saludo.classList.toggle('flex', texto !== '');
    };

    const avisar = (texto) => {
        if (aviso) {
            aviso.textContent = texto;
            aviso.classList.toggle('hidden', texto === '');
        }
    };

    // Un campo oculto con required no deja enviar el formulario, y además
    // viajaría vacío. Deshabilitado, ni se valida ni se envía.
    const abrirRegistro = (abierto) => {
        registro.classList.toggle('hidden', !abierto);
        registro.querySelectorAll('input, select, textarea').forEach((control) => {
            control.disabled = !abierto;
        });
    };

    // La última cédula que tuvo respuesta, para no preguntar dos veces lo
    // mismo; y un contador que descarta las respuestas que llegan tarde.
    let consultada = null;
    let turno = 0;

    // Quien use la pieza (el enlace de turno, por ejemplo) se entera de la
    // respuesta sin tener que volver a preguntar: null es «aún no se sabe».
    const anunciar = (datos) => {
        campo.dispatchEvent(new CustomEvent('cedula:consultada', { detail: datos, bubbles: true }));
    };

    const reiniciar = () => {
        consultada = null;
        turno++;
        mostrarSaludo('');
        avisar('');
        abrirRegistro(false);
        anunciar(null);
    };

    const consultar = async () => {
        const cedula = entrada.value.trim();

        if (cedula === '') {
            reiniciar();
            return;
        }

        if (cedula === consultada) {
            return;
        }

        const este = ++turno;
        let estado = 0;
        let datos = null;

        avisar('');

        try {
            const respuesta = await fetch(campo.dataset.consulta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': campo.dataset.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ cedula }),
            });

            estado = respuesta.status;

            if (respuesta.ok) {
                datos = await respuesta.json();
            }
        } catch {
            // Sin red: se resuelve igual abajo.
        }

        if (este !== turno) {
            return;
        }

        // No se pudo preguntar. El registro queda a la vista y el servidor
        // decide al enviar: si la cédula ya estaba, usa los datos que tiene.
        if (datos === null) {
            consultada = null;
            mostrarSaludo('');
            abrirRegistro(true);
            avisar(estado === 429
                ? 'Demasiados intentos seguidos. Espera un minuto y vuelve a escribir tu identificación.'
                : 'No pudimos comprobar tu identificación. Llena tus datos y envía: si ya estabas registrado, se usan los que tenemos.');
            return;
        }

        consultada = cedula;
        anunciar(datos);

        if (datos.existe) {
            mostrarSaludo('Hola, ' + datos.saludo);
            abrirRegistro(false);
            recordar(cedula);
        } else if (datos.aviso) {
            // Registrada pero no puede seguir (no está en turno, otro cargo): se dice por qué
            // y no se abre el registro, que no le sirve.
            mostrarSaludo('');
            abrirRegistro(false);
            avisar(datos.aviso);
        } else {
            mostrarSaludo('');
            abrirRegistro(true);

            // Un formulario sin registro (derivaciones) no deja entrar a quien no está.
            // El servidor puede decir por qué no (supervisión: «solo para el Apoyo…»).
            if (registro.children.length === 0) {
                avisar(datos.aviso || 'Esta identificación no está registrada. Pide a Coordinación que te registre.');
            }
        }
    };

    entrada.addEventListener('change', consultar);

    // Mientras se corrige, el saludo de la cédula anterior ya no aplica.
    entrada.addEventListener('input', () => {
        if (entrada.value.trim() !== consultada) {
            mostrarSaludo('');
        }
    });

    // En el teléfono, «Ir» en el teclado numérico enviaría el formulario antes
    // de saber quién es: primero se pregunta.
    entrada.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            consultar();
        }
    });

    // Para el teléfono compartido: se olvida la cédula y se empieza de cero.
    campo.querySelector('[data-cedula-no-soy-yo]')?.addEventListener('click', () => {
        olvidar();
        entrada.value = '';
        reiniciar();
        entrada.focus();
    });

    // Una cédula nueva se recuerda al enviar, que es cuando queda registrada.
    entrada.form?.addEventListener('submit', () => {
        const cedula = entrada.value.trim();

        if (cedula !== '') {
            recordar(cedula);
        }
    });

    // Si el servidor lo devolvió abierto —falló algo del registro—, se queda
    // así: ahí está lo que hay que corregir.
    if (campo.hasAttribute('data-registro-abierto')) {
        consultada = entrada.value.trim() || null;
        abrirRegistro(true);
        return;
    }

    abrirRegistro(false);

    if (entrada.value.trim() === '') {
        entrada.value = leerRecordada();
    }

    if (entrada.value.trim() !== '') {
        consultar();
    }
}

function iniciarTodos() {
    document.querySelectorAll('[data-campo-cedula]').forEach(iniciar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarTodos);
} else {
    iniciarTodos();
}
