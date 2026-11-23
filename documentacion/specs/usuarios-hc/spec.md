# Spec: Usuarios HC · ficha, identificación, caracterización, búsqueda y edición con historial

| | |
|---|---|
| Historia | DOC-26 |
| Tipo | Historia de usuario · módulo nuevo · propone RN-52 a RN-56 · abre una excepción a «cada componente ve solo lo suyo» (DOC-25) |
| Estado | **Borrador** |
| Versión | 0.1 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (11-11-2026, de otra persona del Comité; aún sin código de fuente, se propone F-12), secciones 3 a 3.4, 7.2 y 8. Decisiones de Edwar, 2026-11-20 |
| Depende de | DOC-25 (acceso por componente) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Pendiente (Edwar) |

## Problema

Básica y Estabilización reciben a habitantes de calle y hoy no tienen dónde llevar, en
Documenta, quién es cada persona atendida. Cuando alguien llega hay que saber si ya tiene ficha
(aunque la haya abierto otro componente), para no crearla dos veces, y hay que poder completar
sus datos poco a poco, porque muchas veces no se conoce ni su nombre real.

## Vocabulario

- **Usuario HC:** la persona atendida (habitante de calle). El módulo se llama «Usuarios HC».
- **Usuario:** quien tiene cuenta en Documenta (el «Funcionario» del documento de origen).
- **Ficha:** el registro de un Usuario HC. Es **una sola por persona en toda la dependencia**:
  no pertenece a una sede ni a un componente. Su relación con una sede se registra en cada
  estadía (DOC-27).

## Comportamiento

### 1. Listado y búsqueda

- El módulo aparece en los componentes que lo tengan encendido (al arrancar: **Básica y
  Estabilización**; Calle no).
- El listado muestra a **todos los Usuarios HC de la dependencia**, no solo a los del componente
  de quien consulta. Es la única forma de encontrar una ficha que abrió otro componente antes de
  crear otra. **Ver un nombre en la lista no da permiso para ver su caracterización** (ver §6).
- Columnas: nombre completo · tipo y número de documento · número de ficha · componente o
  estado · acciones. En «componente o estado» sale el nombre del componente donde tiene una
  estadía activa; si no tiene ninguna, **Inactivo**. Eso es independiente de si la persona está
  viva o fallecida.
- Búsqueda por **nombre, documento, número de ficha o alias**:
  - nombre, alias y documento aceptan una parte del texto, sin importar mayúsculas ni tildes;
  - el número de ficha se busca exacto;
  - se muestra la foto vigente de la persona, si tiene.
- Filtros: componente · estado (Todos, Activos, Inactivos). Con paginación.
- **Las identidades anteriores siguen sirviendo para buscar:** si a una persona se le corrigió
  el nombre, el documento o la ficha, buscar el dato viejo lleva a su ficha actual, y la pantalla
  dice que la coincidencia es con un dato anterior.
- Un número de ficha que ya no usa nadie puede asignarse a otra persona. Al buscarlo, si alguien
  lo tiene vigente solo sale esa persona; si nadie lo tiene, salen las coincidencias anteriores.
- Acciones por fila, según permisos: **Ver**, **Editar**, **Registrar ingreso** (DOC-27) y
  **Unificar** (DOC-31).
- Al cerrar cualquier ventana se conservan la búsqueda, los filtros y la página.

### 2. Crear un Usuario HC

Quien tiene permiso elige **Crear usuario HC**. Antes de guardar, la pantalla muestra las
posibles coincidencias. **Solo bloquea** si ya existe el mismo tipo y número de documento o el
mismo número de ficha vigente; un nombre parecido avisa pero no bloquea.

Crear la ficha **no registra un ingreso** ni la ata a una sede.

**Identificación**

