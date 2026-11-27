# Spec: Cargar evidencias desde la ficha

| | |
|---|---|
| Historia | DOC-34 |
| Tipo | Mejora · Derivaciones y Seguridad CCTV · cambia RN-32 y RN-67 (ver «Reglas que cambian») |
| Estado | **Aprobada** |
| Versión | 1.0 |
| Fuentes | Pedido de Edwar, 2026-11-27, y sus decisiones del mismo día (quién, solo tarjetas vacías, estados) |
| Base | DOC-20 (casos de derivación y cierre a mano), DOC-24 (carga única), DOC-33 (Seguridad CCTV) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-27 |

## Problema

Hay casos atendidos o cerrados sin todas sus evidencias:

- En **Derivaciones**, el cierre a mano por contingencia (cuando el enlace falló) no deja subir
  evidencias: el caso queda Solucionado con las tres tarjetas en «Sin archivo». Lo mismo pasa
  con los registros anteriores al módulo de casos.
- En **Seguridad CCTV**, la evidencia 2 y la 3 son opcionales en el enlace; si la persona de
  campo las manda después por otro medio, no hay dónde ponerlas.

Hoy no hay forma de completar esas evidencias dentro de Documenta.

## Comportamiento

### 1. Dónde

En la ficha del caso, cada evidencia (1, 2 y 3) se ve como una tarjeta:

- **Derivaciones:** ya existen las tarjetas «Evidencia 1/2/3»; las vacías dicen «Sin archivo».
- **Seguridad CCTV:** hoy la sección «Imágenes» solo lista las que existen. Pasa a mostrar las
  tres tarjetas de evidencia (y el soporte), con «Sin archivo» en las vacías, igual que en
  Derivaciones. La sección la siguen viendo solo quienes pueden ver las imágenes (DOC-33,
  punto 9).

Una tarjeta vacía muestra, a quien puede cargar, el botón **«Cargar evidencia»**, que abre el
mismo campo de foto del enlace: foto con la cámara, con el sello de fecha, hora y ubicación
(RN-13), o imagen del dispositivo. Se carga una evidencia a la vez y se pulsa **«Guardar»**.
El soporte no se carga desde aquí.

### 2. Quién

| Módulo | Pueden cargar |
|---|---|
| Derivaciones | **Edición en adelante** del componente: Edición, Coordinación, Administración (y el superadministrador), para no esperar a la coordinación |
| Seguridad CCTV | Responsables de Seguridad CCTV (Edición o mayor con la casilla), Coordinación y Administración del componente |

- **Cargar evidencias en tarjetas vacías no es cerrar el caso.** **El cierre a mano por
  contingencia en Derivaciones sigue siendo solo del superadministrador, exactamente como hoy
  (RN-32).** Esta spec no le da ese cierre a nadie más.
- En **Seguridad CCTV**, Edición sin la casilla de responsable **no** carga: no puede ver las
  imágenes de CCTV (DOC-33, CA-19), así que tampoco puede llenar sus tarjetas.
- No ven el botón (ven la ficha como hoy): en Derivaciones, Lectura; en Seguridad CCTV, Edición
  sin la casilla y Lectura.

### 3. Cuándo

| Módulo | Se puede cargar | No se puede |
|---|---|---|
| Derivaciones | Caso o registro anterior **Solucionado** | Pendiente (vencido o no), Eliminado |
| Seguridad CCTV | Caso **Atendido** o **Cerrado** | Abierto, Anulado |

### 4. Qué no cambia

- **Desde la tarjeta nunca se reemplaza una evidencia.** Una tarjeta con archivo no muestra
  el botón, y el servidor rechaza cualquier intento de cargar sobre ella con «Esta evidencia
  ya tiene un archivo; no se reemplaza». Si dos personas cargan la misma tarjeta a la vez,
  queda la primera y la segunda recibe ese mensaje, sin que su archivo quede guardado.
  Cambiar una evidencia que ya existe se hace por el repositorio (punto 6).
- **El caso no cambia:** ni el estado, ni la hora de respuesta, ni el tiempo de respuesta, ni
  el «¿Se cumplió?», ni quién lo atendió, ni quién lo cerró.
- **Nada se borra:** la evidencia cargada queda como las demás; no hay «quitar» en la tarjeta.

### 5. Rastro

- La tarjeta dice debajo **«Cargada desde la ficha por <nombre> el <fecha y hora>»** (en 12 h).
  Las que llegaron por el enlace siguen como hoy.
- Cada carga queda en la **auditoría**: quién, el caso, qué evidencia (1, 2 o 3), cuándo, IP y
  navegador.
- La evidencia se guarda donde ya caen las del módulo: en Derivaciones, la carpeta
  «Derivaciones · <componente>»; en Seguridad CCTV, la carpeta «Seguridad CCTV · <componente>»
  con la custodia de los responsables (DOC-33). Así entra sola al Excel y al ZIP de cada
  reporte, y se abre por el control con permiso de siempre (RN-06).
