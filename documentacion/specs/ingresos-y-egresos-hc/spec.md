# Spec: Ingresos y egresos de Usuarios HC · estadías, sedes y capacidad

| | |
|---|---|
| Historia | DOC-27 |
| Tipo | Historia de usuario · módulo nuevo · propone RN-57 a RN-61 |
| Estado | **Aprobada** |
| Versión | 1.0 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (propuesta: F-12), secciones 3.5, 4 a 4.8 y 8 (sedes y capacidad); lo que aquí se cita de él viene de la 0.1, como en DOC-26. Decisiones de Edwar, 2026-11-20. Decisiones de Edwar al aprobar, 2026-11-24 |
| Depende de | **DOC-25** (acceso por componente), construida: módulos por componente, pantalla «Componentes y nodos», componente de la sesión, coordinador líder, avisos y auditoría por componente. **DOC-26** (Usuarios HC), aprobada: la ficha, sus permisos, la búsqueda y el catálogo CIE-10; la necesita en `develop` el **30/11/2026** (§9) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-24 |

## Problema

Básica y Estabilización necesitan saber quién está dentro de cada sede, desde cuándo, cuándo
salió y por qué. De esas horas reales salen los cupos por día y la ocupación (DOC-28), así que
los ingresos y egresos tienen que quedar con su fecha y hora verdaderas, sin cruces y con
rastro de cada corrección.

## Vocabulario

- **Sede:** lugar de un componente donde se recibe a los Usuarios HC. Tiene una **capacidad**
  (cupos) que puede cambiar en el tiempo. Es un término nuevo: no es un nodo (PA-03).
- **Estadía:** el periodo entre un ingreso y su egreso en una sede.
- **Componente de la sesión:** el componente de quien trabaja; para el coordinador líder
  (Administración), el componente en el que está parado en el menú (DOC-25).
- **Días de estadía:** días calendario en que la persona estuvo dentro algún momento (§2).

## Estados de una estadía

| Estado | Qué significa | Puede pasar a | Quién lo cambia |
|---|---|---|---|
| Activa | Ingresó y no ha salido | Egresada, Anulada | Egresada: Edición en adelante. Anulada: Coordinación en adelante, con motivo |
| Egresada | Tiene un egreso válido | Activa (reabrir), Anulada | Coordinación en adelante, con motivo |
| Anulada | El ingreso se invalidó; no cuenta para nada, pero sus datos quedan | — | — |

## Comportamiento

### 1. El módulo y las sedes

- **«Ingresos y egresos» es un módulo aparte**, distinto de Usuarios HC. Como todo módulo, se
  decide por componente y lo enciende **solo el superadministrador** (Edwar) desde «Componentes y
  nodos» (RN-49), en **Básica y Estabilización**; Calle no lo tiene. Edwar lo enciende junto con
  Usuarios HC, porque trabaja sobre sus fichas. Mientras no lo encienda, nadie lo ve. Quien es de
  un componente sin el módulo no lo ve en el menú y, si escribe la dirección, recibe «No tienes
  permiso».
- Cada sede pertenece a un componente, tiene nombre, estado (Activa / Inactiva) y capacidad.
- La capacidad es un **número entero mayor que cero con fecha «vigente desde»**. Crear la sede
  pide su primera capacidad y desde qué día vale. Cambiarla pide la nueva capacidad, desde qué
  día vale (pasado, hoy o futuro) y el **motivo**. Los valores anteriores se conservan: la
  ocupación de un día se calcula con la capacidad que estaba vigente ese día.
- **Solo el superadministrador** crea sedes, las renombra, las activa o desactiva y cambia su
  capacidad, en una sección «Sedes» dentro de la pantalla «Ingresos y egresos» que solo él ve
  (no hay Parametrizador). El coordinador líder y Coordinación no la ven. Todo queda en la
  auditoría.
- Una sede no se borra: se desactiva. **No se puede desactivar una sede con personas dentro:** se
  bloquea y el mensaje dice cuántas estadías activas tiene. Una sede inactiva no se ofrece para
  ingresos nuevos, pero **sigue apareciendo** en las estadías y en los informes de antes.
- Renombrar una sede no cambia el nombre con que quedaron las estadías anteriores.
- Los usuarios de un componente trabajan en **todas las sedes de su componente** (PA-04).

### 2. Pantalla «Ingresos y egresos»

- Lista de estadías **del componente de la sesión**. No hay filtro de componente: el coordinador
  líder ve el componente en el que está parado y, para ver el otro, cambia de componente en el
  menú.