| Campo | Obligatorio | Valores y reglas |
|---|---|---|
| Tipo de documento | Sí | CC Cédula de ciudadanía · TI Tarjeta de identidad · CE Cédula de extranjería · PPT Permiso por Protección Temporal · PEP Permiso Especial de Permanencia · PAS Pasaporte · RC Registro civil · Sin documento · Otro |
| Número de documento | Sí, salvo «Sin documento» | Letras y números; conserva las letras y los ceros iniciales. Con «Sin documento» queda vacío: **no se inventan números**. Único por tipo más número (se permite el mismo número con otro tipo). Para comparar se ignoran mayúsculas y espacios de los extremos |
| Número de ficha | No | Se escribe a mano. Único entre los números vigentes. Vacío no cuenta como repetido |
| Primer nombre | Sí | Puede ser provisional si no se conoce la identidad |
| Segundo nombre, primer apellido, segundo apellido | No | — |
| Alias | No | No reemplaza al primer nombre |
| Foto de la persona | No | Imagen |
| Estado vital | Sí | Viva · Fallecida. Nace en «Viva» |
| Fecha de fallecimiento | No | Puede no conocerse |

- El **nombre completo** junta primer nombre, segundo nombre y apellidos que existan, con un
  solo espacio, sin textos de relleno.
- Cada ficha guarda quién la creó y cuándo.
- Corregir un nombre o un documento **no rompe** la relación con sus estadías ni sus fotos.

### 3. Caracterización

Se llena a medida que se consigue la información; se puede dejar a medias y seguir otro día.
**Todos los campos son opcionales.** Vacío significa «no informado», no «No», ni «Ninguna», ni
cero. Las selecciones de una sola opción se pueden limpiar.

| Campo | Tipo | Valores y reglas |
|---|---|---|
| Escolaridad | Una opción | Primaria · Secundaria · Bachiller · Técnico · Tecnólogo · Profesional · Ninguno |
| Sustancias de impacto | Varias | Alcohol · Basuco · Cocaína · Heroína · Marihuana · Sacol · Tabaco · Tusi · Perico · No consume. «No consume» excluye a las demás |
| Tiempo en calle | Tres números enteros desde cero | Años, meses y semanas, cada uno por separado. No se convierten entre sí ni se rellenan con cero |
| Red de apoyo | Una opción | Sí · No |
| Nivel de apoyo de la red | Una opción | Sin red identificada · Red identificada, sin apoyo disponible · Contacto y apoyo emocional · Apoyo práctico limitado · Apoyo práctico sostenido · Vinculación no recomendable actualmente · Pendiente de valoración |
| Clasificación | Una opción | Habitante en calle · Habitante de calle · Persona en riesgo de calle · Sin clasificación · Matrícula en resocialización · Pendiente de valoración |
| Fecha de nacimiento | Fecha | No futura. La **edad** se calcula en años cumplidos; sin fecha dice «Sin información» |
| Sexo según documento de identidad | Una opción | Femenino (F) · Masculino (M) · No binario (NB) · Transgénero (T) |
| Orientación sexual | Una opción | Heterosexual · Homosexual · Bisexual · Pansexual · Asexual · Otra · Prefiere no responder |
| Nacionalidad | Una opción con búsqueda | Lista de países (ISO 3166-1) en español. Sin país preseleccionado |
| Comuna o corregimiento donde permanece o vive | Una opción | La lista de comunas que Documenta ya usa en los casos (DOC-23): 1 a 16, 50, 60, 70, 80 y 90 |
| EPS | Una opción con búsqueda | Asmet · Asociación Indígena del Cauca · Emsanar · Capital Salud · Coosalud · Nueva EPS · Sanitas · Salud Total · Savia Salud · Sura · Sin afiliación |
| Listado censal | Una opción | Sí · No |
| Fecha de ingreso al Sistema Habitante de Calle | Fecha | Se escribe a mano. **No es una estadía** |
| Diagnóstico principal de psiquiatría | Una opción con búsqueda | Catálogo CIE-10: código y descripción oficial |
| Otros diagnósticos de psiquiatría | Varias, con búsqueda | CIE-10 (ver PA-08) |
| Diagnósticos de medicina general | Varias, con búsqueda | CIE-10; si se llena, uno se marca como principal |
| Antecedentes de protección institucional en la infancia | Una opción | Sí · No |
| Tipo de discapacidad | Varias | Física · Auditiva · Visual · Sordoceguera · Intelectual / cognitiva · Psicosocial (mental) · Ninguna · Sin información. «Ninguna» y «Sin información» excluyen a las demás y entre sí. No hay opción «Múltiple». Un diagnóstico no marca discapacidad por sí solo |
| Cédula física | Una opción | Sí · No |
| Portabilidad | Una opción | Sí · No |
| Código 900 | Una opción | Sí · No |
| Oxígeno dependiente | Una opción | Sí · No |
| Clasificación de autonomía | Una opción | Autónomo · Heterónomo |
| Nivel de independencia funcional | Una opción | Independiente · Requiere supervisión · Requiere ayuda parcial · Requiere ayuda total · Pendiente de valoración |

