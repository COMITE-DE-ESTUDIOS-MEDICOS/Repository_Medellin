/*
 * Derivaciones pendientes: el tiempo que lleva corre a la vista.
 * <span data-contador-desde="2026-10-23T10:00:00-05:00" data-meta-minutos="180"></span>
 * Pasada la meta se pone en rojo. El servidor hace el mismo cálculo al responder.
 */
function pintar(el) {
    const minutos = Math.max(0, Math.floor((Date.now() - Date.parse(el.dataset.contadorDesde)) / 60000));
    const meta = Number(el.dataset.metaMinutos);
    const h = Math.floor(minutos / 60);

    el.textContent = `${h ? `${h} h ` : ''}${minutos % 60} min`;
    el.classList.toggle('text-[var(--danger)]', minutos > meta);
    el.classList.toggle('text-[var(--warning)]', minutos <= meta);
}

function iniciar() {
    const contadores = document.querySelectorAll('[data-contador-desde]');

    if (contadores.length === 0) {
        return;
    }

    contadores.forEach(pintar);
    setInterval(() => contadores.forEach(pintar), 30000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
} else {
    iniciar();
}