- Búsqueda por nombre, documento, número de ficha o alias, con las mismas reglas de la búsqueda
  de Usuarios HC (DOC-26 §1). Filtros: fecha inicial y final (por defecto, del primer día del mes
  a hoy), estado (Todas, Activa, Egresada, **Anuladas**) y sede. «Todas» no incluye las anuladas:
  solo se ven eligiendo «Anuladas». El rango trae las estadías que tuvieron permanencia en esas
  fechas, aunque hayan empezado antes.
- Columnas: nombre completo · documento · ingreso · egreso (fecha y hora) · días de estadía ·
  motivo de egreso · estado · sede · acciones.
- **Días de estadía:** se cuentan los días calendario en que la persona estuvo dentro algún
  momento, **contando el día de entrada y el de salida** (o hasta hoy si sigue activa), en hora
  de Colombia (America/Bogota). Ejemplos: entra el 3 a las 11:50 p. m. y sale el 4 a las
  12:10 a. m. → 2 días; entra y sale el mismo día → 1 día; una estadía que dura cero (salida a la
  misma hora de la llegada) → 0 días. **El mismo cálculo** se usa en esta lista, en el tablero
  (DOC-28) y en el reporte (DOC-29).
- Acciones según el estado y el rol:

| Estado | Acciones |
|---|---|
| Activa | Ver usuario HC · Ver estadía · Corregir ingreso · Registrar egreso · Anular |
| Egresada | Ver usuario HC · Ver estadía · Corregir ingreso · Corregir egreso · Reabrir estadía · Anular |
| Anulada | Ver usuario HC · Ver estadía |

- «Ver estadía» muestra el ingreso, el egreso si existe y el **historial de la estadía**: cada
  cambio con su valor anterior y nuevo, quién, cuándo y por qué, más las reaperturas y la
  anulación.
- Al guardar o cerrar una ventana se conservan la búsqueda, los filtros y la página.
- Las horas se muestran como en el resto de Documenta («5:09 p. m.»), siempre en hora de Colombia.
  **Las horas se registran al minuto, sin segundos.**

### 3. Registrar ingreso

Se abre desde Usuarios HC (con la persona ya elegida) o desde esta pantalla. Solo se ofrece si
la persona **no tiene una estadía activa** y su estado vital es **Viva**.

- **Cualquier componente con el módulo puede registrar el ingreso de cualquier persona de la
  dependencia**, aunque su ficha la haya creado otro componente. Ese ingreso le da acceso a su
  caracterización, como ya dice DOC-26 (§6 y §8), y **queda en la auditoría**.

| Campo | Obligatorio | Valores y reglas |
|---|---|---|
| Persona | Sí | Se busca y se elige entre las fichas de la dependencia (solo identificación, DOC-26 §6.1) |
| Sede | Sí | Sedes activas del componente de la sesión. Si hay una sola, viene puesta |
| Fecha y hora de ingreso | Sí | **Vacías al abrir, sin sugerencia.** Al minuto |
| Componente de procedencia | Sí | Estabilización · Intervención calle · Atención básica · Resignificación · Atención y cuidado · Altas necesidades de apoyo · Albergue · Resocialización (PA-05) |
| Comuna o corregimiento de procedencia | No | Solo aparece con «Intervención calle». La lista de comunas de Documenta. No se copia de la caracterización ni hacia ella |
| Proyección de egreso | No | La misma lista de ocho opciones |
| Diagnósticos de ingreso | No | Varios, del catálogo CIE-10 de DOC-26. **Sale después del 15/12/2026** (§9) |
| Condiciones identificadas al ingreso | No | Varias: Consumo activo de sustancias · Necesidad de apoyo en autocuidado · Dificultades de comportamiento o convivencia · Vulnerabilidad sociofamiliar · Malestar emocional · Agitación o inquietud marcada · Conducta desorganizada · Dificultades aparentes de atención o memoria · Sin manifestaciones relevantes observadas · No valoradas. Las dos últimas excluyen a las demás y entre sí |
| Orientación al ingreso | No | Orientada · Desorientada · No valorada |
| Foto de ingreso | No | Imagen (§8.1). **Sale después del 15/12/2026** |
| Observación | No | Texto largo |

- El componente de la estadía es el de la sesión; no se elige.
- Los diagnósticos y condiciones son **de esa estadía**, no de la persona: un reingreso puede
  traer otros sin cambiar los anteriores.
- **Sin estadías retroactivas:** un ingreso nuevo no puede quedar antes del egreso de la última
  estadía válida de la persona. Cargar estadías pasadas, anteriores a la última, no entra en esta
  versión (candidato).
- **Listas de opciones:** cada respuesta guarda **qué opción era y el texto que vio** quien la
  registró, como en DOC-26 §7. Una opción renombrada o retirada conserva su texto en las
  estadías que la tienen; una retirada no se ofrece para respuestas nuevas, y al corregir una
  estadía que ya la tiene se acepta sin cambiarla.

### 4. Registrar egreso