Para llenar los diagnósticos basta el permiso de editar la caracterización: no hace falta ser
de psiquiatría ni de medicina.

### 4. Ver la ficha

La ficha tiene tres partes: **Identificación**, **Caracterización** e **Historial de cambios**.
Qué partes ve cada quien está en §6.

### 5. Editar, con motivo e historial

- Se puede corregir la identificación o la caracterización **solo si la persona está activa en
  el componente de quien edita**. El coordinador líder y el superadministrador pueden editar
  también a personas inactivas o activas en cualquier componente.
- Al guardar, el sistema vuelve a comprobar el permiso y que la persona siga activa en ese
  componente, y **pide el motivo** (ver PA-04 para el primer llenado de un campo vacío).
- **Nada se sobrescribe sin rastro:** cada cambio guarda el valor anterior, el nuevo, quién,
  cuándo, por qué y desde qué componente. Eso es el Historial de cambios de la ficha.
- Si otra persona cambió la ficha mientras estaba abierta, se avisa y no se guarda encima.
- **Estado vital:** lo corrige quien puede editar la caracterización, con motivo e historial.
  Marcar «Fallecida» desde aquí no cierra la estadía: si hay una activa, se avisa por la campana
  a la Coordinación del componente para que registre el egreso (DOC-27 §6).
- Una ficha **no se borra** (constitución IV). Las repetidas se unifican (DOC-31).

### 6. Datos sensibles: quién ve qué

La caracterización trae datos sensibles (diagnósticos, consumo de sustancias, orientación
sexual, discapacidad, EPS). Propuesta, traducida a los roles de Documenta (PA-02):

| Qué | Quién |
|---|---|
| Buscar y ver la **identificación** (nombre, documento, ficha, alias, foto, estado y componente donde está activa) | Cualquier usuario cuyo componente tenga el módulo, sobre **todas** las fichas de la dependencia |
| Ver la **caracterización** y el **historial de cambios** | Edición en adelante **del componente que ha recibido a la persona** (tiene o tuvo allí una estadía válida); coordinador líder; superadministrador |
| Crear una ficha y llenarla por primera vez | Edición en adelante |
| Editar identificación o caracterización | Edición en adelante, solo con la persona activa en su componente; coordinador líder y superadministrador, siempre |
| Cambiar las listas de opciones y el catálogo CIE-10 | Solo el superadministrador (§7) |

- Los permisos se comprueban en el servidor en cada operación, no solo escondiendo botones.
- **Queda en la auditoría** de Documenta: crear, editar (con valores anteriores y nuevos y el
  motivo), y también **abrir la caracterización** de una persona y **ver o descargar su foto**.
- Las fotos **no se guardan en el módulo Documentos** (que es de Calle) y solo se abren pasando
  por el control de permisos, nunca por una dirección directa (RN-06).

### 7. Listas de opciones

- No hay perfil Parametrizador ni pantalla de Parametrización (decisión de Edwar). Las listas
  de esta spec son **valores de la plataforma que cambia solo Edwar** como superadministrador.
- Cuando una opción se renombra o se retira, **las fichas que ya la tenían conservan el texto
  con que se registró**. Una opción retirada no se ofrece para respuestas nuevas.
