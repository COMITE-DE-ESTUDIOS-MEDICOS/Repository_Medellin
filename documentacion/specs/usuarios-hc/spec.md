# Spec: Usuarios HC · ficha, identificación, caracterización, búsqueda y edición con historial

| | |
|---|---|
| Historia | DOC-26 |
| Tipo | Historia de usuario · módulo nuevo · propone RN-52 a RN-56 · abre una excepción a «cada componente ve solo lo suyo» (RN-01 tal como quedó con DOC-25) |
| Estado | **Borrador** |
| Versión | 0.2 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (11-11-2026, de otra persona del Comité; aún sin código de fuente, se propone F-12), secciones 3 a 3.4, 7.2 y 8. Decisiones de Edwar, 2026-11-20. **La versión 0.2 no volvió a leer ese documento (no está disponible):** lo que aquí se cita de él viene de la 0.1 |
| Depende de | **DOC-25** (acceso por componente), en lo ya construido: módulos por componente, aislamiento por componente, coordinador líder y auditoría por componente. Necesita además, de DOC-25, **ACC-07 y ACC-08** (asignar componente a los usuarios, para que existan usuarios de Básica y Estabilización) y **ACC-10** (avisos por componente, solo para el aviso de «Fallecida»). **DOC-27** (estadías): lo marcado «con DOC-27» (ver §8) |
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
- **Componente de registro:** el componente en el que se creó la ficha (el de quien la creó; si
  la creó el coordinador líder, el componente en el que estaba parado). Es un dato de la ficha
  que no cambia. **No hace a la ficha «de» ese componente**: solo sirve para saber quién la
  conoce mientras la persona no tenga estadías (§8, PA-03).
- **Componentes que conocen a la persona:** el componente de registro y, cuando exista DOC-27,
  cada componente donde la persona tiene o tuvo una estadía válida (activa o egresada; una
  estadía anulada no cuenta).
- **Coordinador líder:** el rol **Administración** (RN-50). No pertenece a ningún componente,
  trabaja todos los de la dependencia y elige en el menú en cuál está parado.
- **Identidad anterior:** un nombre, documento o número de ficha que la persona tuvo y se
  corrigió. Sigue sirviendo para buscar.

## Comportamiento

### 1. El módulo, el listado y la búsqueda

- El módulo se llama **«Usuarios HC»** y se enciende en **Básica y Estabilización**; Calle no lo
  tiene. Como todo módulo, se decide por componente y lo cambia solo el superadministrador
  (RN-49). Quien es de un componente sin el módulo no lo ve en el menú y, si escribe la
  dirección, recibe «No tienes permiso».
- Lo abren **todos los roles** de esos componentes, también Lectura. Al entrar a Documenta, una
  persona de Básica o Estabilización llega a Usuarios HC (hoy llega a la pantalla de
  bienvenida «tu componente todavía no tiene módulos activos»).
- El coordinador líder lo ve cuando está parado en Básica o en Estabilización; parado en Calle,
  no. Desde cualquiera de los dos ve **todas** las fichas de la dependencia (son las mismas).
- El listado muestra a **todos los Usuarios HC de la dependencia**, no solo a los del componente
  de quien consulta. Es la única forma de encontrar una ficha que abrió otro componente antes de
  crear otra. **Ver un nombre en la lista no da permiso para ver su caracterización** (§6). Esta
  es la única excepción al aislamiento por componente de DOC-25 y está acotada en §6.1.
- Columnas: nombre completo · tipo y número de documento · número de ficha · componente o
  estado · acciones. Qué dice «componente o estado» depende de si ya existe DOC-27 (§8):
  - **Con DOC-26 sola:** el componente de registro.
  - **Con DOC-27:** el componente donde tiene una estadía activa; si no tiene ninguna,
    **Inactivo**. Eso es independiente de si la persona está viva o fallecida.
- Búsqueda por **nombre, documento, número de ficha o alias**:
  - nombre, alias y documento aceptan una parte del texto, sin importar mayúsculas ni tildes;
  - el número de ficha se busca exacto;
  - se muestra la foto vigente de la persona, si tiene (ver en §6.2 cómo queda en la auditoría,
    PA-17).
- Filtros: componente y, **con DOC-27**, estado (Todos, Activos, Inactivos). Con DOC-26 sola, el
  filtro de componente usa el componente de registro y no hay filtro de estado. Con paginación.
- **Las identidades anteriores siguen sirviendo para buscar:** si a una persona se le corrigió
  el nombre, el documento o la ficha, buscar el dato viejo lleva a su ficha actual, y la pantalla
  dice que la coincidencia es con un dato anterior.
- Un número de ficha que ya no usa nadie puede asignarse a otra persona. Al buscarlo, si alguien
  lo tiene vigente solo sale esa persona; si nadie lo tiene, salen las coincidencias anteriores.
