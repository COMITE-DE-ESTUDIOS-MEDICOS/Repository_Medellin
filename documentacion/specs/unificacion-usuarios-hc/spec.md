# Spec: Unificar fichas duplicadas de Usuarios HC

| | |
|---|---|
| Historia | DOC-31 |
| Tipo | Historia de usuario · propone RN-66 |
| Estado | **Borrador** (spec resumida: se propone construirla después del 10/12/2026; ver el plan de trabajo) |
| Versión | 0.1 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (propuesta: F-12), secciones 3.6, 7.3 y 10. Decisiones de Edwar, 2026-11-20 |
| Depende de | DOC-26 y DOC-27 (y DOC-30 para la reversión) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Pendiente (Edwar) |

## Problema

A veces la misma persona termina con dos fichas (llegó sin documento, dio otro nombre, la
recibió otro componente). Hay que poder juntarlas sin perder nada de ninguna.

## Comportamiento

### 1. Unificar

- Desde Usuarios HC, la acción **Unificar** pide la ficha **principal** y la **duplicada**
  (tienen que ser distintas).
- Antes de ejecutar se ve una comparación: las diferencias de identificación y de
  caracterización, las estadías de cada una y los conflictos.
- Se elige, **campo por campo**, qué valor queda vigente.
- **Nada se pierde:** se conservan todas las estadías, las identidades anteriores de las dos
  fichas (siguen sirviendo para buscar), los valores descartados y los dos historiales, cada
  cosa con su procedencia y con quién registró cada dato.
- Si las dos fichas tienen **estadías que se cruzan en el tiempo**, la unificación se bloquea
  hasta corregir ese conflicto (RN-57).
- Después de unificar, los indicadores y reportes cuentan **una sola persona**.

### 2. Quién

| Caso | Quién |
|---|---|
| Todas las estadías de las dos fichas son de su componente | Coordinación en adelante de ese componente |
| Alguna estadía es de otro componente | El documento pide una **solicitud** al coordinador general (Pendiente → Aprobada pendiente de ejecución / Rechazada → Ejecutada), con avisos por la campana. Propuesta más simple en PA-01 |
| Revertir una unificación equivocada | Solo el superadministrador (PA-02) |

Unificar no se delega a Edición. Toda unificación exige ver la comparación y queda en la
auditoría con las dos fichas, los valores elegidos y quién la hizo. Quien unifica ve la
caracterización de las dos fichas: por eso el caso entre componentes no es de la Coordinación de
un solo componente.

### 3. Revertir (según el documento)

Restaura las dos fichas con sus registros de origen; el superadministrador dice a cuál de las
dos pertenece cada registro creado después de unificar; se conserva la evidencia de la
unificación y de la reversión, y los cierres afectados quedan Desactualizados (DOC-30).

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | ¿Se construye el trámite de solicitud y aprobación entre componentes? | **No en la primera versión:** cuando hay otro componente de por medio, la unificación la hace directamente el **coordinador líder** (que ya ve todos los componentes). La Coordinación se lo pide por fuera del sistema. El trámite queda como candidato | **Sí** |
| PA-02 | ¿Se construye la reversión desde una pantalla? | **No en la primera versión:** la unificación guarda la procedencia de cada dato para que revertir sea posible; si hace falta, lo hace Edwar como caso excepcional, con auditoría. La pantalla queda como candidato | **Sí** |
| PA-03 | Mientras esta spec no esté construida, ¿qué se hace con un duplicado? | Se deja una de las dos fichas sin usar y se anota en su observación o alias; Edwar las junta cuando exista la función. DOC-26 ya avisa de coincidencias al crear | No |
| PA-04 | Si las dos fichas tienen foto, número de ficha o documento distintos, ¿cuál queda? | Se elige como cualquier otro campo; el otro queda como identidad anterior | No |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | La comparación muestra las diferencias, las estadías de cada ficha y los conflictos antes de ejecutar | Por construir |
| CA-02 | Tras unificar queda una sola ficha con las estadías de las dos y los valores elegidos | Por construir |
| CA-03 | Buscar el nombre, documento o número de ficha de la duplicada lleva a la ficha principal, señalado como dato anterior | Por construir |
| CA-04 | Los valores descartados y los dos historiales siguen consultables con su procedencia | Por construir |
| CA-05 | Dos fichas con estadías que se cruzan no se unifican y se dice cuál es el conflicto | Por construir |
| CA-06 | El tablero y el reporte cuentan una sola persona después de unificar | Por construir |
| CA-07 | Edición recibe rechazo; Coordinación recibe rechazo si alguna estadía es de otro componente; el coordinador líder sí puede | Por construir |
| CA-08 | La unificación queda en la auditoría con las dos fichas, lo elegido y el autor | Por construir |
| CA-09 | No se puede unificar una ficha consigo misma | Por construir |

## Reglas que cambian

- **RN-66 (propuesta). Unificar no pierde nada.** Las fichas repetidas se juntan conservando
  estadías, identidades, valores descartados e historiales; solo Coordinación en adelante, y
  entre componentes solo el coordinador líder.
- **Constitución IV** se cumple: la ficha duplicada no se borra, queda absorbida con su rastro.

## Decisiones (Edwar, 2026-11-20)

- Solo el coordinador líder actúa sobre varios componentes.

## Candidatos para la siguiente versión

- Trámite de solicitud y aprobación entre componentes, con sus avisos (PA-01).
- Pantalla de reversión para el superadministrador (PA-02).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-20 | Borrador resumido a partir del documento v1.6 |
