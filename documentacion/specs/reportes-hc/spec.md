# Spec: Reportes de Habitante de Calle · reporte de cupos en Excel

| | |
|---|---|
| Historia | DOC-29 |
| Tipo | Historia de usuario · módulo nuevo · propone RN-64 |
| Estado | **Borrador** |
| Versión | 0.1 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (propuesta: F-12), secciones 6.1 y 6.2. Decisiones de Edwar, 2026-11-20 |
| Depende de | DOC-25, DOC-27 y DOC-28 (el mismo cálculo de permanencia) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Pendiente (Edwar) |

## Problema

Además de ver los indicadores en pantalla, la coordinación tiene que entregar el detalle del
periodo: cuántas personas hubo cada día y qué estadías lo explican.

## Comportamiento

### 1. Descargar

- En «Reportes» se eligen componente, sede (una o todas las del componente) y fechas, y se pulsa
  **Descargar reporte**. No hay tabla ni vista previa en pantalla.
- Fechas por defecto: del primer día del mes a hoy; las dos cuentan. Se rechaza un rango
  invertido o futuro. Si incluye hoy, se calcula hasta el momento de generarlo.
- Se pueden descargar reportes parciales en cualquier momento: **descargar no cierra el periodo**
  ni bloquea nada.
- El archivo dice el tipo de reporte, los filtros usados y la fecha y hora en que se generó.
- **Hay un solo tipo de reporte: «Reporte de cupos»**, con columnas fijas. El documento pedía
  tipos de reporte que arma el Parametrizador; como ese perfil no existe, no se construyen (PA-01).

### 2. Hoja «Resumen diario»

Una fila por cada día del rango, también los días sin movimientos.

| Columna | Cálculo |
|---|---|
| Fecha | El día |
| Personas con presencia | Personas que estuvieron algún tiempo en las sedes elegidas ese día; cada una, una vez |
| Personas con 12 horas o más | Personas que sumaron 12 horas o más ese día **entre las sedes elegidas**; cada una, una vez. (El cupo/día del tablero exige las 12 horas en la **misma** sede) |
| Ingresos | Ingresos válidos con fecha y hora dentro del día |
| Egresos | Egresos válidos con fecha y hora dentro del día |
| Personas dentro al cierre | Personas presentes justo antes de las 12:00 a. m. del día siguiente; para hoy, al momento de generar |

### 3. Hoja «Personas y estadías»

Una fila por cada estadía con alguna permanencia en el periodo, aunque no llegue a 12 horas.
Quien salió y volvió aparece en varias filas. Ordenadas por nombre.

| Columna | Contenido |
|---|---|
| Sede | Con el nombre que tenía al registrar la estadía |
| Componente | El de la estadía, con su nombre de entonces |
| Tipo de documento | El vigente de la persona |
| Número de documento | Como texto: conserva letras y ceros iniciales; vacío si no tiene |
| Nombre completo | — |
| Fecha y hora de ingreso | La real, aunque sea anterior al rango |
| Fecha y hora de egreso | La real; vacía si sigue activa |
| Estadía | Días que la persona lleva en el componente desde su ingreso (PA-02) |
| Estadía dentro del periodo consultado | Días del periodo con algún tiempo en esta estadía (entrar a las 11:00 p. m. y salir a la 1:00 a. m. son dos días) |
| Días con 12 horas o más | Días del periodo en que **esta estadía** tuvo 12 horas o más; una activa, hasta la hora de generación |
| Estado | Activa o Egresada |
| Motivo de egreso | Vacío si no ha egresado |
| Componente destino | Solo en egresos por derivación |
| Proyección de egreso | La elegida al ingreso, o vacío |

- **La suma de «Días con 12 horas o más» no es el indicador de cupos/día:** dos estadías de 6 y
  8 horas en la misma sede y fecha tienen cero cada una, y juntas dan 1 cupo/día en el tablero.
- Las estadías anuladas no salen.
- Los documentos van como texto, las fechas como fecha y hora de Excel y las cifras como
  números; se conservan las tildes.
