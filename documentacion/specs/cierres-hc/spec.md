# Spec: Cierres de periodo de Habitante de Calle

| | |
|---|---|
| Historia | DOC-30 |
| Tipo | Historia de usuario · módulo nuevo · propone RN-65 · agrega bloqueos a DOC-27 |
| Estado | **Borrador** (spec resumida: se propone construirla después del 10/12/2026; ver el plan de trabajo) |
| Versión | 0.1 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (propuesta: F-12), secciones 6.3, 6.4 y 4.8. Decisiones de Edwar, 2026-11-20 |
| Depende de | DOC-27 y DOC-29 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Pendiente (Edwar) |

## Problema

Cuando un mes termina y sus cifras se entregan, no deben cambiar por una corrección tardía sin
que nadie lo note. El cierre guarda el resultado del mes y bloquea los cambios de ese periodo.

## Comportamiento

### 1. Crear un cierre

- El cierre es **por sede y por mes**, y **solo de un mes ya terminado**. Cada sede es
  independiente.
- Pide sede, mes y confirmación de que el periodo quedará bloqueado.
- Guarda una **versión que no cambia**: el reporte de cupos (DOC-29) de esa sede y ese mes, con
  quién lo cerró y cuándo.
- Cerrar un mes **no egresa** a nadie: las estadías activas siguen activas.

### 2. Qué bloquea

Mientras el periodo esté cerrado no se puede, en esa sede y ese mes:

- registrar ingresos o egresos con fecha de ese mes;
- corregir, reabrir o anular algo que afecte ese mes (se revisan las fechas de antes y de
  después de la corrección: todos los meses tocados tienen que estar abiertos);
- cambiar la capacidad con vigencia dentro de ese mes.

El permiso de Coordinación para pasar del límite de un mes **no salta** este bloqueo. Un cambio
que toca varias sedes o meses cerrados exige reabrirlos todos.

### 3. Reabrir

- Pide sede, mes y **motivo obligatorio**.
- El cierre anterior **se conserva** como evidencia. Para volver a cerrar se crea un cierre
  nuevo, con otro número de versión y otro archivo.
- Si después de reabrir cambian los datos, los cierres anteriores quedan marcados
  **Desactualizado** y se avisa por la campana a la Coordinación del componente. Nada se
  regenera ni se sobrescribe en silencio.

### 4. Pantalla «Cierres»

- Filtros: componente, sede, año y mes.
- Columnas: periodo · versión · fecha de cierre · responsable · estado del periodo (Abierto /
  Cerrado) · archivo (Vigente / Desactualizado) · componente · sede · acciones.
- Acciones: **Descargar cierre** (el archivo guardado de esa versión, no uno recalculado),
  **Reabrir periodo** y **Ver historial** (cierres, reaperturas, responsables, fechas y motivos).

### 5. Quién

| Acción | Quién |
|---|---|
| Crear un cierre y reabrir un periodo | Coordinación en adelante, en las sedes de su componente; coordinador líder; superadministrador. No se delega |
| Ver la lista y descargar un cierre | Quien puede descargar reportes de esa sede (DOC-29) |

Crear, reabrir y descargar quedan en la auditoría. El archivo guardado trae nombres y documentos
y se entrega solo pasando por el control de permisos (RN-06).

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | **¿Hay una fecha en que haga falta el primer cierre?** Si se empieza a registrar en diciembre, el primer mes cerrable es diciembre, y se cerraría en enero | Construir después del 10/12; el primer cierre sería el de diciembre de 2026 | **Sí** (para el plan) |
| PA-02 | El cierre guarda nombres y documentos «como estaban». ¿Cuánto tiempo se conservan esos archivos? (constitución V) | Lo define el Comité; mientras tanto no se borran | No |
| PA-03 | ¿Un cierre se puede crear con el mes en curso para una entrega parcial? | No: para eso está el reporte, que no bloquea | No |
| PA-04 | El documento bloquea el cierre si hay borradores de atenciones | No aplica: Atenciones es desarrollo futuro | No |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | No se puede cerrar el mes en curso ni uno futuro; sí uno terminado | Por construir |
| CA-02 | El cierre guarda un archivo que se descarga idéntico aunque después cambien los datos o el nombre de la persona | Por construir |
| CA-03 | En una sede y mes cerrados se rechazan: ingreso, egreso, corrección, reapertura, anulación y cambio de capacidad con fecha de ese mes, también para Coordinación con permiso de pasar del mes | Por construir |
| CA-04 | Corregir una fecha de un mes abierto a uno cerrado (o al revés) se rechaza | Por construir |
| CA-05 | Cerrar una sede no bloquea otra sede del mismo componente | Por construir |
| CA-06 | Reabrir exige motivo, conserva el cierre anterior y deja el periodo Abierto | Por construir |
| CA-07 | Tras reabrir y cambiar un dato, el cierre anterior queda Desactualizado y se avisa a la Coordinación; cerrar de nuevo crea otra versión | Por construir |
| CA-08 | Edición y Lectura reciben rechazo al cerrar o reabrir; Coordinación de otro componente, también | Por construir |
| CA-09 | Cerrar un mes no cambia el estado de ninguna estadía | Por construir |
| CA-10 | Crear, reabrir y descargar un cierre quedan en la auditoría | Por construir |

## Reglas que cambian

- **RN-65 (propuesta). Cierre por sede y mes.** Guarda una versión inmutable y bloquea los
  cambios del periodo; reabrir exige motivo y conserva las versiones.
- **RN-57 a RN-60 (DOC-27):** se les suma «y el periodo afectado tiene que estar abierto».
- **Estados:** periodo (Abierto, Cerrado) y archivo de cierre (Vigente, Desactualizado).
- **`glossary.md`:** Cierre, Periodo, Reapertura.

## Decisiones (Edwar, 2026-11-20)

- Dentro de Documenta; las facultades de «coordinaciones» del documento son las de Coordinación
  en adelante, por componente, y las del coordinador líder.

## Candidatos para la siguiente versión

- Bloqueo por borradores de atenciones, cuando exista ese módulo.
- Cierre de todas las sedes de un componente en un solo paso.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-20 | Borrador resumido a partir del documento v1.6 |
