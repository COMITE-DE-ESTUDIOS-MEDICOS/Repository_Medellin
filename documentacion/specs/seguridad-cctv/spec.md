# Spec: Seguridad CCTV

| | |
|---|---|
| Historia | DOC-33 |
| Tipo | Historia de usuario · módulo nuevo · cambia RN-01, RN-43, RN-48 y RN-49 · propone RN-67 y RN-68 |
| Estado | **Aprobada** |
| Versión | 1.0 |
| Fuentes | Pedido de Edwar, 2026-11-27, y sus decisiones del mismo día (quién cierra, quién registra, tiempos, evidencias) |
| Base | DOC-20 (casos de derivación), DOC-23 (comuna), DOC-24 (soporte opcional), DOC-25 (módulos por componente) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-27 |

## Problema

En Calle llegan requerimientos de seguridad desde las cámaras, el 123 por Telegram, la Policía
o Espacio público. Hoy se llevan en una hoja de cálculo: no queda quién lo registró, a quién se
le pasó, cuándo se atendió ni con qué evidencias, y no hay cifras por entidad ni por comuna.
Se necesita lo mismo que ya hace Derivaciones, con sus propios campos, sin meta de tiempo y
con un responsable que cierra los casos.

## Comportamiento

### 1. El módulo

- **«Seguridad CCTV»** aparece en el menú, en el grupo **«Atención territorial»**, debajo de
  Derivaciones.
- Es un módulo que se enciende por componente, como los demás (RN-49). Se enciende en
  **Calle**; Básica y Estabilización no lo tienen.
- Todo lo que guarda pertenece a la dependencia y al componente: nadie de otro componente lo
  ve, ni por dirección directa, ni en listas, contadores o reportes (RN-01).

### 2. Responsable de Seguridad CCTV (casilla por usuario)

- En la ficha del usuario hay una casilla **«Responsable de Seguridad CCTV»**, junto a
  «Aprueba novedades de turno». La marcan o desmarcan **Coordinación o Administración**.
  Queda en la auditoría.
- La casilla solo da poder **sobre este módulo**. Sirve con rol Edición o mayor; con Lectura
  no tiene efecto (como «Aprueba novedades», RN-23).
- Ejemplo: Freddy queda con rol Edición y la casilla marcada.

### 3. Quién hace qué

| Acción | Quién |
|---|---|
| Ver la lista, la ficha y el reporte | Edición, Coordinación y Administración del componente |
| Registrar un caso, corregir sus datos, compartir el enlace | Responsable de Seguridad CCTV, Coordinación y Administración |
| Atender el caso | La persona de campo, por el enlace (punto 5) |
| Cerrar el caso | Responsable de Seguridad CCTV, Coordinación y Administración |
| Anular el caso (con motivo) | Coordinación y Administración |

Lectura no entra al módulo. Edición sin la casilla solo consulta.

### 4. Registrar el caso

«Seguridad CCTV → **Nuevo caso**», pensado para el celular:

| Campo | Obligatorio | Cómo |
|---|---|---|
| Número de caso | — | Lo pone el sistema: **CCTV-001**, CCTV-002… Consecutivo por dependencia, nunca se repite ni se reinicia; aparte del DC de derivaciones |
| Entidad que solicita | Sí | Cámaras · 123 Telegram · Policía · Espacio público · Otras (con «Otras», se escribe cuál) |
| Fecha y hora del requerimiento | Sí | Un solo campo con fecha y hora. La escribe quien registra (cuándo llegó el requerimiento). No puede ser futura |
| A quién se le pasa la novedad | Sí | Persona de campo activa del componente, de la lista |
| Nodo que atiende el requerimiento | Sí | Nodo activo del componente |
| Descripción del caso | Sí | Texto, hasta 5000 caracteres |
| Ubicación del requerimiento | Sí | Texto, hasta 500 caracteres |
| Comuna | Sí | La lista de comunas y corregimientos que ya usan los casos (DOC-23) |
| Soporte del requerimiento | No | Una imagen (foto con la cámara o del dispositivo) |

Al guardar, la pantalla muestra el número **en grande** para dictárselo a la persona de campo.
Un doble toque en «Guardar» no crea dos casos.

