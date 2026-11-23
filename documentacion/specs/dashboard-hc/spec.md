# Spec: Dashboard de Habitante de Calle · indicadores de atención y ocupación

| | |
|---|---|
| Historia | DOC-28 |
| Tipo | Historia de usuario · módulo nuevo · propone RN-62 y RN-63 |
| Estado | **Borrador** |
| Versión | 0.1 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (propuesta: F-12), sección 2. Decisiones de Edwar, 2026-11-20 |
| Depende de | DOC-25 y DOC-27 (estadías, sedes y capacidad) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Pendiente (Edwar) |

## Problema

La coordinación necesita saber, para un rango de fechas, cuántas personas atendió cada sede y
qué tanto se usó su capacidad. La medida que se reporta es el **cupo/día**: una persona que
permanece 12 horas o más en una sede en un mismo día.

## Comportamiento

### 1. Filtros

- **Componente:** el de quien consulta, fijo. El coordinador líder elige uno o todos.
- **Sede:** una o todas las del componente. Si hay una sola, queda fija.
- **Fecha inicial:** por defecto, el primer día del mes. **Fecha final:** por defecto, hoy.
- Las dos fechas cuentan (del 1 al 10 son 10 días). La inicial no puede ser posterior a la
  final y no se aceptan fechas futuras.
- **El corte** es el momento hasta donde se usan los datos: si la fecha final es hoy, la hora de
  la consulta; si es un día anterior, el final de ese día. La pantalla muestra los filtros
  usados y la fecha y hora del corte, en hora de Colombia.

### 2. Indicadores (en tarjetas)

| Indicador | Qué muestra y cómo se calcula | Formato |
|---|---|---|
| Personas dentro al corte | Personas que estaban dentro de las sedes elegidas al corte, aunque hayan ingresado antes de la fecha inicial | Entero |
| Ingresos | Llegadas registradas en las sedes y fechas elegidas. Quien sale y vuelve cuenta otra llegada | Entero |
| Egresos | Salidas registradas en las sedes y fechas elegidas, aunque la llegada sea anterior. Un egreso anulado al reabrir deja de contar | Entero |
| Personas atendidas | Personas que estuvieron algún tiempo en las sedes durante el periodo, aunque no llegaran a 12 horas. Cada persona, una sola vez | Entero |
| Estadía promedio | Suma de los días de las estadías ÷ número de estadías. Una estadía activa se cuenta hasta el corte (PA-01) | PA-01 |
| Cupos/día ejecutados | Por cada fecha, personas que permanecieron 12 horas o más en una misma sede; se suman las fechas del periodo | Entero |
| Cupos/día posibles | Capacidad × número de días. Si la capacidad cambió, cada tramo por separado. Con varias sedes, la suma | Entero |
| Promedio diario | Cupos/día ejecutados ÷ número de días del periodo | Un decimal |
| Ocupación | (Cupos/día ejecutados ÷ cupos/día posibles) × 100. Puede pasar de 100 % | Un decimal |
| Capacidad promedio del rango | Cupos/día posibles ÷ número de días del periodo | Un decimal |

- Se calcula con toda la precisión y **solo se redondea lo que se muestra**.
- Para los cupos posibles se usa **la capacidad de cada fecha**. Si el periodo termina hoy, hoy
  cuenta con su capacidad completa, pero las permanencias solo hasta la hora de la consulta.
- Sin personas ni movimientos, los indicadores muestran **cero**.
- Si **falta la capacidad de alguna fecha**, se dice cuál dato falta y no se muestran cupos
  posibles, capacidad promedio ni ocupación incompletos.
- Con cupos posibles en cero, la ocupación dice **«No calculable»**.
- Las estadías anuladas no cuentan.

### 3. Cómo se cuenta la permanencia (regla de 12 horas)

- Se usan las fechas y horas reales de ingreso y egreso. Sin egreso, se cuenta hasta el corte.
- Se cuenta **por día calendario**, de medianoche a medianoche, en hora de Colombia.
- Para los cupos/día se **suma el tiempo de la persona en la misma sede y la misma fecha**,
  aunque sea de varias estadías. Cada persona aporta como máximo **1 cupo/día por sede y fecha**.
- Una salida exactamente a las 12:00 a. m. termina el día anterior y no agrega tiempo al día
  nuevo; si un ingreso se registra a esa misma hora, ese tiempo es de la nueva estadía y no se
  cuenta dos veces.