- Acciones por fila, según permisos: **Ver** y **Editar**. **Registrar ingreso** aparece cuando
  exista DOC-27, y **Unificar** cuando exista DOC-31.
- Al cerrar cualquier ventana se conservan la búsqueda, los filtros y la página.

### 2. Crear un Usuario HC

Quien tiene permiso (§6) elige **Crear usuario HC**. Antes de guardar, la pantalla muestra las
posibles coincidencias entre **todas** las fichas de la dependencia, con su identificación y
nada más. **Solo bloquea** si ya existe el mismo tipo y número de documento o el mismo número de
ficha vigente; un nombre parecido avisa pero no bloquea.

Crear la ficha **no registra un ingreso** ni la ata a una sede. Guarda su **componente de
registro**.

**Identificación**

| Campo | Obligatorio | Valores y reglas |
|---|---|---|
| Tipo de documento | Sí | CC Cédula de ciudadanía · TI Tarjeta de identidad · CE Cédula de extranjería · PPT Permiso por Protección Temporal · PEP Permiso Especial de Permanencia · PAS Pasaporte · RC Registro civil · Sin documento · Otro |
| Número de documento | Sí, salvo «Sin documento» | Letras y números; conserva las letras y los ceros iniciales. Con «Sin documento» queda vacío: **no se inventan números**. Único por tipo más número (se permite el mismo número con otro tipo). Para comparar se ignoran mayúsculas y espacios de los extremos |
| Número de ficha | No | Se escribe a mano. Único entre los números vigentes. Vacío no cuenta como repetido |
| Primer nombre | Sí | Puede ser provisional si no se conoce la identidad |
| Segundo nombre, primer apellido, segundo apellido | No | — |
| Alias | No | No reemplaza al primer nombre |
| Foto de la persona | No | Imagen. Formatos, tamaño y sello: §6.3 |
| Estado vital | Sí | Viva · Fallecida. Nace en «Viva» |
| Fecha de fallecimiento | No | Puede no conocerse |

- El **nombre completo** junta primer nombre, segundo nombre y apellidos que existan, con un
  solo espacio, sin textos de relleno.
- Cada ficha guarda quién la creó, cuándo y en qué componente.
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
| Comuna o corregimiento donde permanece o vive | Una opción | La misma lista de comunas y corregimientos de Medellín que ya usan los casos de derivación (DOC-23), que está en la configuración de la plataforma: comunas 1 a 16 y corregimientos 50, 60, 70, 80 y 90 |
| EPS | Una opción con búsqueda | Asmet · Asociación Indígena del Cauca · Emsanar · Capital Salud · Coosalud · Nueva EPS · Sanitas · Salud Total · Savia Salud · Sura · Sin afiliación (ver PA-07) |
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

- Se puede corregir la identificación o la caracterización si quien edita tiene el rol (§6) y
  la persona está en su componente:
  - **con DOC-26 sola:** si su componente es el componente de registro de la ficha;
  - **con DOC-27:** si la persona está **activa en su componente**; si la persona todavía no
    tiene ninguna estadía válida, sigue valiendo el componente de registro (§8).
  - El coordinador líder y el superadministrador pueden editar siempre, también a personas
    inactivas o activas en cualquier componente.
- Al guardar, el sistema vuelve a comprobar el permiso y que la persona siga en ese componente,
  y **pide el motivo** (ver PA-04 para el primer llenado de un campo vacío).
- **Nada se sobrescribe sin rastro:** cada cambio guarda el valor anterior, el nuevo, quién,
  cuándo, por qué y desde qué componente (para el coordinador líder, el componente en el que
  estaba parado). Eso es el Historial de cambios de la ficha. Además queda en la auditoría de
  Documenta (§6.2).
- **La foto no se sobrescribe:** cambiarla o quitarla deja la anterior guardada (constitución
  IV) y el cambio en el historial. La ficha muestra solo la foto vigente; ver las anteriores es
  un candidato.
- Si otra persona cambió la ficha mientras estaba abierta, se avisa y no se guarda encima.
- **Estado vital:** lo corrige quien puede editar la caracterización, con motivo e historial.
  - **Con DOC-26 sola** no hay estadías: marcar «Fallecida» solo cambia el dato, deja el
    historial y no avisa a nadie.
  - **Con DOC-27 y ACC-10:** marcar «Fallecida» desde aquí **no cierra la estadía**. Si hay una
    activa, se avisa por la campana a la Coordinación del componente de esa estadía y al
    coordinador líder, para que registren el egreso (DOC-27 §6; PA-16).
- Una ficha **no se borra** (constitución IV). Las repetidas se unifican (DOC-31).

### 6. Datos sensibles: quién ve qué