| Campo | Obligatorio | Valores y reglas |
|---|---|---|
| Persona, componente y sede | — | Solo lectura, de la estadía |
| Fecha y hora de egreso | Sí | **Vacías, sin sugerencia**, también después de una reapertura. Al minuto |
| Motivo de egreso | Sí | Evasión · Reintegro familiar · Derivación a otro componente · **Traslado a otra sede del componente** · Expulsión · Cumplimiento de tiempo · Fallecimiento · Traslado a unidad hospitalaria · Egreso voluntario · Vinculación laboral |
| Componente destino | Solo con «Derivación a otro componente» | La lista de ocho opciones. Si el motivo cambia a otro, el destino se quita y queda en el historial |
| Fecha de fallecimiento | No | Solo con «Fallecimiento». Propone la fecha del egreso; se puede corregir o borrar si no se conoce |
| Foto de egreso | No | Imagen (§8.1). **Sale después del 15/12/2026** |
| Observación | No | Texto largo |

- **Derivar o trasladar cierra la estadía de origen, pero no crea el ingreso**: lo registra quien
  recibe a la persona, en el otro componente o en la otra sede. Ese ingreso puede quedar a la
  misma hora del egreso (§7, regla 2).

### 5. Corregir, reabrir y anular

- **Corregir ingreso o egreso:** con **motivo obligatorio**. No borra el valor anterior: queda
  en el historial de la estadía con quién, cuándo y por qué. Si otra persona cambió el registro
  mientras estaba abierto, se avisa y no se guarda encima.
- **Reabrir estadía** (el documento también la llama «Anular egreso»): anula el egreso y la
  estadía vuelve a Activa. Pide motivo y confirmación. El egreso anulado conserva todos sus
  datos, con quién lo reabrió, cuándo y por qué. Antes de reabrir se comprueba que la persona no
  tenga otra estadía activa y que, sin ese egreso, no se cruce con otra estadía válida; si pasa,
  se bloquea y se dice cuál es el conflicto. El egreso que se registre después es uno nuevo, con
  su fecha y hora reales.
- **Anular:** invalida la estadía completa sin borrar sus datos, **esté Activa o Egresada**. Pide
  motivo y confirmación. Una estadía anulada no cuenta en indicadores ni reportes, no da acceso a
  la caracterización (DOC-26) y solo se ve con el filtro «Anuladas».
- Reabrir y anular son **solo de Coordinación en adelante** dentro de su componente (y del
  coordinador líder, parado en ese componente, y del superadministrador). No se delegan.

### 6. Fallecimiento

- Un egreso por **Fallecimiento** deja a la persona como **Fallecida** en su ficha, con la fecha
  de fallecimiento si se escribió, y el cambio queda en el historial de la ficha. La fecha de
  fallecimiento es independiente de la fecha y hora del egreso.
- **Reabrir o anular un egreso por Fallecimiento** pregunta **«¿La persona está viva?»**:
  - **Sí:** se reabre (o se anula) y la persona vuelve a **Viva**; el cambio queda en el historial
    de la ficha con el motivo, quién y cuándo.
  - **No:** no se reabre ni se anula.
- Corregir el motivo de un egreso **a** «Fallecimiento» deja a la persona Fallecida, como
  registrarlo; corregirlo **desde** «Fallecimiento» a otro motivo hace la misma pregunta.
- Marcar «Fallecida» desde la ficha **no egresa** sola: si hay estadía activa se avisa por la
  campana a la **Coordinación del componente de la estadía y al coordinador líder**
  (Administración), para que registren el egreso (DOC-26 §5, PA-16).
- Una persona fallecida no admite ingresos nuevos hasta que se corrija su estado.

### 7. Qué valida el sistema al guardar

Vale igual al registrar, corregir, reabrir y anular. Si algo falla, **no se guarda nada a
medias**, el formulario conserva lo escrito y el mensaje dice qué corregir.

1. **Una sola estadía activa por persona en toda la dependencia.** Si ya tiene una, se bloquea
   la llegada y se dice **en qué componente está activa**, sin mostrar nada más de ella.
2. **Las estadías de una persona no se cruzan en el tiempo**, aunque sean de sedes o componentes
   distintos. Sí se permite una llegada **exactamente a la hora** de la salida anterior.
3. La salida no puede ser anterior a la llegada. Con la misma fecha y hora, la estadía dura cero
   y no aporta presencia ni cupos.
4. **No se aceptan fechas futuras.** La hora que se compara es la del servidor, en hora de Colombia.
5. **Límite de un mes:** ingresos, egresos y correcciones de fecha aceptan hasta un mes
   calendario hacia atrás, contando el día completo (el 3 de noviembre se acepta desde el 3 de
   octubre a las 12:00 a. m.). Si el día no existe en el mes anterior, vale el último día de ese
   mes. **Al corregir, el límite vale para la fecha nueva y para la que se reemplaza:** una fecha
   de hace más de un mes no la corrige Edición, aunque la nueva sea reciente. **Coordinación en
   adelante** puede pasar de ese límite (PA-02); ese permiso no salta ninguna otra validación.
