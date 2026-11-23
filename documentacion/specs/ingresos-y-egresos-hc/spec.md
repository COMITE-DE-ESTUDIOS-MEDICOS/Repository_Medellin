# Spec: Ingresos y egresos de Usuarios HC · estadías, sedes y capacidad

| | |
|---|---|
| Historia | DOC-27 |
| Tipo | Historia de usuario · módulo nuevo · propone RN-57 a RN-61 |
| Estado | **Borrador** |
| Versión | 0.1 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (propuesta: F-12), secciones 3.5, 4 a 4.8 y 8 (sedes y capacidad). Decisiones de Edwar, 2026-11-20 |
| Depende de | DOC-25 (acceso por componente) y DOC-26 (Usuarios HC) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Pendiente (Edwar) |

## Problema

Básica y Estabilización necesitan saber quién está dentro de cada sede, desde cuándo, cuándo
salió y por qué. De esas horas reales salen los cupos por día y la ocupación (DOC-28), así que
los ingresos y egresos tienen que quedar con su fecha y hora verdaderas, sin cruces y con
rastro de cada corrección.

## Vocabulario

- **Sede:** lugar de un componente donde se recibe a los Usuarios HC. Tiene una **capacidad**
  (cupos) que puede cambiar en el tiempo. Es un término nuevo: no es un nodo (PA-03).
- **Estadía:** el periodo entre un ingreso y su egreso en una sede.

## Estados de una estadía

| Estado | Qué significa | Puede pasar a | Quién lo cambia |
|---|---|---|---|
| Activa | Ingresó y no ha salido | Egresada, Anulada | Egresada: Edición en adelante. Anulada: Coordinación en adelante |
| Egresada | Tiene un egreso válido | Activa (reabrir) | Coordinación en adelante, con motivo |
| Anulada | El ingreso se invalidó; no cuenta para nada, pero sus datos quedan | — | — |

## Comportamiento

### 1. Sedes y capacidad

- Cada sede pertenece a un componente, tiene nombre, estado (Activa / Inactiva) y capacidad.
- La capacidad es un **número entero mayor que cero con fecha «vigente desde»**. Cambiarla
  pide la nueva capacidad, desde qué día vale (pasado, hoy o futuro) y el **motivo**. Los
  valores anteriores se conservan: la ocupación de un día se calcula con la capacidad de ese día.
- **Solo el superadministrador** crea sedes, las renombra, las activa o desactiva y cambia su
  capacidad (no hay Parametrizador). Todo queda en la auditoría.
- Una sede no se borra: se desactiva. Renombrarla no cambia el nombre con que quedaron las
  estadías anteriores.
- Los usuarios de un componente trabajan en **todas las sedes de su componente** (PA-04).

### 2. Pantalla «Ingresos y egresos»

- Lista de estadías **del componente de quien consulta** (el coordinador líder, del componente
  que elija).
- Búsqueda por nombre, documento, número de ficha o alias. Filtros: fecha inicial y final (por
  defecto, del primer día del mes a hoy), estado (Todas, Activa, Egresada), componente y sede.
  El rango trae las estadías que tuvieron permanencia en esas fechas, aunque hayan empezado antes.
- Columnas: nombre completo · documento · ingreso · egreso (fecha y hora) · días de estadía ·
  motivo de egreso · estado · componente · sede · acciones.
- Acciones según el estado y el rol:

| Estado | Acciones |
|---|---|
| Activa | Ver usuario HC · Ver estadía · Corregir ingreso · Registrar egreso · Anular ingreso |
| Egresada | Ver usuario HC · Ver estadía · Corregir ingreso · Corregir egreso · Reabrir estadía |

- «Ver estadía» muestra el ingreso, el egreso si existe y el historial de cambios, reaperturas y
  anulaciones.
- Al guardar o cerrar una ventana se conservan la búsqueda, los filtros y la página.
- Las horas se muestran como en el resto de Documenta («5:09 p. m.»), siempre en hora de Colombia.