La caracterización trae datos sensibles (diagnósticos, consumo de sustancias, orientación
sexual, discapacidad, EPS). Traducido a los roles de Documenta tal como quedaron con DOC-25
(PA-02): Lectura, Edición y Coordinación pertenecen a **un** componente y trabajan dentro de él;
Administración es el coordinador líder, sin componente, y trabaja el componente en el que está
parado.

| Qué | Quién |
|---|---|
| Buscar y ver la **identificación** (nombre, documento, ficha, alias, foto, estado vital y componente que muestra la lista) | Cualquier rol de un componente que tenga el módulo, sobre **todas** las fichas de la dependencia. El coordinador líder y el superadministrador, parados en un componente con el módulo |
| Ver la **caracterización** y el **historial de cambios** | Edición y Coordinación de un **componente que conoce a la persona** (con DOC-26 sola, el componente de registro; con DOC-27, también donde tiene o tuvo una estadía válida); coordinador líder; superadministrador. Lectura, no (PA-11) |
| Crear una ficha y llenarla por primera vez | Edición en adelante (en su componente, que queda como componente de registro) |
| Editar identificación o caracterización | Edición y Coordinación, según §5; coordinador líder y superadministrador, siempre |
| Cambiar las listas de opciones y los catálogos | Solo el superadministrador (§7) |

En esta spec Coordinación puede lo mismo que Edición; lo que suma Coordinación está en DOC-27,
DOC-30 y DOC-31.

#### 6.1 La excepción al aislamiento por componente, acotada

Con DOC-25 cada usuario ve solo los datos de su componente (RN-01). Usuarios HC necesita una
excepción (PA-01). **Lo que cruza componentes:**

- el listado y la búsqueda de fichas, con la identificación y nada más;
- las comprobaciones al crear o corregir (documento o número de ficha repetido, nombres
  parecidos), que miran todas las fichas y muestran solo identificación;
- con DOC-27, saber en qué componente está activa una persona (sin ver su estadía).

**Lo que no cruza, y sigue como en DOC-25:**

- **la dependencia:** nunca se ve una ficha de otra dependencia (constitución III, RN-01);
- **el módulo:** un componente sin Usuarios HC (Calle) no ve ninguna ficha, ni por dirección
  directa (RN-49);
- **la caracterización, el historial y las fotos de estadías:** solo para los componentes que
  conocen a la persona; para los demás, rechazo, también por dirección directa;
- **las estadías** (DOC-27): siguen siendo de su componente;
- **la auditoría:** Coordinación sigue viendo solo lo que hicieron los usuarios de su componente
  (DOC-25, PA-06). Si un usuario de Estabilización abre la caracterización de una persona creada
  en Básica, eso lo ve la Coordinación de Estabilización y el coordinador líder, no la de Básica.

#### 6.2 Auditoría

- Los permisos se comprueban en el servidor en cada operación, no solo escondiendo botones.
- **Queda en la auditoría** de Documenta (quién, qué, cuándo, IP y navegador, RN-04): crear,
  editar (con valores anteriores y nuevos y el motivo), y también **abrir la caracterización**
  de una persona y **ver o descargar su foto**. Ya hay precedente: abrir un documento custodiado
  y consultar un caso desde la vía pública quedan auditados.
- Las fotos que se muestran en una página de resultados de la búsqueda también pasan por el
  control de permisos; la página deja **un** registro en la auditoría con las personas cuya foto
  se mostró, en vez de uno por foto (PA-17).

#### 6.3 Fotos

- Las fotos de la persona **no se guardan en el módulo Documentos** (que es solo de Calle,
  RN-51): no aparecen en Documentos ni en sus carpetas. Se guardan en el mismo almacenamiento de
  la plataforma, fuera de la web, y solo se abren pasando por el control de permisos y la
  auditoría, nunca por una dirección directa (RN-06, constitución V).
- **Formatos y tamaño: los de las evidencias fotográficas que ya acepta Documenta** (PA-10,
  resuelta): JPG o JPEG (también el JFIF que a veces guarda Windows), PNG y WEBP; hasta el
  tamaño máximo de archivo de la plataforma (hoy 25 MB). En el navegador se reduce a 2560 px en
  su lado largo, y el servidor no acepta más de 4096 px.
- Se puede tomar con la cámara o elegir de la galería. **No lleva el sello de hora, fecha,
  dirección y coordenadas** de las evidencias (PA-15).

### 7. Listas de opciones y catálogos

- No hay perfil Parametrizador ni pantalla de Parametrización (decisión de Edwar). Las listas
  de esta spec son **valores de la plataforma que cambia solo Edwar** como superadministrador,
  con un despliegue.
