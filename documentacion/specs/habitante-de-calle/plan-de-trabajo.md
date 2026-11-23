# Plan de trabajo: Habitante de Calle en Documenta

| | |
|---|---|
| Qué es | Calendario y alcance. **No** es el `plan.md` ni el `tasks.md` técnico de cada spec: esos los hace el arquitecto cuando Edwar apruebe cada una |
| Estado | **Borrador** para que Edwar decida |
| Fecha | 2026-11-20 |
| Metas pedidas | 30/11/2026, y como máximo 10/12/2026 |
| Equipo | Edwar y José |
| Redactó | Claude |

## Resumen honesto

**El documento completo no cabe al 10 de diciembre.** Hay 14 días hábiles entre dos personas
(28 días-persona), y de ahí salen también la aprobación de las specs, los planes técnicos, las
pruebas de cada criterio y la revisión en el servidor de pruebas. Además son datos sensibles de
salud: no es terreno para atajos.

Lo que sí cabe, y es lo que se recomienda:

- **Al 30/11:** el acceso por componente terminado y Usuarios HC avanzado, en el servidor de
  pruebas. **Ese día todavía no hay nada que una sede pueda usar.**
- **Al 10/12:** se puede **registrar a las personas y sus ingresos y egresos** con todas sus
  validaciones. El tablero de indicadores, solo si sobra tiempo.
- **Después:** reporte en Excel, cierres de periodo y unificación de fichas.

La razón del orden: **lo único que no se puede recuperar después son los ingresos y egresos con
su hora real.** Los indicadores y el reporte se calculan a partir de esos datos, así que pueden
llegar unos días más tarde sin perder nada. Los cierres no hacen falta hasta que termine el
primer mes registrado.

## Días disponibles

| Tramo | Días hábiles | Días-persona |
|---|---|---|
| 20/11 al 30/11 (viernes 20; lunes 23 a viernes 27; lunes 30) | 7 | 14 |
| 1/12 al 10/12 (1 al 4; lunes 7; 9 y 10. El martes 8 es festivo) | 7 | 14 |

El 20 y el 23 se van, como mínimo, en responder las preguntas que bloquean, aprobar las specs y
escribir los planes técnicos. Quedan unos **24 días-persona de construcción**.

## Las specs y su tamaño

Los días son una estimación gruesa de quien redacta, para ordenar; **los confirma el arquitecto
en cada `plan.md`**.

| Spec | Qué es | Depende de | Días-persona (aprox.) | Cuándo |
|---|---|---|---|---|
| [DOC-25](../acceso-por-componente/spec.md) | Acceso por componente; Básica y Estabilización | — | 5 | 30/11 |
| [DOC-26](../usuarios-hc/spec.md) | Usuarios HC: ficha, caracterización, búsqueda, edición con historial | DOC-25 | 7 a 8 | Avanzado el 30/11, terminado el 10/12 |
| [DOC-27](../ingresos-y-egresos-hc/spec.md) | Sedes y capacidad; ingresos, egresos, corregir, reabrir, anular, fallecimiento | DOC-25, DOC-26 | 8 a 9 | 10/12 |
| [DOC-28](../dashboard-hc/spec.md) | Tablero: cupos/día, ocupación, regla de 12 horas | DOC-27 | 3 a 4 | 10/12 **solo si sobra**; si no, la semana siguiente |
| [DOC-29](../reportes-hc/spec.md) | Reporte de cupos en Excel | DOC-27, DOC-28 | 2 a 3 | Después del 10/12 |
| [DOC-30](../cierres-hc/spec.md) | Cierres de periodo | DOC-27, DOC-29 | 4 a 5 | Después (antes de cerrar diciembre, en enero) |
| [DOC-31](../unificacion-usuarios-hc/spec.md) | Unificar fichas duplicadas | DOC-26, DOC-27 | 4 a 6 | Después |
| **Suma** | | | **33 a 40** | Hay 24 |

DOC-25, DOC-26 y DOC-27 suman 20 a 22 días-persona: es lo que cabe, con poco margen.

## Orden y dependencias

