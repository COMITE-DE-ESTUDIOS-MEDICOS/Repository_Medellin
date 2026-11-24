# Spec: Acceso por componente · componentes Básica y Estabilización

| | |
|---|---|
| Historia | DOC-25 |
| Tipo | Historia de usuario · cambia RN-01, RN-02, RN-03 y a quién avisan RN-23, RN-25, RN-33 y RN-45; agrega RN-49 a RN-51 |
| Estado | **Aprobada** |
| Versión | 1.3 |
| Fuentes | Pedido y decisiones de Edwar, 2026-11-20 y 2026-11-23. Documento «Sistema_HC_Especificacion_v1_6» (de otra persona; **aún no está en `fuentes/`**, se propone F-12), secciones 1, 7 y 8, de las que aquí solo se usan las decisiones que Edwar tomó. F-06 (reunión del 9-10-2026: componentes Calle, Básica, Estabilización) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-23 |

## Problema

Documenta trabaja hoy solo para el componente **Calle**. Van a entrar **Básica** y
**Estabilización** (donde los habitantes de calle pasan a una especie de internado y reciben
medicación), con módulos propios que se especifican después.

Hoy el permiso se decide solo por el **rol en la dependencia**: cualquiera con rol Edición en
Inclusión Social ve Turnos, Derivaciones y Supervisión, y cualquiera con cuenta ve Documentos.
No existe ninguna relación entre un usuario y un componente. Si mañana se crea una cuenta para
alguien de Básica, vería todo lo de Calle.

Edwar no quiere que una persona de un componente vea los módulos de otro: **primero el acceso
se decide por componente y, dentro de eso, por rol.**

## Comportamiento

### 1. Cada componente tiene sus módulos

Qué módulos usa un componente es un **dato del componente**, no algo fijo en el programa
(constitución III). Los módulos que existen hoy:

| Módulo | Qué pantallas trae |
|---|---|
| Documentos | Documentos y carpetas, Enlaces de carga, Tipos de documento, Grupos de custodia |
| Turnos | Enlaces por cédula, Personas de campo, En turno ahora, Novedades, Marcaciones y reporte, Tipos de turno, Puntos críticos |
| Derivaciones | Derivaciones (casos, indicadores, reporte) |
| Supervisión | Supervisión (registros, analítica, reporte) |

Al arrancar:

| Componente | Módulos |
|---|---|
| Calle | Documentos, Turnos, Derivaciones, Supervisión |
| Básica | Ninguno, hasta que existan los de Habitante de Calle |
| Estabilización | Ninguno, hasta que existan los de Habitante de Calle |

- **Documentos es solo de Calle** (decisión de Edwar). Como los documentos y las carpetas no
  pertenecen a un componente, este módulo **no se puede encender en un segundo componente**
  con esta spec: hacerlo mostraría los documentos de Calle. Si otro componente lo necesita, va
  en una spec propia.
- Los módulos de un componente los cambia **solo el superadministrador** (ver PA-07).

### 2. Se crean Básica y Estabilización

- Quedan creados y activos en Inclusión Social, **sin módulos y sin nodos**, con las reglas de
  turno de fábrica (no las usan mientras no tengan el módulo Turnos).
- Aparecen en «Componentes y nodos» y se pueden elegir al crear o editar un usuario.
- Calle no cambia: sus nodos, reglas, cargos, enlaces y personas quedan como están.

### 3. Cada usuario pertenece a un componente

- Al dar acceso a una persona en una dependencia se elige, además del rol, **su componente**.
  Es obligatorio y es **uno solo** (PA-02).
- **Los usuarios que ya existen quedan en Calle**, con el rol que tienen (PA-04). Para ellos
  nada cambia.
- Cambiar a alguien de componente queda en la auditoría, como cualquier cambio de rol.
- Una persona con acceso a dos dependencias tiene un componente en cada una.
- Si por algún motivo un usuario queda sin componente, entra pero no ve ningún módulo, y la
  pantalla le dice que pida a su coordinación que se lo asignen.

### 4. Qué ve cada quien: primero el componente, después el rol

Para abrir una pantalla o un dato se tienen que cumplir **las dos** condiciones:

1. El módulo está entre los del **componente del usuario**.
2. Su **rol** alcanza para esa acción (la tabla de roles de hoy no cambia).