- Cuando una opción se renombra o se retira, **las fichas que ya la tenían conservan el texto
  con que se registró**. Una opción retirada no se ofrece para respuestas nuevas, pero sigue
  mostrándose en las fichas que la tienen. Por eso cada respuesta debe guardar **el texto que vio
  quien la registró** y, además, **qué opción era**, para poder contarla junto con las demás
  aunque después cambie de nombre (nota para el plan).
- No se crean campos nuevos desde una pantalla: un campo nuevo es un cambio de esta spec.
- Dónde viven:
  - **las listas cortas** (tipo de documento, escolaridad, sustancias, EPS, comunas…): en la
    configuración de la plataforma, junto a las comunas que ya usan los casos (PA-05);
  - **el catálogo CIE-10** (miles de códigos) **y la lista de países**: en catálogos de solo
    lectura que se cargan al desplegar y no se editan desde ninguna pantalla (PA-13).

### 8. Antes y después de Ingresos y egresos (DOC-27)

Según el plan de trabajo, DOC-26 se construye **antes** que DOC-27, y varias reglas de esta spec
hablan de estadías, que nacen con DOC-27. Esta sección dice qué hace DOC-26 sola y qué cambia
cuando llegue DOC-27. La regla que une las dos es el **componente de registro**: mientras una
persona no tenga estadías, la conoce el componente donde se creó su ficha (PA-03).

| Regla | Con DOC-26 sola | Cuando llegue DOC-27 |
|---|---|---|
| Columna «componente o estado» | El componente de registro | El componente donde tiene una estadía activa, o «Inactivo» |
| Filtros | Componente (de registro) | Componente (donde está activa) y estado: Todos, Activos, Inactivos |
| Quién ve la caracterización y el historial (Edición en adelante) | El componente de registro | El componente de registro y los componentes donde tiene o tuvo una estadía válida |
| Quién edita (Edición en adelante) | El componente de registro | El componente donde está activa. Si nunca ha tenido una estadía válida, el componente de registro. Si tuvo estadías y hoy no está activa en ninguna, solo el coordinador líder y el superadministrador |
| Coordinador líder y superadministrador | Ven y editan todo | Igual |
| «Registrar ingreso» | No aparece | Aparece según DOC-27 §3 |
| Marcar «Fallecida» | Cambia el dato, con motivo e historial; sin aviso | Si hay estadía activa, avisa por la campana (requiere también ACC-10) y no egresa |

Qué entrega DOC-26 sola, para usarla o probarla en el servidor de pruebas: el módulo encendido en
Básica y Estabilización; crear, buscar y ver fichas en toda la dependencia; la caracterización
completa (los diagnósticos, cuando esté el CIE-10, PA-06); editar con motivo e historial;
identidades anteriores; fotos; auditoría de consultas. Lo que **no** puede hacer sin DOC-27: que
un segundo componente vea la caracterización de una persona que creó otro (salvo el coordinador
líder), ni saber quién está dentro de una sede.

Si DOC-26 llegara a producción antes que DOC-27, un componente que recibe a una persona creada
en el otro no podría ver ni completar su caracterización hasta que exista el ingreso. Por eso
se propone que **las dos vayan juntas a producción** (PA-14).

## Preguntas abiertas

**Para aprobar, Edwar responde** (cada una con la propuesta por defecto; basta «sí a todo salvo
la N»):