### 5. Atender el caso (vía pública)

Quien registra comparte un **enlace de Seguridad CCTV** (enlace por cédula con QR, como el de
derivaciones). Se crea desde el módulo; lo crean y lo revocan los mismos que registran.

La persona de campo abre el enlace en el celular y:

1. Escribe su **cédula**: registrada, activa y **en turno** (RN-43). Solo ve el saludo
   recortado («Hola, Jos…»).
2. Escribe el **número del caso**: solo los dígitos, el «CCTV-» ya está puesto.
3. **Ve el caso que va a atender:** la descripción, la ubicación, la comuna y el soporte (si
   hay). Nada más: ni quién lo registró, ni a quién se le pasó, ni la entidad.
4. Sube las **evidencias**: la **#1 obligatoria**, la #2 y la #3 opcionales. Cada una es una
   foto con el sello de fecha, hora y ubicación tomada en el momento, o una imagen del
   dispositivo (el mismo campo de derivaciones, RN-13).
5. Si quiere, escribe la **justificación en caso de no cumplir** (opcional).
6. Pulsa **Enviar atención**.

- **Ver el caso es la misma excepción de derivaciones (RN-48):** solo después de la cédula
  activa y en turno y del número exacto de un caso **Abierto** del mismo componente. La
  consulta va por POST, con el mismo límite de intentos (10 cada 10 min por IP y por cédula) y
  queda auditada. El soporte se abre por una dirección firmada que vence a los 10 min, sin
  caché. El servidor revalida todo al enviar y nada se guarda en el navegador.
- **Nunca datos personales** (RN-15): de la persona de campo, solo el saludo recortado; del
  caso, solo los cuatro datos del punto 3.
- Un número que no existe, de otro componente, ya atendido, cerrado o anulado recibe **el mismo
  mensaje**: «No hay un caso abierto con ese número», y no muestra nada. Hay límite de intentos
  y cada consulta y cada envío quedan en la auditoría (cédula, número, IP).
- Puede atenderlo cualquier persona de campo del componente en turno, no solo a quien se le
  pasó la novedad. La ficha muestra las dos: a quién se le pasó y quién lo atendió.
- Un caso **se atiende una sola vez**. Si dos envíos llegan juntos, el segundo recibe «Este
  caso ya fue atendido» y sus fotos no quedan guardadas.

### 6. Tiempos (solo para trazabilidad)

- **Hora de respuesta:** la del servidor en el momento del envío. Nadie la escribe.
- **Tiempo de respuesta:** hora de respuesta menos fecha y hora del requerimiento, en horas y
  minutos («1 h 25 min»).
- **No hay meta de tiempo**, ni «vencido», ni avisos por tiempo. La justificación nunca se
  exige.

### 7. Cerrar, corregir y anular

- **Cerrar:** solo un caso **Atendido**. Lo cierra el responsable, Coordinación o
  Administración, después de revisar las evidencias. Queda quién y cuándo.
- **Corregir:** mientras el caso no esté cerrado ni anulado, quienes registran corrigen los
  datos del registro y la justificación. Cambiar la fecha y hora del requerimiento exige
  **motivo**, no puede quedar futura ni después de la hora de respuesta, y recalcula el tiempo.
  La hora de respuesta y las evidencias no se cambian. Cada corrección queda en la auditoría con
  el valor anterior y el nuevo.
- **Anular:** Coordinación o Administración, con **motivo obligatorio**, en cualquier estado
  menos anulado. El caso no se borra: queda «Anulado», con quién, cuándo y por qué, y sale de
  los reportes. Sus archivos se conservan.

### 8. Estados

| Estado | Qué significa | Puede pasar a | Quién lo cambia |
|---|---|---|---|
| Abierto | Registrado, espera la atención | Atendido, Anulado | A Atendido: la persona de campo (por el enlace). A Anulado: Coordinación, Administración |
| Atendido | Se envió la atención; el tiempo quedó fijo | Cerrado, Anulado | A Cerrado: responsable, Coordinación, Administración. A Anulado: Coordinación, Administración |
| Cerrado | Revisado y terminado | Anulado | Coordinación, Administración |
| Anulado | Retirado con motivo; no cuenta en reportes | — | — |