- Formatos y tamaño: los mismos de las evidencias del enlace (imágenes, hasta 25 MB). Otro
  formato recibe «La evidencia debe ser una imagen (JPG, PNG…)».

### 6. Cambiar una evidencia existente: por el repositorio, con versiones

Cada evidencia (las del enlace, las de la carga única y las de la ficha) ya es un documento
del repositorio con su historial de versiones. Cambiarla **no** se hace desde la tarjeta:

- Una tarjeta con archivo muestra el enlace **«Abrir en el repositorio»** a ese documento, solo
  a quien puede verlo allí. Ahí se ven sus versiones y se sube una **nueva versión**, como hoy:
  la anterior queda en el historial, se puede descargar y nada se borra (RN-05).
- Quién puede subir la nueva versión sigue las reglas actuales del repositorio, sin cambios:
  - **Seguridad CCTV:** la carpeta tiene la custodia de los responsables (RN-08), así que solo
    la ven y la cambian los responsables con Edición o mayor, Coordinación y Administración.
    Un responsable con Lectura la ve pero no la cambia. Coincide con quien carga en la tarjeta.
  - **Derivaciones:** la carpeta «Derivaciones · <componente>» no tiene custodia: la ve toda la
    dependencia con acceso a Documentos, y suben versión **Edición** en adelante. Coincide con
    quien carga en la tarjeta (PA-06).
  - En los dos, el repositorio exige que el componente tenga encendido el módulo Documentos
    (RN-49, RN-51). Calle lo tiene.
- **La ficha muestra siempre la versión actual:** la imagen que abre la ficha de CCTV, las
  fotos del Excel y del ZIP de los dos reportes salen de la versión vigente. El cambio se ve
  solo, sin tocar el caso.
- **La tarjeta lo deja ver:** si la evidencia tiene más de una versión, dice debajo
  **«Versión N · cambiada el <fecha y hora> por <nombre>»** (la última versión). Con una sola
  versión no dice nada nuevo (o «Cargada desde la ficha…», punto 5).
- Subir una versión no cambia el estado, las horas, el tiempo ni quién atendió o cerró, y
  queda en la auditoría como cualquier versión del repositorio.

Nota para el arquitecto: el documento ya guarda quién lo creó y cuándo (las evidencias del
enlace quedan sin usuario), y cada versión quién la subió y cuándo, así que no parece hacer
falta columna nueva para el «quién y cuándo» ni para «cambiada el… por…». Que lo confirme el
plan.

## Preguntas abiertas

Todas resueltas por Edwar al aprobar (2026-11-27).

| PA | Pregunta | Respuesta | Bloquea |
|---|---|---|---|
| PA-01 | En Derivaciones el pedido dice «los mismos del cierre por contingencia», pero ese cierre hoy es **solo del superadministrador** (RN-32, `corregirHoras`), no de Coordinación ni Administración. ¿Quién carga? | **Resuelta distinto a la propuesta:** el cierre a mano sigue **solo del superadministrador**, sin cambios. Cargar en tarjetas vacías no es cerrar: lo hace **Edición en adelante** del componente (y el superadministrador), para no esperar a la coordinación. RN-32 solo se amplía con eso | No (resuelta) |
| PA-02 | ¿Los registros anteriores (sin código DC) también se completan? | Sí, si están Solucionados | No (resuelta) |
| PA-03 | ¿Galería además de la cámara? | Sí, el mismo campo del enlace (cámara con sello o imagen del dispositivo) | No (resuelta) |
| PA-04 | ¿Se pide motivo al cargar? | No: la tarjeta y la auditoría ya dicen quién y cuándo | No (resuelta) |
| PA-05 | ¿Se avisa por la campana a alguien? | No en esta versión (C-02) | No (resuelta) |
| PA-06 | En Derivaciones, Edición ya puede subir una nueva versión de una evidencia por el repositorio, pero no llenaba una tarjeta vacía. ¿Se deja así? | Ya no aplica: con PA-01, Edición también llena tarjetas vacías; quien carga y quien sube versión coinciden. La carpeta sigue sin custodia | No (resuelta) |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Edición carga la evidencia 2 vacía de una derivación Solucionada (y Coordinación y Administración también pueden): la tarjeta muestra el archivo y «Cargada desde la ficha por <nombre> el <fecha>»; el estado, la hora de respuesta, el tiempo, el «¿Se cumplió?» y quién atendió quedan iguales | Por construir (`test_ca01_…`) |
| CA-02 | Un responsable de Seguridad CCTV carga la evidencia 3 vacía de un caso Atendido y otra de un caso Cerrado; el estado, la hora y el tiempo de respuesta, quién atendió y quién cerró quedan iguales; el archivo queda en la carpeta con custodia de los responsables | Por construir |
| CA-03 | Quien no puede cargar no ve el botón y, por dirección directa, recibe «No tienes permiso»: en Derivaciones, Lectura; en CCTV, Edición sin la casilla y Lectura | Por construir |
| CA-04 | Cargar sobre una tarjeta que ya tiene archivo (incluida la evidencia 1 que subió la persona de campo) se rechaza con «Esta evidencia ya tiene un archivo; no se reemplaza», y el archivo original sigue intacto | Por construir |
| CA-05 | Se rechaza cargar en una derivación Pendiente o Eliminada y en un caso CCTV Abierto o Anulado | Por construir |
| CA-06 | Un caso de otro componente o de otra dependencia da «No encontrado» o «No tienes permiso», también por dirección directa (RN-01) | Por construir |
| CA-07 | Un archivo que no es imagen o que pasa de 25 MB se rechaza con un mensaje claro y no queda nada guardado | Por construir |
| CA-08 | Dos cargas a la misma tarjeta vacía al mismo tiempo: queda una sola evidencia y la segunda recibe el rechazo de CA-04 sin dejar archivo guardado | Por construir |
| CA-09 | Cada carga deja una entrada de auditoría con quién, el caso y el número de evidencia | Por construir |
| CA-10 | Una tarjeta con archivo muestra «Abrir en el repositorio» solo a quien puede ver el documento: en CCTV, a un responsable, Coordinación y Administración, y no a Edición sin la casilla | Por construir |
| CA-11 | Tras subir una nueva versión de una evidencia en el repositorio, la ficha (y la imagen que abre en CCTV), el Excel y el ZIP muestran la nueva; la anterior sigue en el historial; la tarjeta dice «Versión 2 · cambiada el <fecha> por <nombre>»; el estado, las horas y el tiempo del caso no cambian | Por construir |
| CA-12 | El cierre a mano por contingencia de Derivaciones sigue siendo solo del superadministrador: Edición, Coordinación y Administración, aunque puedan cargar evidencias, no ven esa acción y por dirección directa reciben «No tienes permiso» | Por construir |

