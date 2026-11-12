/**
 * Selects que dependen de otro: se elige el componente y el de nodo (o el de
 * cargo) muestra solo los suyos. Así no hace falta repetir «Calle» en cada
 * opción: ya se sabe de qué componente son.
 *
 *   <select id="componente_id">…</select>
 *   <select data-depende-de="#componente_id">
 *       <option value="">Selecciona…</option>
 *       <option value="7" data-componente="3">Nodo 1</option>
 *   </select>
 *
 * Con el componente vacío se ven todas (útil en los filtros). Si la opción
 * elegida deja de ser del componente, se vacía en vez de quedar escondida.
 */
function enlazar(hijo) {
    const padre = document.querySelector(hijo.dataset.dependeDe);

    if (!padre) {
        return;
    }

    const aplicar = () => {
        const componente = padre.value;

        hijo.querySelectorAll('option[data-componente]').forEach((opcion) => {
            const visible = componente === '' || opcion.dataset.componente === componente;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
        });

        if (hijo.selectedOptions[0]?.disabled) {
            hijo.value = '';
        }
    };

    padre.addEventListener('change', aplicar);
    aplicar();
}

document.querySelectorAll('select[data-depende-de]').forEach(enlazar);
