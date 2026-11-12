/**
 * En la ficha de un archivo, ← y → pasan al anterior y al siguiente de la
 * misma carpeta (los enlaces [data-navegar] que pone la vista). No se
 * interceptan mientras se escribe un comentario o se elige algo.
 */
document.addEventListener('keydown', (evento) => {
    if (evento.altKey || evento.ctrlKey || evento.metaKey || evento.shiftKey) {
        return;
    }

    const lado = { ArrowLeft: 'anterior', ArrowRight: 'siguiente' }[evento.key];
    const escribiendo = evento.target.closest?.('input, textarea, select, [contenteditable="true"]');

    if (!lado || escribiendo) {
        return;
    }

    const enlace = document.querySelector(`[data-navegar="${lado}"]`);

    if (enlace) {
        evento.preventDefault();
        window.location.href = enlace.href;
    }
});
