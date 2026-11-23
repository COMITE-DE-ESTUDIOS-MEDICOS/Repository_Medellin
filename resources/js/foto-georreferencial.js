/**
 * Estampa hora y lugar sobre una foto tomada con la cámara: un recuadro con
 * la hora y, debajo, la fecha, la dirección a nivel de calle y las
 * coordenadas, como las apps de «foto con marca de tiempo». Solo se aplica a
 * fotos de cámara (no a lo elegido de galería/archivo): es evidencia de que
 * se tomó ahí y entonces, no un sello decorativo.
 *
 * Todo con Canvas + Geolocation nativos del navegador. Si el usuario niega
 * la ubicación, la foto se estampa igual, solo sin dirección.
 */

const AMARILLO = '#f5b800';

function cargarImagen(src) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null); // una foto que no se lee se envía tal cual
        img.src = src;
    });
}

/**
 * Última posición conocida. Se pide al entrar al módulo y otra vez en cada
 * toque de «Tomar foto», no al estampar: en iPhone el aviso de permiso salía
 * justo al volver de la cámara y la espera se agotaba mientras la persona
 * tocaba «Permitir». Volver a pedirla en cada toque es también lo que deja
 * recuperarse sin recargar a quien la negó y luego la activó en Ajustes.
 */
let ultima = null;
let pedido = null;

// Si la persona ya contestó al permiso, con un sí o con un no. Mientras no
// haya contestado, abrir la cámara taparía el aviso.
let respondida = false;
let avisar = () => {};

// Sin esto la foto solo decía «Ubicación no disponible» y nadie sabía por qué.
// Dónde se devuelve el permiso cambia según el teléfono, y es lo único útil
// que se puede decir: ningún navegador deja volver a preguntar una vez negado.
const esIphone = /iPhone|iPad|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPad que se anuncia como Mac
const esAndroid = /Android/.test(navigator.userAgent);

// En iPhone todos los navegadores usan el motor de Safari, pero cada app
// tiene su propio permiso de ubicación ante el sistema. Si Opera o Chrome no
// lo tienen, la página no puede ni preguntar: se arregla en Ajustes del iPhone.
const esSafari = esIphone && !/CriOS|FxiOS|EdgiOS|OPiOS|OPT\/|OPX\/|GSA\//.test(navigator.userAgent);

const COMO_PERMITIR = esSafari
    ? 'toca «aA» en la barra de direcciones → Configuración del sitio web → Ubicación → Permitir. Revisa también Ajustes → Privacidad → Localización → Sitios web de Safari.'
    : esIphone
        ? 'abre Ajustes del iPhone → busca este navegador (Opera, Chrome…) → Ubicación → «Al usar la app». Revisa también Ajustes → Privacidad → Localización, que esté activada.'
    : esAndroid
        ? 'toca el candado junto a la dirección de la página → Permisos → Ubicación → Permitir. Si no aparece, revisa Ajustes del teléfono → Aplicaciones → tu navegador → Permisos → Ubicación.'
        : 'haz clic en el ícono junto a la dirección de la página y permite la ubicación.';

/**
 * DOC-15: cómo encender la ubicación precisa en cada teléfono. Sin ella, iOS
 * (y a veces Android) entrega una ubicación aproximada, con kilómetros de error.
 */
export const COMO_ACTIVAR_PRECISA = esSafari
    ? 'Ajustes → Privacidad y seguridad → Localización → Sitios web de Safari → «Al usar la app» y activa «Ubicación exacta».'
    : esIphone
        ? 'Ajustes → busca este navegador (Chrome, Opera…) → Ubicación → «Al usar la app» y activa «Ubicación exacta».'
    : esAndroid
        ? 'Ajustes → Ubicación → activa «Usar ubicación precisa» (o «Precisión de la ubicación de Google»).'
        : 'Activa el GPS o conéctate a una red Wi-Fi.';

const MOTIVOS = {
    1: 'No hay permiso de ubicación: ' + COMO_PERMITIR + ' Luego vuelve a tomar la foto.',
    2: 'El teléfono no está entregando la ubicación. Revisa que la ubicación (GPS) esté activada en los ajustes del teléfono.',
    3: 'La ubicación está tardando en llegar. Al aire libre la señal mejora.',
};

let ciudad = null;
let direccion = null;
let ciudadPedida = null;

