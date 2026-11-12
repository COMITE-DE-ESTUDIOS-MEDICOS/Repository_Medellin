# Spec: Anular una marca o cambiarle el tipo

| | |
|---|---|
| Historia | DOC-18 |
| Tipo | Historia de usuario |
| Estado | **Aprobada · Construida** |
| Versión | 1.0 |
| Fuentes | Caso de una persona del N7 (21:00–04:30) con el turno invertido del 27 al 29-10-2026; pedido de Edwar del 30-10-2026 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 30-10-2026 («Anular y cambiar tipo», en lugar de borrar) |

## Problema

Una marca de más (un reintento justo después de salir) invierte el turno: desde ahí, al
llegar el sistema le marca «salida» y al irse «entrada». «Corregir hora» solo cambia la hora,
así que no había cómo enderezarlo, y el pedido fue poder «eliminar» marcas.

## Comportamiento

Coordinación y Administración (no Edición), en «Corregir hora» de una marca vigente, tienen
un segundo recuadro con motivo obligatorio y dos botones:

- **Cambiar a entrada / salida:** la marca queda anulada y la reemplaza otra del tipo
  contrario a la misma hora (como una corrección). No se exige coherencia con las vecinas:
  se usa justamente cuando las vecinas también están mal. Solo se rechaza una hora futura.
- **Anular marca** (pide confirmación): la marca queda anulada sin reemplazo. Deja de contar
  para el turno, las novedades y el reporte.

En los dos casos nada se borra (constitución, RN-17): la marca queda tachada, con quién,
cuándo y el motivo; dice «Anulada» o «Corregida». Las novedades pendientes que dependían de
ella pasan a «Corregida». Todo queda en la auditoría.

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Coordinación cambia el tipo: la original queda anulada y la nueva es del otro tipo, a la misma hora | `test_doc18_ca01_coordinacion_cambia_el_tipo_y_la_original_queda_anulada` |
| CA-02 | Anular no borra la marca, la saca de las vigentes y cierra sus novedades | `test_doc18_ca02_anular_quita_la_marca_sin_borrarla_y_cierra_sus_novedades` |
| CA-03 | Edición no anula ni cambia el tipo | `test_doc18_ca03_edicion_no_anula_ni_cambia_el_tipo` |
| CA-04 | Una marca anulada no se vuelve a anular | `test_doc18_ca04_una_marca_anulada_no_se_vuelve_a_anular` |

Verificado en el navegador (local, 30-10-2026) con una persona SIM con el turno invertido:
anular el reintento y voltear tres marcas dejó la secuencia entrada/salida correcta; Edición
no ve los botones; en celular cabe sin scroll horizontal.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-10-30 | Aprobada y construida. Pruebas en `CorreccionYTurnoCerradoTest` |