### 3. Registrar ingreso

Se abre desde Usuarios HC (con la persona ya elegida) o desde esta pantalla. Solo se ofrece si
la persona **no tiene una estadía activa** y su estado vital es **Viva**.

| Campo | Obligatorio | Valores y reglas |
|---|---|---|
| Persona | Sí | Se busca y se elige |
| Componente | Sí | El de quien registra, fijo. El coordinador líder elige |
| Sede | Sí | Sedes activas del componente. Si hay una sola, viene puesta |
| Fecha y hora de ingreso | Sí | **Vacías al abrir, sin sugerencia** |
| Componente de procedencia | Sí | Estabilización · Intervención calle · Atención básica · Resignificación · Atención y cuidado · Altas necesidades de apoyo · Albergue · Resocialización (PA-05) |
| Comuna o corregimiento de procedencia | No | Solo aparece con «Intervención calle». La lista de comunas de Documenta. No se copia de la caracterización ni hacia ella |
| Proyección de egreso | No | La misma lista de ocho opciones |
| Diagnósticos de ingreso | No | Varios, catálogo CIE-10 |
| Condiciones identificadas al ingreso | No | Varias: Consumo activo de sustancias · Necesidad de apoyo en autocuidado · Dificultades de comportamiento o convivencia · Vulnerabilidad sociofamiliar · Malestar emocional · Agitación o inquietud marcada · Conducta desorganizada · Dificultades aparentes de atención o memoria · Sin manifestaciones relevantes observadas · No valoradas. Las dos últimas excluyen a las demás y entre sí |
| Orientación al ingreso | No | Orientada · Desorientada · No valorada |
| Foto de ingreso | No | Imagen |
| Observación | No | Texto largo |

Los diagnósticos y condiciones son **de esa estadía**, no de la persona: un reingreso puede
traer otros sin cambiar los anteriores.

### 4. Registrar egreso

| Campo | Obligatorio | Valores y reglas |
|---|---|---|
| Persona, componente y sede | — | Solo lectura, de la estadía |
| Fecha y hora de egreso | Sí | **Vacías, sin sugerencia**, también después de una reapertura |
| Motivo de egreso | Sí | Evasión · Reintegro familiar · Derivación a otro componente · Expulsión · Cumplimiento de tiempo · Fallecimiento · Traslado a unidad hospitalaria · Egreso voluntario · Vinculación laboral |
| Componente destino | Solo con «Derivación a otro componente» | La lista de ocho opciones. Si el motivo cambia a otro, el destino se quita y queda en el historial |
| Fecha de fallecimiento | No | Solo con «Fallecimiento». Propone la fecha del egreso; se puede corregir o borrar si no se conoce |
| Foto de egreso | No | Imagen |
| Observación | No | Texto largo |

Derivar cierra la estadía de origen e indica el destino, pero **no crea el ingreso** en el otro
componente: lo registra quien recibe a la persona.

### 5. Corregir, reabrir y anular

- **Corregir ingreso o egreso:** con **motivo obligatorio**. No borra el valor anterior: queda
  en el historial con quién, cuándo y por qué. Si otra persona cambió el registro mientras
  estaba abierto, se avisa y no se guarda encima.
- **Reabrir estadía** (el documento también la llama «Anular egreso»): anula el egreso y la
  estadía vuelve a Activa. Pide motivo y confirmación. El egreso anulado conserva todos sus
  datos, con quién lo reabrió, cuándo y por qué. Antes de reabrir se comprueba que la persona no
  tenga otra estadía activa y que, sin ese egreso, no se cruce con otra estadía válida; si pasa,
  se bloquea y se dice cuál es el conflicto. El egreso que se registre después es uno nuevo, con
  su fecha y hora reales.
