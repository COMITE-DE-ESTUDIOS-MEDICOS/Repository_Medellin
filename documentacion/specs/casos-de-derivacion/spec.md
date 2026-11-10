# Spec: Casos de derivación creados por la oficina

| | |
|---|---|
| Historia | DOC-20 |
| Tipo | Historia de usuario · cambia RN-29 a RN-36 y abre una excepción a la vía pública |
| Estado | **Aprobada** |
| Versión | 0.3 |
| Fuentes | Pedido de Edwar y respuestas a P-1 a P-8 del 10-11-2026 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-10 |

## Problema

Hoy la persona de campo crea la derivación desde su enlace. Ella misma escribe la entidad, la
descripción, la ubicación y la hora del requerimiento. Las 3 horas van desde esa hora, que
escribe a mano, hasta que un líder la responde en Documenta. Esto trae tres problemas:

- la hora la escribe la persona;
- la respuesta se registra tarde, y eso dispara el tiempo, aunque el caso se haya atendido antes;
- cuando la oficina recibe el caso, no queda registro de ese momento.

## Comportamiento

### 1. La oficina crea el caso

Edición, Coordinación y Administración entran a «Atención territorial → Casos → **Nuevo
caso**». La pantalla está pensada para el celular.

- **Mensaje para la atención** (texto libre, obligatorio).
- **Quién lo envía** (obligatorio): Alcaldía · Autoridades locales del sector · Otra (si es
  Otra, se escribe cuál).
- **Ubicación** (obligatoria).
- **Soporte del caso** (obligatorio, uno): foto con la cámara, imagen o PDF del dispositivo.
- El componente es el de la dependencia activa (se elige si hay más de uno).

Al guardar, el caso recibe su código **DC-001** y **el tiempo empieza a correr**, con la hora
del servidor. La pantalla muestra el código en grande para que se lo dicten a la persona de
campo.

### 2. La persona de campo atiende el caso

Usa su enlace de derivaciones (`/d/{token}`). El título deja de ser «Registrar derivación»
y pasa a **«Atender caso»**.

1. Escribe su **cédula** (como hoy: registrada, activa y **en turno**, RN-43).
2. Escribe el **código exacto del caso**: solo los dígitos, porque el «DC-» ya está puesto.
3. Ve el caso: mensaje, quién lo envía, ubicación y el soporte.
4. Elige el **nodo que atiende**, que va en el informe. Por defecto aparece su nodo habitual.
5. Sube las **3 evidencias fotográficas**: foto en vivo con el sello de fecha, hora y
   ubicación, o una imagen del dispositivo (el mismo campo de hoy).
6. Responde **«¿Se cumplió con el requerimiento?» Sí / No**. Con No, la observación es
   obligatoria.
7. Si pasaron más de 3 h desde la creación, escribe la **justificación**.
8. Pulsa **Enviar atención**. **Esa es la marca de cierre**, con la hora del servidor: el
   tiempo se cuenta entre la creación y este envío.

Lo importante del informe es **quién hizo la gestión**: la persona de campo (cédula, nombre,
cargo) y el nodo.

Un caso **se atiende una sola vez**, aunque la respuesta sea «No». Si hace falta volver, la
oficina crea un caso nuevo.

### 3. El panel: trazabilidad, no respuesta

La lista y la ficha del caso ya no tienen «Responder». Muestran:

- código y estado;
- quién lo creó y cuándo;
- quién lo atendió (persona de campo y nodo) y cuándo;
- el tiempo y si cumplió la meta de 3 h;
- el «¿Se cumplió?» y las observaciones y justificación;
- el soporte y las 3 evidencias.

Los indicadores siguen (abiertos, vencidos, cumplimiento, nodos con casos abiertos) y se
agrega cuántos casos atendió cada persona de campo y su tiempo promedio. El Excel y el ZIP
conservan su forma, con las columnas nuevas.

### 4. Correcciones

- **Edición y Coordinación** corrigen solo los textos (mensaje, quién lo envía, ubicación,
  observación). **No tocan las horas ni el Sí/No**: son automáticos.
- **El superadministrador de la plataforma** (solo esa cuenta, no el rol Administración de una
  dependencia), para el plan de contingencia, puede corregir también la hora de creación, la
  hora de cierre y el Sí/No, con **motivo obligatorio**. Puede además cerrar a mano un caso
  que se atendió y no se pudo enviar, eligiendo a la persona de campo. Todo queda en la
  auditoría con el valor anterior y el nuevo.
- **Las derivaciones anteriores** (flujo viejo) se quedan como están, marcadas «registro
  anterior», en la lista y en el Excel. Solo el superadministrador puede ajustarles la hora de
  cierre. Hoy cualquier editor puede hacerlo; eso se quita.

### 5. Avisos (campana)

Se acaba el recordatorio cada 30 minutos, que tenía sentido cuando la oficina debía
responder.

- **Caso vencido**: a las 3 h sin atención, un solo aviso «DC-012 venció sin atención» a
  Edición y Coordinación de la dependencia.
- **Caso atendido**: un aviso a quien creó el caso, con el tiempo y si se cumplió. Este aviso
  retira el de vencido.

### Estados

| Estado | Qué significa | Lo cambia |
|---|---|---|
| Abierto | Creado; el tiempo corre | Al crearlo, Edición en adelante |
| Abierto · vencido | Pasaron 3 h sin atención (se calcula, no se guarda) | — |
| Atendido | Se envió la atención: el tiempo se detuvo | La persona de campo; el superadministrador en contingencia |
| Eliminado | Borrado lógico, sale del reporte | Coordinación en adelante (como hoy) |

