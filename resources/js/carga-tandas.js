/**
 * Cargas grandes por tandas.
 *
 * Se pueden elegir cientos de archivos, pero el servidor recibe como mucho
 * 50 por petición. En vez de mandarlo todo de una vez —una sola petición de
 * gigas, que un corte de red tumbaría entera—, el navegador lo parte en
 * tandas y las manda una detrás de otra:
 *
 *   - si son 50 o menos, el formulario se envía como siempre (nada cambia);
 *   - cada tanda lleva como mucho 50 archivos y no pasa de unos MB;
 *   - el progreso va en un panel que no se cierra solo: «Paso 2 de 6»;
 *   - si una tanda falla por la red, se reintenta sola; si falla del todo,
 *     se puede reintentar desde ahí sin repetir lo que ya entró (el servidor
 *     reconoce lo recibido de esta misma carga: CargaPorTandas.php);
 *   - mientras sube, cerrar la pestaña pide confirmación.
 *
 * Se engancha a los formularios con data-tandas.
 */

const REINTENTOS = 3;

const mb = (bytes) => (bytes / 1048576).toFixed(bytes < 10485760 ? 1 : 0).replace('.', ',') + ' MB';

/** Índices de cada tanda: como mucho porTanda archivos y, si se puede, no más de maxBytes. */
function partir(archivos, porTanda, maxBytes) {
    const tandas = [];
    let actual = [];
    let peso = 0;

    archivos.forEach((archivo, indice) => {
        const llena = actual.length >= porTanda || (actual.length > 0 && peso + archivo.size > maxBytes);

        if (llena) {
            tandas.push(actual);
            actual = [];
            peso = 0;
        }

        actual.push(indice);
        peso += archivo.size;
    });

    if (actual.length > 0) {
        tandas.push(actual);
    }

    return tandas;
}

/** Un POST con barra de progreso. XMLHttpRequest y no fetch: fetch no informa lo subido. */
function enviar(url, datos, alSubir) {
    return new Promise((resolver) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', (evento) => evento.lengthComputable && alSubir(evento.loaded));

        xhr.addEventListener('load', () => {
            let cuerpo = null;

            try {
                cuerpo = JSON.parse(xhr.responseText);
            } catch {
                // Una página de error en vez de JSON: el estado basta.
            }

            resolver({ estado: xhr.status, cuerpo });
        });
        xhr.addEventListener('error', () => resolver({ estado: 0, cuerpo: null }));
        xhr.addEventListener('timeout', () => resolver({ estado: 0, cuerpo: null }));
        xhr.send(datos);
    });
}

const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

/** El panel de progreso: fijo abajo a la derecha, no se cierra solo. */
function crearPanel() {
    const panel = document.createElement('div');
    panel.setAttribute('role', 'status');
    panel.setAttribute('aria-live', 'polite');
    panel.className = 'fixed bottom-4 right-4 z-50 w-80 max-w-[calc(100vw-2rem)] rounded-lg bg-[var(--card)] p-4 text-sm text-[var(--text)] shadow-lg ring-1 ring-[var(--border)]';
    panel.innerHTML = `
        <p data-titulo class="font-semibold">Cargando archivos</p>
        <p data-paso class="mt-1 text-[var(--muted)]"></p>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-[var(--card-soft)]">
            <div data-barra class="h-full w-0 rounded-full bg-[var(--primary)] transition-[width] duration-300"></div>
        </div>
        <p data-nota class="mt-2 text-xs text-[var(--muted)]">No cierres esta pestaña hasta que termine.</p>
        <div data-acciones class="mt-3 hidden gap-2">
            <button type="button" data-reintentar class="liquid-button-primary rounded-md px-3 py-1.5 text-xs font-medium">Reintentar</button>
            <button type="button" data-cerrar class="rounded-md px-3 py-1.5 text-xs text-[var(--muted)] hover:text-[var(--text)]">Cerrar</button>
        </div>`;
    document.body.appendChild(panel);

    const $ = (selector) => panel.querySelector(selector);

    return {
        paso: (texto) => { $('[data-paso]').textContent = texto; },
        avance: (fraccion) => { $('[data-barra]').style.width = Math.min(100, Math.round(fraccion * 100)) + '%'; },
        error: (titulo, texto, alReintentar) => {
            $('[data-titulo]').textContent = titulo;
            $('[data-paso]').textContent = texto;
            $('[data-nota]').classList.add('hidden');
            $('[data-barra]').classList.replace('bg-[var(--primary)]', 'bg-[var(--danger)]');
            $('[data-acciones]').classList.replace('hidden', 'flex');
            $('[data-reintentar]').onclick = () => {
                $('[data-titulo]').textContent = 'Cargando archivos';
                $('[data-nota]').classList.remove('hidden');
                $('[data-barra]').classList.replace('bg-[var(--danger)]', 'bg-[var(--primary)]');
                $('[data-acciones]').classList.replace('flex', 'hidden');
                alReintentar();
            };
            $('[data-cerrar]').onclick = () => panel.remove();
        },
    };
}