- **Anular ingreso:** invalida la estadía completa sin borrar sus datos. Pide motivo y
  confirmación. Una estadía anulada no cuenta en indicadores ni reportes.
- Reabrir y anular son **solo de Coordinación en adelante** dentro de su componente (y del
  coordinador líder y el superadministrador). No se delegan.

### 6. Fallecimiento

- Un egreso por **Fallecimiento** deja a la persona como **Fallecida**. La fecha de fallecimiento
  propuesta es independiente de la fecha y hora del egreso.
- Marcar «Fallecida» desde la ficha **no egresa** sola: si hay estadía activa se avisa por la
  campana a la Coordinación del componente para que registre el egreso.
- Al reabrir o corregir un egreso por fallecimiento se pregunta también por el estado vital: no
  vuelve a «Viva» sin confirmación de quien tiene permiso para editar la ficha.
- Una persona fallecida no admite ingresos nuevos hasta que se corrija su estado.

### 7. Qué valida el sistema al guardar

Vale igual al registrar y al corregir. Si algo falla, **no se guarda nada a medias**, el
formulario conserva lo escrito y el mensaje dice qué corregir.

1. **Una sola estadía activa por persona en toda la dependencia.** Si ya tiene una, se bloquea
   la llegada y se dice **en qué componente está activa**, sin mostrar nada más de ella.
2. **Las estadías de una persona no se cruzan en el tiempo**, aunque sean de sedes o componentes
   distintos. Sí se permite una llegada **exactamente a la hora** de la salida anterior.
3. La salida no puede ser anterior a la llegada. Con la misma fecha y hora, la estadía dura cero
   y no aporta presencia ni cupos.
4. **No se aceptan fechas futuras.** La hora que se compara es la del servidor.
5. **Límite de un mes:** ingresos, egresos y correcciones de fecha aceptan hasta un mes
   calendario hacia atrás, contando el día completo (el 3 de noviembre se acepta desde el 3 de
   octubre a las 12:00 a. m.). Si el día no existe en el mes anterior, vale el último día de ese
   mes. **Coordinación en adelante** puede pasar de ese límite (PA-02); ese permiso no salta
   ninguna otra validación.
6. **La capacidad avisa, no bloquea:** al alcanzar o pasar la capacidad de la sede se muestra una
   advertencia y se deja continuar. La capacidad no sube sola.
7. **Sin duplicados:** doble clic, reintento de red o dos personas guardando a la vez no crean
   dos movimientos.
8. Al guardar se vuelven a comprobar el permiso, que la cuenta siga activa, el estado vital, que
   la sede y las opciones sigan activas, y los cruces.
9. La **fecha real** del ingreso o egreso y la **fecha en que se registró** son dos datos
   distintos; la segunda la pone el sistema. Completar un dato opcional después no cambia la
   fecha real.
10. Cuando existan los cierres de periodo (DOC-30), ningún cambio toca un mes cerrado sin
    reabrirlo antes.

### 8. Quién puede qué y datos sensibles

| Acción | Quién (dentro de su componente) |
|---|---|
| Ver la lista de estadías, sin diagnósticos, condiciones ni fotos | Lectura en adelante |
| Ver la estadía completa (diagnósticos y condiciones de ingreso, fotos, observaciones, historial) | Edición en adelante |
| Registrar ingreso y registrar egreso | Edición en adelante |
| Corregir ingreso y corregir egreso, con motivo | Edición en adelante (PA-02) |
| Registrar o corregir fechas de más de un mes | Coordinación en adelante |
| Reabrir estadía y anular ingreso | Coordinación en adelante |
| Sedes y capacidad | Solo el superadministrador |

- **Las estadías de otro componente no se ven** (salvo el coordinador líder y el
  superadministrador). De una persona activa en otro componente solo se sabe en cuál.
- Los diagnósticos, las condiciones y las fotos de ingreso y egreso son datos sensibles: abrir
  una estadía completa y abrir sus fotos **queda en la auditoría**, igual que cada registro,
  corrección, reapertura y anulación (con valores anteriores, nuevos y motivo).