### 9. Lista y ficha

- **Lista:** número, estado, entidad, fecha del requerimiento, comuna, nodo, a quién se le
  pasó, tiempo de respuesta. Filtros: estado, entidad, comuna, nodo y rango de fechas del
  requerimiento. Paginada como la de casos (25, 50, 100).
- **Ficha:** todos los campos; quién lo registró y cuándo; quién lo atendió (cédula, nombre,
  cargo) y cuándo; quién lo cerró o anuló y cuándo; el soporte y las evidencias, que se abren
  por un control con permiso y auditoría (RN-06).
- **Las imágenes** (soporte y evidencias) dentro de la aplicación las ven **solo** los
  responsables de Seguridad CCTV y Coordinación y Administración del componente: quedan en la
  carpeta «Seguridad CCTV · Calle», con custodia del grupo de responsables. Edición sin la
  casilla ve la ficha sin abrir las imágenes. En el enlace, quien atiende ve solo el soporte
  del caso que atiende.

### 10. Reporte ejecutivo

Pantalla «Seguridad CCTV → **Reporte**», con rango de fechas del requerimiento (por defecto,
el mes en curso). Los anulados no cuentan.

- **Estados:** casos abiertos, atendidos y cerrados, y el total.
- **Tiempos de respuesta** (de los atendidos y cerrados): promedio, mediana, mínimo y máximo,
  en horas y minutos.
- **Por entidad:** cantidad de casos de cada una, con su tiempo promedio.
- **Por comuna:** cantidad de casos de cada comuna, con su tiempo promedio.

**Excel**, como el de derivaciones (encabezado negro, fotos dentro de sus celdas): una hoja
«Casos» con una fila por caso del rango y todas las columnas de la ficha, y una hoja «Resumen»
con las cifras de la pantalla.

## Preguntas abiertas

Todas resueltas por Edwar al aprobar (2026-11-27).

