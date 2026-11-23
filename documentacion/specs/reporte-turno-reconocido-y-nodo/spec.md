# Spec: Reporte de turnos con el turno reconocido y sin ceros por nodo

| | |
|---|---|
| Historia | DOC-19 |
| Tipo | Historia de usuario |
| Estado | **Aprobada · Construida** |
| Versión | 1.0 |
| Fuentes | Reporte del 04-11-2026: «Días trabajados 0» para gente que sí trabajó; pedido de Edwar de que el reporte diga el turno (N1, N7…) de cada entrada |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 04-11-2026 («si», a las dos correcciones y al turno por horario) |

## Problema

1. El Excel se bajaba con los filtros de la URL, no con los de la pantalla: quien cambiaba
   las fechas sin pulsar «Filtrar» recibía el rango por defecto (del 1 del mes a hoy).
2. Con el filtro de Nodo, la pestaña «Resumen mensual» listaba a la gente por su nodo
   habitual pero contaba solo los turnos marcados en ese nodo: quien cubrió en otro nodo
   salía con «Días trabajados 0» en rojo.
3. El reporte no decía qué turno de la malla era cada entrada. Es lo que Nómina necesita
   para identificar el horario.
4. Al descargar, la pantalla se quedaba en «Cargando»: el overlay se muestra en cada clic
   que navega, y una descarga no navega, así que nadie lo quitaba.

## Comportamiento

- **El botón «Descargar reporte (Excel)» envía los filtros tal como están en pantalla**,
  sin tener que pulsar «Filtrar» antes. La pantalla no se queda cargando (ni en las demás
  descargas de Documenta: Excel de novedades, derivaciones, supervisiones, plantilla,
  documentos y carpetas).
- **Pestaña «Turnos»:** una columna nueva después de «Nodo»: **Turno** (el código de la
  malla que corresponde a la hora de entrada: N1, N7, T3…). El turno se reconoce con la misma regla que al marcar
  (`Programacion::reconocer`: el inicio más cercano dentro de la ventana del componente,
  y gana el habitual si cabe), pero **se calcula al armar el reporte**, no se lee de lo
  guardado: así las marcas manuales y las corregidas también lo traen. Si ninguna
  ventana encaja, la celda dice **Sin reconocer**, en rojo.
- **La ficha de la persona** muestra el mismo código en cada turno.
- **Pestaña «Resumen mensual» con filtro de Nodo:** aparecen las personas con ese nodo
  habitual y las que marcaron en él; cada una con **todos** sus turnos del período, aunque
  los haya hecho en otro nodo. La pestaña «Turnos» sí se queda con los marcados en el nodo.

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | El reporte y la ficha traen el turno reconocido por la hora; una entrada manual también; sin encaje, «Sin reconocer» | `test_doc19_ca01_el_reporte_y_la_ficha_traen_el_turno_reconocido_por_la_hora` |
| CA-02 | Con el filtro de nodo, quien cubrió en otro nodo sale con sus días (en su nodo y en el que cubrió); «Turnos» solo trae los del nodo | `test_doc19_ca02_el_resumen_por_nodo_cuenta_los_turnos_hechos_en_otro_nodo` |

## Candidatos para la siguiente versión

- Si Nómina necesita ver el turno aunque la entrada caiga fuera de la ventana, ampliar
  «Reconocer antes/después (min)» en el componente, o una columna «Turno más cercano».
- Una columna «Turno» en la pantalla «Marcaciones y reporte» (hoy solo en el Excel y la ficha).
- Una columna «Turno habitual» junto a «Turno», para ver de un vistazo quién cubrió otro
  turno. Edwar la quitó por ahora (04-11-2026).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-11-04 | Aprobada y construida. Pruebas en `TurnosAutomaticosTest` y `AdminMarcacionesYNovedadesTest` |