- Las fotos no se guardan en el módulo Documentos y solo se abren con permiso (RN-06).

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | **¿Cuáles son las sedes de Básica y de Estabilización, con qué capacidad y desde qué fecha?** El documento no las trae (la reunión del 9-10-2026 nombra San Cristóbal y Buenos Aires) | Las entrega la coordinación por escrito y quedan en `fuentes/` (constitución I) | **Sí** |
| PA-02 | El documento da un permiso separado para cada acción (registrar ingreso, registrar egreso, corregir ingreso, corregir egreso, fechas sin límite). ¿Se traduce como en §8? | Sí: Edición registra y corrige; pasar del mes es de Coordinación. Sin permisos sueltos por ahora | **Sí** |
| PA-03 | ¿«Sede» es un concepto nuevo o es un **nodo** del componente? | Nuevo: tiene capacidad con fechas, y el nodo es un equipo o zona de turnos. Lo confirma Edwar y el arquitecto decide cómo guardarlo | **Sí** |
| PA-04 | El documento dice a la vez que al usuario se le asignan sedes (selección múltiple obligatoria) y que «accede a todas las sedes» de su componente | Sin sedes por usuario: cada quien trabaja todas las de su componente | **Sí** |
| PA-05 | La lista de procedencia, destino y proyección trae **ocho «componentes»** (Estabilización, Intervención calle, Atención básica…), y Documenta tiene tres (Calle, Básica, Estabilización). ¿Son lo mismo? | No se enlazan: es una lista de opciones de texto. Los componentes de Documenta conservan su nombre | **Sí** |
| PA-06 | El documento salta de la sección 4.4 a la 4.7, y la 5.2 cita una 4.6 sobre reaperturas. ¿Faltan reglas de las secciones 4.5 y 4.6? | Preguntar a quien lo escribió. Esta spec usa lo que sí está (4.4, 4.7 y 4.8) | **Sí** |
| PA-07 | «Días de estadía» de la lista: ¿cómo se cuentan? | Días calendario con alguna permanencia, del ingreso al egreso (o a hoy si está activa); igual que en el reporte (DOC-29) | No |
| PA-08 | ¿Quién ve las fotos de ingreso y egreso? El documento menciona «fotos operativas restringidas de otros componentes» sin definirlas | Solo el componente de la estadía, desde Edición, y el coordinador líder | No |
| PA-09 | El límite de un mes y la regla de capacidad son cifras del negocio: ¿quién las confirma por escrito? | La coordinación que encargó el documento; se registra en `fuentes/` | No |
| PA-10 | ¿Hay estadías en curso o pasadas que deban cargarse al empezar (personas que hoy ya están dentro)? | Las activas se registran a mano con su fecha real (Coordinación puede pasar del mes). Una carga masiva sería otra spec | **Sí** (para el plan) |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Solo el superadministrador crea sedes y cambia su capacidad; cada cambio exige fecha de vigencia y motivo, conserva el valor anterior y se audita | Por construir |
| CA-02 | Se registra un ingreso con fecha y hora escritas por el usuario; el formulario abre con fecha y hora vacías | Por construir |
| CA-03 | No se ofrece ni se acepta un ingreso para una persona con estadía activa o fallecida; si la estadía activa es de otro componente, el mensaje dice cuál y nada más | Por construir |
| CA-04 | Un ingreso o egreso que se cruza con otra estadía válida de la persona se rechaza, aunque sea de otra sede o componente; uno a la hora exacta de la salida anterior se acepta | Por construir |
| CA-05 | Se rechazan: fecha futura, salida anterior a la llegada, y fechas de más de un mes para Edición; Coordinación sí puede pasar del mes | Por construir |
| CA-06 | El límite de un mes cuenta el día completo y usa el último día cuando el mes anterior no lo tiene (31 de marzo → desde el 28 o 29 de febrero) | Por construir |
| CA-07 | Al pasar la capacidad de la sede se advierte y se deja guardar | Por construir |
| CA-08 | Enviar dos veces el mismo ingreso o egreso crea uno solo | Por construir |
| CA-09 | «No valoradas» y «Sin manifestaciones relevantes observadas» no se guardan con otras condiciones ni entre sí | Por construir |
| CA-10 | La comuna de procedencia solo se pide con «Intervención calle»; el componente destino, solo con «Derivación a otro componente», y se quita al cambiar el motivo | Por construir |
| CA-11 | Registrar un egreso deja la estadía Egresada con su motivo; un egreso por derivación no crea ingreso en otro componente | Por construir |
| CA-12 | Corregir un ingreso o un egreso exige motivo y deja en el historial el valor anterior, el nuevo, el autor y la fecha | Por construir |
| CA-13 | Si el registro cambió mientras otro lo editaba, el segundo guardado se rechaza con aviso | Por construir |
| CA-14 | Reabrir una estadía exige motivo, conserva el egreso anulado con sus datos y deja la estadía Activa; se bloquea si la persona tiene otra activa o si habría cruce. El nuevo egreso abre con fecha y hora vacías | Por construir |
| CA-15 | Anular un ingreso exige motivo, conserva los datos y saca la estadía de listas de activas, indicadores y reportes | Por construir |
| CA-16 | Edición recibe rechazo al reabrir o anular; Coordinación de otro componente, también | Por construir |
| CA-17 | Un egreso por Fallecimiento deja a la persona Fallecida; reabrirlo no la devuelve a Viva sin confirmación | Por construir |
| CA-18 | Un usuario de un componente no ve ni abre estadías de otro (rechazo, no solo lista vacía); Lectura no ve diagnósticos, condiciones ni fotos | Por construir |
| CA-19 | Abrir una estadía completa y abrir sus fotos quedan en la auditoría | Por construir |
| CA-20 | Si una validación falla no se guarda nada y el formulario conserva lo escrito | Por construir |