6. **La capacidad avisa, no bloquea:** al alcanzar o pasar la capacidad de la sede se muestra una
   advertencia y se deja continuar. La capacidad no sube sola.
7. **Sin estadías retroactivas:** un ingreso nuevo no queda antes del egreso de la última estadía
   válida de la persona (§3).
8. **Sin duplicados:** doble clic, reintento de red o dos personas guardando a la vez no crean
   dos movimientos.
9. Al guardar se vuelven a comprobar la dependencia, el módulo, el permiso, que la cuenta siga
   activa, el estado vital, que la sede y las opciones sigan activas, y los cruces.
10. La **fecha real** del ingreso o egreso y la **fecha en que se registró** son dos datos
    distintos; la segunda la pone el sistema. Completar un dato opcional después no cambia la
    fecha real.
11. Cuando existan los cierres de periodo (DOC-30), ningún cambio toca un mes cerrado sin
    reabrirlo antes.

Las reglas 5 y 6 (límite de un mes y «la capacidad avisa, no bloquea») son **decisión de Edwar
mientras llega la confirmación escrita** de la coordinación (PA-09).

### 8. Quién puede qué y datos sensibles

| Acción | Quién (en el componente de la sesión, con el módulo encendido) |
|---|---|
| Ver la lista de estadías, sin diagnósticos, condiciones ni fotos | Lectura en adelante |
| Ver la estadía completa (diagnósticos y condiciones de ingreso, observaciones, historial de la estadía) | Edición en adelante |
| Ver las fotos de ingreso y egreso | Edición en adelante del componente de la estadía; el superadministrador (§8.1) |
| Registrar ingreso y registrar egreso | Edición en adelante |
| Corregir ingreso y corregir egreso, con motivo | Edición en adelante (PA-02) |
| Registrar o corregir fechas de más de un mes | Coordinación en adelante |
| Reabrir y anular | Coordinación en adelante |
| Sedes y capacidad | Solo el superadministrador |

- El coordinador líder, parado en un componente, puede lo que puede Coordinación en ese componente.
- **Las estadías de otro componente no se ven ni se abren**, tampoco por dirección directa. De una
  persona activa en otro componente solo se sabe en cuál. **Una estadía de otra dependencia da
  «No encontrado»** en todo: verla, sus fotos, corregirla, reabrirla, anularla.
- **Auditoría:** queda en la auditoría de Documenta (quién, qué, cuándo, IP y navegador) cada
  registro, corrección, reapertura y anulación, cada cambio de sedes y capacidad, y **cada vez que
  se abre una estadía completa o una foto**. De una corrección la auditoría guarda **solo los
  nombres de los campos que cambiaron y el motivo, no los valores**, como en DOC-26 §6.2. Los
  valores quedan en el historial de la estadía, que se ve con el permiso de la estadía completa.

#### 8.1 Fotos de ingreso y egreso (después del 15/12/2026)

- Las ve **solo el componente de la estadía** (Edición en adelante, y el coordinador líder parado
  en ese componente) **y el superadministrador**. Otro componente que conozca a la persona no las
  ve, aunque vea su caracterización.
- Se aceptan los mismos formatos y tamaños que la foto de la persona (DOC-26 §6.3).
- **Sin sello** de hora, fecha, dirección y coordenadas, y **sin datos de ubicación**: los datos
  internos de la foto (EXIF) se quitan en el navegador y también en el servidor.
- No se guardan en el módulo Documentos; solo se abren pasando por el permiso y la auditoría
  (RN-06), y el navegador no las guarda en su caché.

### 9. Calendario y producción

- DOC-27 necesita de DOC-26, en `develop` el **30/11/2026**, la tabla de fichas, sus permisos y la
  búsqueda.
- **Punto de control el 4/12/2026:** si ese día registrar ingresos y egresos no funciona en el
  servidor de pruebas, corregir, reabrir y anular pasan a la semana siguiente.
- **Recortes del 15/12/2026:** los diagnósticos de ingreso (con el CIE-10 de DOC-26) y las fotos
  de ingreso y egreso salen después.
- **Producción:** DOC-27 sale **junto con DOC-26**, y las dos esperan las respuestas de jurídica
  sobre la Ley 1581 (DOC-26, PA-09 y PA-18).
- **Sedes reales:** Edwar las pide hoy por escrito a cada coordinación (PA-01). Mientras no
  lleguen, se construye y se prueba con sedes de prueba.

## Preguntas abiertas

