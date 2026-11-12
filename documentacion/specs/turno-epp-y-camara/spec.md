# Spec: EPP al marcar entrada y foto solo con la cámara

| | |
|---|---|
| Historia | DOC-12 |
| Tipo | Historia de usuario |
| Estado | **Aprobada · Construida** |
| Versión | 1.0 |
| Fuentes | Pedido de Edwar del 26-10-2026 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 26-10-2026 («listo a programar», con las recomendaciones) |
| Valida en la Review | [completar: Coordinación de Calle] |

## Objetivo

Que al marcar entrada la persona de campo diga si recibió los elementos de protección
personal (EPP), y que la foto del turno sea siempre tomada en el momento con la cámara.

## Problema

- No queda constancia de si a cada persona le entregaron los EPP al iniciar el turno.
- La foto se puede subir desde la galería: puede ser vieja o de otro lugar, y sin el
  sello de hora y lugar.

## Usuarios y roles involucrados

- **Persona de campo:** responde la pregunta de EPP y toma la foto (CA-01 a CA-04).
- **Edición en adelante:** ve la respuesta en las marcaciones, en el reporte y, si fue
  «No», en Novedades (CA-05, CA-06).

## Historia de usuario

**DOC-12.** Como Coordinación, quiero que cada entrada diga si la persona recibió los EPP
y traiga una foto tomada en el sitio, para tener constancia y que la foto sirva como
prueba.

## Reglas de negocio que aplica

RN-13, RN-16, RN-17, RN-23. Reglas nuevas propuestas:

- **RN-41. EPP al entrar.** Toda entrada por el enlace responde «¿Recibiste los elementos
  de protección personal (EPP)?» con Sí o No. Un «No» crea la novedad «Sin EPP»,
  pendiente, que se valida como las demás.
- **RN-42. Foto de turno solo con la cámara.** En el enlace de turnos la foto se toma con
  la cámara en el momento; no se puede elegir de la galería.

## Criterios de aceptación

**CA-01. La pregunta aparece al entrar.**
- **Dado** que a la persona le toca **entrada**,
- **entonces** el enlace muestra «¿Recibiste los elementos de protección personal (EPP)?»
  con dos botones, «Sí» y «No», y no deja registrar sin responder:
  «Responde si recibiste los EPP.».

**CA-02. No aparece al salir.**
- **Dado** que le toca **salida**, **entonces** la pregunta no aparece.

**CA-03. Se guarda con la entrada.**
- **Dado** una entrada registrada,
- **entonces** la respuesta queda guardada con esa marcación y se ve en Marcaciones, en la
  ficha de la persona y en el reporte de turnos (columna «EPP»).

**CA-04. Solo cámara.**
- **Dado** el enlace de turnos,
- **entonces** solo aparece «Tomar foto»; «Elegir archivo» no está. La foto lleva el
  sello de hora y lugar.

**CA-05. «No» crea una novedad.**
- **Dado** una entrada con «No»,
- **entonces** nace la novedad «Sin EPP», pendiente, que llega por la campana a quienes
  aprueban novedades (RN-23) y se aprueba o rechaza como las demás.

**CA-06. Corrección manual.**
- **Dado** que un líder agrega una entrada que faltó,
- **entonces** la respuesta de EPP queda «Sin dato» (no se inventa).

## Casos límite

- **Celular sin permiso de cámara:** «Tomar foto» abre la cámara del propio celular, que
  pide el permiso a su manera. Si el componente exige foto, no se puede marcar sin ella.
- **Computador sin cámara:** si el componente exige foto, no se puede marcar desde ahí.
- **Las marcaciones viejas** quedan «Sin dato» en EPP.
- **Alcance técnico:** quitar la galería se hace en la pantalla; alguien con conocimientos
  técnicos podría enviar un archivo igual. La defensa es el sello y la hora del servidor
  (RN-16).

## Datos que crea y consume

| Dato | Acción | Área dueña |
|---|---|---|
| Respuesta de EPP de la entrada | Crea | Turnos |
| Novedad «Sin EPP» | Crea | Turnos |

## Fuera de alcance

- Detallar qué elemento falta (casco, guantes…). Ver C-01.
- Quitar la galería en los enlaces de derivaciones y de evidencias (siguen igual).

## Decisiones (antes preguntas abiertas)

| # | Pregunta | Recomendación aprobada | Dueño |
|---|---|---|---|
| PA-01 | ¿Un «No» debe crear novedad, o solo quedar registrado? | Novedad «Sin EPP»: así alguien lo atiende | Edwar |
| PA-02 | ¿La pregunta va solo al entrar? | Sí, solo en la entrada | Edwar |

## Candidatos para la siguiente versión

| # | Candidato |
|---|---|
| C-01 | Lista de EPP por cargo y marcar cuáles recibió |

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-10-26 | Borrador inicial |
| 1.0 | 2026-10-26 | Aprobada y construida. Pruebas: `tests/Feature/Turnos/EppYCamaraTest.php` (`test_ca01` a `test_ca06`) |
