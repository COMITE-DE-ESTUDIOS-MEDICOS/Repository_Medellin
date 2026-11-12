/**
 * El formulario de supervisión territorial (/s/{token}, DOC-11). Los pasos los
 * lleva pasos.js y la cédula de quien supervisa cedula.js; esto suma:
 *
 * - «Otro» en el punto crítico abre un campo para escribirlo.
 * - «¿Casos escalados?» en Sí exige «¿Cuál?».
 * - Las cédulas de los dinamizadores se comprueban al salir del campo: solo
 *   vuelve el saludo recortado, y una no registrada no deja pasar del paso.
 * - Al enviar, la ubicación del celular va en campos ocultos (sin señal, va
 *   vacía y el registro queda «sin ubicación»).
 */
import { datosDeUbicacion, prepararUbicacion } from './foto-georreferencial';

function iniciar(form) {
    prepararUbicacion();

    // Punto crítico: del catálogo, u «Otro» escrito.
    const punto = form.querySelector('[data-punto]');
    const otro = form.querySelector('[data-punto-otro]');
    const alCambiarPunto = () => {
        const esOtro = punto.selectedOptions[0]?.hasAttribute('data-otro');
        otro.classList.toggle('hidden', !esOtro);
        otro.required = esOtro;
        punto.required = !esOtro;

        if (!esOtro) {
            otro.value = '';
        }
    };
    punto?.addEventListener('change', alCambiarPunto);
    if (punto && otro) {
        alCambiarPunto();
    }

    // Casos escalados: «¿Cuál?» solo con Sí.
    const cual = form.querySelector('[data-escalados-cual]');
    const alCambiarEscalados = () => {
        const si = form.querySelector('[data-escalados]:checked')?.value === 'si';
        cual?.classList.toggle('hidden', !si);
        cual?.querySelector('textarea')?.toggleAttribute('required', si);
    };
    form.querySelectorAll('[data-escalados]').forEach((radio) => radio.addEventListener('change', alCambiarEscalados));
    alCambiarEscalados();

    // Dinamizadores: comprobar la cédula sin revelar nada más que el saludo.
    const paso = [...form.querySelectorAll('[data-paso]')].findIndex((p) => p.querySelector('[data-dinamizador]'));
    const malos = new Set();
    const bloquear = () => form.dispatchEvent(new CustomEvent('pasos:bloquear', {
        detail: { paso, motivo: malos.size ? 'Revisa las cédulas de los dinamizadores: tienen que estar registradas y en turno.' : '' },
    }));

    form.querySelectorAll('[data-dinamizador]').forEach((caja) => {
        const entrada = caja.querySelector('input');
        const saludo = caja.querySelector('[data-dinamizador-saludo]');
        const mostrar = (texto, bien) => {
            saludo.textContent = texto;
            saludo.classList.toggle('hidden', texto === '');
            saludo.classList.toggle('text-[var(--success)]', bien);
            saludo.classList.toggle('text-[var(--warning)]', !bien);
        };

        entrada.addEventListener('change', async () => {
            const cedula = entrada.value.trim();
            malos.delete(entrada.name);
            mostrar('', true);

            if (cedula !== '') {
                try {
                    const respuesta = await fetch(form.dataset.consultaDinamizador, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ cedula }),
                    });
                    const datos = respuesta.ok ? await respuesta.json() : null;

                    if (datos?.existe) {
                        mostrar(`✓ ${datos.saludo}`, true);
                    } else if (datos) {
                        malos.add(entrada.name);
                        mostrar(datos.aviso || 'Esta cédula no está registrada como persona de campo.', false);
                    }
                } catch {
                    // Sin red: lo decide el servidor al enviar.
                }
            }

            bloquear();
        });
    });

    // Ubicación al enviar.
    form.addEventListener('submit', () => {
        const ubicacion = datosDeUbicacion();
        const poner = (nombre, valor) => {
            form.querySelector(`[name="${nombre}"]`).value = valor ?? '';
        };

        poner('lat', ubicacion?.lat);
        poner('lng', ubicacion?.lng);
        poner('precision_m', ubicacion?.precision);
        poner('municipio', ubicacion?.municipio);
    }, true);
}

document.querySelectorAll('form[data-supervision]').forEach(iniciar);
