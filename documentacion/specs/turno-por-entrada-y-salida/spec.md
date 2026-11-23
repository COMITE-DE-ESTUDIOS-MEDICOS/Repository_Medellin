# Spec: Turno reconocido por entrada y salida, y cierre sin salida por cargo

| | |
|---|---|
| Historia | DOC-21 |
| Tipo | Historia de usuario · cambia el reconocimiento del turno (DOC-5, DOC-19) y el cierre automático |
| Estado | **Aprobada** |
| Versión | 0.4 |
| Fuentes | Pedido de Edwar del 2026-11-10 y sus respuestas 1 a 4 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-10 |

## Problema

1. **El turno se elige solo por la hora de entrada.** El viernes hay turnos que empiezan casi a
   la misma hora: N1 a las 5:00 y T4 a las 6:00 por la mañana; T4. a las 12:00 y N1. a las 12:30
   por la tarde. Con solo la entrada, el sistema adivina. Comprobado con los tipos de turno de
   Calle:

   | Marca el viernes | Hoy da | Pero podría ser |
   |---|---|---|
   | 5:40 | T4 a tiempo | N1 con 40 min de tardanza |
   | 6:00, con N1 habitual | N1 con 60 min de tardanza | T4 a tiempo |
   | 12:20 | N1. a tiempo | T4. con 20 min de tardanza |

   Además, el turno habitual gana aunque la persona llegue tarde. Y casi nadie tiene un habitual
   asignado: son muchas personas y no se alcanzan a mantener.

2. **Las salidas que no se marcan.** Hoy el turno se cierra solo a las 14 h de la entrada, igual
   para todos, y eso lo hace la tarea programada (cron). Si el cron no corre, o si la persona
   vuelve a marcar antes de las 14 h, **la entrada del día siguiente se toma como salida** y
   descuadra todo.

## Comportamiento

### 1. El turno se reconoce con la entrada y la salida

- **Al marcar la entrada** se sigue reconociendo con lo que hay, la hora de entrada, para el
  aviso de tardanza. Es provisional.
- **Cuando el turno tiene salida**, se elige el turno que mejor encaja con las dos horas: la
  suma de qué tan lejos queda la entrada del inicio y la salida del fin. Ese es el que sale en el
  reporte, en la ficha y en el Excel para Nómina.
- **El habitual ya no gana por defecto.** Solo desempata cuando dos turnos encajan igual de
  bien.
- La tardanza se recalcula con el turno ya elegido.
- Si el turno se cerró solo (sección 2), la salida no es real: se queda el turno reconocido por
  la entrada.

Ejemplos del viernes:

| Entrada | Salida | Turno |
|---|---|---|
| 6:00 | 10:05 | T4 |
| 6:00 | 12:35 | N1 con 60 min de tardanza |
| 5:40 | 12:30 | N1 con 40 min de tardanza |
| 12:20 | 16:00 | T4. con 20 min de tardanza |
| 12:20 | 20:00 | N1. |

### 2. Cierre sin salida

Cuando alguien no marca la salida, el turno se cierra solo:

- **Cargo con máximo propio**: a esas horas de la entrada. Conductor camioneta y Conductor
  microbús, **15 h**. Se configura en **Editar componente → Jornada → Duración máxima por cargo**.
- **Los demás**: al **fin del turno más largo que esa entrada podría ser, más el margen de horas
  extra** (la «Tolerancia de horas extra», 60 min). Así cada turno se cierra según su horario y a
  nadie que esté trabajando se le corta:

  | Entrada | Puede ser | Se cierra a las |
  |---|---|---|
  | Lunes 5:00 | N1 | 13:30 |
  | Viernes 6:00 | T4 o un N1 que llegó tarde | 13:30 |
  | Viernes 12:05 | T4. o N1. | 21:00 |
  | Domingo 6:00 | C13 | 19:00 |
  | 21:00 | N7 | 5:30 |

- **Si ningún turno encaja** con la hora de entrada: a la «Duración máxima del turno» del
  componente.

Quien se queda más que el margen (más de 1 h extra) queda cortado en el fin del turno más 60 min;
eso lo corrige Coordinación con la salida real.