- Un reporte nuevo de un mes pasado usa la identificación **vigente** de la persona; las
  opciones elegidas en los movimientos salen con el texto con que se registraron.

### 4. Quién lo descarga y datos personales

- **Lectura en adelante**, del componente que tenga el módulo, solo con las sedes de su
  componente. El coordinador líder y el superadministrador, todos.
- El archivo trae **nombres y documentos** de Usuarios HC, pero **ningún dato de
  caracterización, diagnóstico ni condición de ingreso**.
- Cada descarga **queda en la auditoría** con quién, cuándo y con qué filtros.
- El archivo se genera al pedirlo y se entrega pasando por el control de permisos (RN-06).

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | El documento pide tipos de reporte configurables (elegir y ordenar columnas, varias hojas). ¿Se dejan fuera? | **Sí:** un solo reporte fijo. Un reporte nuevo se pide como cambio de spec | **Sí** |
| PA-02 | Columna «Estadía»: «días que la persona ha estado en el componente desde su ingreso». ¿Días calendario con presencia o bloques de 24 horas? ¿De esta estadía o sumando reingresos? | Días calendario con alguna permanencia, de esta estadía, del ingreso al egreso (o a la generación) | **Sí** |
| PA-03 | ¿Lectura puede descargar un archivo con nombres y documentos? En el documento el perfil «Consulta y reportes» lo hace | Sí, es su función; queda auditado. Si Edwar prefiere, desde Edición | **Sí** |
| PA-04 | ¿El reporte debe coincidir con algún formato que hoy se entrega (a la Alcaldía, a un contrato)? | Pedir un ejemplo del archivo que hoy se entrega y compararlo antes de construir | No |
| PA-05 | ¿Se incluye el número de ficha? El documento lo deja opcional | No en esta versión | No |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | El archivo tiene las dos hojas con las columnas de §2 y §3, en ese orden, y dice filtros y fecha y hora de generación | Por construir |
| CA-02 | El resumen trae una fila por cada día del rango, también los días en cero | Por construir |
| CA-03 | Una persona con 6 horas en una sede y 8 en otra el mismo día cuenta en «Personas con 12 horas o más» del resumen, y ninguna de sus dos filas del detalle tiene días de 12 horas | Por construir |
| CA-04 | Una estadía que empezó antes del rango y sigue activa sale con su ingreso real, egreso vacío y estado Activa | Por construir |
| CA-05 | Una estadía de las 11:00 p. m. a la 1:00 a. m. tiene 2 días dentro del periodo y 0 días de 12 horas | Por construir |
| CA-06 | Las estadías anuladas no aparecen ni cuentan | Por construir |
| CA-07 | Un documento con ceros iniciales o letras llega intacto; las fechas son fechas de Excel y las cifras, números | Por construir |
| CA-08 | Una sede renombrada sale con el nombre que tenía cuando se registró la estadía | Por construir |
| CA-09 | Se rechazan un rango invertido y uno futuro | Por construir |
| CA-10 | Un usuario no descarga datos de sedes de otro componente (rechazo); el archivo no trae caracterización ni diagnósticos; la descarga queda auditada | Por construir |
| CA-11 | Descargar no cierra ni bloquea el periodo | Por construir |

## Reglas que cambian

- **RN-64 (propuesta). Reporte de cupos.** Excel con resumen diario y detalle de estadías, sin
  datos de caracterización; cada descarga se audita.
- **RN-28** no cambia y sirve de modelo: Documenta entrega las cifras; lo que se cobre o pague
  con ellas no lo calcula Documenta.

## Decisiones (Edwar, 2026-11-20)

- Sin Parametrizador: no hay tipos de reporte configurables por terceros.

## Candidatos para la siguiente versión

- Tipos de reporte adicionales, con número de ficha o con datos de caracterización (exige
  revisar quién puede descargarlos).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-20 | Borrador a partir del documento v1.6 y las decisiones de Edwar |
