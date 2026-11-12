/**
 * Copiar o mover varios a la vez en el explorador. Los checks de carpetas y
 * documentos apuntan al formulario #form-lote; aquí solo se enseña la barra
 * cuando hay algo marcado, se cuenta, y se marca o desmarca todo.
 *
 * El check de una carpeta no se ve: lo marca «Seleccionar» en el menú ⋮ de su
 * tarjeta ([data-lote-alternar]), y de ahí en adelante es un check como los
 * de los documentos. Cómo se ve marcada lo resuelve el CSS (has-[:checked]);
 * aquí solo se deja dicho para el lector de pantalla ([data-lote-estado]).
 *
 * Mover pide confirmación: cambia dónde está todo lo marcado y lo verán así
 * los demás. Copiar no, porque no le quita nada a nadie.
 */
function iniciar(barra) {
    const items = () => document.querySelectorAll('[data-lote-item]');
    const cuenta = barra.querySelector('[data-lote-cuenta]');
    const todos = document.querySelector('[data-lote-todos]');

    const actualizar = () => {
        const marcados = Array.from(items()).filter((item) => item.checked).length;
        const documentos = Array.from(document.querySelectorAll('[data-lote-documento]'));

        cuenta.textContent = String(marcados);
        barra.hidden = marcados === 0;

        document.querySelectorAll('[data-lote-estado]').forEach((estado) => {
            estado.textContent = document.getElementById(estado.dataset.loteEstado)?.checked ? 'Seleccionada' : '';
        });

        if (todos) {
            const marcadosDocs = documentos.filter((d) => d.checked).length;
            todos.checked = documentos.length > 0 && marcadosDocs === documentos.length;
            todos.indeterminate = marcadosDocs > 0 && marcadosDocs < documentos.length;
        }
    };

    document.addEventListener('change', (evento) => {
        if (evento.target.matches('[data-lote-item]')) {
            actualizar();
        }
    });

    // En el botón y no en el documento: así el check ya cambió cuando el
    // menú se cierra y devuelve el foco al ⋮, que lo anuncia con su estado.
    document.querySelectorAll('[data-lote-alternar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const item = document.getElementById(boton.dataset.loteAlternar);

            item.checked = !item.checked;
            item.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    todos?.addEventListener('change', () => {
        document.querySelectorAll('[data-lote-documento]').forEach((d) => {
            d.checked = todos.checked;
        });
        actualizar();
    });

    barra.querySelector('[data-lote-limpiar]')?.addEventListener('click', () => {
        items().forEach((item) => {
            item.checked = false;
        });
        actualizar();
    });

    barra.addEventListener('submit', (evento) => {
        const boton = evento.submitter;

        if (boton?.matches('[data-lote-mover]')) {
            const destino = barra.querySelector('select[name="destino"]');
            const nombre = destino.selectedOptions[0]?.textContent.trim() ?? 'la carpeta elegida';

            if (!window.confirm(`¿Mover ${cuenta.textContent} a «${nombre}»? Dejarán de estar donde están ahora.`)) {
                evento.preventDefault();
            }
        }
    });

    // Al volver con «atrás», el navegador puede recordar los checks marcados.
    actualizar();
}

const barra = document.querySelector('[data-lote-barra]');

if (barra) {
    iniciar(barra);
}