1. **PA-01** · ¿Se acepta que la búsqueda de fichas cruce componentes? → Sí, solo la identificación (§6.1).
2. **PA-02** · ¿Los permisos del documento se traducen a los roles de Documenta como en §6? → Sí, sin permisos sueltos.
3. **PA-03** · ¿Quién conoce una ficha sin estadías? → El componente donde se creó; la ficha guarda ese componente.
4. **PA-04** · ¿Llenar un campo vacío pide motivo? → No; cambiar un dato ya informado y cualquier dato de identificación, sí.
5. **PA-05** · ¿Las listas cortas van en la configuración de la plataforma? → Sí, junto a las comunas.
6. **PA-06** · ¿De dónde sale el CIE-10? → Tabla de referencia del Ministerio de Salud; si no llega, los diagnósticos salen después.
7. **PA-07** · ¿Aprobar esta spec autoriza guardar la EPS del Usuario HC (constitución V)? → Sí, solo la EPS del Usuario HC.
8. **PA-12** · ¿Hay fichas que cargar al empezar? → No; si las hay, spec aparte.
9. **PA-13** · ¿El CIE-10 y los países van en catálogos de solo lectura cargados al desplegar? → Sí, sin enmendar la constitución.
10. **PA-14** · ¿DOC-26 va a producción junto con DOC-27? → Sí, juntas; DOC-26 sola, solo en el servidor de pruebas.
11. **PA-15** · ¿La foto de la persona va sin el sello de hora y lugar (excepción a RN-13)? → Sí, sin sello y sin coordenadas.

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | El listado muestra a **todas** las personas de la dependencia, y con DOC-25 cada usuario ve solo su componente (RN-01). ¿Se acepta la búsqueda entre componentes como excepción? DOC-25 ya la anticipa como «la única excepción» si Edwar la aprueba | **Sí**, acotada como en §6.1: cruza solo la identificación; no cruza dependencias, ni componentes sin el módulo, ni la caracterización, el historial o las estadías | **Sí** |
| PA-02 | El documento trae siete perfiles y unos diez permisos sueltos (crear, editar identidad, editar caracterización…), cada uno por separado. Nombra además un perfil «Registro de personas y movimientos» que **no está en su lista de perfiles**. ¿Se traduce a los roles de Documenta como en §6? | **Sí:** Lectura busca y ve identificación; Edición crea, ve y edita en su componente; Coordinación, lo mismo aquí, y suma lo de DOC-27, DOC-30 y DOC-31; Administración es el coordinador líder y ve y edita todo. Sin permisos sueltos por ahora; si hace falta separar «editar identidad», se agrega una casilla por persona como «Aprueba novedades» | **Sí** |
| PA-03 | Una ficha sin ninguna estadía (siempre, mientras no exista DOC-27): ¿quién puede verle y completarle la caracterización? El documento permite el llenado inicial antes del primer ingreso, pero la edición exige estadía activa | La ve y la completa, desde Edición, el **componente donde se creó**: la ficha guarda ese componente de registro, que no cambia. Con DOC-27, se suman los componentes donde tenga o haya tenido una estadía válida, y editar pasa a ser del componente donde esté activa (§8) | **Sí** |
| PA-04 | ¿Llenar por primera vez un campo vacío de la caracterización pide motivo? El documento exige motivo para «correcciones» | Motivo obligatorio al cambiar un dato ya informado y en cualquier dato de identificación; llenar un campo vacío no lo pide, pero queda en el historial | **Sí** |
| PA-05 | ¿Dónde viven las **listas cortas** de opciones? La constitución dice que los valores de la plataforma están en la configuración y que no hay tabla de parámetros | En la configuración de la plataforma, como las comunas, que Edwar cambia y despliega. Sin pantalla. Una opción retirada se marca como retirada, no se quita, para que las fichas que la tienen la sigan mostrando (§7) | **Sí** |
| PA-06 | **Catálogo CIE-10:** ¿de qué fuente oficial en español y qué versión? Son miles de códigos | La tabla de referencia CIE-10 del Ministerio de Salud, cargada una vez y registrada como fuente. Si no está a tiempo, los tres campos de diagnóstico salen después | **Sí** (solo para los diagnósticos) |
| PA-07 | La caracterización incluye **EPS**, y la constitución (V) dice que datos de EPS no deben entrar a Documenta «mientras no exista una funcionalidad aprobada para ellos». ¿Aprobar esta spec es esa aprobación? | **Sí, solo para la EPS del Usuario HC**; la regla nació para los datos de Nómina, que siguen fuera. Que quede escrito en las decisiones al aprobar. La lista de nombres de EPS en el código no es un dato personal; la EPS de cada persona vive solo en la base de datos | **Sí** |
| PA-08 | «Otros diagnósticos de psiquiatría» repite en el documento la regla «uno principal», aunque ya existe el campo del principal | En «Otros» no se marca principal; en medicina general, sí | No |
| PA-09 | ¿Cuánto tiempo se guardan las fotos y los datos de un Usuario HC? La constitución (V) pide un plazo de retención definido por el Comité | Lo define el Comité (jurídica); mientras tanto no se borra nada, como hoy con las fotos de turnos (contexto, PA-10 del dominio, también abierta) | No |
| PA-10 | ~~Formatos de la foto~~ | **Resuelta en 0.2 con lo que ya existe:** los formatos de las evidencias fotográficas (JPG/JPEG/JFIF, PNG, WEBP), el tamaño máximo de la plataforma y la reducción a 2560 px con tope de 4096 px en el servidor (§6.3) | — |
| PA-11 | ¿Lectura debe ver la caracterización? El perfil «Consulta y reportes» del documento es «sin contenido asistencial» | No: Lectura ve identificación (y, con DOC-28 y DOC-29, tablero y reportes) | No |
| PA-12 | ¿Hay fichas que ya existan en Excel u otro sistema y deban cargarse al empezar? El documento no lo dice | Si las hay, es una spec aparte; no cabe en diciembre | **Sí** (para el plan) |
| PA-13 | **Nueva.** El CIE-10 (miles de códigos) y los países (unos 250) no caben bien en la configuración, y la constitución dice que no hay tabla de parámetros. ¿Dónde viven? | En **catálogos de solo lectura**, cargados por el despliegue (como se sembraron Básica y Estabilización) y sin pantalla para editarlos. No son una tabla de parámetros: no guardan valores que cambien cómo funciona Documenta, sino datos de referencia externos, como ya lo son los tipos de turno o los tipos de documento. **No exige enmendar la constitución**; se sugiere aclarar en su fila «Parámetros» que los catálogos de referencia externos van aparte. Lo decide Edwar | **Sí** (solo para nacionalidad y diagnósticos) |
| PA-14 | **Nueva.** El plan de trabajo construye DOC-26 antes que DOC-27. ¿DOC-26 se publica en producción sola o junto con DOC-27? | **Juntas a producción.** DOC-26 sola queda en el servidor de pruebas: en producción, sin ingresos, un componente no podría ver la caracterización de una persona que creó el otro (§8) | **Sí** (para el plan) |
| PA-15 | **Nueva.** RN-13 dice que toda foto tomada con la cámara lleva el sello de hora, fecha, dirección y coordenadas. ¿También la foto de la persona? | **No:** es un retrato para identificar, no la evidencia de una actuación, y las coordenadas serían un dato personal más sin uso. Se anota en RN-13 como excepción. Se puede tomar con la cámara o elegir de la galería | **Sí** (solo la foto) |
| PA-16 | **Nueva.** El aviso por «Fallecida» con estadía activa: la 0.1 y DOC-27 §6 dicen «a la Coordinación del componente». DOC-25 §7 hace que los avisos de un componente lleguen también al coordinador líder | A la Coordinación del componente de la estadía **y al coordinador líder**, como los demás avisos desde DOC-25. Se ajusta DOC-27 §6 igual. Necesita un tipo nuevo en la campana (RN-38) | No |
| PA-17 | **Nueva.** RN-06 dice que toda vista previa de un archivo queda registrada, y la búsqueda muestra la foto de cada persona. Auditar cada miniatura llenaría la auditoría | La página de resultados deja **un** registro con las personas cuya foto se mostró. Alternativa: no mostrar fotos en la lista y verlas solo al abrir la ficha | No |
| PA-18 | **Nueva.** Los datos de salud y la orientación sexual son datos sensibles (Ley 1581 de 2012). ¿Hay que registrar en la ficha la autorización de tratamiento de datos de la persona? El documento (según la 0.1) no lo pide | Lo define jurídica del Comité junto con la retención (PA-09). Si hace falta, es un campo nuevo en una versión siguiente | No |

