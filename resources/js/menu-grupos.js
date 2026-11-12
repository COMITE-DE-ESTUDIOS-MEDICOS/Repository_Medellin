/**
 * Los grupos plegables del menú lateral (<details data-grupo-menu>).
 * Recuerda en el navegador cuáles dejó abiertos cada quien; el grupo de la
 * página actual siempre viene abierto, se haya guardado lo que se haya
 * guardado. Sin localStorage, simplemente no recuerda.
 */
const LLAVE = 'documenta:menu-grupos';

function leer() {
    try {
        return JSON.parse(window.localStorage.getItem(LLAVE)) ?? {};
    } catch {
        return {};
    }
}

function guardar(estado) {
    try {
        window.localStorage.setItem(LLAVE, JSON.stringify(estado));
    } catch {
        // Sin memoria en este navegador.
    }
}

const estado = leer();

document.querySelectorAll('details[data-grupo-menu]').forEach((grupo) => {
    const clave = grupo.dataset.grupoMenu;

    // Sin título no hay cómo volver a abrirlo: ese grupo no se pliega.
    if (!grupo.querySelector('summary')) {
        return;
    }

    if (!grupo.hasAttribute('data-grupo-activo') && clave in estado) {
        grupo.open = estado[clave];
    }

    grupo.addEventListener('toggle', () => {
        estado[clave] = grupo.open;
        guardar(estado);
    });
});