**Resueltas por Edwar al aprobar (2026-11-24)**, salvo PA-01 (sedes reales) y PA-09
(confirmación escrita), que no bloquean el desarrollo.

| PA | Pregunta | Decisión | Estado |
|---|---|---|---|
| PA-01 | ¿Cuáles son las sedes de Básica y de Estabilización, con qué capacidad y desde qué fecha? El documento no las trae (la reunión del 9-10-2026 nombra San Cristóbal y Buenos Aires) | Edwar las pide hoy a cada coordinación por escrito (sede, capacidad, desde qué fecha) y quedan en `fuentes/` (constitución I). Sin eso, se construye con sedes de prueba | **Abierta.** Bloquea el uso real, no el desarrollo |
| PA-02 | El documento da un permiso separado para cada acción. ¿Se traduce como en §8? | **Sí:** Edición registra y corrige; pasar del mes, reabrir y anular son de Coordinación. Sin permisos sueltos | **Resuelta** con la propuesta |
| PA-03 | ¿«Sede» es un concepto nuevo o es un **nodo** del componente? | **Nuevo:** tiene capacidad con fechas y la maneja el superadministrador dentro de «Ingresos y egresos» (§1) | **Resuelta** (Edwar, 2026-11-24) |
| PA-04 | ¿Al usuario se le asignan sedes o accede a todas las de su componente? | Sin sedes por usuario: cada quien trabaja todas las de su componente | **Resuelta** con la propuesta |
| PA-05 | Los ocho «componentes» de procedencia, destino y proyección, ¿son los componentes de Documenta? | **No se enlazan:** es una lista de opciones que guarda código y texto (§3) | **Resuelta** con la propuesta |
| PA-06 | Al documento le faltan las secciones 4.5 y 4.6 | **Se construye sin ellas**, con lo que sí está (4.4, 4.7 y 4.8) | **Resuelta** (Edwar, 2026-11-24) |
| PA-07 | ¿Cómo se cuentan los «Días de estadía»? | Días calendario con alguna permanencia, contando el de entrada y el de salida, en hora de Colombia; el mismo cálculo en lista, tablero y reporte (§2) | **Resuelta** (Edwar, 2026-11-24) |
| PA-08 | ¿Quién ve las fotos de ingreso y egreso? | Solo el componente de la estadía y el superadministrador; sin sello ni ubicación (§8.1). Salen después del 15/12 | **Resuelta** (Edwar, 2026-11-24) |
| PA-09 | El límite de un mes y «la capacidad avisa, no bloquea»: ¿quién los confirma por escrito? | **Quedan como decisión de Edwar** mientras llega la confirmación escrita de la coordinación, que se guardará en `fuentes/` | **Abierta** (confirmación escrita). No bloquea |
| PA-10 | ¿Hay estadías que cargar al empezar? | Las personas que hoy están dentro se registran a mano con su fecha real (Coordinación puede pasar del mes). Las estadías pasadas, anteriores a la última, no se cargan en esta versión (candidato) | **Resuelta** (Edwar, 2026-11-24) |
| PA-11 | **Nueva.** ¿Se acepta un ingreso de un día anterior a la primera capacidad de la sede? | Sí; esos días se muestran sin capacidad en el tablero (DOC-28) | No bloquea; se acepta la propuesta salvo que Edwar diga otra cosa |

## Criterios de aceptación