## Criterios de aceptación

Los criterios marcados **«con DOC-27»** solo se pueden probar cuando existan las estadías; los
demás se prueban con DOC-26 sola.

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Un usuario de Básica o de Estabilización, con cualquier rol, ve «Usuarios HC» en el menú y, al entrar, llega a él; uno de Calle no lo ve y recibe rechazo por dirección directa (lista, ficha y foto). El coordinador líder lo ve parado en Básica o Estabilización y no parado en Calle | Por construir |
| CA-02 | El listado incluye las fichas creadas en el otro componente, con su componente de registro, y nunca fichas de otra dependencia | Por construir |
| CA-03 | La búsqueda encuentra por parte del nombre, del alias o del documento sin importar mayúsculas ni tildes, y por número de ficha exacto, en el motor de pruebas y en el de los servidores | Por construir |
| CA-04 | Después de corregir un nombre o un documento, buscar el dato anterior lleva a la ficha vigente y lo señala como coincidencia anterior | Por construir |
| CA-05 | Se crea una ficha solo con tipo «Sin documento» y un primer nombre provisional; el número queda vacío | Por construir |
| CA-06 | No se crea una ficha con el mismo tipo y número de documento de otra (aunque cambien mayúsculas o espacios, y aunque la otra se haya creado en otro componente), ni con un número de ficha vigente en otra; sí con el mismo número y otro tipo | Por construir |
| CA-07 | Un nombre parecido a otro muestra el aviso de coincidencia, con solo la identificación de la otra ficha, y deja crear | Por construir |
| CA-08 | Un documento con letras y ceros iniciales se guarda y se muestra tal cual | Por construir |
| CA-09 | La caracterización se guarda con todos los campos vacíos y con solo algunos; un campo vacío se muestra como no informado | Por construir |
| CA-10 | «No consume» no se puede guardar junto con otra sustancia; «Ninguna» y «Sin información» de discapacidad, tampoco con otras ni entre sí | Por construir |
| CA-11 | Años, meses y semanas de tiempo en calle se guardan por separado, sin convertir ni rellenar con cero | Por construir |
| CA-12 | La edad se calcula desde la fecha de nacimiento; una fecha futura se rechaza; sin fecha dice «Sin información» | Por construir |
| CA-13 | Edición y Coordinación del componente de registro editan la ficha; las del otro componente reciben rechazo al abrir la edición, por dirección directa y al guardar; Lectura no edita; el coordinador líder y el superadministrador sí | Por construir |
| CA-14 | Cada edición guarda valor anterior, valor nuevo, autor, fecha y hora, motivo y componente (el del coordinador líder, el que tenía elegido), y se ve en el Historial de cambios; sin motivo no se guarda una corrección | Por construir |
| CA-15 | Si la ficha cambió mientras otro la editaba, el segundo guardado se rechaza con aviso | Por construir |
| CA-16 | Un usuario del componente que no creó la ficha ve su identificación y **recibe rechazo** al abrir su caracterización y su historial; Lectura de cualquier componente tampoco los ve; el coordinador líder sí | Por construir |
| CA-17 | Abrir la caracterización y abrir la foto quedan en la auditoría; una página de resultados con fotos deja un registro; la foto no se abre sin sesión ni sin permiso | Por construir |
| CA-18 | Una ficha ya guardada con una opción que después se renombró o se retiró conserva el texto original; la opción retirada no se ofrece para respuestas nuevas | Por construir |
| CA-19 | Marcar «Fallecida» a una persona sin estadías pide motivo, deja el historial y no envía avisos | Por construir |
| CA-20 | La ficha guarda su componente de registro (el de quien la crea; para el coordinador líder, el que tenía elegido) y editarla no lo cambia | Por construir |
| CA-21 | Ni la lista, ni el aviso de coincidencias, ni el mensaje de documento o ficha repetidos muestran datos de caracterización o del historial de una ficha que el usuario no puede ver | Por construir |
| CA-22 | La foto acepta JPG, JPEG, JFIF, PNG y WEBP hasta el tamaño máximo; rechaza otros formatos y fotos de más de 4096 px de lado; no aparece en Documentos; no lleva sello; cambiarla conserva la anterior y deja el cambio en el historial | Por construir |
| CA-23 | La lista muestra el componente donde la persona tiene una estadía activa, o «Inactivo»; el filtro de estado separa Activos e Inactivos | Por construir (con DOC-27) |
| CA-24 | Edición y Coordinación editan solo a personas activas en su componente (o, sin ninguna estadía válida, del componente de registro); a una persona que hoy no está activa en ninguno solo la editan el coordinador líder y el superadministrador | Por construir (con DOC-27) |
| CA-25 | Un componente donde la persona tuvo una estadía activa o egresada ve su caracterización y su historial; una estadía anulada no da ese acceso | Por construir (con DOC-27) |
| CA-26 | Marcar «Fallecida» a una persona con estadía activa no la egresa y avisa a la Coordinación de ese componente y al coordinador líder | Por construir (con DOC-27 y ACC-10) |
| CA-27 | «Registrar ingreso» aparece en la lista solo para quien puede registrarlo y solo para personas vivas sin estadía activa | Por construir (con DOC-27) |