- No se crean campos nuevos desde una pantalla: un campo nuevo es un cambio de esta spec.
- La forma de guardarlas (PA-05) la propone esta spec y la decide Edwar.

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | El listado muestra a **todas** las personas de la dependencia, y Edwar decidió que cada usuario ve solo su componente. ¿Se acepta la búsqueda entre componentes como excepción? | **Sí**, limitada a la identificación; es lo que evita las fichas repetidas. La caracterización sigue cerrada por componente | **Sí** |
| PA-02 | El documento trae siete perfiles y unos diez permisos sueltos (crear, editar identidad, editar caracterización…), cada uno por separado. Nombra además un perfil «Registro de personas y movimientos» que **no está en su lista de perfiles**. ¿Se traduce a los roles de Documenta como en §6? | **Sí:** Lectura busca y ve identificación; Edición crea, ve y edita; Coordinación suma lo de DOC-27, DOC-30 y DOC-31. Sin permisos sueltos por ahora; si hace falta separar «editar identidad», se agrega una casilla por persona como «Aprueba novedades» | **Sí** |
| PA-03 | Una ficha recién creada, sin ninguna estadía: ¿quién puede verle y completarle la caracterización? El documento permite el llenado inicial antes del primer ingreso, pero la edición exige estadía activa | Mientras no tenga estadía, la ve y la completa el componente de quien la creó | **Sí** |
| PA-04 | ¿Llenar por primera vez un campo vacío de la caracterización pide motivo? El documento exige motivo para «correcciones» | Motivo obligatorio al cambiar un dato ya informado y en cualquier dato de identificación; llenar un campo vacío no lo pide, pero queda en el historial | **Sí** |
| PA-05 | ¿Dónde viven las listas de opciones? | En la configuración de la plataforma (como las comunas), que Edwar cambia y despliega. Sin pantalla. El catálogo CIE-10 y los países, cargados una vez | **Sí** |
| PA-06 | **Catálogo CIE-10:** ¿de qué fuente oficial en español y qué versión? Son miles de códigos | La tabla de referencia CIE-10 del Ministerio de Salud, cargada una vez. Si no está a tiempo, los tres campos de diagnóstico salen después | **Sí** (solo para los diagnósticos) |
| PA-07 | La caracterización incluye **EPS**, y la constitución (V) dice que datos de EPS no deben entrar a Documenta «mientras no exista una funcionalidad aprobada para ellos». ¿Aprobar esta spec es esa aprobación? | Sí, para la EPS del Usuario HC; la regla nació para los datos de Nómina. Lo decide Edwar | **Sí** |
| PA-08 | «Otros diagnósticos de psiquiatría» repite en el documento la regla «uno principal», aunque ya existe el campo del principal | En «Otros» no se marca principal; en medicina general, sí | No |
| PA-09 | ¿Cuánto tiempo se guardan las fotos y los datos de un Usuario HC? La constitución (V) pide un plazo de retención | Lo define el Comité (jurídica); mientras tanto no se borra nada | No |
| PA-10 | Formatos de la foto: el documento dice JPG/JPEG o PNG para el ingreso y no dice nada para la de la persona | JPG, PNG y WEBP, con el tamaño máximo de las fotos de Documenta | No |
| PA-11 | ¿Lectura debe ver la caracterización? El perfil «Consulta y reportes» del documento es «sin contenido asistencial» | No: Lectura ve identificación, tablero y reportes | No |
| PA-12 | ¿Hay fichas que ya existan en Excel u otro sistema y deban cargarse al empezar? El documento no lo dice | Si las hay, es una spec aparte; no cabe en diciembre | **Sí** (para el plan) |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Un usuario de Básica o de Estabilización ve el módulo; uno de Calle no lo ve y recibe rechazo por dirección directa | Por construir |
| CA-02 | El listado incluye fichas creadas por otro componente, con su chip de componente o «Inactivo» | Por construir |
| CA-03 | La búsqueda encuentra por parte del nombre, del alias o del documento sin importar mayúsculas ni tildes, y por número de ficha exacto | Por construir |
| CA-04 | Después de corregir un nombre o un documento, buscar el dato anterior lleva a la ficha vigente y lo señala como coincidencia anterior | Por construir |
| CA-05 | Se crea una ficha solo con tipo «Sin documento» y un primer nombre provisional; el número queda vacío | Por construir |
| CA-06 | No se crea una ficha con el mismo tipo y número de documento de otra (aunque cambien mayúsculas o espacios), ni con un número de ficha vigente en otra; sí con el mismo número y otro tipo | Por construir |
| CA-07 | Un nombre parecido a otro muestra el aviso de coincidencia y deja crear | Por construir |
| CA-08 | Un documento con letras y ceros iniciales se guarda y se muestra tal cual | Por construir |
| CA-09 | La caracterización se guarda con todos los campos vacíos y con solo algunos; un campo vacío se muestra como no informado | Por construir |
| CA-10 | «No consume» no se puede guardar junto con otra sustancia; «Ninguna» y «Sin información» de discapacidad, tampoco con otras ni entre sí | Por construir |
| CA-11 | Años, meses y semanas de tiempo en calle se guardan por separado, sin convertir ni rellenar con cero | Por construir |
| CA-12 | La edad se calcula desde la fecha de nacimiento; una fecha futura se rechaza; sin fecha dice «Sin información» | Por construir |
| CA-13 | Editar exige que la persona esté activa en el componente de quien edita; si no, se rechaza. El coordinador líder y el superadministrador sí pueden | Por construir |
| CA-14 | Cada edición guarda valor anterior, valor nuevo, autor, fecha y hora, motivo y componente, y se ve en el Historial de cambios; sin motivo no se guarda una corrección | Por construir |
| CA-15 | Si la ficha cambió mientras otro la editaba, el segundo guardado se rechaza con aviso | Por construir |
| CA-16 | Un usuario de un componente que nunca recibió a la persona ve su identificación y **recibe rechazo** al abrir su caracterización y su historial; Lectura tampoco los ve | Por construir |
| CA-17 | Abrir la caracterización y abrir la foto quedan en la auditoría; la foto no se abre sin sesión ni sin permiso | Por construir |
| CA-18 | Una ficha ya guardada con una opción que después se renombró o se retiró conserva el texto original | Por construir |
| CA-19 | Marcar «Fallecida» a una persona con estadía activa no la egresa y avisa a la Coordinación de ese componente | Por construir |