Los marcados **«después del 15/12»** salen con los diagnósticos y las fotos (§9).

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Solo el superadministrador crea sedes, las renombra, las activa o desactiva y cambia su capacidad; cada cambio de capacidad exige fecha «vigente desde» y motivo, conserva el valor anterior y se audita. El coordinador líder, Coordinación y los demás reciben rechazo, también por dirección directa | Por construir |
| CA-02 | Se registra un ingreso con fecha y hora escritas por el usuario; el formulario abre con fecha y hora vacías; se guarda al minuto, sin segundos | Por construir |
| CA-03 | No se ofrece ni se acepta un ingreso para una persona con estadía activa (en cualquier componente) o fallecida; si la estadía activa es de otro componente, el mensaje dice cuál y nada más | Por construir |
| CA-04 | Un ingreso o egreso que se cruza con otra estadía válida de la persona se rechaza, aunque sea de otra sede o componente; uno a la hora exacta de la salida anterior se acepta | Por construir |
| CA-05 | Se rechazan: fecha futura, salida anterior a la llegada, y fechas de más de un mes para Edición; Coordinación sí puede pasar del mes, pero no de las demás reglas | Por construir |
| CA-06 | El límite de un mes cuenta el día completo y usa el último día cuando el mes anterior no lo tiene (31 de marzo → desde el 28 o 29 de febrero) | Por construir |
| CA-07 | Al alcanzar o pasar la capacidad vigente de la sede se advierte y se deja guardar; la capacidad no cambia | Por construir |
| CA-08 | Enviar dos veces el mismo ingreso o egreso, o guardarlo dos personas a la vez, crea uno solo | Por construir |
| CA-09 | «No valoradas» y «Sin manifestaciones relevantes observadas» no se guardan con otras condiciones ni entre sí | Por construir |
| CA-10 | La comuna de procedencia solo se pide con «Intervención calle»; el componente destino, solo con «Derivación a otro componente», y se quita al cambiar el motivo, quedando en el historial | Por construir |
| CA-11 | Registrar un egreso deja la estadía Egresada con su motivo; un egreso por derivación no crea ingreso en otro componente | Por construir |
| CA-12 | Corregir un ingreso o un egreso exige motivo y deja en el historial de la estadía el valor anterior, el nuevo, el autor y la fecha | Por construir |
| CA-13 | Si el registro cambió mientras otro lo editaba, el segundo guardado se rechaza con aviso | Por construir |
| CA-14 | Reabrir una estadía exige motivo, conserva el egreso anulado con sus datos y deja la estadía Activa; se bloquea si la persona tiene otra activa o si habría cruce, diciendo el conflicto. El nuevo egreso abre con fecha y hora vacías | Por construir |
| CA-15 | Anular una estadía Activa exige motivo, conserva los datos y la saca de las activas, de los indicadores y de los reportes | Por construir |
| CA-16 | Edición recibe rechazo al reabrir o anular; Coordinación de otro componente, también, incluso por dirección directa | Por construir |
| CA-17 | Un egreso por Fallecimiento deja a la persona Fallecida, con historial en la ficha. Reabrirlo o anularlo pregunta «¿La persona está viva?»: con «Sí» se reabre y la persona vuelve a Viva, con historial en la ficha; con «No» no se reabre ni se anula. Corregir el motivo a «Fallecimiento» la deja Fallecida; corregirlo desde «Fallecimiento» hace la misma pregunta | Por construir |
| CA-18 | Un usuario de un componente no ve ni abre estadías de otro (rechazo, no solo lista vacía); Lectura ve la lista pero no diagnósticos, condiciones, observaciones, historial ni fotos | Por construir |
| CA-19 | Abrir una estadía completa y abrir una foto quedan en la auditoría, cada vez | Por construir |
| CA-20 | Si una validación falla no se guarda nada y el formulario conserva lo escrito | Por construir |
| CA-21 | **Nuevo.** Mientras el superadministrador no enciende «Ingresos y egresos» en un componente, nadie de ese componente lo ve en el menú y la dirección directa da rechazo (lista, estadía, fotos, guardar); al encenderlo aparece sin otro paso. Calle no lo tiene | Por construir |
| CA-22 | **Nuevo.** La lista muestra solo las estadías del componente de la sesión, sin filtro de componente; el coordinador líder ve las del componente en que está parado. El rango de fechas trae las estadías con permanencia en él aunque empezaran antes. «Todas» no incluye anuladas; el filtro «Anuladas» las muestra | Por construir |
| CA-23 | **Nuevo.** Los días de estadía cuentan los días calendario con alguna permanencia, incluidos el de entrada y el de salida, en hora de Colombia: 3 a las 11:50 p. m. → 4 a las 12:10 a. m. da 2; entrar y salir el mismo día da 1; duración cero da 0; una activa cuenta hasta hoy. El mismo cálculo sirve al tablero y al reporte | Por construir |
| CA-24 | **Nuevo.** Corregir una estadía deja en la auditoría general solo los nombres de los campos cambiados y el motivo, **sin los valores**; los valores se ven solo en el historial de la estadía, con permiso de estadía completa | Por construir |
| CA-25 | **Nuevo.** El egreso «Traslado a otra sede del componente» cierra la estadía sin crear el ingreso; el ingreso en la otra sede, a la misma hora del egreso, se acepta | Por construir |
| CA-26 | **Nuevo.** Desactivar una sede con estadías activas se rechaza diciendo cuántas hay; una sede inactiva no se ofrece para ingresos nuevos y sigue apareciendo en las estadías y los informes anteriores; renombrarla no cambia el nombre guardado en las estadías anteriores | Por construir |
| CA-27 | **Nuevo.** Coordinación anula una estadía Egresada, con motivo; la estadía deja de contar y deja de dar acceso a la caracterización (DOC-26 CA-25) | Por construir |
| CA-28 | **Nuevo.** Edición no puede corregir una fecha de hace más de un mes, aunque la nueva esté dentro del mes; Coordinación sí | Por construir |
| CA-29 | **Nuevo.** Un ingreso anterior al egreso de la última estadía válida de la persona se rechaza con un mensaje que lo explica | Por construir |
| CA-30 | **Nuevo.** Un componente registra el ingreso de una persona cuya ficha creó otro componente; el ingreso queda en la auditoría y, desde ahí, Edición de ese componente ve la caracterización | Por construir |
| CA-31 | **Nuevo.** Marcar «Fallecida» desde la ficha a una persona con estadía activa no la egresa y avisa a la Coordinación del componente de la estadía y al coordinador líder | Por construir (la misma de DOC-26 CA-26) |
| CA-32 | **Nuevo.** Rechazos: una estadía o una sede de otra dependencia da «No encontrado» al verla, abrir sus fotos, corregirla, reabrirla o anularla, también por dirección directa; Lectura recibe rechazo al registrar, corregir, reabrir o anular; sin sesión no se abre nada | Por construir |
| CA-33 | **Nuevo.** Las respuestas de listas guardan opción y texto: una opción renombrada o retirada conserva su texto en las estadías que la tienen; la retirada no se ofrece en estadías nuevas y una corrección que no la toca se guarda | Por construir |
| CA-34 | **Nuevo.** Si entre abrir el formulario y guardar se desactiva la cuenta, se desactiva la sede, se retira una opción elegida o la persona pasa a Fallecida, el guardado se rechaza con un mensaje que dice qué pasó | Por construir |
| CA-35 | **Nuevo.** Las fotos de ingreso y egreso se guardan sin sello y sin datos de ubicación (también si no pasaron por el navegador); las abre Edición en adelante del componente de la estadía y el superadministrador; otro componente que conozca a la persona recibe rechazo; la respuesta no deja que el navegador la guarde en caché | Por construir (después del 15/12) |
| CA-36 | **Nuevo.** Los diagnósticos de ingreso se eligen del catálogo CIE-10 de DOC-26, varios por estadía, sin cambiar los de otras estadías ni la caracterización; el selector muestra el texto guardado aunque el código se haya renombrado | Por construir (después del 15/12) |