## Reglas que cambian

Propuestas para `docs/context/domain.md` (el número lo fija quien lo actualice):

- **RN-57. Una estadía activa por persona.** En toda la dependencia; las estadías de una
  persona no se cruzan.
- **RN-58. Fechas reales.** Las escribe quien registra, sin sugerencia; no futuras; hasta un mes
  atrás, salvo Coordinación. La fecha de registro la pone el sistema.
- **RN-59. La capacidad advierte, no bloquea.** Y se guarda con su fecha de vigencia.
- **RN-60. Corregir, reabrir y anular sin borrar.** Con motivo; reabrir y anular, solo
  Coordinación en adelante.
- **RN-61. Fallecimiento.** El egreso por fallecimiento cambia el estado vital; el estado vital
  no egresa solo.
- **Estados:** agregar la estadía (Activa, Egresada, Anulada) y la sede (Activa, Inactiva).
- **`glossary.md`:** Sede, Capacidad, Estadía, Ingreso, Egreso, Reabrir.
- **RN-38:** la campana necesita un tipo para los avisos de Usuarios HC.

## Decisiones (Edwar, 2026-11-20)

- Dentro de Documenta, con Authentik.
- Sin Parametrizador: sedes, capacidades y listas las cambia solo el superadministrador.
- Solo el coordinador líder ve varios componentes.
- Se empieza por Básica y Estabilización.

## Candidatos para la siguiente versión

- Asignar sedes a un usuario, si hace falta separar equipos dentro de un componente.
- Permisos sueltos por acción (corregir ingreso, corregir egreso).
- Carga masiva de estadías anteriores (PA-10).
- «Anular ingreso» revisará las atenciones asociadas cuando exista ese módulo (desarrollo futuro).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-20 | Borrador a partir del documento v1.6 y las decisiones de Edwar |