/**
 * El municipio, que en OpenStreetMap no tiene una sola etiqueta. En Colombia
 * 'county' es el municipio y 'city' puede traer la vereda: en el norte del
 * Valle de Aburrá, 'city' devolvía «Platanito Parte Baja» donde el municipio
 * es Barbosa. Y lo que llega viene con los nombres del DANE pegados
 * —«Perímetro Urbano Medellín», «Bogotá ciudad»—, que en una foto sobran.
 */
export function nombreMunicipio(address = {}) {
    const municipio = address.county || address.city || address.town
        || address.village || address.municipality;

    return municipio
        ? municipio
            .replace(/^(per[ií]metro|zona|[áa]rea)\s+urban[ao]\s+(de\s+)?/i, '')
            .replace(/\s+ciudad$/i, '')
            .trim() || null
        : null;
}

/** «Carrera 65» → «Cra. 65»: como se escriben las direcciones en Colombia. */
const ABREVIATURAS = [
    [/^carrera\b/i, 'Cra.'],
    [/^calle\b/i, 'Cl.'],
    [/^avenida\b/i, 'Av.'],
    [/^transversal\b/i, 'Tv.'],
    [/^diagonal\b/i, 'Dg.'],
    [/^circular\b/i, 'Cir.'],
];

/**
 * La dirección a nivel de calle: «Cra. 65 #97-121, Castilla, Medellín,
 * Antioquia». Lo que OpenStreetMap no sepa (el número, el barrio) se omite.
 */
export function direccionLegible(address = {}) {
    let via = address.road || address.pedestrian || address.footway || '';

    for (const [patron, corta] of ABREVIATURAS) {
        via = via.replace(patron, corta);
    }

    const calle = via ? via + (address.house_number ? ' #' + address.house_number : '') : '';
    const barrio = address.neighbourhood || address.suburb || address.quarter || address.hamlet;

    return [calle, barrio, nombreMunicipio(address), address.state].filter(Boolean).join(', ') || null;
}

/**
 * El lugar a partir de las coordenadas, contra Nominatim (el geocodificador
 * de OpenStreetMap): unas cifras no le dicen nada a quien después revisa la
 * foto, «Cra. 65 #97-121, Castilla, Medellín» sí. Si no hay red o tarda, la
 * foto sale igual con las coordenadas solas.
 */
async function resolverCiudad({ latitude, longitude }) {
    // zoom=18: nivel de calle (con número y barrio). 'ciudad' sigue siendo
    // municipio y departamento, que es lo que se guarda en la marcación.
    const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1&accept-language=es'
        + `&lat=${latitude}&lon=${longitude}`;

    try {
        const respuesta = await fetch(url, { signal: AbortSignal.timeout?.(8000) });
        const { address = {} } = await respuesta.json();

        ciudad = [nombreMunicipio(address), address.state].filter(Boolean).join(', ') || null;
        direccion = direccionLegible(address);
    } catch {
        ciudad = null; // sin red, sin permiso de salida o demasiado lento
        direccion = null;
    }
}

/** La ciudad solo se espera si ya hay coordenadas: nunca retrasa la foto por sí sola. */
function esperarCiudad() {
    if (ciudad || !ciudadPedida) {
        return Promise.resolve(ciudad);
    }

    return Promise.race([ciudadPedida, new Promise((r) => setTimeout(r, 4000))]).then(() => ciudad);
}

function pedir() {
    if (!window.isSecureContext || !navigator.geolocation) {
        avisar('Este navegador no permite leer la ubicación en esta página.');
        return null;
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                ultima = pos.coords;
                respondida = true;
                ciudadPedida ??= resolverCiudad(pos.coords);
                avisar('');
                resolve();
            },
            (error) => {
                respondida = true;
                avisar(MOTIVOS[error.code] ?? MOTIVOS[2]);
                resolve();
            },
            { enableHighAccuracy: true, maximumAge: 60000, timeout: 20000 },
        );
    }).finally(() => {
        pedido = null;
    });
}

/** @param {(texto: string) => void} [alAvisar] recibe el motivo cuando no hay ubicación, y '' cuando la hay. */
export function prepararUbicacion(alAvisar) {
    if (alAvisar) {
        avisar = alAvisar;
    }

    pedido ??= pedir();
}

/** Si ya se sabe qué pasa con la ubicación: hay coordenadas, o la persona contestó que no. */
export function ubicacionResuelta() {
    return ultima !== null || respondida;
}

export function hayUbicacion() {
    return ultima !== null;
}

/**
 * Las coordenadas para guardarlas en columnas (las marcaciones de turno se
 * filtran por lugar; dibujadas en la foto no se pueden consultar).
 * null si no hay ubicación.
 */