Y dentro de un módulo, el usuario ve y trabaja **solo los datos de su componente**: personas de
campo, enlaces por cédula, marcaciones, novedades, tipos de turno, puntos críticos, nodos,
derivaciones y supervisiones.

- **El menú** muestra únicamente los módulos de su componente. Un grupo sin módulos no aparece.
- **No basta con esconder el menú:** quien escriba la dirección de una pantalla, de un registro,
  de una descarga o de un reporte de un módulo que su componente no tiene, o de un dato de otro
  componente, recibe un **rechazo** («No tienes permiso» o «No encontrado») y nunca el dato.
  Lo mismo vale para los botones y acciones dentro de las pantallas.
- Las listas para elegir componente o nodo (filtros, formularios) solo ofrecen lo de su
  componente.
- **Al entrar**, cada quien llega al primer módulo de su componente. Hoy todos llegan a
  Documentos; alguien de Básica o Estabilización llega a una pantalla de bienvenida que dice
  que su componente todavía no tiene módulos activos.
- Perfil, campana, manual y cambio de tema siguen disponibles para todos.

### 5. El coordinador líder es el rol Administración

Decisión de Edwar (2026-11-23): **el coordinador líder es quien tiene el rol Administración.**
No es una marca aparte ni un rol nuevo.

- **Administración no pertenece a ningún componente y tiene acceso total** a todos los
  componentes de la dependencia. El documento de origen lo llama «Coordinador general».
- Es quien **da los permisos**: crea usuarios, les asigna componente y rol, y los mueve de
  componente. Así el soporte del día a día (cuentas, accesos) queda en la coordinación y no en
  el superadministrador.
- Administración elige en qué componente está parado y ve lo que vería una persona de ese
  componente con acceso total (PA-05).
- El **superadministrador** sigue viendo todo en todas las dependencias, como hoy.

### 6. Coordinación, Edición y Lectura: atadas a su componente

**Coordinación** es la coordinación **de un componente** (el «Coordinador de componente» del
documento de origen): igual que Edición y Lectura, ve y trabaja solo su componente. Los módulos
de Administración quedan así:

| Pantalla | Quién la ve | Qué ve |
|---|---|---|
| Usuarios | Coordinación y Administración | Coordinación: solo los usuarios de su componente; crea usuarios solo en su componente y no puede pasarlos a otro. Administración: todos los usuarios, en cualquier componente |
| Componentes y nodos | Coordinación y Administración | Coordinación: solo su componente (sus nodos y sus reglas). Administración: todos. Crear un componente y cambiar sus módulos: solo el superadministrador |
| Tipos de documento, Grupos de custodia | Coordinación de Calle y Administración | Como hoy (son del módulo Documentos) |
| Auditoría | Coordinación y Administración | Coordinación: lo de su componente, también lo de la vía pública y lo automático (PA-06). Administración: todo |
| Enviar aviso | Administración | A los usuarios de los componentes que elija |
| Manual de usuario | Todos | Como hoy |

Sigue valiendo que nadie gestiona a un usuario con un rol mayor que el suyo (RN-02): Coordinación
no crea ni edita usuarios de Administración. El rol Administración lo da solo el superadministrador.

### 7. Avisos por la campana

Los avisos que hoy van «a Edición y Coordinación de la dependencia» pasan a ir a los usuarios
de esos roles **del componente del registro**, más el coordinador líder:

- novedades de turno para quien aprueba (RN-23) y «no registró entrada» (RN-25);
- derivación vencida (RN-33);
- supervisión importante (RN-45).

Los avisos de versión (RN-37) siguen llegando a todos.

### 8. Lo que no cambia

- **Personas de campo:** siguen sin cuenta, con su cédula única por dependencia y su componente
  (RN-14). No se les asigna nada nuevo.
- **Enlaces públicos por cédula** (`/t/…`, `/d/…`) y de supervisión: ya son de un componente.
  No cambia su dirección, ni los QR impresos, ni lo que muestran (RN-15, RN-48).
- **Enlaces de carga** de documentos: siguen siendo del módulo Documentos.
- **Roles:** Lectura, Edición, Coordinación y Administración conservan lo que cada uno puede
  hacer; solo cambia sobre qué componente.
- **Ingreso:** sigue siendo con Authentik (RN-03).
- **Calle:** quienes ya lo usan ven el mismo menú, las mismas pantallas y los mismos datos.