| PA | Pregunta | Respuesta | Bloquea |
|---|---|---|---|
| PA-01 | ¿Cómo se ve el número de caso? | Confirmada: **CCTV-001**, consecutivo por dependencia, aparte de DC | No |
| PA-02 | ¿«A quién se le pasa la novedad» es una persona de campo de la lista o texto libre? | Persona de campo activa del componente, de la lista | No |
| PA-03 | ¿Solo puede atender la persona a quien se le pasó la novedad? | Confirmada: cualquier persona de campo del componente en turno; la ficha muestra las dos | No |
| PA-04 | ¿El enlace debe mostrar la descripción y el soporte del caso, como en derivaciones? | **Cambia: sí.** Después de cédula en turno y número de caso, muestra la descripción, la ubicación, la comuna y el soporte (si hay). Nunca datos personales. Entra en la misma excepción de derivaciones (RN-48) | No |
| PA-05 | ¿El nodo lo elige quien registra o la persona de campo al atender? | Quien registra, obligatorio | No |
| PA-06 | ¿Quién atiende debe estar en turno? | Sí, como en derivaciones (RN-43) | No |
| PA-07 | ¿Edición sin la casilla ve el módulo? ¿Y Lectura? | Edición consulta (lista, ficha, reporte); Lectura no entra | No |
| PA-08 | ¿Las fotos pueden venir de la galería o solo de la cámara? | Las dos, como en derivaciones | No |
| PA-09 | ¿Dónde quedan los archivos y quién ve las imágenes? | Confirmada: carpeta «Seguridad CCTV · Calle» con custodia del grupo de responsables. Las ven solo los responsables, Coordinación y Administración del componente; en el enlace, quien atiende ve solo el soporte de su caso | No |
| PA-10 | ¿Cuánto tiempo se guardan las imágenes de cámaras? (constitución V, Ley 1581) | El mismo plazo que se defina para las fotos de turnos (PA-10 del dominio) | No |
| PA-11 | ¿Se avisa por la campana al atender un caso? | No en esta versión; la lista filtra «Atendido» (ver C-01) | No |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | El módulo sale en «Atención territorial» solo en un componente que lo tiene encendido; sin él, la lista, la ficha, el reporte y el Excel dan «No tienes permiso», también por dirección directa | Por construir |
| CA-02 | Un caso de otro componente no sale en la lista, el reporte ni el Excel, y su ficha da «No encontrado» o «No tienes permiso» | Por construir |
| CA-03 | Coordinación y Administración marcan la casilla «Responsable de Seguridad CCTV»; Edición no puede marcarla; el cambio queda auditado | Por construir |
| CA-04 | Edición con la casilla registra, comparte el enlace y cierra; Edición sin la casilla solo consulta; Lectura no entra; la casilla no da ningún permiso fuera del módulo | Por construir |
| CA-05 | Registrar exige entidad (y el texto con «Otras»), fecha y hora no futura, persona de campo y nodo activos del componente, descripción, ubicación y comuna de la lista; el soporte es opcional y solo imagen | Por construir |
| CA-06 | Cada caso recibe CCTV-NNN consecutivo y sin repetir, independiente de DC; el doble envío del formulario devuelve el mismo caso | Por construir |
| CA-07 | En el enlace, una cédula no registrada, inactiva o fuera de turno no atiende ni ve ningún caso; con una válida solo se ve el saludo recortado. Con cédula válida y el número de un caso Abierto del componente se ven la descripción, la ubicación, la comuna y el soporte (si hay), y nada más: ni la entidad, ni quién registró, ni a quién se le pasó, ni datos personales | Por construir |
| CA-08 | Un número inexistente, de otro componente, atendido, cerrado o anulado recibe el mismo mensaje y no muestra nada del caso; la consulta va por POST con límite de 10 cada 10 min por IP y por cédula; cada consulta y cada envío quedan auditados | Por construir |
| CA-18 | El soporte en el enlace se abre solo por una dirección firmada que vence a los 10 min, sin caché, y solo el del caso consultado; vencida o de otro caso, no abre | Por construir |
| CA-19 | Dentro de la aplicación, el soporte y las evidencias los abren solo el responsable de Seguridad CCTV, Coordinación y Administración del componente; Edición sin la casilla ve la ficha pero no abre las imágenes, tampoco por dirección directa ni por el repositorio | Por construir |
| CA-09 | Atender exige la evidencia #1; la #2, la #3 y la justificación son opcionales; el caso pasa a Atendido con la hora del servidor y quién lo atendió | Por construir |
| CA-10 | El tiempo de respuesta es la hora de respuesta menos la del requerimiento y se muestra en horas y minutos; no hay meta, «vencido» ni justificación exigida | Por construir |
| CA-11 | Un caso no se atiende dos veces; el segundo envío recibe «Este caso ya fue atendido» y no deja archivos guardados | Por construir |
| CA-12 | Solo un caso Atendido se cierra, y solo lo cierran el responsable, Coordinación o Administración | Por construir |
| CA-13 | Corregir la fecha del requerimiento exige motivo, no acepta una fecha futura ni posterior a la respuesta, recalcula el tiempo y audita el antes y el después; un caso cerrado o anulado no se corrige | Por construir |
| CA-14 | Anular exige motivo y es solo de Coordinación y Administración; el caso no se borra, queda Anulado y sale de los reportes | Por construir |
| CA-15 | Registrar, corregir, cerrar, anular, atender y abrir un archivo dejan auditoría | Por construir |
| CA-16 | El reporte, en el rango de fechas, da abiertos, atendidos, cerrados, la cantidad por entidad y por comuna, y el promedio, la mediana, el mínimo y el máximo del tiempo (con un caso de prueba de tiempos conocidos) | Por construir; verificar la consulta también contra MySQL |
| CA-17 | El Excel trae la hoja «Casos» con una fila por caso del rango (sin anulados) y la hoja «Resumen» con las mismas cifras de la pantalla | Por construir |

## Reglas que cambian