```
DOC-25 acceso por componente
   └─ DOC-26 Usuarios HC
         └─ DOC-27 Ingresos y egresos (con sedes y capacidad)
               ├─ DOC-28 Tablero ──┐  (comparten el cálculo de permanencia)
               ├─ DOC-29 Reporte ──┘
               │      └─ DOC-30 Cierres
               └─ DOC-31 Unificación
```

- **DOC-25 va primero y sola a producción.** Cambia permisos del Calle que ya está en uso; para
  Calle no debe cambiar nada (su CA-03). Conviene publicarla aparte, antes de que exista una sola
  ficha de Usuario HC.
- DOC-26 puede empezar el mismo día que DOC-25: sus pantallas y datos no dependen de los
  permisos; se conectan cuando DOC-25 esté lista.
- El tablero y el reporte usan **un solo cálculo** de permanencia; lo construye una sola persona.

## Reparto sugerido

Una persona por módulo, para no tocar los mismos archivos.

| Tramo | Edwar | José |
|---|---|---|
| 20 y 23/11 | Responder las preguntas que bloquean; aprobar DOC-25, 26 y 27; pedir sedes y capacidades y la fuente del CIE-10 | Con el arquitecto: `plan.md` y `tasks.md` de DOC-25 y DOC-26 |
| 24 al 30/11 | **DOC-25** completa, con sus pruebas, en el servidor de pruebas | **DOC-26**: listas de opciones, crear, buscar, ficha y caracterización |
| 1 al 4/12 | DOC-25 a producción. **DOC-27**: sedes y capacidad, registrar ingreso y egreso, validaciones | **DOC-26**: edición con motivo e historial, búsqueda por identidades anteriores, auditoría de consultas, diagnósticos CIE-10 |
| 7, 9 y 10/12 | **DOC-27**: corregir, reabrir, anular, fallecimiento | Ayuda en DOC-27 (pruebas y pantalla de estadías); si sobra, cálculo de permanencia de DOC-28 |
| Después | DOC-30 Cierres | DOC-28 Tablero, DOC-29 Reporte; luego DOC-31 |

**Por qué así:** DOC-25 toca la seguridad de todo Documenta y Edwar es quien mejor la conoce y
quien responde por producción. José lleva Usuarios HC de principio a fin. Los archivos comunes
(menú, configuración, novedades de versión) los toca uno a la vez: se acuerda el orden y se
hacen entregas pequeñas a `develop` cada día.

## Qué entra en cada fecha

### Para el 30/11 (servidor de pruebas)

- DOC-25 terminada: componentes Básica y Estabilización creados, usuarios con componente,
  rechazo entre componentes, coordinador líder.
- DOC-26 a medio camino: crear, buscar y ver fichas, con la caracterización.

### Para el 10/12 (servidor de pruebas; a producción solo si se alcanzó a revisar)

- DOC-25 en producción.
- DOC-26 completa.
- DOC-27 completa: se puede llevar el día a día de ingresos y egresos.
- DOC-28, solo si sobra tiempo. No se promete.

### Después del 10/12

- DOC-28 (si no entró) y DOC-29: una semana más.
- DOC-30: antes de que haya que cerrar diciembre.
- DOC-31: cuando aparezcan los primeros duplicados reales.

## Qué se recomienda dejar fuera, y por qué

| Del documento | Qué se hace | Por qué |
|---|---|---|
| Ingreso con Google y reglas de sesión de 24 horas o 30 días | No se hace | Decisión de Edwar: Authentik y las sesiones de Documenta |
| Módulo Funcionarios, siete perfiles y permisos delegables | No se hace; se usan Usuarios y roles de Documenta más el componente | Decisión de Edwar. Construir permisos sueltos por acción son varios días y otra superficie de errores de seguridad |
| Parametrización (crear campos, listas, tipos de reporte) | No se hace; las listas las cambia Edwar | Decisión de Edwar. Era, sola, el módulo más grande del documento |
| Disciplinas | No se hacen | Solo sirven a Atenciones, que es desarrollo futuro |
| Atenciones y evoluciones | Fuera de alcance | Ya es desarrollo futuro en el documento |
| Gráficas de caracterización | Fuera | Desarrollo futuro en el documento |
| Módulo de Auditoría nuevo | No se hace; se usa la Auditoría de Documenta, más el historial dentro de cada ficha y estadía | Ya existe |
| Solicitud y aprobación de unificaciones entre componentes; pantalla de reversión | Fuera de la primera versión (DOC-31, PA-01 y PA-02) | Mucho trabajo para casos raros; el coordinador líder puede unificar directamente |
| Carga de fichas o estadías que ya existan en otro lado | Fuera; sería una spec propia | El documento no la pide y no cabe |