## Fuera de alcance

Los módulos del sistema «Habitante de Calle» van en sus propias specs: Usuarios HC (DOC-26),
Ingresos y egresos (DOC-27), Dashboard (DOC-28), Reportes (DOC-29), Cierres (DOC-30) y
Unificación (DOC-31). Esta spec solo deja listo el lugar donde se van a encender, en Básica y
Estabilización. El calendario está en
[`habitante-de-calle/plan-de-trabajo.md`](../habitante-de-calle/plan-de-trabajo.md).

**Excepción que piden esas specs:** DOC-26 necesita que la búsqueda de fichas de Usuarios HC
cruce componentes, mostrando solo la identificación (su PA-01). Si Edwar la aprueba, es la única
excepción a «cada usuario ve solo los datos de su componente» del §4.

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | ¿Coordinación y Administración quedan atadas a un componente? | **Resuelta (Edwar, 2026-11-23):** Coordinación sí, es la coordinación de un componente. Administración no: es el coordinador líder, sin componente y con acceso total (§5) | — |
| PA-02 | ¿Un usuario puede pertenecer a dos componentes a la vez? | **Resuelta (Edwar, 2026-11-23): no, uno solo.** Quien necesite ver varios es de Administración | — |
| PA-03 | ¿Quién da el rol Administración (coordinador líder)? | **Resuelta (Edwar, 2026-11-23): solo el superadministrador.** Los usuarios que hoy tienen Administración siguen siéndolo; Edwar revisa la lista antes de desplegar, porque pasan a ver todos los componentes | — |
| PA-04 | ¿Los usuarios actuales de Lectura, Edición y Coordinación pasan a Calle? | **Resuelta (Edwar, 2026-11-23): sí, todos a Calle.** Administración no lleva componente | — |
| PA-05 | ¿Cómo trabaja Administración (el coordinador líder) con varios componentes? | **Aprobada con la spec (propuesta por defecto):** un selector de componente en el menú, debajo del de dependencia; parado en uno, ve lo de ese componente. Sin vista «todos juntos» por ahora | — |
| PA-06 | Auditoría: ¿qué ve Coordinación de un componente? Los registros de auditoría no están ligados a un componente | **Cambiada por Edwar (2026-11-24):** cada registro guarda su componente; Coordinación ve los de su componente, incluidos los de la vía pública y los automáticos (los anteriores a DOC-25, sin componente, los ve el componente con Documentos); Administración y el superadministrador, todo | — |
| PA-07 | ¿Quién crea componentes y decide sus módulos? ¿Cambian los catálogos que ya existen? | **Resuelta (Edwar, 2026-11-23): crear componentes y decidir sus módulos es solo del superadministrador.** Los catálogos que ya existen (tipos de turno, puntos críticos, cargos, tipos de documento) siguen como hoy; «solo Edwar cambia las listas» aplica a las listas de Habitante de Calle | — |
| PA-08 | El documento «Sistema_HC_Especificacion_v1_6» y las decisiones del 2026-11-20 no están en `documentacion/fuentes/` | Registrarlos como fuente (F-12) y como decisiones (D-NN) antes de aprobar, para poder citarlos | No |
| PA-09 | ¿Básica y Estabilización necesitan nodos o reglas propias desde ya? | No: se crean vacíos y se completan con las specs de Habitante de Calle | No |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Existen los componentes Básica y Estabilización en Inclusión Social, activos y sin módulos; Calle tiene Documentos, Turnos, Derivaciones y Supervisión | Por construir |
| CA-02 | Después del cambio, los usuarios que ya existían con Lectura, Edición o Coordinación pertenecen a Calle; los de Administración no tienen componente; todos conservan su rol y su «Aprueba novedades» | Por construir |
| CA-03 | Un usuario de Calle (Lectura, Edición, Coordinación y Administración) ve el mismo menú y abre las mismas pantallas que antes; todas las pruebas que ya existían siguen pasando | Por construir |
| CA-04 | Un usuario de Básica o de Estabilización, con cualquier rol, no ve en el menú Documentos, Turnos, Atención territorial ni las pantallas de Administración que dependen de Documentos, y al entrar llega a la pantalla de bienvenida | Por construir |
| CA-05 | Ese mismo usuario recibe rechazo al abrir por dirección directa cada pantalla de Documentos, Turnos, Derivaciones y Supervisión, incluidos sus reportes y descargas | Por construir |
| CA-06 | Un usuario de un componente recibe rechazo al abrir, corregir o descargar un dato de otro componente (persona de campo, marcación, novedad, enlace por cédula, derivación, supervisión, tipo de turno, punto crítico), y esos datos no salen en sus listados, contadores ni reportes | Por construir |
| CA-07 | Un documento, una carpeta o un enlace de carga no se pueden abrir ni descargar desde un componente sin el módulo Documentos | Por construir |
| CA-08 | Al crear o editar un usuario de Lectura, Edición o Coordinación el componente es obligatorio; Coordinación solo puede elegir el suyo y solo ve a los usuarios de su componente; Administración elige cualquiera; el cambio queda en la auditoría | Por construir |
| CA-09 | Un usuario de Administración no tiene componente, ve y trabaja todos los componentes de la dependencia y administra los usuarios de todos; bajarlo a Coordinación exige asignarle un componente y lo deja solo en él | Por construir |
| CA-10 | Solo el superadministrador da el rol Administración, crea componentes y cambia los módulos de un componente; a los demás (incluida Administración) se les rechaza | Por construir |
| CA-11 | Encender un módulo en un componente lo hace aparecer en el menú de sus usuarios y apagarlo lo quita y rechaza el acceso, sin cambiar el programa. Documentos no se puede encender en un segundo componente | Por construir |
| CA-12 | Los avisos de novedades de turno, «no registró entrada», derivación vencida y supervisión importante llegan solo a los usuarios del componente del registro y al coordinador líder | Por construir |
| CA-13 | Un aviso enviado por Administración llega solo a los usuarios de su componente; la Auditoría de Coordinación muestra solo acciones de usuarios de su componente | Por construir |
| CA-14 | Los enlaces públicos por cédula de turnos, derivaciones y supervisión funcionan igual que antes, con las mismas direcciones | Por construir (y las pruebas que ya existen) |
| CA-15 | Un usuario sin componente no abre ningún módulo y ve el mensaje para pedir que se lo asignen; el superadministrador sigue viendo todo | Por construir |