export function datosDeUbicacion() {
    return ultima
        ? { lat: ultima.latitude, lng: ultima.longitude, precision: ultima.accuracy, municipio: ciudad }
        : null;
}

/** Pide la ubicación y espera la respuesta (o el tope de 20 s del propio navegador). */
export function pedirUbicacionAhora() {
    prepararUbicacion();

    return pedido ?? Promise.resolve();
}

function ubicacion() {
    if (ultima) {
        return Promise.resolve(ultima);
    }

    prepararUbicacion();

    if (!pedido) {
        return Promise.resolve(null);
    }

    return Promise.race([pedido, new Promise((r) => setTimeout(r, 20000))]).then(() => ultima);
}

/** Cuánto hay que encoger una imagen para que su lado largo no pase del tope (1 = déjala como está). */
export function escalaPara(ancho, alto, ladoMaximo) {
    const lado = Math.max(ancho, alto);

    return ladoMaximo > 0 && lado > ladoMaximo ? ladoMaximo / lado : 1;
}

function aLienzo(imagen, escala) {
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(imagen.width * escala);
    lienzo.height = Math.round(imagen.height * escala);
    lienzo.getContext('2d').drawImage(imagen, 0, 0, lienzo.width, lienzo.height);

    return lienzo;
}

/**
 * Del lienzo a un archivo. Todo sale JPEG salvo el PNG, que se queda PNG para
 * no tragarse la transparencia: toBlob devuelve PNG cuando no sabe hacer el
 * formato pedido (WebP en Safari), y una foto en PNG pesa varias veces más.
 */
async function aArchivo(lienzo, original, lastModified = original.lastModified) {
    const tipo = original.type === 'image/png' ? 'image/png' : 'image/jpeg';
    const blob = await new Promise((resolve) => lienzo.toBlob(resolve, tipo, 0.92));

    if (!blob) {
        return original;
    }

    // El servidor guarda la extensión que dice el nombre: si el contenido pasó
    // a JPEG, el nombre tiene que decirlo también.
    const nombre = tipo === original.type ? original.name : original.name.replace(/\.[^.]+$/, '') + '.jpg';

    return new File([blob], nombre, { type: blob.type, lastModified });
}

/**
 * Reduce una imagen de galería al tope de resolución. Lo que ya cabe se
 * devuelve intacto: recomprimir un JPEG que no hace falta tocar solo lo
 * degrada.
 */
export async function reducirImagen(archivo, ladoMaximo) {
    if (!ladoMaximo || !archivo.type.startsWith('image/')) {
        return archivo;
    }

    const imagen = await cargarImagen(URL.createObjectURL(archivo));
    const escala = imagen ? escalaPara(imagen.width, imagen.height, ladoMaximo) : 1;

    return escala === 1 ? archivo : aArchivo(aLienzo(imagen, escala), archivo);
}

/** Corta un texto en líneas que quepan en el ancho, como mucho 'maximo' líneas. */
function enLineas(ctx, texto, ancho, maximo) {
    const palabras = texto.split(' ');
    const lineas = [];
    let actual = '';

    palabras.forEach((palabra) => {
        const prueba = actual ? actual + ' ' + palabra : palabra;

        if (ctx.measureText(prueba).width > ancho && actual) {
            lineas.push(actual);
            actual = palabra;
        } else {
            actual = prueba;
        }
    });

    if (actual) {
        lineas.push(actual);
    }

    if (lineas.length > maximo) {
        lineas.length = maximo;
        lineas[maximo - 1] = lineas[maximo - 1].replace(/,?\s*\S*$/, '') + '…';
    }

    return lineas;
}