## Riesgos

| Riesgo | Qué pasa si ocurre | Cómo se baja |
|---|---|---|
| Las preguntas que bloquean no se responden el 20 o el 23 | Cada día de espera es un día menos de catorce | Edwar las responde antes de empezar; las propuestas de cada spec sirven de respuesta por defecto |
| No llegan las **sedes y capacidades** de Básica y Estabilización | DOC-27 no se puede probar ni usar | Pedirlas hoy, por escrito |
| El **catálogo CIE-10** oficial en español no está a tiempo | Los campos de diagnóstico se retrasan | Salir sin esos tres campos y agregarlos después (DOC-26, PA-06) |
| DOC-25 rompe algo de **Calle en producción** | Se frena la operación de turnos y derivaciones, que sí está en uso | Va primero, sola, con todas las pruebas existentes pasando y revisión en el servidor de pruebas |
| **Datos sensibles de salud** con prisa | Una fuga entre componentes es grave y no tiene vuelta atrás | No recortar los criterios de rechazo ni la auditoría de consultas; si algo no alcanza, se quita funcionalidad, no controles |
| El documento tiene huecos (secciones 4.5 y 4.6 que faltan, un perfil sin definir, dos versiones del alcance del coordinador general, y un final con «condiciones comunes y puntos por definir» que no está) | Reglas que aparecen cuando ya está construido | Preguntar hoy a quien lo escribió |
| Las cifras del negocio (12 horas, límite de un mes) vienen de un documento que no está en `fuentes/` | No hay respaldo si alguien las discute | Registrar el documento como fuente y pedir confirmación escrita |
| Las pruebas corren en SQLite y los cálculos de permanencia usan fechas y agrupaciones | Funciona en pruebas y falla en el servidor | Verificar esos cálculos contra MySQL (constitución VII) |
| Estimación optimista | El 10/12 no alcanza ni DOC-27 completa | Si el 4/12 no está registrando ingresos y egresos en pruebas, se deja corregir, reabrir y anular para la semana siguiente y se avisa |
| Quedan pendientes de antes: probar DOC-24 con un Excel real y su cierre del 30/11 | Compiten por los mismos días | Decidir hoy si se atienden o se aplazan |
| **Infraestructura (DOC-32):** con la migración a AWS en enero, la fase 1 en el servidor actual se reduce a revisar qué `php.ini` usa el sitio (y Redis solo si Edwar lo decide). Docker y el monitoreo van después del 10/12 | Poco: unas horas, no días | Se hace de noche o lo toma quien termine primero su parte de DOC-25 |
| **Administración pasa a ver todos los componentes (DOC-25, decisión del 2026-11-23)** | Quien hoy tenga Administración en producción verá Básica y Estabilización apenas existan | Revisar la lista de usuarios de Administración antes de desplegar DOC-25 (DOC-25, PA-03) |

## Antes de empezar: lo que tiene que decidir Edwar

1. DOC-25: ~~si Coordinación y Administración quedan atadas a un componente (PA-01)~~ resuelta el
   2026-11-23 (Coordinación atada; Administración es el coordinador líder, con acceso total).
   Faltan: quién da el rol Administración (PA-03), si los usuarios actuales pasan a Calle (PA-04),
   un componente por usuario (PA-02) y quién crea componentes (PA-07).
2. DOC-26: si se acepta la búsqueda de fichas entre componentes (PA-01), la traducción de
   permisos a roles (PA-02), dónde viven las listas (PA-05), la fuente del CIE-10 (PA-06) y la
   EPS frente a la constitución (PA-07).
3. DOC-27: las sedes y sus capacidades (PA-01), qué es una sede (PA-03) y las secciones que
   faltan en el documento (PA-06).
4. Si existe información previa que haya que cargar (DOC-26 PA-12, DOC-27 PA-10).
5. Si la meta del 10/12 es el servidor de pruebas o producción.
