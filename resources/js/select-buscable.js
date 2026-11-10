/**
 * Un buscador para los <select data-buscable> de carpetas: con cientos de
 * carpetas (una por persona, por ejemplo), la lista nativa no sirve ni
 * filtrada. El <select> queda oculto y es el que se envía; encima va un
 * campo que muestra la carpeta elegida y, al escribir, una lista corta:
 *  - el nombre de la carpeta grande y la ruta donde está, pequeña, debajo;
 *  - varias palabras se buscan todas («reg juan»), sin tildes ni mayúsculas;
 *  - primero las que empiezan por lo escrito, y como mucho MAXIMO resultados.
 */

const MAXIMO = 30;

const normalizar = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function iniciar(select) {
    const opciones = [...select.options].map((o) => {
        const ruta = o.textContent.trim();
        const corte = ruta.lastIndexOf(' / ');

        return {
            valor: o.value,
            ruta,
            nombre: corte >= 0 ? ruta.slice(corte + 3) : ruta,
            donde: corte >= 0 ? ruta.slice(0, corte) : '',
            buscable: normalizar(ruta),
            nombreBuscable: normalizar(corte >= 0 ? ruta.slice(corte + 3) : ruta),
        };
    });

    if (opciones.length < 8) {
        return; // Con pocas opciones, el selector de siempre basta.
    }

    // La primera opción sin valor («Raíz», «Selecciona…») se ofrece siempre.
    const fija = opciones[0]?.valor === '' ? opciones[0] : null;
    const resto = fija ? opciones.slice(1) : opciones;

    const caja = document.createElement('div');
    caja.className = 'relative min-w-0' + (select.classList.contains('flex-1') ? ' flex-1' : '') + (select.classList.contains('mt-1') ? ' mt-1' : '');

    const campo = document.createElement('input');
    campo.type = 'text';
    campo.autocomplete = 'off';
    campo.placeholder = 'Buscar carpeta por nombre o ruta…';
    campo.setAttribute('role', 'combobox');
    campo.setAttribute('aria-expanded', 'false');
    campo.setAttribute('aria-autocomplete', 'list');
    campo.setAttribute('aria-label', select.getAttribute('aria-label') || 'Buscar carpeta');
    campo.className = 'liquid-input w-full text-sm';
    if (select.id) {
        // La etiqueta <label for> enfoca el buscador.
        campo.id = select.id + '_buscar';
        document.querySelector(`label[for="${select.id}"]`)?.setAttribute('for', campo.id);
    }

    const lista = document.createElement('ul');
    lista.setAttribute('role', 'listbox');
    lista.className = 'absolute left-0 right-0 z-50 mt-1 hidden max-h-80 overflow-y-auto rounded-lg bg-[var(--card)] py-1 text-sm shadow-lg ring-1 ring-[var(--border)]';

    // En línea y no con la clase «hidden»: .liquid-input fija display y le gana.
    select.style.display = 'none';
    select.tabIndex = -1;
    select.before(caja);
    caja.append(campo, lista, select);

    let visibles = [];
    let activa = -1;

    const etiquetaElegida = () => opciones.find((o) => o.valor === select.value)?.ruta ?? '';
    const mostrarElegida = () => { campo.value = etiquetaElegida(); };

    function buscar(texto) {
        const palabras = normalizar(texto.trim()).split(/\s+/).filter(Boolean);

        if (!palabras.length) {
            return resto;
        }

        // Todas las palabras en la ruta; primero las que el nombre empieza por la primera palabra.
        return resto
            .filter((o) => palabras.every((p) => o.buscable.includes(p)))
            .sort((a, b) => Number(!a.nombreBuscable.startsWith(palabras[0])) - Number(!b.nombreBuscable.startsWith(palabras[0])));
    }

    function pintar() {
        const encontradas = buscar(campo.value === etiquetaElegida() ? '' : campo.value);
        visibles = [...(fija ? [fija] : []), ...encontradas.slice(0, MAXIMO)];
        activa = Math.min(activa, visibles.length - 1);

        lista.replaceChildren(...visibles.map((o, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', String(o.valor === select.value));
            li.className = 'cursor-pointer px-3 py-2 ' + (i === activa ? 'bg-[var(--card-soft)]' : 'hover:bg-[var(--card-soft)]');

            const nombre = document.createElement('span');
            nombre.className = 'block font-medium text-[var(--text)] break-words' + (o.valor === select.value ? ' text-[var(--primary)]' : '');
            nombre.textContent = o.nombre;
            li.append(nombre);

            if (o.donde) {
                const donde = document.createElement('span');
                donde.className = 'block text-xs text-[var(--muted)] break-words';
                donde.textContent = o.donde;
                li.append(donde);
            }

            // mousedown y no click: el campo no pierde el foco antes de elegir.
            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                elegir(o);
            });

            return li;
        }));

        const pie = document.createElement('li');
        pie.className = 'px-3 py-2 text-xs text-[var(--muted)]';
        if (!encontradas.length) {
            pie.textContent = 'Ninguna carpeta coincide.';
            lista.append(pie);
        } else if (encontradas.length > MAXIMO) {
            pie.textContent = `Se muestran ${MAXIMO} de ${encontradas.length}. Escribe más para afinar (puedes usar varias palabras).`;
            lista.append(pie);
        }
    }

    function abrir() {
        pintar();
        lista.classList.remove('hidden');
        campo.setAttribute('aria-expanded', 'true');
    }

    function cerrar() {
        lista.classList.add('hidden');
        campo.setAttribute('aria-expanded', 'false');
        activa = -1;
        mostrarElegida();
    }

    function elegir(opcion) {
        select.value = opcion.valor;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        cerrar();
    }

    campo.addEventListener('focus', () => {
        campo.select();
        abrir();
    });
    campo.addEventListener('input', () => {
        activa = -1;
        abrir();
    });
    campo.addEventListener('blur', cerrar);

    campo.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (lista.classList.contains('hidden')) {
                abrir();
            }
            activa = (activa + (e.key === 'ArrowDown' ? 1 : -1) + visibles.length) % visibles.length;
            pintar();
            lista.children[activa]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            // Enter elige, no envía el formulario.
            e.preventDefault();
            const opcion = visibles[activa] ?? (campo.value.trim() ? visibles.find((o) => o !== fija) : null);
            if (opcion) {
                elegir(opcion);
            }
        } else if (e.key === 'Escape') {
            cerrar();
        }
    });

    // Un select oculto y obligatorio sin elegir: el aviso va en el buscador.
    select.addEventListener('invalid', (e) => {
        e.preventDefault();
        campo.setCustomValidity('Elige una carpeta.');
        campo.reportValidity();
    });
    select.addEventListener('change', () => campo.setCustomValidity(''));

    mostrarElegida();
}

function iniciarTodos() {
    document.querySelectorAll('select[data-buscable]').forEach(iniciar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarTodos);
} else {
    iniciarTodos();
}