function rectanguloRedondeado(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

/**
 * @param {File} archivo
 * @param {number} ladoMaximo  tope del lado largo (0 = sin tope)
 * @param {string} etiqueta    lo que dice debajo de la hora: «Evidencia», «Turno»…
 */
export async function estamparFoto(archivo, ladoMaximo = 0, etiqueta = 'Evidencia') {
    const [foto, coords] = await Promise.all([
        cargarImagen(URL.createObjectURL(archivo)),
        ubicacion(),
    ]);

    if (!foto) {
        return archivo;
    }

    // Se dibuja ya a la escala del tope, y el sello se calcula sobre el
    // lienzo reducido: así conserva siempre las mismas proporciones.
    const lienzo = aLienzo(foto, escalaPara(foto.width, foto.height, ladoMaximo));
    const ctx = lienzo.getContext('2d');
    const { width: ancho, height: alto } = lienzo;

    // Todo se mide en «u», una centésima del lado corto: el sello se ve igual
    // en una foto vertical u horizontal, grande o pequeña.
    const u = Math.min(ancho, alto) / 100;
    const m = 3.5 * u;
    const fuente = (peso, tamano) => `${peso} ${Math.round(tamano)}px system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif`;

    // El mismo instante que se estampa viaja en lastModified, para que el
    // servidor guarde exactamente la fecha que se lee en la foto.
    const momento = new Date();
    // En 12 h, como en la aplicación: «5:09 p. m.».
    const hora = new Intl.DateTimeFormat('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true }).format(momento).replace(/\s+/g, ' ');
    const partesFecha = new Intl.DateTimeFormat('es-CO', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
        .formatToParts(momento)
        .reduce((p, { type, value }) => ({ ...p, [type]: value.replace('.', '') }), {});
    const fecha = `${partesFecha.weekday.charAt(0).toUpperCase()}${partesFecha.weekday.slice(1)}, ${partesFecha.day} de ${partesFecha.month} ${partesFecha.year}`;

    if (coords) {
        await esperarCiudad();
    }

    const lugar = coords ? (direccion || ciudad) : null;
    const cifras = coords ? `${coords.latitude.toFixed(6)}, ${coords.longitude.toFixed(6)}` : 'Ubicación no disponible';

    // Las líneas del pie, de arriba abajo, para saber cuánto miden antes de pintar.
    const anchoTexto = Math.min(ancho * 0.72, ancho - 2 * m - 6 * u);
    ctx.font = fuente(500, 3.4 * u);
    const lineasLugar = lugar ? enLineas(ctx, lugar, anchoTexto, 2) : [];
    const pie = [
        { texto: etiqueta, font: fuente(700, 4.6 * u), alto: 6 * u },
        { texto: fecha, font: fuente(500, 3.6 * u), alto: 5 * u },
        ...lineasLugar.map((texto) => ({ texto, font: fuente(500, 3.4 * u), alto: 4.6 * u })),
        { texto: cifras, font: fuente(400, 2.6 * u), alto: 4 * u },
    ];
    const altoPie = pie.reduce((suma, linea) => suma + linea.alto, 0);

    // El recuadro de la hora, encima del pie.
    const altoCaja = 14 * u;
    ctx.font = fuente(700, 10 * u);
    const anchoHora = ctx.measureText(hora).width;
    const radioCheck = 3.2 * u;
    const anchoCaja = 3 * u + anchoHora + 2.5 * u + radioCheck * 2 + 3 * u;

    const yPie = alto - m - altoPie;
    const yCaja = yPie - 2 * u - altoCaja;

    // Recuadro de la hora, con el check.
    rectanguloRedondeado(ctx, m, yCaja, anchoCaja, altoCaja, 1.6 * u);
    ctx.fillStyle = 'rgba(52, 26, 18, 0.92)';
    ctx.fill();

    ctx.fillStyle = '#fff';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.font = fuente(700, 10 * u);
    ctx.fillText(hora, m + 3 * u, yCaja + altoCaja / 2);

    const cx = m + 3 * u + anchoHora + 2.5 * u + radioCheck;
    const cy = yCaja + altoCaja / 2;
    ctx.beginPath();
    ctx.arc(cx, cy, radioCheck, 0, Math.PI * 2);
    ctx.fillStyle = AMARILLO;
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(cx - radioCheck * 0.42, cy + radioCheck * 0.02);
    ctx.lineTo(cx - radioCheck * 0.1, cy + radioCheck * 0.34);
    ctx.lineTo(cx + radioCheck * 0.45, cy - radioCheck * 0.3);
    ctx.strokeStyle = '#2a1a10';
    ctx.lineWidth = 0.75 * u;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.stroke();

    // El pie, con la barra amarilla a la izquierda.
    ctx.fillStyle = AMARILLO;
    ctx.fillRect(m, yPie + 0.6 * u, 0.7 * u, altoPie - 1.2 * u);

    // Sin fondo: el texto se lee por la sombra, sobre cualquier foto.
    ctx.shadowColor = 'rgba(0,0,0,0.8)';
    ctx.shadowBlur = 1.2 * u;
    ctx.fillStyle = '#fff';
    let y = yPie;
    pie.forEach((linea) => {
        ctx.font = linea.font;
        ctx.fillText(linea.texto, m + 2.6 * u, y + linea.alto / 2, anchoTexto);
        y += linea.alto;
    });

    ctx.shadowBlur = 0;

    return aArchivo(lienzo, archivo, momento.getTime());
}