## Reglas que cambian

Propuestas para `docs/context/domain.md` (el número lo fija quien lo actualice):

- **RN-57. Una estadía activa por persona.** En toda la dependencia; las estadías de una
  persona no se cruzan; no se cargan estadías anteriores a la última.
- **RN-58. Fechas reales.** Las escribe quien registra, sin sugerencia, al minuto; no futuras;
  hasta un mes atrás (la fecha nueva y la que se corrige), salvo Coordinación. La fecha de
  registro la pone el sistema. *Decisión de Edwar, pendiente de confirmación escrita (PA-09).*
- **RN-59. La capacidad advierte, no bloquea.** Se guarda con su fecha de vigencia; una sede con
  personas dentro no se desactiva. *Decisión de Edwar, pendiente de confirmación escrita (PA-09).*
- **RN-60. Corregir, reabrir y anular sin borrar.** Con motivo e historial de la estadía; la
  auditoría general guarda los campos y el motivo, no los valores. Reabrir y anular (también una
  estadía egresada), solo Coordinación en adelante.
- **RN-61. Fallecimiento.** El egreso por fallecimiento deja a la persona Fallecida; reabrirlo
  pregunta si está viva; el estado vital no egresa solo y su aviso va a la Coordinación del
  componente y al coordinador líder.
- **RN-49:** se agrega el módulo **Ingresos y egresos**, encendido en Básica y Estabilización por
  el superadministrador desde «Componentes y nodos».
- **RN-04 y RN-06:** se extienden a las estadías y sus fotos, incluidas las lecturas (abrir la
  estadía completa, ver una foto).
- **RN-13:** excepción para las fotos de ingreso y egreso, sin sello ni ubicación (como la foto
  de la persona en DOC-26).
- **RN-38:** la campana necesita un tipo para los avisos de Usuarios HC (el mismo de DOC-26).
- **Estados:** agregar la estadía (Activa, Egresada, Anulada) y la sede (Activa, Inactiva).
- **`glossary.md`:** Sede, Capacidad, Estadía, Ingreso, Egreso, Reabrir, Días de estadía. En
  **Módulo**, agregar Ingresos y egresos.

## Notas para el arquitecto

No cambian el comportamiento; son puntos que el `plan.md` debe resolver.

- **Módulo:** una clave nueva en `Componente::MODULOS` con la etiqueta «Ingresos y egresos»,
  distinta de la de Usuarios HC. Revisar que no choque con un parámetro de ruta.
- **Aislamiento:** la estadía pertenece a un componente (filtro de dependencia y de componente);
  la sede, también. Saber en qué componente está activa una persona es la única consulta que
  cruza componentes (DOC-26 §6.1).
- **Días de estadía:** un solo cálculo compartido con DOC-28 y DOC-29, en America/Bogota;
  probar contra MySQL si se hace en la consulta.