Al cerrarse:

- la salida queda a la hora del tope, como «cierre automático»;
- sale la novedad **«Fulano no marcó salida»**, para revisión;
- **en el reporte el turno se ve desde ese momento**, con las horas hasta el cierre y la marca
  **«Cierre automático · por corregir»**, hasta que Coordinación corrija la salida o confirme la
  novedad.

### 3. Que no dependa del cron

Si alguien marca y tiene una entrada abierta que ya pasó su tope, **primero se le cierra ese
turno** como en la sección 2. **Lo que marca ahora queda como entrada**, nunca como la salida de
ayer. El cron sigue corriendo cada hora, pero ya no es la única defensa.

## Criterios de aceptación

| CA | Criterio |
|---|---|
| CA-01 | Con entrada y salida, el turno es el que mejor encaja con las dos horas (los ejemplos de la tabla, uno por fila) |
| CA-02 | El habitual solo desempata; ya no gana con tardanza frente a otro turno que encaja mejor |
| CA-03 | El reporte, la ficha y el Excel muestran el turno y la tardanza ya con la salida |
| CA-04 | Sin salida, un cargo con máximo propio se cierra a esas horas (15 h los conductores); los demás, al fin del turno más largo posible más 60 min; sin turno, a la duración máxima |
| CA-05 | El C13 del domingo no se corta; un N1 que llega tarde el viernes y marca a las 12:30 tiene una salida normal |
| CA-06 | El cierre deja la novedad «no marcó salida», y el turno aparece en el reporte con «Cierre automático · por corregir» |
| CA-07 | Si al marcar hay una entrada abierta que ya pasó el tope, se cierra y lo nuevo queda como entrada, aunque el cron no haya corrido |
| CA-08 | La «Duración máxima por cargo» se edita en el componente y solo admite cargos del componente |

## Reglas que cambian

- El reconocimiento del turno de DOC-5 y DOC-19: ahora usa la entrada y la salida.
- El cierre automático: ahora usa el tope por cargo, el de los turnos cortos y el cierre al
  marcar.
- RN nueva sobre el tope por cargo y sobre cómo aparece en el reporte un turno sin salida.

## Decisiones (antes preguntas abiertas)

Edwar aprobó la spec con estas propuestas.

- **PA-01 y PA-02 (reemplazadas por la opción B, 2026-11-10).** En la revisión apareció un
  problema: con el cierre a las 10:20, a un N1 que llegaba tarde el viernes se le cortaba el
  turno aunque siguiera trabajando. Edwar eligió la opción B: cerrar al fin del turno más largo
  que la entrada podría ser, más la tolerancia (§2).
- **v0.4 (2026-11-10):** la prueba de estrés de una semana (`turnos:simular-semana`) mostró
  que un tope fijo de 8 h cortaba el C13 del domingo (12 h) y a quien se quedaba de más (37
  turnos de 246). Edwar aprobó cerrar todos los turnos por su horario más 60 min de margen.
- **turnos_por_dia se respeta:** si el turno de hoy se cerró solo, no se abre otro ese día; lo
  corrige Coordinación.

## Candidatos para la siguiente versión

- Avisar a Coordinación por la campana cuando un turno se cierra solo, además de la novedad.
- Turno habitual por día de la semana: un T4 que se queda más de 1 h 45 min extra el viernes
  (sale después de las 11:45) se lee como un N1 con tardanza, porque las marcas son las mismas.
  Si pasa seguido, que el líder asigne T4 los viernes y que eso desempate.
- Revisar que el cron esté activo en producción y mostrar en Documenta cuándo corrió por última
  vez.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-10 | Borrador con las respuestas de Edwar |
| 0.2 | 2026-11-10 | Aprobada, con PA-01 y PA-02 aceptadas como se propusieron |
| 0.3 | 2026-11-10 | Cierre de las entradas que parecen turno corto: opción B (el turno más largo posible) |
| 0.4 | 2026-11-10 | El cierre por horario aplica a todos los turnos, con el margen de horas extra; ya no hay un tope fijo de 8 h |