## Reglas que cambian

Propuestas para `docs/context/domain.md` al aprobarse (el número lo fija quien lo actualice):

- **RN-52. Una ficha por persona.** El Usuario HC tiene una sola ficha en la dependencia; no
  pertenece a una sede ni a un componente. Buscarla está permitido entre componentes, y solo
  muestra la identificación. **Es una excepción a RN-01 tal como queda con DOC-25.**
- **RN-53. Identidad.** Tipo más número de documento es único; sin documento no hay número; el
  número de ficha es único entre los vigentes; las identidades anteriores siguen buscables.
- **RN-54. La caracterización es opcional.** Vacío es «no informado».
- **RN-55. Corregir con motivo e historial.** Solo sobre personas activas en el componente
  propio; nunca se pierde el valor anterior.
- **RN-56. Datos sensibles del Usuario HC.** La caracterización la ve el componente que ha
  recibido a la persona, desde Edición; abrirla queda auditado.
- **RN-04 y RN-06:** se extienden a las fichas y fotos de Usuarios HC (sin cambio de texto).
- **`glossary.md`:** Usuario HC, Ficha, Caracterización, Estado vital.
- **Constitución V:** ver PA-07 (EPS) y PA-09 (retención).

## Decisiones (Edwar, 2026-11-20)

- Se construye dentro de Documenta, con ingreso por Authentik.
- La persona atendida es «Usuario HC» y el módulo «Usuarios HC».
- No hay Parametrizador: las listas las cambia solo Edwar.
- Los perfiles y permisos del documento no se adoptan tal cual: se traducen a los roles de
  Documenta y al acceso por componente.
- Se empieza por Básica y Estabilización.

## Candidatos para la siguiente versión

- Permisos sueltos por persona (editar identidad, editar caracterización) si los roles no alcanzan.
- Gráficas de caracterización (el documento las deja como desarrollo futuro).
- Historial de fotos de la persona (el documento habla de «foto vigente»).
- Carga inicial de fichas existentes (PA-12).
- Atenciones y evoluciones por disciplina: desarrollo futuro en el documento; fuera de alcance.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-20 | Borrador a partir del documento v1.6 y las decisiones de Edwar |