## Reglas que cambian

Esta spec no modifica `docs/context/`; al aprobarse, el contexto debe recibir:

- **RN-01:** además de la dependencia, cada usuario ve solo los datos de **su componente**.
- **RN-02:** el rol sigue siendo por dependencia. Lectura, Edición y Coordinación se ejercen
  dentro de un componente; Coordinación deja de ver toda la dependencia. Administración es el
  coordinador líder: sin componente, acceso total y administra los usuarios.
- **RN-03:** dar acceso a una persona exige elegir su componente.
- **RN-23, RN-25, RN-33, RN-45:** los avisos van a los usuarios del componente del registro y al
  coordinador líder, no a toda la dependencia.
- **Nuevas (propuestas):**
  - **RN-49. Módulos por componente.** Cada componente tiene su lista de módulos; un usuario
    solo abre los módulos de su componente y, dentro de ellos, lo que su rol permite. La lista
    la cambia solo el superadministrador.
  - **RN-50. Coordinador líder.** Es el rol Administración: no pertenece a ningún componente,
    ve y trabaja todos los de la dependencia y da los permisos (crea usuarios y les asigna
    componente y rol).
  - **RN-51. Documentos es de Calle.** El repositorio documental pertenece a un solo componente
    mientras documentos y carpetas no tengan componente.
- **`stakeholders.md`:** la tabla de actores y roles (Coordinación «de la dependencia» pasa a ser
  del componente; se agrega el coordinador líder).
- **`glossary.md`:**
  - **Usuario HC:** habitante de calle atendido. El módulo también se llama «Usuarios HC».
  - **Usuario:** quien tiene cuenta en Documenta (el «Funcionario» del documento de origen). No
    cambia.
  - **Persona de campo:** no cambia.
  - **Coordinador líder** y **Módulo**: términos nuevos. En **Componente** se agrega que tiene
    sus módulos.
- **Constitución III:** no se contradice; se sugiere que diga también «cada componente ve solo lo
  suyo». Lo decide Edwar.

## Decisiones (Edwar, 2026-11-20)

- El sistema «Habitante de Calle» se construye **dentro de Documenta**, no como aplicación aparte.
- El ingreso sigue siendo con **Authentik**. El ingreso con Google que pedía el documento no se
  adopta.