/** El primer error de validación, legible. */
function mensajeDe(respuesta) {
    const errores = respuesta.cuerpo?.errors;

    if (errores) {
        return Object.values(errores).flat()[0];
    }

    return {
        419: 'La página expiró. Recárgala y vuelve a elegir los archivos que faltan.',
        429: 'Demasiados envíos seguidos. Espera un minuto y toca «Reintentar».',
        404: 'El enlace ya no está disponible.',
        403: 'No tienes permiso para cargar aquí.',
    }[respuesta.estado] ?? 'Se perdió la conexión o el servidor no respondió.';
}

function iniciar(formulario) {
    const porTanda = Number(formulario.dataset.porTanda || 50);
    const maxBytes = Number(formulario.dataset.mbPorTanda || 150) * 1048576;

    formulario.addEventListener('submit', (evento) => {
        const entrada = formulario.querySelector('input[type="file"][name="archivo[]"]');
        const archivos = Array.from(entrada?.files ?? []);

        // Hasta una tanda, el formulario de siempre: nada cambia.
        if (archivos.length <= porTanda || formulario.dataset.cargando) {
            return;
        }

        evento.preventDefault();
        formulario.dataset.cargando = '1';

        // Lo común a todas las tandas: los demás campos del formulario.
        const comun = new FormData(formulario);
        const nombres = comun.getAll('nombres[]');
        const tomadas = comun.getAll('tomadas[]');
        ['archivo[]', 'nombres[]', 'tomadas[]'].forEach((campo) => comun.delete(campo));

        const tandas = partir(archivos, porTanda, maxBytes);
        const lote = crypto.randomUUID();
        const pesoTotal = archivos.reduce((suma, a) => suma + a.size, 0);
        const panel = crearPanel();

        let subidoAntes = 0;
        let siguiente = 0;

        const avisarAlSalir = (e) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', avisarAlSalir);

        const terminar = () => {
            window.removeEventListener('beforeunload', avisarAlSalir);
            delete formulario.dataset.cargando;
        };

        const seguir = async () => {
            for (; siguiente < tandas.length; siguiente++) {
                const indices = tandas[siguiente];
                const pesoTanda = indices.reduce((suma, i) => suma + archivos[i].size, 0);

                panel.paso(`Paso ${siguiente + 1} de ${tandas.length} · ${indices.length} archivos · ${mb(pesoTanda)}`);

                const datos = new FormData();
                comun.forEach((valor, campo) => datos.append(campo, valor));
                indices.forEach((i) => {
                    datos.append('archivo[]', archivos[i]);

                    // Las listas paralelas viajan con su archivo, en el mismo orden.
                    if (nombres.length === archivos.length) {
                        datos.append('nombres[]', nombres[i]);
                    }

                    if (tomadas.length === archivos.length) {
                        datos.append('tomadas[]', tomadas[i]);
                    }
                });
                datos.append('lote', lote);
                datos.append('lote_parte', String(siguiente + 1));
                datos.append('lote_partes', String(tandas.length));
                datos.append('lote_archivos', String(archivos.length));

                let respuesta = null;

                for (let intento = 1; intento <= REINTENTOS; intento++) {
                    respuesta = await enviar(formulario.action, datos, (subido) => panel.avance((subidoAntes + subido) / pesoTotal));

                    // Solo se reintenta lo que puede arreglarse esperando: la red o el servidor.
                    const pasajero = respuesta.estado === 0 || respuesta.estado >= 500;

                    if (!pasajero || intento === REINTENTOS) {
                        break;
                    }

                    panel.paso(`Paso ${siguiente + 1} de ${tandas.length} · reintentando (${intento} de ${REINTENTOS - 1})…`);
                    await esperar(2000 * intento);
                }

                if (respuesta.estado < 200 || respuesta.estado >= 300) {
                    const entraron = tandas.slice(0, siguiente).reduce((suma, t) => suma + t.length, 0);

                    panel.error(
                        entraron > 0 ? `Se cargaron ${entraron} de ${archivos.length}` : 'No se pudo cargar',
                        mensajeDe(respuesta),
                        seguir,
                    );
                    window.mostrarToast?.('error', mensajeDe(respuesta));
                    window.removeEventListener('beforeunload', avisarAlSalir);

                    return;
                }

                subidoAntes += pesoTanda;
                panel.avance(subidoAntes / pesoTotal);

                if (respuesta.cuerpo?.redirect) {
                    terminar();
                    window.location.assign(respuesta.cuerpo.redirect);

                    return;
                }
            }

            terminar();
        };

        seguir();
    });
}

document.querySelectorAll('form[data-tandas]').forEach(iniciar);
