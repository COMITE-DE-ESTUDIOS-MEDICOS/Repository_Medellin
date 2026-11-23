# Spec: Jornada esperada por tipo de turno y tolerancia de horas extra

| | |
|---|---|
| Historia | DOC-8 |
| Tipo | Historia de usuario · resuelve PA-01 de `domain.md` y cambia el valor de RN-22 |
| Estado | **Aprobada** |
| Versión | 1.0 |
| Fuentes | Edwar, 2026-11-10: «si es T4 se espera que trabaje 4 h sin descanso; si es N1, 7 h más el descanso» y «un margen de 60 minutos para una extra, en un rango de 40 a 60» |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-10 |

## Problema

Todos los turnos se comparaban con una sola jornada, la del componente (7 h). Un T4 de 4 h bien
trabajado salía como «turno corto» y un C13 de 12 h salía como horas extra. Además, la
tolerancia era la misma para llegar tarde y para salir tarde: salir 18 o 25 minutos después ya
generaba horas extra que alguien tenía que aprobar. En la prueba de estrés de una semana salieron
170 «turno corto», casi todos falsos.

## Comportamiento

1. **Cada turno se compara con su jornada**: su horario menos el descanso que le toca según los
   tramos del componente. No se configura nada nuevo, sale de Tipos de turno.

   | Turno | Horario | Descanso | Jornada |
   |---|---|---|---|
   | T4 / T4. | 4 h | — | 4 h |
   | N1 / N1. | 7 h 30 min | 30 min | 7 h |
   | C13 | 12 h | 30 min | 11 h 30 min |

   El turno es el reconocido con la entrada y la salida (DOC-21). Sin un turno con horario, se
   usa la «Jornada esperada» del componente, como antes.
2. **Una tolerancia propia para las horas extra**: «Tolerancia de horas extra (minutos)», en el
   componente, **entre 40 y 60**; vale **60** por defecto. Salir hasta ese tiempo después no es
   hora extra. La tolerancia de siempre (20) sigue para la tardanza y el turno corto.
3. La misma jornada va al **reporte y al Excel** (columna «Jornada (h)»): las novedades y el
   reporte dicen siempre lo mismo.
4. **El descanso va por el turno** (Edwar, 2026-11-10): se usa el tramo del horario del turno
   reconocido, no el del tiempo en el sitio. Un T4 que sale a las 10:05 no pierde 30 min:
   trabajó 4 h 5 min. Nunca se descuenta más de lo que daría el tiempo en el sitio: un N1 que
   se va a las 3 h no descuenta.
5. La tolerancia de horas extra es también el **margen con el que se cierra solo un turno sin
   salida** (DOC-21 v0.4).

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | T4 de 4 h, N1 de 7 h 30 min, C13 de 12 h y N7 no generan novedad; un N1 que sale una hora antes sí es corto | `JornadaPorTurnoTest::test_doc8_ca01_…` |
| CA-02 | 50 min de más no son hora extra; 75 min sí; la tolerancia del turno corto sigue en 20 | `test_doc8_ca02_…` |
| CA-03 | El Excel muestra la jornada del turno (T4 → 4 h) | `test_doc8_ca03_…` |
| CA-04 | El descanso va por el turno: el T4 nunca descuenta; el N1, 30 min; sin turno, por el tiempo en el sitio | `test_doc8_el_descanso_va_por_el_turno` |

## Lo que hay que saber

- En el ejemplo de Talento Humano (9 h 46 min en el sitio, 46 min de más), con la tolerancia de
  horas extra en 60 **no** sale hora extra; con 40, sí. Lo decide el valor que se ponga en el
  componente.
- Los minutos de más dentro de la tolerancia se ven en «Horas trabajadas», pero no como «Horas
  extra».

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-11-10 | Construida y aprobada con las decisiones de Edwar |
| 1.1 | 2026-11-10 | El descanso va por el horario del turno (el T4 nunca descuenta) |