- **No habrá perfil «Parametrizador».** Agregar o cambiar opciones de las listas lo hace solo
  Edwar como superadministrador.
- Solo existe **una figura que supervisa varios componentes**: el coordinador líder. Todos los
  demás usuarios ven únicamente su componente.
- El módulo **Documentos** (documentos, carpetas, enlaces de carga) es **solo de Calle**.
- Al habitante de calle atendido se le llama **«Usuario HC»** y el módulo se llamará «Usuarios
  HC». Quien tiene cuenta sigue siendo «Usuario» y «Persona de campo» no cambia.
- El acceso a los módulos se decide **primero por componente y después por rol**.

## Decisiones (Edwar, 2026-11-23)

- **Coordinación queda atada a su componente:** es la coordinación de un componente.
- **Administración no pertenece a ningún componente y tiene acceso total:** es el coordinador
  líder. Da los permisos y lleva el soporte de cuentas y accesos, para que no recaiga en el
  superadministrador («no quiero estar brindando soporte dentro del sistema»).
- No hay marca aparte de coordinador líder.
- **Los módulos que existen hoy (Documentos, Turnos, Derivaciones, Supervisión) son de Calle,**
  incluidos sus enlaces públicos: por cédula (turnos, derivaciones, supervisión) y de carga de
  documentos.
  Los casos DC siguen con su numeración de siempre, porque Derivaciones es solo de Calle. Si
  Básica o Estabilización necesitan algo parecido, se hace un módulo nuevo con su spec,
  reutilizando la lógica (Edwar, 2026-11-24).
- El rol **Administración lo da solo el superadministrador**.
- **Los usuarios actuales** de Lectura, Edición y Coordinación **pasan a Calle**.
- **Un solo componente por usuario.**
- **Crear componentes y decidir sus módulos es solo del superadministrador.**
- PA-05 (selector de componente) y PA-06 (auditoría por componente) se aprueban con la propuesta de la spec.

## Candidatos para la siguiente versión

- Specs de Habitante de Calle: Usuarios HC, Ingresos y egresos, Dashboard, reportes, cierres y
  listas de opciones (solo superadministrador).
- Los Usuarios HC traen datos sensibles (salud, medicación): la spec que los cree debe decir su
  tratamiento y retención (constitución V, Ley 1581 de 2012).
- Documentos por componente, si Básica o Estabilización necesitan repositorio propio.
- Vista «todos los componentes juntos» para el coordinador líder.
- Una persona de campo que trabaje en dos componentes (hoy la cédula es única por dependencia y
  tiene un solo componente).
- Pantalla para que el superadministrador encienda y apague módulos, si cambiarlos se vuelve
  frecuente.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.3 | 2026-11-24 | Decisiones de Edwar tras la revisión de ACC-11: la auditoría guarda el componente (PA-06), y Administración recibe los avisos manuales (P-04 del plan) |
| 1.2 | 2026-11-24 | Las «Reglas que cambian» pasan a `docs/context/` (RN-01, RN-02, RN-03, RN-23, RN-25, RN-33, RN-45; nuevas RN-49 a RN-51; actores y glosario) con ACC-15. Lo que depende de ACC-07 a ACC-10 queda marcado como pendiente allí |
| 1.1 | 2026-11-24 | Aclaración de Edwar: los módulos de hoy son de Calle y la numeración DC no cambia; otro componente que necesite algo así tendrá su propio módulo |
| 1.0 | 2026-11-23 | Aprobada por Edwar: Administración la da solo el superadministrador, usuarios actuales a Calle, un componente por usuario, componentes y módulos solo del superadministrador; PA-05 y PA-06 con la propuesta |
| 0.3 | 2026-11-23 | Decisiones de Edwar: Coordinación atada a su componente; Administración es el coordinador líder (sin componente, acceso total, da los permisos); sin marca aparte. PA-01 resuelta; PA-03 y PA-04 reformuladas |
| 0.2 | 2026-11-20 | Leído el documento v1.6: se enlazan DOC-26 a DOC-31 y el plan de trabajo; se anota la excepción de búsqueda entre componentes y la contradicción sobre el alcance del coordinador general (PA-03) |
| 0.1 | 2026-11-20 | Borrador con el pedido y las decisiones de Edwar |