## Reglas que cambian

Propuestas para `docs/context/domain.md` al aprobarse (el número lo fija quien lo actualice):

- **RN-52. Una ficha por persona.** El Usuario HC tiene una sola ficha en la dependencia; no
  pertenece a una sede ni a un componente, pero guarda su componente de registro. Buscarla está
  permitido entre componentes con el módulo Usuarios HC, y solo muestra la identificación.
  **Es la excepción a RN-01 que DOC-25 dejó anunciada;** no cruza dependencias.
- **RN-53. Identidad.** Tipo más número de documento es único; sin documento no hay número; el
  número de ficha es único entre los vigentes; las identidades anteriores siguen buscables.
- **RN-54. La caracterización es opcional.** Vacío es «no informado».
- **RN-55. Corregir con motivo e historial.** Edita el componente donde la persona está activa
  o, sin estadías, el componente de registro; el coordinador líder y el superadministrador,
  siempre. Nunca se pierde el valor anterior, tampoco la foto anterior.
- **RN-56. Datos sensibles del Usuario HC.** La caracterización y el historial los ven, desde
  Edición, los componentes que conocen a la persona (registro o estadía válida), el coordinador
  líder y el superadministrador; abrirlos y ver las fotos queda auditado.
- **RN-49:** se agrega el módulo **Usuarios HC**, encendido en Básica y Estabilización; se abre
  desde Lectura. El texto «Básica y Estabilización, ninguno por ahora» cambia.
- **RN-04 y RN-06:** se extienden a las fichas y fotos de Usuarios HC, incluidas las lecturas
  (abrir la caracterización, ver la foto) y las miniaturas de la búsqueda (PA-17).