| Situación | Cupos/día |
|---|---|
| 14 horas en una sede el lunes | 1 |
| Sale y vuelve a la misma sede el lunes: 6 horas y después 8 | 1 |
| Entra el lunes a las 6:00 p. m. y sale el martes a las 8:00 a. m. (6 h + 8 h) | 0 |
| 6 horas en una sede y 8 en otra el mismo día | 0 |
| 12 horas el lunes y 12 el martes en la misma sede | 2 |

Con varias sedes, «Personas atendidas» cuenta a cada persona una vez en todo el periodo;
«Cupos/día ejecutados» suma los días que cumplen la regla en cada sede.

### 4. Quién lo ve

- **Lectura en adelante**, del componente que tenga el módulo, y solo con las sedes de su
  componente. El coordinador líder y el superadministrador, todos los componentes.
- El tablero **solo muestra cifras**: ningún nombre, documento ni dato sensible.
- Las **gráficas de caracterización** son desarrollo futuro en el documento: fuera de alcance.

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | **Estadía promedio:** el documento no dice qué estadías entran (¿las que tuvieron permanencia en el periodo?), ni si los días son enteros o con decimales, ni si se cuenta la estadía completa o solo la parte dentro del periodo | Entran las estadías con permanencia en el periodo; se cuenta la estadía completa (del ingreso al egreso o al corte), en días con un decimal | **Sí** (solo este indicador) |
| PA-02 | El documento dice que el Coordinador general ve «todas las sedes» y, en otra parte, «los componentes de su contrato» | El coordinador líder ve todos los componentes de la dependencia (DOC-25, PA-03) | **Sí** |
| PA-03 | La regla de 12 horas por día calendario es una cifra del negocio (constitución I): ¿quién la confirma por escrito? Con ella, quien entra a las 6:00 p. m. y sale a las 8:00 a. m. no cuenta cupo | La coordinación que encargó el documento; se registra en `fuentes/` | **Sí** |
| PA-04 | ¿El menú dice «Dashboard» o un nombre en español («Tablero»)? | «Tablero HC», por la regla de español claro; lo decide Edwar | No |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Cada fila de la tabla de §3 da el número de cupos/día indicado | Por construir |
| CA-02 | Una salida a las 12:00 a. m. no suma tiempo al día nuevo, y un ingreso a esa misma hora no duplica tiempo | Por construir |
| CA-03 | Una sede con 150 cupos durante 10 días y 300 cupos/día ejecutados da: 1.500 posibles, promedio diario 30,0, ocupación 20,0 % y capacidad promedio 150,0 | Por construir |
| CA-04 | Una sede con 150 cupos por 10 días y 200 por 20 días da 5.500 posibles y capacidad promedio 183,3 | Por construir |
| CA-05 | Con varias sedes, una persona que pasó por dos cuenta una vez en «Personas atendidas» | Por construir |
| CA-06 | «Personas dentro al corte» incluye a quien ingresó antes de la fecha inicial y no ha salido; con fecha final de un día anterior usa el final de ese día | Por construir |
| CA-07 | Una estadía anulada y un egreso anulado no cuentan en ningún indicador | Por construir |
| CA-08 | Un periodo sin movimientos muestra ceros; si falta la capacidad de una fecha se dice cuál y no se muestra ocupación; con posibles en cero dice «No calculable» | Por construir |
| CA-09 | Se rechazan un rango invertido y una fecha futura; por defecto va del primer día del mes a hoy | Por construir |
| CA-10 | La ocupación puede mostrar más de 100 % | Por construir |
| CA-11 | Un usuario solo ve cifras de las sedes de su componente; pedir otra sede u otro componente por dirección directa se rechaza. El coordinador líder ve todas | Por construir |
| CA-12 | El tablero y el reporte de cupos (DOC-29) dan las mismas cifras para los mismos filtros y el mismo corte | Por construir |

## Reglas que cambian

Propuestas para `docs/context/domain.md`:

- **RN-62. Cupo/día.** Una persona aporta un cupo/día por cada fecha en que suma 12 horas o más
  en una misma sede, por día calendario en hora de Colombia; máximo uno por persona, sede y fecha.
- **RN-63. Ocupación.** Cupos/día ejecutados sobre cupos/día posibles, con la capacidad vigente
  en cada fecha; se redondea solo al mostrar.
- **`glossary.md`:** Cupo/día, Corte, Ocupación, Personas atendidas.

## Decisiones (Edwar, 2026-11-20)

- Dentro de Documenta; solo el coordinador líder ve varios componentes.

## Candidatos para la siguiente versión

- Gráficas de caracterización.
- Comparar periodos y sedes.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-20 | Borrador a partir del documento v1.6 y las decisiones de Edwar |