- **Duplicados y cruces:** comprobarlos dentro de la misma transacción que guarda.
- **Datos de prueba:** sedes y estadías ficticias.

## Decisiones (Edwar, 2026-11-20)

- Dentro de Documenta, con Authentik.
- Sin Parametrizador: sedes, capacidades y listas las cambia solo el superadministrador.
- Solo el coordinador líder ve varios componentes.
- Se empieza por Básica y Estabilización.

## Decisiones al aprobar (Edwar, 2026-11-24)

1. «Ingresos y egresos» es un módulo aparte, que Edwar enciende en Básica y Estabilización; las
   sedes y su capacidad las maneja él como superadministrador en esa pantalla.
2. La auditoría guarda solo qué campos cambiaron y el motivo; los valores quedan en el historial
   de la estadía.
3. Nuevo motivo de egreso «Traslado a otra sede del componente»; la otra sede registra el ingreso.
4. No se desactiva una sede con personas dentro (se dice cuántas); las inactivas siguen en los
   informes anteriores.
5. Reabrir un egreso por fallecimiento pregunta «¿La persona está viva?»: sí → vuelve a Viva,
   con historial en la ficha; no → no se reabre.
6. Coordinación puede anular directamente una estadía egresada, con motivo; las anuladas se ven
   con el filtro «Anuladas».
7. Sin estadías retroactivas en esta versión (candidato).
8. El límite de un mes vale para la fecha nueva y para la vieja.
9. Horas al minuto, sin segundos.
10. Días de estadía: días calendario con alguna permanencia, contando entrada y salida; el mismo
    cálculo en lista, tablero y reporte, en America/Bogota.
11. Fotos de ingreso y egreso sin sello ni ubicación, EXIF quitado; las ven solo el componente de
    la estadía y el superadministrador. Salen después del 15/12.
12. Diagnósticos de ingreso después del 15/12, con el CIE-10 de DOC-26.
13. Cualquier componente registra el ingreso de cualquier persona de la dependencia; eso le da
    acceso a su caracterización (DOC-26), auditado.
14. El límite de un mes y «la capacidad avisa, no bloquea» quedan como decisión de Edwar mientras
    llega la confirmación escrita.
15. Se construye sin las secciones 4.5 y 4.6 del documento de origen.
16. Recortes: diagnósticos y fotos después del 15/12; si el 4/12 ingresos y egresos no funcionan
    en pruebas, corregir, reabrir y anular pasan a la semana siguiente. Producción con DOC-26,
    después de la Ley 1581.
17. Las sedes reales las pide Edwar por escrito a cada coordinación; sin ellas, sedes de prueba.

Alineado con DOC-25 y DOC-26: el componente es el de la sesión, sin filtro de componente; el aviso
por fallecimiento va también al coordinador líder; las opciones guardan código y texto; la
capacidad tiene historial por fecha.

## Candidatos para la siguiente versión

- Cargar estadías pasadas, anteriores a la última de la persona (retroactivas) (decisión 7, PA-10).
- Asignar sedes a un usuario, si hace falta separar equipos dentro de un componente.
- Permisos sueltos por acción (corregir ingreso, corregir egreso).
- Sede destino en el egreso por traslado, si hace falta saber a cuál va antes de que la reciban.
- «Anular» revisará las atenciones asociadas cuando exista ese módulo (desarrollo futuro).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-11-24 | **Aprobada por Edwar.** Diecisiete decisiones: módulo aparte «Ingresos y egresos» con las sedes a cargo del superadministrador; auditoría sin valores (historial de la estadía); motivo «Traslado a otra sede del componente»; no se desactiva una sede con personas dentro; reabrir o anular un egreso por fallecimiento pregunta si la persona está viva; Coordinación anula estadías egresadas y filtro «Anuladas»; sin estadías retroactivas; límite de un mes también para la fecha vieja; horas al minuto; días de estadía definidos (America/Bogota); fotos sin sello ni ubicación, solo del componente de la estadía y del superadministrador; diagnósticos y fotos después del 15/12; ingreso desde cualquier componente; mes y capacidad como decisión provisional de Edwar; sin secciones 4.5 y 4.6; punto de control el 4/12 y producción con DOC-26 tras la Ley 1581; sedes reales pedidas por escrito. Alineada con DOC-25 y DOC-26: componente de la sesión sin filtro de componente, aviso también al coordinador líder, opciones con código y texto. PA-02 a PA-08 y PA-10 resueltas; PA-01 y PA-09 abiertas sin bloquear el desarrollo; PA-11 nueva. CA-01 a CA-05, CA-07, CA-08, CA-10, CA-12, CA-14 a CA-18 ajustados; CA-21 a CA-36 nuevos |
| 0.1 | 2026-11-20 | Borrador a partir del documento v1.6 y las decisiones de Edwar |