- **RN-13:** excepción para la foto de la persona, sin sello (PA-15).
- **RN-38:** la campana necesita un tipo para los avisos de Usuarios HC (con DOC-27, PA-16).
- **«Parámetros del negocio»** de `domain.md`: dos filas nuevas, las listas de Usuarios HC (en
  la configuración, solo el superadministrador) y los catálogos CIE-10 y países (solo lectura,
  por despliegue) (PA-05, PA-13).
- **`glossary.md`:** Usuario HC (quitar «por construir»), Ficha, Caracterización, Estado vital,
  Componente de registro, Identidad anterior. En **Módulo**, agregar Usuarios HC.
- **Constitución V:** ver PA-07 (EPS), PA-09 (retención) y PA-18 (autorización de tratamiento).
- **Constitución, fila «Parámetros» del stack:** aclaración sugerida, no obligatoria (PA-13).

## Notas para el arquitecto

No cambian el comportamiento; son puntos que el `plan.md` debe resolver.

- **Nombre del módulo:** se propone la clave `usuarios_hc` con la etiqueta «Usuarios HC», siguiendo
  `Componente::MODULOS` (minúsculas, en plural). Revisar que no choque con un parámetro de ruta
  (la lección de `supervisiones`). Mientras no exista la pantalla de módulos (DOC-25, ACC-09), se
  enciende en Básica y Estabilización por migración. La llegada (`InicioController`) hoy solo deja
  a Lectura llegar a Documentos: Usuarios HC lo abre Lectura.
- **Aislamiento:** la ficha lleva el filtro por dependencia, no el de componente (no pertenece a
  uno). La caracterización, el historial y las fotos se protegen en la Policy con el componente de
  registro (y, con DOC-27, las estadías), más el módulo en la ruta y en la Policy. Cualquier
  consulta que se salte el filtro de componente lo hace a propósito y por escrito, como en DOC-25 D4.
- **Búsqueda sin tildes:** los servidores usan MySQL o MariaDB con `utf8mb4_unicode_ci`, que ya
  ignora tildes y mayúsculas (y trata la «ñ» como «n»); las pruebas corren en SQLite, cuyo `LIKE`
  no ignora tildes; y en una posible migración a PostgreSQL tampoco se ignoran sin extensiones.
  El plan debe elegir una forma que no dependa del motor (por ejemplo, guardar aparte el texto
  normalizado para buscar), decidir qué pasa con la «ñ» y probar CA-03 en SQLite y en MySQL.
- **Respuestas de listas (§7):** guardar la opción y el texto que se vio al registrarla; una opción
  retirada se marca, no se borra de la configuración.
- **Selecciones múltiples y JSON:** si se guardan como JSON o con juntas, probar contra MySQL
  (constitución VII). Lo mismo la búsqueda por identidades anteriores.
- **Fotos:** el disco de `config/repositorio.php` (`disco`), `formatos.imagenes`, `tamano_maximo_kb`
  e `imagen.lado_maximo` / `lado_maximo_servidor`. Hoy todas las fotos (marcaciones, derivaciones,
  supervisiones) se guardan como documentos del repositorio y se descargan por la ruta del módulo
  Documentos, que Básica y Estabilización no tienen: hace falta un camino propio con permiso y
  auditoría. El campo de archivo de la cámara sella siempre: necesita poder ir sin sello (PA-15).
- **Auditoría de lecturas:** acciones nuevas, con el precedente de «documento abierto» y «caso
  consultado».
- **Datos de prueba:** fichas ficticias; nunca personas reales.

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
- Ver las fotos anteriores de la persona (ya se conservan, §5; falta la pantalla).
- Carga inicial de fichas existentes (PA-12).
- Atenciones y evoluciones por disciplina: desarrollo futuro en el documento; fuera de alcance.
- Autorización de tratamiento de datos en la ficha, si jurídica la pide (PA-18).
- Búsqueda sin tildes que no dependa del motor de base de datos, si el plan la deja para después
  (ver «Notas para el arquitecto»).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.2 | 2026-11-24 | Alineada con DOC-25 construida (módulo Usuarios HC en Básica y Estabilización; coordinador líder = Administración; la excepción de búsqueda acotada en §6.1; aviso de «Fallecida» sujeto a DOC-27 y ACC-10); qué entrega DOC-26 antes de DOC-27 (§8, componente de registro) y criterios marcados «con DOC-27»; listas cortas en la configuración y catálogos CIE-10 y países de solo lectura (PA-05, PA-13 nueva); fotos con los formatos y tamaños que ya existen (PA-10 resuelta), sin sello (PA-15) y sin sobrescribir; auditoría de miniaturas (PA-17); producción junto con DOC-27 (PA-14); aviso también al coordinador líder (PA-16); autorización de tratamiento (PA-18); nota sobre búsqueda sin tildes; párrafo «Para aprobar» |
| 0.1 | 2026-11-20 | Borrador a partir del documento v1.6 y las decisiones de Edwar |
