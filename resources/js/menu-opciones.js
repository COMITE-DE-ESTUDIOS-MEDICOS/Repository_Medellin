/**
 * Los menús ⋮ (<details data-menu-opciones>), como el de las tarjetas de
 * carpeta del explorador. Abrir y cerrar ya lo hace el <details>, también sin
 * JavaScript; aquí va lo que el navegador no da para que se porte como menú:
 *
 * - uno abierto a la vez: abrir uno cierra el que hubiera;
 * - se cierra al tocar fuera, con Tab y con Escape, y entonces el foco vuelve
 *   al ⋮ para no perder el sitio;
 * - ↑ y ↓ recorren las opciones, Inicio y Fin van a la primera y la última;
 * - si no cabe debajo se abre hacia arriba, y nunca se sale por los lados.
 */
const MENUS = 'details[data-menu-opciones]';
const MARGEN = 8;

const opciones = (menu) => Array.from(menu.querySelectorAll('[role="menuitem"]'));

function cerrar(menu, devolverFoco = false) {
    if (!menu.open) {
        return;
    }

    if (devolverFoco) {
        menu.querySelector('summary').focus();
    }

    menu.open = false;
}

/*
 * Se mide ya abierto. Primero se deja donde lo pone la vista (debajo y
 * alineado a la derecha del ⋮) y desde ahí se corrige lo que no quepa.
 */
function colocar(menu) {
    const lista = menu.querySelector('[role="menu"]');
    const ancho = document.documentElement.clientWidth;
    const alto = document.documentElement.clientHeight;

    lista.style.removeProperty('translate');
    lista.style.removeProperty('top');
    lista.style.removeProperty('bottom');

    const caja = lista.getBoundingClientRect();
    const boton = menu.querySelector('summary').getBoundingClientRect();

    if (caja.bottom > alto - MARGEN && boton.top - caja.height > MARGEN) {
        lista.style.top = 'auto';
        lista.style.bottom = 'calc(100% + 0.25rem)';
    }

    if (caja.left < MARGEN) {
        lista.style.translate = `${MARGEN - caja.left}px 0`;
    } else if (caja.right > ancho - MARGEN) {
        lista.style.translate = `${ancho - MARGEN - caja.right}px 0`;
    }
}

function iniciar(menu) {
    const boton = menu.querySelector('summary');

    // Con ↑ el foco entra por la última opción; con todo lo demás, por la primera.
    let entrarPorElFinal = false;

    menu.addEventListener('toggle', () => {
        boton.setAttribute('aria-expanded', String(menu.open));

        if (!menu.open) {
            return;
        }

        document.querySelectorAll(`${MENUS}[open]`).forEach((otro) => {
            if (otro !== menu) {
                cerrar(otro);
            }
        });

        colocar(menu);

        const lista = opciones(menu);
        (entrarPorElFinal ? lista.at(-1) : lista[0])?.focus();
        entrarPorElFinal = false;
    });

    menu.addEventListener('keydown', (evento) => {
        const lista = opciones(menu);
        const actual = lista.indexOf(document.activeElement);

        switch (evento.key) {
            case 'ArrowDown':
            case 'ArrowUp': {
                evento.preventDefault();
                const subir = evento.key === 'ArrowUp';

                if (!menu.open) {
                    entrarPorElFinal = subir;
                    menu.open = true;
                } else if (actual === -1) {
                    (subir ? lista.at(-1) : lista[0])?.focus();
                } else {
                    lista[(actual + (subir ? -1 : 1) + lista.length) % lista.length].focus();
                }
                break;
            }
            case 'Home':
            case 'End':
                if (menu.open && actual !== -1) {
                    evento.preventDefault();
                    (evento.key === 'Home' ? lista[0] : lista.at(-1)).focus();
                }
                break;
            case 'Tab':
                // Sin preventDefault: el foco vuelve al ⋮ y el Tab sigue
                // desde ahí, hacia delante o hacia atrás, como si nada.
                cerrar(menu, true);
                break;
            case ' ':
                // Los enlaces no responden al espacio; en un menú, sí.
                if (actual !== -1 && lista[actual].tagName === 'A') {
                    evento.preventDefault();
                    lista[actual].click();
                }
                break;
        }
    });
}

const menus = document.querySelectorAll(MENUS);

menus.forEach(iniciar);

if (menus.length > 0) {
    document.addEventListener('click', (evento) => {
        document.querySelectorAll(`${MENUS}[open]`).forEach((menu) => {
            if (!menu.contains(evento.target)) {
                cerrar(menu);
            } else if (evento.target.closest('button[role="menuitem"]')) {
                // Una opción que no navega (Seleccionar) ya hizo lo suyo.
                cerrar(menu, true);
            }
        });
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            document.querySelectorAll(`${MENUS}[open]`).forEach((menu) => cerrar(menu, true));
        }
    });
}