## Seguridad: ver el caso desde la vía pública

Hoy la vía pública nunca muestra datos personales al navegador. Aquí sí hay que mostrar el
caso, y el mensaje y el soporte pueden traer datos del ciudadano. Edwar autorizó la excepción
(P-2) con estas condiciones:

1. **Solo en el enlace de derivaciones.** Un enlace de turno, o uno vencido o revocado, no
   consulta casos.
2. **Primero la identidad.** La cédula tiene que estar registrada y activa en la dependencia
   del enlace, y en turno. Sin eso no se consulta ningún código.
3. **Código exacto** del caso, de la misma dependencia y componente del enlace, y **abierto**.
   Un número que no existe, uno ya atendido o uno de otro componente reciben **el mismo
   mensaje**: «No hay un caso abierto con ese número». Así no se puede sondear qué números
   existen.
4. **La consulta va por POST**, no en la URL, para que el código no quede en el historial
   ni en los registros del servidor. Se limita por IP y por cédula (10 consultas cada 10
   min). **Cada consulta queda en la auditoría**: quién vio qué caso y desde qué IP.
5. **El soporte se sirve por una ruta firmada que vence a los 10 minutos**, ligada al caso
   y a ese enlace, con `Cache-Control: no-store`. Nunca es un archivo público.
6. Se muestra **solo** lo necesario para atender: mensaje, quién lo envía, ubicación y
   soporte. No se muestra quién lo creó ni otros casos.
7. Al enviar, el servidor **vuelve a validar** todo lo anterior. No confía en lo que el
   navegador ya consultó.
8. Nada del caso se guarda en el almacenamiento del navegador.

*Riesgo que queda:* los códigos son consecutivos y se pueden adivinar. Lo mitigan los puntos
2 a 4: hace falta una cédula real en turno, hay un límite de intentos y queda el rastro. Si
algún día no basta, se agrega una clave corta por caso. Va en «Candidatos».

## Duplicados

| Riesgo | Cómo se evita |
|---|---|
| Dos casos con el mismo código | El código lo asigna el servidor dentro de una transacción con bloqueo, y la base tiene un índice único (dependencia, número). Ante un choque, se reintenta |
| Doble toque en «Crear caso» | El formulario lleva una clave de un solo uso: el segundo envío con la misma clave devuelve el caso ya creado, no uno nuevo |
| Dos personas, o dos toques, atienden el mismo caso | Al enviar, el caso se bloquea en una transacción y solo se cierra si sigue abierto. El segundo envío recibe «Este caso ya fue atendido». Las fotos de un envío rechazado se borran |
| El mismo caso creado dos veces a mano | Al crear, si hay otro caso abierto con la misma ubicación y el mismo «quién lo envía» en los últimos 30 min, se avisa (se puede seguir) |

El código no se reinicia: DC-001 … DC-999, DC-1000, DC-25000. Se muestra con al menos 3
dígitos.

## Criterios de aceptación

| CA | Criterio |
|---|---|
| CA-01 | Edición crea un caso y recibe DC-NNN consecutivo; el tiempo empieza a la hora del servidor |
| CA-02 | El código nunca se repite: dos creaciones seguidas dan números distintos, la misma clave de formulario devuelve el mismo caso y pasado DC-999 sigue DC-1000 |
| CA-03 | Sin cédula registrada y en turno no se consulta ningún caso |
| CA-04 | Un número inexistente, uno ya atendido y uno de otro componente reciben el mismo mensaje; cada consulta se audita y hay límite de intentos |
| CA-05 | El soporte se ve solo por una URL firmada y vencible |
| CA-06 | Atender exige el nodo, las 3 evidencias y el Sí/No (con observación si es No); el envío cierra el caso a la hora del servidor |
| CA-07 | Pasadas las 3 h se exige justificación |
| CA-08 | Un caso no se atiende dos veces; el segundo envío no deja archivos sueltos |
| CA-09 | Edición, Coordinación y Administración no cambian horas ni el Sí/No; el superadministrador sí, con motivo y auditoría |
| CA-10 | La ficha y el Excel muestran quién creó, quién atendió (persona y nodo), las horas, el tiempo y el cumplimiento |
| CA-11 | Al vencerse llega un solo aviso; al atenderse, el creador recibe el suyo y se retira el de vencido |
| CA-12 | Las derivaciones anteriores siguen en la lista y el Excel; su hora de cierre solo la ajusta el superadministrador |

## Reglas que cambian

- RN-29: ahora el caso lo crea la oficina y la persona de campo lo atiende.
- RN-30: el tiempo corre desde la creación.
- RN-32: lo que detiene el tiempo es el envío de la atención.
- RN-33: los avisos (vencido y atendido).
- RN-34: la trazabilidad.
- RN-36: las columnas del Excel.
- Una RN nueva para la excepción de la vía pública, con las 8 condiciones de seguridad.

## Candidatos para la siguiente versión

- Una clave corta por caso, además del código, si el consecutivo resulta fácil de sondear.
- Asignar el caso a una persona de campo concreta, que lo vea sin escribir el código.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-10 | Borrador para revisión |
| 0.2 | 2026-11-10 | Con las respuestas de Edwar a P-1 a P-8, seguridad y duplicados |
| 0.3 | 2026-11-10 | La edición de horas y del Sí/No es solo del superadministrador (Edwar) |
