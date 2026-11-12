# Spec: Registrar solo en turno · avisos de supervisión

| | |
|---|---|
| Historia | DOC-13 |
| Tipo | Historia de usuario |
| Estado | **Aprobada · Construida** |
| Versión | 1.0 |
| Fuentes | Pedido y respuestas de Edwar del 26-10-2026 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 26-10-2026 (respuestas a las tres preguntas) |

## Objetivo

Que solo pueda registrar algo en los enlaces por cédula quien está trabajando en ese
momento, y que lo importante de una supervisión llegue de inmediato a los líderes.

## Problema

- Hoy se puede reportar una derivación, una supervisión o enviar evidencias sin haber
  marcado entrada: el registro no prueba que la persona estaba en turno.
- Una supervisión con una incidencia crítica queda en la lista sin que nadie se entere.

## Reglas de negocio

- **RN-43. Registrar solo en turno.** Para registrar una supervisión, una derivación o un
  envío de evidencias por cédula, la persona tiene que estar **en turno**: su última
  marcación vigente es una entrada (sin salida). Una cédula nueva no está en turno.
- **RN-44. Se supervisa a quien está en turno.** Los dinamizadores de una supervisión
  también tienen que estar en turno.
- **RN-45. Avisos de supervisión.** Una supervisión con incidencia Relevante o Crítica,
  con casos escalados, o con calificación global de 1 o 2, avisa por la campana a Edición
  y Coordinación de la dependencia, en la pestaña «Supervisión».

## Criterios de aceptación

**CA-01. Supervisión sin turno.** Quien supervisa y no está en turno ve, al escribir su
cédula, «Para registrar la supervisión tienes que estar en turno: marca tu entrada
primero.», y el servidor rechaza el envío con el mismo mensaje.

**CA-02. Dinamizador sin turno.** Un dinamizador registrado que no está en turno se
rechaza con «Esta persona no está en turno: no ha marcado entrada.».

**CA-03. Derivación sin turno.** Quien no está en turno ve «Para reportar una derivación
tienes que estar en turno: marca tu entrada primero.» y no se registra nada.

**CA-04. Evidencias sin turno.** En un enlace de carga por cédula, quien no está en turno
(o es una cédula nueva) ve «Para enviar evidencias tienes que estar en turno: marca tu
entrada primero.» y no se recibe nada. Los enlaces de carga sin cédula (remitentes
externos) no cambian.

**CA-05. En turno sí registra.** Con una entrada marcada y sin salida, los tres
formularios funcionan como antes.

**CA-06. Aviso de lo importante.** Una supervisión con incidencia Relevante o Crítica,
casos escalados o calificación global ≤ 2 crea un aviso para cada usuario activo de
Edición y Coordinación: «Supervisión #N en <punto>: <motivos>.», con enlace al detalle.
Una supervisión sin nada de eso no avisa.

**CA-07. Pestaña propia.** En Notificaciones, los avisos de supervisión van en la pestaña
«Supervisión», separados de turnos y derivaciones.

## Casos límite

- **Turno cerrado por el sistema** (pasó la duración máxima sin salida): ya no está en turno.
- **Marcó salida hace un momento:** ya no está en turno; si necesita registrar, marca otra
  entrada (si su componente permite más de un turno al día).

## Fuera de alcance

- Exigir que el turno sea del mismo nodo o punto del registro.
- Resumen diario de supervisiones (opción no elegida).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-10-26 | Aprobada con las respuestas de Edwar |
| 1.0 | 2026-10-26 | Construida. Pruebas: `test_doc13_*` en `SupervisionTerritorialTest`, `DerivacionesTest` y `PublicoEvidenciasTest`. Efecto a tener en cuenta: el autorregistro por enlaces de evidencias queda sin uso (una cédula nueva no está en turno); se registra marcando entrada |
