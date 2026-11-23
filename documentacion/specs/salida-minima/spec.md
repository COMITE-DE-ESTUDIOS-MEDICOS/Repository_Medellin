# Spec: Salida mínima (reintentos que no son salida)

| | |
|---|---|
| Historia | DOC-17 |
| Tipo | Corrección / historia de usuario |
| Estado | **Aprobada · Construida** |
| Versión | 1.1 |
| Fuentes | Reporte del equipo de campo del 29-10-2026 (caso de una entrada a las 12:31 con salida a las 12:37) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 29-10-2026 («30 minutos… que sea editable en nodos») |

## Problema

Con datos móviles lentos, la entrada llega al servidor pero la respuesta no alcanza a llegar
al teléfono. La persona cree que falló y vuelve a intentarlo; como ya está en turno, el
botón dice «Registrar salida», nadie lo lee y queda una salida falsa minutos después de
entrar. El doble toque (2 minutos) no lo cubre: tomar otra foto y esperar la ubicación tarda
más.

## Comportamiento

- Regla de componente `salida_minima_min` (30 de fábrica), editable en Componentes y nodos
  → «Salida mínima (minutos)». 0 la apaga (queda solo el doble toque).
- Una marca antes de ese tiempo después de la última marca (entrada **o salida**, v1.1) no
  crea nada: responde «Ya estaba registrada tu entrada/salida» con su hora (RN-47).
- Al poner la cédula en ese tiempo, el botón dice «Tu marca ya quedó registrada ✓» y
  está deshabilitado.
- Una salida real antes de ese tiempo la agrega Coordinación (corrección manual).

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Reintentar a los 29 min de la entrada no crea salida y la cédula responde `reciente` | `test_ca01_reintentar_minutos_despues_de_la_entrada_no_crea_una_salida` |
| CA-02 | A los 30 min la marca es salida | `test_ca02_pasado_el_margen_la_marca_es_salida` |
| CA-03 | El margen es regla del componente | `test_ca03_el_margen_es_regla_del_componente` |
| CA-04 | Un reintento después de una **salida** tampoco abre una entrada (turno de noche) | `test_doc17_ca04_un_reintento_despues_de_la_salida_no_abre_una_entrada` |

## Candidatos para la siguiente versión

| C | Qué |
|---|---|
| C-01 | Enviar la marca por `fetch` con reintento automático, para que la persona vea la confirmación aunque la red falle a medias |

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-10-29 | Aprobada y construida. Pruebas en `PublicoMarcarTest` |
| 1.1 | 2026-10-30 | También después de una salida (aprobó Edwar): un reintento a las 04:43 tras salir a las 04:41 abría una entrada e invertía el turno N7. Prueba en `CorreccionYTurnoCerradoTest` |