## Reglas que cambian

- **RN-32** (solo se amplía): a «Edición y Coordinación solo corrigen textos» se agrega:
  «Edición en adelante (Edición, Coordinación, Administración y el superadministrador)
  completan, desde la ficha, las evidencias vacías de un caso Solucionado; nunca reemplazan una
  que ya existe y no cambian horas ni el estado (DOC-34)». **El cierre a mano por contingencia
  sigue siendo solo del superadministrador, exactamente como hoy: en eso RN-32 no cambia.**
- **RN-67:** se agrega: «Los mismos que cierran completan, desde la ficha, las evidencias vacías
  de un caso Atendido o Cerrado, sin reemplazar ninguna y sin cambiar el caso (DOC-34)».
- **DOC-33, punto 7** («las evidencias no se cambian»): se precisa. Corregir el caso no cambia
  las evidencias; completar una tarjeta vacía tampoco. Una evidencia existente solo cambia
  por una nueva versión en el repositorio, con su historial (RN-05, RN-08).
- **RN-05, RN-08** y las reglas del repositorio no cambian: se aplican tal cual a las evidencias.

## Decisiones (Edwar, 2026-11-27)

- En CCTV carga quien puede cerrar: responsables (Edición o mayor con la casilla), Coordinación
  y Administración. Edición sin la casilla no, porque no ve las imágenes de CCTV (DOC-33, CA-19).
- En Derivaciones carga **Edición en adelante**, para no esperar a la coordinación. Cargar no es
  cerrar: **el cierre a mano sigue solo del superadministrador** (PA-01, RN-32).
- Con eso, quien llena la tarjeta vacía y quien sube nueva versión en el repositorio coinciden
  en Derivaciones (PA-06); no se pone custodia a la carpeta.
- Las demás preguntas, con su respuesta propuesta.
- Solo tarjetas vacías: lo que subió el personal en territorio no se toca desde la tarjeta.
- Solo casos atendidos o cerrados; el caso no cambia en nada más.
- Aclaración: una evidencia existente sí se puede cambiar, pero en el repositorio, con una
  nueva versión (la anterior queda en el historial). La tarjeta con archivo enlaza a ese
  documento para quien puede verlo.

## Candidatos para la siguiente versión

| C | Candidato |
|---|---|
| C-01 | Poner los campos de evidencias en el formulario «Cerrar a mano (contingencia)» de Derivaciones |
| C-02 | Aviso por la campana a quien creó el caso cuando se completa una evidencia |
| C-03 | Cargar el soporte desde la ficha cuando el caso se creó sin él (DOC-24) |
| C-04 | Varias evidencias vacías en un solo envío |

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-27 | Borrador con el pedido y las decisiones de Edwar, y su aclaración: cambiar una evidencia existente por versiones en el repositorio (punto 6, PA-06, CA-10, CA-11) |
| 1.0 | 2026-11-27 | Aprobada por Edwar. PA-01 resuelta distinto: en Derivaciones carga Edición en adelante; el cierre a mano sigue solo del superadministrador (RN-32 solo se amplía; CA-12 nuevo). CCTV sin cambios, con la razón explícita (DOC-33, CA-19). PA-06 resuelta y C-05 retirado. Demás PA con su propuesta. CA-01 y CA-03 ajustados |