- **RN-01:** los casos de Seguridad CCTV se suman a los datos que cada componente ve solo suyos.
- **RN-43:** atender un caso de Seguridad CCTV también exige estar en turno.
- **RN-48:** la excepción deja de ser solo de derivaciones. Queda: «Es la única vez que la
  vía pública muestra contenido de un caso, y solo en el enlace de derivaciones **y en el de
  Seguridad CCTV**». Con las mismas condiciones (cédula activa y en turno, número exacto de un
  caso abierto del mismo componente, mismo mensaje para todo lo demás, POST con límite, cada
  consulta auditada, soporte por dirección firmada de 10 min sin caché, revalidación al enviar,
  nada en el navegador). En Seguridad CCTV se muestra solo la descripción, la ubicación, la
  comuna y el soporte.
- **RN-49:** se agrega el módulo **Seguridad CCTV** (clave `seguridad_cctv`), encendido en Calle.
- **RN-67 (nueva). Seguridad CCTV: quién registra y quién cierra.** Registran, corrigen y
  cierran quienes tienen «Responsable de Seguridad CCTV» (con rol Edición o mayor),
  Coordinación y Administración del componente; anulan, con motivo, Coordinación y
  Administración. La casilla la marcan Coordinación o Administración y no da poder fuera del
  módulo. Atiende cualquier persona de campo del componente en turno, por el enlace. Las
  imágenes del caso las ven solo los responsables, Coordinación y Administración.
- **RN-68 (nueva). Seguridad CCTV: tiempo sin meta.** La fecha y hora del requerimiento la
  escribe quien registra (no futura); la hora de respuesta es la del servidor al enviar la
  atención. El tiempo de respuesta es solo trazabilidad: no hay meta, vencimiento ni avisos.
- **Glosario:** «Caso CCTV», «Responsable de Seguridad CCTV» y el tipo de enlace «Seguridad
  CCTV» en «Enlace por cédula».
- **Estados del dominio:** se agrega la tabla «Caso de Seguridad CCTV» (punto 8).

No cambian RN-29 a RN-36: Derivaciones sigue igual (RN-48 solo suma el enlace de Seguridad
CCTV; para derivaciones nada cambia).

## Decisiones (Edwar, 2026-11-27)

- Módulo aparte, muy parecido a Derivaciones, en «Atención territorial», encendido por
  componente.
- Cierra quien tiene la casilla «Responsable de Seguridad CCTV», y también Coordinación y
  Administración. La casilla solo da poder sobre este módulo.
- Registran quien tiene la casilla y Coordinación y Administración, y comparten un enlace para
  que la persona en territorio atienda con su cédula y el número del caso.
- No hay tiempo meta: el tiempo es solo trazabilidad; la justificación es opcional.
- Evidencias con sello de fecha, hora y ubicación: la #1 obligatoria, la #2 y la #3 opcionales.
- Al aprobar: el enlace sí muestra el caso a quien va a atender (descripción, ubicación, comuna
  y soporte), en la misma excepción RN-48 de derivaciones, nunca datos personales (PA-04). Las
  imágenes en la aplicación, solo responsables, Coordinación y Administración (PA-09).
  CCTV-001 por dependencia (PA-01). Atiende cualquier persona de campo del componente en
  turno (PA-03). Las demás PA, con su respuesta por defecto.

## Candidatos para la siguiente versión

| C | Candidato |
|---|---|
| C-01 | Aviso por la campana al responsable cuando un caso queda Atendido |
| C-02 | Alertas por tiempo (por ejemplo, un caso abierto más de N horas), si se fija una meta |
| C-04 | Comentarios en la ficha, como en los casos de derivación (DOC-23) |
| C-05 | Reabrir un caso cerrado, con motivo |
| C-06 | Cierre a mano en contingencia por el superadministrador, como en derivaciones |
| C-07 | Gráficas en el reporte (por mes, por entidad) |

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-27 | Borrador con el pedido y las decisiones de Edwar |
| 1.0 | 2026-11-27 | Aprobada por Edwar. PA-04 cambia: el enlace muestra el caso (excepción RN-48, se agregan CA-18 y la propuesta de cambio a RN-48; se retira C-03). PA-09: imágenes solo para responsables, Coordinación y Administración (CA-19). PA-01 y PA-03 confirmadas; las demás PA con su respuesta por defecto |
