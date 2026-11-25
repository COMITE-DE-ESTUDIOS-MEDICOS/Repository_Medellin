# Spec: Usuarios HC · ficha, identificación, caracterización, búsqueda y edición con historial

| | |
|---|---|
| Historia | DOC-26 |
| Tipo | Historia de usuario · módulo nuevo · propone RN-52 a RN-56 · abre una excepción a «cada componente ve solo lo suyo» (RN-01 tal como quedó con DOC-25) |
| Estado | **Aprobada** |
| Versión | 1.1 |
| Fuentes | «Sistema_HC_Especificacion_v1_6» (11-11-2026, de otra persona del Comité; aún sin código de fuente, se propone F-12), secciones 3 a 3.4, 7.2 y 8. Decisiones de Edwar, 2026-11-20. Respuestas por defecto aceptadas por Edwar para avanzar, 2026-11-24. Decisiones de Edwar al aprobar, 2026-11-24. [Investigación del CIE-10 y los países](investigacion-cie10-y-paises.md), 2026-11-24. **Desde la 0.2 no se volvió a leer el documento v1.6 (no está disponible):** lo que aquí se cita de él viene de la 0.1 |
| Depende de | **DOC-25** (acceso por componente), construida en todo lo que esta spec usa: módulos por componente y la pantalla «Componentes y nodos» donde el superadministrador los enciende (ACC-09), aislamiento por componente, coordinador líder, usuarios con componente (ACC-07 y ACC-08), avisos por componente (ACC-10) y auditoría por componente. **DOC-27** (estadías): lo marcado «con DOC-27» (ver §8) |
| La necesita | **DOC-27**, que necesita en `develop` **el 30/11/2026** la tabla y el modelo de Usuarios HC, sus permisos y la búsqueda (§8) |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-24 |

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
  (RN-49). **Lo enciende Edwar**, como superadministrador, desde la pantalla «Componentes y
  nodos» de cada servidor (pruebas y producción), sin migración ni carga de datos (PA-29).
  Mientras no lo encienda, nadie lo ve. Quien es de un componente sin el módulo no lo ve en el
  menú y, si escribe la dirección, recibe «No tienes permiso».
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
  estado · acciones. **La lista no muestra fotos:** la foto se ve solo al abrir la ficha (PA-17).
  Qué dice «componente o estado» depende de si ya existe DOC-27 (§8):
  - **Con DOC-26 sola:** el componente de registro.
  - **Con DOC-27:** el componente donde tiene una estadía activa; si no tiene ninguna,
    **Inactivo**. Eso es independiente de si la persona está viva o fallecida.
- Búsqueda por **nombre, documento, número de ficha o alias**:
  - **antes de buscar, el texto escrito se normaliza:** se quitan las tildes, no importan las
    mayúsculas, la «ñ» cuenta como «n» (PA-23) y solo quedan letras, números y espacios; los
    demás signos (puntos, guiones, comas) cuentan como espacio (PA-22);
  - nombre y alias: **cada palabra** escrita tiene que aparecer, completa o en parte, en el
    nombre completo o en el alias, en cualquier orden («perez juan» encuentra a «Juan Carlos
    Pérez») (PA-22);
  - documento: una parte del número, comparada sin espacios ni signos;
  - el número de ficha se busca exacto, **sin tener en cuenta los guiones**: se ignoran las
    mayúsculas, los espacios de los extremos y los guiones («f-120», «F120» y «F--120» encuentran
    a «F-120», y «F-120» encuentra a una ficha guardada como «F120»), porque son la misma ficha
    (PA-20).
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
ficha vigente; un nombre parecido avisa pero no bloquea. Es «parecido» (PA-21): el mismo primer
nombre y primer apellido (sin tildes ni mayúsculas), el mismo alias, o el mismo número de
documento con otro tipo.

Crear la ficha **no registra un ingreso** ni la ata a una sede. Guarda su **componente de
registro**.

**Identificación**

| Campo | Obligatorio | Valores y reglas |
|---|---|---|
| Tipo de documento | Sí | CC Cédula de ciudadanía · TI Tarjeta de identidad · CE Cédula de extranjería · PPT Permiso por Protección Temporal · PEP Permiso Especial de Permanencia · PAS Pasaporte · RC Registro civil · Sin documento · Otro |
| Número de documento | Sí, salvo «Sin documento» | Se guarda y se muestra como se escribió, sin los espacios de los extremos: conserva las letras y los ceros iniciales. Para validar y comparar se pasa a mayúsculas y se quitan los espacios de los extremos; **después de eso solo puede tener letras de la A a la Z (sin tildes ni «ñ») y números del 0 al 9, de 1 a 20 caracteres**, sin puntos, guiones ni espacios (PA-19). Así se compara igual en el motor de base de datos de las pruebas y en el de los servidores. Con «Sin documento» queda vacío: **no se inventan números**. Único por tipo más número (se permite el mismo número con otro tipo) |
| Número de ficha | No | Se escribe a mano. Se le quitan los espacios de los extremos y se pasa a mayúsculas; **después de eso solo puede tener letras de la A a la Z (sin tildes ni «ñ»), números del 0 al 9 y el guion «-», de 1 a 30 caracteres, con al menos una letra o un número** («---» no se acepta). Se guarda y se muestra así, en mayúsculas y con los guiones como se escribieron («f-120» queda «F-120»). **Para comparar se quitan los guiones:** «a12» y «A12» son la misma ficha; «f-120», «F-120», «F120» y «F--120», también (PA-20). Único entre los números vigentes de toda la dependencia, comparado sin guiones. Vacío no cuenta como repetido |
| Primer nombre | Sí | Puede ser provisional si no se conoce la identidad |
| Segundo nombre, primer apellido, segundo apellido | No | — |
| Alias | No | No reemplaza al primer nombre |
| Foto de la persona | No | Imagen. Formatos, tamaño, metadatos y sello: §6.3 |
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
| Nacionalidad | Una opción con búsqueda | Lista de países (ISO 3166-1) en español, de la tabla «Pais» de SISPRO (PA-13). Sin país preseleccionado. **Sale después del 15/12/2026**, con su catálogo (§7) |
| Comuna o corregimiento donde permanece o vive | Una opción | La misma lista de comunas y corregimientos de Medellín que ya usan los casos de derivación (DOC-23), que está en la configuración de la plataforma: comunas 1 a 16 y corregimientos 50, 60, 70, 80 y 90 |
| EPS | Una opción con búsqueda | Asmet · Asociación Indígena del Cauca · Emsanar · Capital Salud · Coosalud · Nueva EPS · Sanitas · Salud Total · Savia Salud · Sura · Sin afiliación. Autorizada por la aprobación de esta spec, solo para el Usuario HC (PA-07) |
| Listado censal | Una opción | Sí · No |
| Fecha de ingreso al Sistema Habitante de Calle | Fecha | Se escribe a mano. **No es una estadía.** Sin regla de fecha: se acepta también una futura (PA-24) |
| Diagnóstico principal de psiquiatría | Una opción con búsqueda | Catálogo CIE-10 de SISPRO: código y descripción oficial. **Sale después del 15/12/2026** (PA-06) |
| Otros diagnósticos de psiquiatría | Varias, con búsqueda | CIE-10, sin marcar principal (PA-08). **Después del 15/12/2026** |
| Diagnósticos de medicina general | Varias, con búsqueda | CIE-10; si se llena, uno se marca como principal. **Después del 15/12/2026** |
| Antecedentes de protección institucional en la infancia | Una opción | Sí · No |
| Tipo de discapacidad | Varias | Física · Auditiva · Visual · Sordoceguera · Intelectual / cognitiva · Psicosocial (mental) · Ninguna · Sin información. «Ninguna» y «Sin información» excluyen a las demás y entre sí. No hay opción «Múltiple». Un diagnóstico no marca discapacidad por sí solo |
| Cédula física | Una opción | Sí · No |
| Portabilidad | Una opción | Sí · No |
| Código 900 | Una opción | Sí · No |
| Oxígeno dependiente | Una opción | Sí · No |
| Clasificación de autonomía | Una opción | Autónomo · Heterónomo |
| Nivel de independencia funcional | Una opción | Independiente · Requiere supervisión · Requiere ayuda parcial · Requiere ayuda total · Pendiente de valoración |

- Para llenar los diagnósticos basta el permiso de editar la caracterización: no hace falta ser
  de psiquiatría ni de medicina.
- Los diagnósticos **no se validan ni avisan** por la edad o el sexo para los que el CIE-10 dice
  que vale cada código: se puede registrar, por ejemplo, un TDAH a un adulto (PA-25).

### 4. Ver la ficha

La ficha tiene tres partes: **Identificación** (con la foto vigente), **Caracterización** e
**Historial de cambios**. Qué partes ve cada quien está en §6.

### 5. Editar, con motivo e historial

- Se puede corregir la identificación o la caracterización si quien edita tiene el rol (§6) y
  la persona está en su componente:
  - **con DOC-26 sola:** si su componente es el componente de registro de la ficha;
  - **con DOC-27:** si la persona está **activa en su componente**; si la persona todavía no
    tiene ninguna estadía válida, sigue valiendo el componente de registro (§8).
  - El coordinador líder y el superadministrador pueden editar siempre, también a personas
    inactivas o activas en cualquier componente.
- Al guardar, el sistema vuelve a comprobar la dependencia, el permiso y que la persona siga en
  ese componente, y **pide el motivo** cuando se cambia o se quita un dato ya informado y en
  cualquier cambio de la identificación. Llenar por primera vez un campo vacío de la
  caracterización no lo pide, pero queda igual en el historial (PA-04).
- **Nada se sobrescribe sin rastro:** cada cambio guarda el valor anterior, el nuevo, quién,
  cuándo, por qué y desde qué componente (para el coordinador líder, el componente en el que
  estaba parado). Eso es el Historial de cambios de la ficha. En la auditoría de Documenta queda
  que hubo un cambio, **sin los valores** (§6.2).
- **Cambiar o quitar una respuesta de la caracterización reemplaza la respuesta vigente** y la
  anterior queda **completa en el historial**, con la opción (o el código) que era y el texto
  con que se registró; en las de varias opciones, cada opción que sale o entra. **El historial es
  el registro de verdad** de lo que tuvo la ficha: así se cumple «nada se pierde» (constitución
  IV) sin guardar respuestas inactivas en la ficha (PA-28, decisión de Edwar al aprobar).
- **La foto no se sobrescribe:** cambiarla o quitarla deja la anterior guardada (constitución
  IV) y el cambio en el historial. La ficha muestra solo la foto vigente; ver las anteriores es
  un candidato.
- Si otra persona cambió la ficha mientras estaba abierta, se avisa y no se guarda encima.
- **Estado vital:** lo corrige quien puede editar la caracterización, con motivo e historial.
  - **Con DOC-26 sola** no hay estadías: marcar «Fallecida» solo cambia el dato, deja el
    historial y no avisa a nadie.
  - **Con DOC-27:** marcar «Fallecida» desde aquí **no cierra la estadía**. Si hay una activa,
    se avisa por la campana a la Coordinación del componente de esa estadía y al coordinador
    líder, para que registren el egreso (DOC-27 §6; PA-16). Los avisos por componente ya
    existen (DOC-25, ACC-10).
- Una ficha **no se borra** (constitución IV). Las repetidas se unifican (DOC-31).

### 6. Datos sensibles: quién ve qué

La caracterización trae datos sensibles (diagnósticos, consumo de sustancias, orientación
sexual, discapacidad, EPS). Traducido a los roles de Documenta tal como quedaron con DOC-25
(PA-02): Lectura, Edición y Coordinación pertenecen a **un** componente y trabajan dentro de él;
Administración es el coordinador líder, sin componente, y trabaja el componente en el que está
parado.

| Qué | Quién |
|---|---|
| Buscar y ver la **identificación** (nombre, documento, ficha, alias, estado vital y componente que muestra la lista; y, al abrir la ficha, su foto) | Cualquier rol de un componente que tenga el módulo, sobre **todas** las fichas de la dependencia. El coordinador líder y el superadministrador, parados en un componente con el módulo |
| Ver la **caracterización** y el **historial de cambios** (que es donde están los valores de cada cambio) | Edición y Coordinación de un **componente que conoce a la persona** (con DOC-26 sola, el componente de registro; con DOC-27, también donde tiene o tuvo una estadía válida); coordinador líder; superadministrador. Lectura, no (PA-11) |
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

- **la dependencia:** nunca se ve una ficha de otra dependencia (constitución III, RN-01). **Toda
  acción sobre una ficha comprueba la dependencia, no solo «ver»:** verla, abrir su
  caracterización o su historial, ver su foto, abrir la edición y guardar. Una ficha de otra
  dependencia da «No encontrado» en todas, también por dirección directa;
- **el módulo:** un componente sin Usuarios HC (Calle) no ve ninguna ficha, ni por dirección
  directa (RN-49);
- **la caracterización y el historial:** solo para los componentes que conocen a la persona;
  para los demás, rechazo, también por dirección directa;
- **las fotos de ingreso y egreso de una estadía** (DOC-27 §8.1): solo el componente de esa
  estadía (desde Edición, y el coordinador líder parado en ese componente) y el
  superadministrador. Otro componente que conozca a la persona no las ve, aunque vea su
  caracterización; rechazo también por dirección directa (decisión de Edwar 2026-11-24, v1.1);
- **las estadías** (DOC-27): siguen siendo de su componente;
- **la auditoría:** Coordinación sigue viendo solo lo que hicieron los usuarios de su componente
  (DOC-25, PA-06). Si un usuario de Estabilización abre la caracterización de una persona creada
  en Básica, eso lo ve la Coordinación de Estabilización y el coordinador líder, no la de Básica.

#### 6.2 Auditoría

- Los permisos se comprueban en el servidor en cada operación, no solo escondiendo botones.
- **Queda en la auditoría** de Documenta (quién, qué, cuándo, IP y navegador, RN-04): crear una
  ficha, corregirla, **abrir su caracterización o su historial** y **ver su foto**. Ya hay
  precedente: abrir un documento custodiado y consultar un caso desde la vía pública quedan
  auditados.
- **Los datos de salud no van a la auditoría general.** De una corrección, la auditoría guarda
  **solo los nombres de los campos cambiados y el motivo**, nunca los valores anteriores ni los
  nuevos. Los valores quedan solo en el Historial de cambios de la ficha, que se ve con su propio
  permiso (el de la caracterización, §6). Así, quien revisa la auditoría sin poder ver la
  caracterización no lee diagnósticos, sustancias ni orientación sexual.
- Como la lista no muestra fotos (PA-17), buscar no deja registros de fotos: cada vez que
  alguien abre la foto de una persona queda un registro.

#### 6.3 Fotos

- Las fotos de la persona **no se guardan en el módulo Documentos** (que es solo de Calle,
  RN-51): no aparecen en Documentos ni en sus carpetas. Se guardan en el mismo almacenamiento de
  la plataforma, fuera de la web, y solo se abren pasando por el control de permisos y la
  auditoría, nunca por una dirección directa (RN-06, constitución V).
- **Se ve solo al abrir la ficha**, no en la lista de búsqueda (PA-17).
- **Formatos y tamaño: los de las evidencias fotográficas que ya acepta Documenta** (PA-10,
  resuelta): JPG o JPEG (también el JFIF que a veces guarda Windows), PNG y WEBP; hasta el
  tamaño máximo de archivo de la plataforma (hoy 25 MB). En el navegador se reduce a 2560 px en
  su lado largo, y el servidor no acepta más de 4096 px.
- Se puede tomar con la cámara o elegir de la galería. **No lleva el sello de hora, fecha,
  dirección y coordenadas** de las evidencias (PA-15).
- **Sin datos de ubicación:** muchos teléfonos guardan dentro de la foto las coordenadas donde
  se tomó. Se quitan en el navegador y **también en el servidor**, para que una foto que llegue
  sin pasar por el navegador tampoco las conserve.
- **No se guarda en la caché del navegador:** cada vez que se abre pasa por el control de
  permisos y queda en la auditoría.

### 7. Listas de opciones y catálogos

- No hay perfil Parametrizador ni pantalla de Parametrización (decisión de Edwar). Las listas
  de esta spec son **valores de la plataforma que cambia solo Edwar** como superadministrador,
  con un despliegue.
- Cuando una opción se renombra o se retira, **las fichas que ya la tenían conservan el texto
  con que se registró**. Una opción retirada no se ofrece para respuestas nuevas, pero sigue
  mostrándose en las fichas que la tienen. Por eso cada respuesta debe guardar **el texto que vio
  quien la registró** y, además, **qué opción era**, para poder contarla junto con las demás
  aunque después cambie de nombre (nota para el plan).
- **Al corregir una ficha que ya tiene una opción retirada, se acepta guardarla sin cambiarla.**
  Vale para las listas de la caracterización y también para el **tipo de documento**: una ficha
  con un tipo que después se retiró se puede corregir sin que obligue a cambiarlo.
- **Los selectores del CIE-10 y de países muestran el texto guardado** del código que la ficha ya
  tiene registrado, aunque después ese código se haya renombrado o deshabilitado en el catálogo.
- No se crean campos nuevos desde una pantalla: un campo nuevo es un cambio de esta spec.
- Dónde viven:
  - **las listas cortas** (tipo de documento, escolaridad, sustancias, EPS, comunas…): en la
    configuración de la plataforma, junto a las comunas que ya usan los casos (PA-05);
  - **el catálogo CIE-10** (miles de códigos, tabla «CIE10» de SISPRO) **y la lista de países**
    (tabla «Pais» de SISPRO): en catálogos de solo lectura que **se cargan una sola vez** en cada
    servidor y no se editan desde ninguna pantalla (PA-06, PA-13). No son parámetros: son datos
    de referencia externos. Recargarlos es un candidato (PA-26).
- **Carga de los catálogos** (PA-27):
  - el archivo de SISPRO **no se guarda en el repositorio**: Edwar lo carga con un comando en
    cada servidor, como enciende el módulo;
  - la carga **no aparece en la pantalla de Auditoría**, porque los catálogos no pertenecen a
    ninguna dependencia. El comando deja escrito en el registro del servidor qué archivo cargó,
    cuántos códigos y cuándo, y la carga se anota en el `CHANGELOG.md`;
  - **los diagnósticos y la nacionalidad salen después del 15/12/2026**, cuando llegue el
    archivo y se cargue. Hasta entonces esos campos no aparecen en la caracterización.

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
| Marcar «Fallecida» | Cambia el dato, con motivo e historial; sin aviso | Si hay estadía activa, avisa por la campana y no egresa |

Qué entrega DOC-26 sola, para usarla o probarla en el servidor de pruebas: el módulo encendido en
Básica y Estabilización; crear, buscar y ver fichas en toda la dependencia; la caracterización
(sin los diagnósticos ni la nacionalidad, que salen después del 15/12/2026 con sus catálogos,
§7); editar con motivo e historial; identidades anteriores; fotos; auditoría de consultas. Lo
que **no** puede hacer sin DOC-27: que un segundo componente vea la caracterización de una
persona que creó otro (salvo el coordinador líder), ni saber quién está dentro de una sede.

**Lo que DOC-27 necesita de DOC-26, y cuándo:** DOC-27 se construye sobre las fichas. Necesita
en `develop`, **el 30/11/2026**, la tabla y el modelo de Usuarios HC, sus permisos (quién ve y
quién edita una ficha) y la búsqueda. El resto de DOC-26 puede llegar después.

**Calendario y producción** (PA-14, aprobada): DOC-26 termina hacia el
**15/12/2026** y sale a producción **junto con DOC-27**. DOC-26 sola queda en el servidor de
pruebas: en producción, sin ingresos, un componente que recibe a una persona creada en el otro
no podría ver ni completar su caracterización. Además, la salida a producción espera las
respuestas de jurídica sobre la Ley 1581 (PA-09 y PA-18).

## Preguntas abiertas

**Resueltas por Edwar al aprobar (2026-11-24),** todas salvo dos. **PA-09 y PA-18 (Ley 1581)
siguen abiertas: bloquean el paso a producción, no el desarrollo.** Las que no bloqueaban
(PA-08, PA-11, PA-16, PA-19 y PA-21 a PA-26) quedan aprobadas con su respuesta por defecto; las
aceptadas por defecto para avanzar (PA-14, PA-17, PA-28 y PA-29), también. La columna
«Decisión» dice cómo quedó cada una.

| PA | Pregunta | Decisión | Estado |
|---|---|---|---|
| PA-01 | El listado muestra a **todas** las personas de la dependencia, y con DOC-25 cada usuario ve solo su componente (RN-01). ¿Se acepta la búsqueda entre componentes como excepción? DOC-25 ya la anticipa como «la única excepción» si Edwar la aprueba | **Sí**, acotada como en §6.1: cruza solo la identificación; nunca otra dependencia, ni componentes sin el módulo, ni la caracterización, el historial o las estadías | **Resuelta** (Edwar, 2026-11-24) |
| PA-02 | El documento trae siete perfiles y unos diez permisos sueltos (crear, editar identidad, editar caracterización…), cada uno por separado. Nombra además un perfil «Registro de personas y movimientos» que **no está en su lista de perfiles**. ¿Se traduce a los roles de Documenta como en §6? | **Sí:** Lectura busca y ve identificación; Edición crea, ve y edita en su componente; Coordinación, lo mismo aquí, y suma lo de DOC-27, DOC-30 y DOC-31; Administración es el coordinador líder y ve y edita todo. Sin permisos sueltos por ahora; si hace falta separar «editar identidad», se agrega una casilla por persona como «Aprueba novedades» (candidato) | **Resuelta** (Edwar, 2026-11-24) |
| PA-03 | Una ficha sin ninguna estadía (siempre, mientras no exista DOC-27): ¿quién puede verle y completarle la caracterización? El documento permite el llenado inicial antes del primer ingreso, pero la edición exige estadía activa | La ve y la completa, desde Edición, el **componente donde se creó**: la ficha guarda ese componente de registro, que no cambia. Con DOC-27, se suman los componentes donde tenga o haya tenido una estadía válida, y editar pasa a ser del componente donde esté activa (§8) | **Resuelta** (Edwar, 2026-11-24) |
| PA-04 | ¿Llenar por primera vez un campo vacío de la caracterización pide motivo? El documento exige motivo para «correcciones» | Motivo obligatorio al cambiar un dato ya informado y en cualquier dato de identificación; llenar un campo vacío no lo pide, pero queda en el historial (§5) | **Resuelta** (Edwar, 2026-11-24) |
| PA-05 | ¿Dónde viven las **listas cortas** de opciones? La constitución dice que los valores de la plataforma están en la configuración y que no hay tabla de parámetros | En la configuración de la plataforma, como las comunas, que Edwar cambia y despliega. Sin pantalla. Una opción retirada se marca como retirada, no se quita, para que las fichas que la tienen la sigan mostrando (§7) | **Resuelta** (Edwar, 2026-11-24) |
| PA-06 | **Catálogo CIE-10:** ¿de qué fuente oficial en español y qué versión? Son miles de códigos | La tabla de referencia **«CIE10» de SISPRO** (Ministerio de Salud; la misma que exige el RIPS; Res. 1895 de 2001): 12.634 códigos, el capítulo de psiquiatría (F00–F99) completo, última actualización 22-oct-2026 (investigación, §2.1 a §2.3). Se registra como fuente con su fecha. **Se carga una sola vez**; recargarla es candidato (PA-26). **Se recorta del 15/12/2026:** los tres campos de diagnóstico salen después, cuando llegue el archivo. La carga no va a la pantalla de Auditoría: queda en el registro del servidor (archivo, cantidad de códigos, fecha) y en el `CHANGELOG.md` (§7) | **Resuelta** (Edwar, 2026-11-24) |
| PA-07 | La caracterización incluye **EPS**, y la constitución (V) dice que datos de EPS no deben entrar a Documenta «mientras no exista una funcionalidad aprobada para ellos». ¿Aprobar esta spec es esa aprobación? | **Sí, solo para la EPS del Usuario HC**; la regla nació para los datos de Nómina, que siguen fuera. Que quede escrito en las decisiones al aprobar. La lista de nombres de EPS en el código no es un dato personal; la EPS de cada persona vive solo en la base de datos. Escrita en las decisiones al aprobar | **Resuelta** (Edwar, 2026-11-24) |
| PA-08 | «Otros diagnósticos de psiquiatría» repite en el documento la regla «uno principal», aunque ya existe el campo del principal | En «Otros» no se marca principal; en medicina general, sí | **Resuelta** con la respuesta por defecto |
| PA-09 | ¿Cuánto tiempo se guardan las fotos y los datos de un Usuario HC? La constitución (V) pide un plazo de retención definido por el Comité | Lo define el Comité (jurídica); mientras tanto no se borra nada, como hoy con las fotos de turnos (contexto, PA-10 del dominio, también abierta) | **Abierta.** Bloquea el paso a producción, no el desarrollo (Edwar, 2026-11-24) |
| PA-10 | ~~Formatos de la foto~~ | **Resuelta en 0.2 con lo que ya existe:** los formatos de las evidencias fotográficas (JPG/JPEG/JFIF, PNG, WEBP), el tamaño máximo de la plataforma y la reducción a 2560 px con tope de 4096 px en el servidor (§6.3) | **Resuelta** (0.2) |
| PA-11 | ¿Lectura debe ver la caracterización? El perfil «Consulta y reportes» del documento es «sin contenido asistencial» | No: Lectura ve identificación (y, con DOC-28 y DOC-29, tablero y reportes) | **Resuelta** con la respuesta por defecto |
| PA-12 | ¿Hay fichas que ya existan en Excel u otro sistema y deban cargarse al empezar? El documento no lo dice | **No hay carga inicial de fichas en este alcance.** Si la coordinación de Básica o de Estabilización tiene fichas en Excel, va en una spec aparte, después de diciembre | **Resuelta** (Edwar, 2026-11-24) |
| PA-13 | El CIE-10 (miles de códigos) y los países (unos 250) no caben bien en la configuración, y la constitución dice que no hay tabla de parámetros. ¿Dónde viven? | En **catálogos de solo lectura**, sin pantalla para editarlos, **cargados una sola vez** en cada servidor (recargarlos es candidato). Países: la tabla de referencia **«Pais» de SISPRO** (la del RIPS; 249 países con código ISO 3166-1, nombres en español; 3-oct-2018; investigación §2.6). No son parámetros: no guardan valores que cambien cómo funciona Documenta, sino datos de referencia externos. **No hace falta cambiar la constitución.** **La nacionalidad se recorta del 15/12/2026:** sale con el catálogo. La carga queda en el registro del servidor y en el `CHANGELOG.md`, no en la pantalla de Auditoría (§7) | **Resuelta** (Edwar, 2026-11-24) |
| PA-14 | El plan de trabajo construye DOC-26 antes que DOC-27. ¿DOC-26 se publica en producción sola o junto con DOC-27? | **Juntas a producción.** DOC-26 sola queda en el servidor de pruebas (§8). DOC-26 termina hacia el **15/12/2026** | **Resuelta** (Edwar, 2026-11-24) |
| PA-15 | RN-13 dice que toda foto tomada con la cámara lleva el sello de hora, fecha, dirección y coordenadas. ¿También la foto de la persona? | **No:** es un retrato para identificar, no la evidencia de una actuación, y las coordenadas serían un dato personal más sin uso. Se anota en RN-13 como excepción. Se puede tomar con la cámara o elegir de la galería; se le quitan los datos de ubicación (§6.3) | **Resuelta** (Edwar, 2026-11-24) |
| PA-16 | El aviso por «Fallecida» con estadía activa: la 0.1 y DOC-27 §6 dicen «a la Coordinación del componente». DOC-25 §7 hace que los avisos de un componente lleguen también al coordinador líder | A la Coordinación del componente de la estadía **y al coordinador líder**, como los demás avisos desde DOC-25. Se ajusta DOC-27 §6 igual. Necesita un tipo nuevo en la campana (RN-38) | **Resuelta** con la respuesta por defecto |
| PA-17 | RN-06 dice que toda vista previa de un archivo queda registrada, y la búsqueda mostraba la foto de cada persona. Auditar cada miniatura llenaría la auditoría | **La lista no muestra fotos; la foto se ve solo al abrir la ficha**, y cada vez queda en la auditoría. La alternativa de la 0.2 (fotos en la lista con un registro por página) pasa a candidato | **Resuelta** (Edwar, 2026-11-24) |
| PA-18 | Los datos de salud y la orientación sexual son datos sensibles (Ley 1581 de 2012). ¿Hay que registrar en la ficha la autorización de tratamiento de datos de la persona? El documento (según la 0.1) no lo pide | Lo define jurídica del Comité junto con la retención (PA-09). Si hace falta, es un campo nuevo en una versión siguiente | **Abierta.** Bloquea el paso a producción, no el desarrollo (Edwar, 2026-11-24) |
| PA-19 | **Nueva (del plan, P-02).** ¿Qué caracteres acepta el número de documento? ¿Algún documento (pasaporte, PPT, «Otro») trae guion o punto? | Después de pasar a mayúsculas y quitar los espacios de los extremos: solo letras A–Z (sin tildes ni «ñ») y números 0–9, de 1 a 20 caracteres (§2). Es la misma forma del usuario de Authentik (RN-03). Escrito con puntos, guiones o espacios, se rechaza con «Escribe el número solo con letras y números, sin puntos, guiones ni espacios». Alternativa (descartada): quitar los puntos solos, como la cédula de las personas de campo (RN-14) | **Resuelta** con la respuesta por defecto |
| PA-20 | **Nueva (del plan, P-03).** ¿«a12» y «A12» son el mismo número de ficha? | **Sí:** se ignoran mayúsculas y espacios de los extremos, como en el documento. Para que el motor de pruebas y el de los servidores comparen igual, la ficha acepta **A–Z sin tildes ni «ñ», 0–9 y el guion «-»**, de 1 a 30 caracteres, con al menos una letra o un número (**se permite el guion**, decisión de Edwar al aprobar). Se guarda y se muestra en mayúsculas, con sus guiones. **Para comparar se quitan los guiones** (decisión de Edwar, 2026-11-24, v1.1): «f-120» = «F-120» = «F120» = «F--120», en la unicidad y en la búsqueda (§1, §2) | **Resuelta** (Edwar, 2026-11-24) |
| PA-21 | **Nueva (del plan, P-08).** ¿Qué cuenta como «nombre parecido» en el aviso de coincidencias? | El mismo primer nombre y primer apellido (sin tildes ni mayúsculas), o el mismo alias, o el mismo número de documento con otro tipo (§2). Sin comparación por sonido («Yeison» y «Jeison»): no hay una que funcione igual en los dos motores; queda como candidato | **Resuelta** con la respuesta por defecto |
| PA-22 | **Nueva (del plan, P-09).** ¿Cómo se busca con varias palabras? | Cada palabra tiene que aparecer en el nombre completo o en el alias, en cualquier orden. Los signos (puntos, guiones, comas) cuentan como espacio. El documento se compara sin espacios ni signos; la ficha, exacta y sin guiones (§1, PA-20) | **Resuelta** con la respuesta por defecto |
| PA-23 | **Nueva (del plan, P-10).** ¿La «ñ» se distingue de la «n» al buscar? | **No:** «Pena» encuentra a «Peña», como ya pasa hoy en los servidores | **Resuelta** con la respuesta por defecto |
| PA-24 | **Nueva (del plan, P-11).** ¿La fecha de ingreso al Sistema Habitante de Calle puede ser futura? | **Sí, sin regla** (la 0.2 no ponía ninguna). Si Edwar quiere que no sea futura, se agrega un criterio | **Resuelta** con la respuesta por defecto |
| PA-25 | **Nueva (del plan, P-13).** El CIE-10 de SISPRO dice para cada código el sexo y la edad en que vale (F900, TDAH, de 1 a 17 años; 885 códigos para un solo sexo). ¿Se valida? Si se bloquea, a un adulto no se le podría registrar un TDAH, y el sexo no se puede cruzar con «No binario» o «Transgénero», ni la edad sin fecha de nacimiento | **No se valida ni se avisa** (§3). Avisar sin bloquear («Este código suele usarse de 1 a 17 años») queda como candidato | **Resuelta** con la respuesta por defecto |
| PA-26 | **Nueva (del plan, P-16).** La CIE-11 reemplaza a la CIE-10 hacia el **14-ago-2027** (Res. 1442 de 2024 y 1657 de 2025; el RIPS ya pide las dos desde junio de 2026). Los tres campos de diagnóstico dicen «Catálogo CIE-10» | DOC-26 registra **solo CIE-10**, con el catálogo cargado una vez. Antes de agosto de 2027, una spec aparte decide si se registra en CIE-11, en las dos (doble codificación) o se convierte, y la recarga del catálogo. Las dos van a candidatos | **Resuelta** con la respuesta por defecto |
| PA-27 | **Nueva.** ¿Dónde queda el Excel del CIE-10 y de países que se carga? La investigación (§2.7) dice guardarlo **fuera del repositorio**; el plan (D9) lo pone **dentro**, para que el despliegue lo cargue solo. No trae datos personales; ni SISPRO ni la OMS publican una licencia escrita para la CIE-10 (investigación §2.1) | **Fuera del repositorio.** Edwar lo carga con un comando en cada servidor, como enciende el módulo (PA-29); el repositorio no guarda el archivo. El comando escribe en el registro del servidor qué archivo cargó, cuántos códigos y cuándo, y la carga se anota en el `CHANGELOG.md` (§7) | **Resuelta** (Edwar, 2026-11-24) |
| PA-28 | **Nueva (del plan, P-05).** Cambiar o quitar una respuesta de la caracterización, ¿qué deja? | **La respuesta vigente se reemplaza y la anterior queda completa en el historial**, con su opción o código y su texto. El historial es el registro de verdad; así se cumple «nada se pierde» (constitución IV), y se escribe en `domain.md` como forma de cumplirla (§5; P-17 del plan) | **Resuelta** (Edwar, 2026-11-24) |
| PA-29 | **Nueva.** ¿Cómo se enciende el módulo Usuarios HC en Básica y Estabilización? | **Lo enciende Edwar**, como superadministrador, desde la pantalla «Componentes y nodos» de cada servidor (construida con DOC-25, ACC-09), **sin migración ni carga de datos** | **Resuelta** (Edwar, 2026-11-24) |

## Criterios de aceptación

Los criterios marcados **«con DOC-27»** solo se pueden probar cuando existan las estadías; los
marcados **«después del 15/12»** salen con los catálogos CIE-10 y países (PA-06, PA-13); los
demás se prueban con DOC-26 sola.

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Un usuario de Básica o de Estabilización, con cualquier rol, ve «Usuarios HC» en el menú y, al entrar, llega a él; uno de Calle no lo ve y recibe rechazo por dirección directa (lista, ficha y foto). El coordinador líder lo ve parado en Básica o Estabilización y no parado en Calle | Por construir |
| CA-02 | El listado incluye las fichas creadas en el otro componente, con su componente de registro, y nunca fichas de otra dependencia | Por construir |
| CA-03 | La búsqueda normaliza el texto antes de buscar (sin tildes ni mayúsculas, «ñ» como «n», signos como espacio) y encuentra: por cada palabra en el nombre o el alias, en cualquier orden y en parte («perez juan» y «pena» encuentran a «Juan Carlos Peña Pérez»); por parte del documento aunque se escriba con puntos o espacios; y por número de ficha exacto, sin importar mayúsculas ni guiones («f-120» y «F120» encuentran a «F-120»). Da los mismos resultados en el motor de pruebas y en el de los servidores | Por construir |
| CA-04 | Después de corregir un nombre o un documento, buscar el dato anterior lleva a la ficha vigente y lo señala como coincidencia anterior | Por construir |
| CA-05 | Se crea una ficha solo con tipo «Sin documento» y un primer nombre provisional; el número queda vacío | Por construir |
| CA-06 | No se crea una ficha con el mismo tipo y número de documento de otra (aunque cambien mayúsculas o espacios, y aunque la otra se haya creado en otro componente), ni con un número de ficha vigente en otra; sí con el mismo número y otro tipo | Por construir |
| CA-07 | Un nombre parecido a otro (mismo primer nombre y primer apellido sin tildes ni mayúsculas, mismo alias, o mismo número de documento con otro tipo, PA-21) muestra el aviso de coincidencia, con solo la identificación de la otra ficha, y deja crear | Por construir |
| CA-08 | Un documento con letras y ceros iniciales se guarda y se muestra tal cual | Por construir |
| CA-09 | La caracterización se guarda con todos los campos vacíos y con solo algunos; un campo vacío se muestra como no informado | Por construir |
| CA-10 | «No consume» no se puede guardar junto con otra sustancia; «Ninguna» y «Sin información» de discapacidad, tampoco con otras ni entre sí | Por construir |
| CA-11 | Años, meses y semanas de tiempo en calle se guardan por separado, sin convertir ni rellenar con cero | Por construir |
| CA-12 | La edad se calcula desde la fecha de nacimiento; una fecha futura se rechaza; sin fecha dice «Sin información» | Por construir |
| CA-13 | Edición y Coordinación del componente de registro editan la ficha; las del otro componente reciben rechazo al abrir la edición, por dirección directa y al guardar; Lectura no edita; el coordinador líder y el superadministrador sí | Por construir |
| CA-14 | Cada edición guarda valor anterior, valor nuevo, autor, fecha y hora, motivo y componente (el del coordinador líder, el que tenía elegido), y se ve en el Historial de cambios; sin motivo no se guarda una corrección | Por construir |
| CA-15 | Si la ficha cambió mientras otro la editaba, el segundo guardado se rechaza con aviso | Por construir |
| CA-16 | Un usuario del componente que no creó la ficha ve su identificación y **recibe rechazo** al abrir su caracterización y su historial; Lectura de cualquier componente tampoco los ve; el coordinador líder sí | Por construir |
| CA-17 | Abrir la caracterización, abrir el historial y abrir la foto quedan en la auditoría, cada vez; la lista no muestra fotos; la foto no se abre sin sesión ni sin permiso | Por construir |
| CA-18 | Una ficha ya guardada con una opción que después se renombró o se retiró conserva el texto original; la opción retirada no se ofrece para respuestas nuevas, y al corregir esa ficha se acepta sin cambiarla. **Vale también para el tipo de documento:** una ficha con un tipo retirado se corrige (por ejemplo, el nombre) y se guarda conservando su tipo; una ficha nueva no puede elegirlo | Por construir |
| CA-19 | Marcar «Fallecida» a una persona sin estadías pide motivo, deja el historial y no envía avisos | Por construir |
| CA-20 | La ficha guarda su componente de registro (el de quien la crea; para el coordinador líder, el que tenía elegido) y editarla no lo cambia | Por construir |
| CA-21 | Ni la lista, ni el aviso de coincidencias, ni el mensaje de documento o ficha repetidos muestran datos de caracterización o del historial de una ficha que el usuario no puede ver | Por construir |
| CA-22 | La foto acepta JPG, JPEG, JFIF, PNG y WEBP hasta el tamaño máximo; rechaza otros formatos y fotos de más de 4096 px de lado; no aparece en Documentos; no lleva sello; cambiarla conserva la anterior y deja el cambio en el historial | Por construir |
| CA-23 | La lista muestra el componente donde la persona tiene una estadía activa, o «Inactivo»; el filtro de estado separa Activos e Inactivos | Por construir (con DOC-27) |
| CA-24 | Edición y Coordinación editan solo a personas activas en su componente (o, sin ninguna estadía válida, del componente de registro); a una persona que hoy no está activa en ninguno solo la editan el coordinador líder y el superadministrador | Por construir (con DOC-27) |
| CA-25 | Un componente donde la persona tuvo una estadía activa o egresada ve su caracterización y su historial; una estadía anulada no da ese acceso | Por construir (con DOC-27) |
| CA-26 | Marcar «Fallecida» a una persona con estadía activa no la egresa y avisa a la Coordinación de ese componente y al coordinador líder | Por construir (con DOC-27) |
| CA-27 | «Registrar ingreso» aparece en la lista solo para quien puede registrarlo y solo para personas vivas sin estadía activa | Por construir (con DOC-27) |
| CA-28 | **Nuevo.** El número de documento se acepta si, pasado a mayúsculas y sin los espacios de los extremos, tiene solo letras A–Z y números 0–9 y de 1 a 20 caracteres («  ab0012 » se acepta y choca con «AB0012» del mismo tipo); con puntos, guiones, espacios internos, tildes, «ñ» o más de 20 caracteres se rechaza con un mensaje que dice cómo escribirlo. Las mismas comparaciones dan lo mismo en el motor de pruebas y en el de los servidores | Por construir |
| CA-29 | **Cambia en 1.1.** «a12» y « A12 » son el mismo número de ficha; «f-120», «F-120» y «F120» también: no se pueden tener vigentes en dos fichas de la dependencia. Buscar «F120» encuentra la ficha guardada como «F-120». Escrita «f-120», la ficha se guarda y se muestra «F-120». «---», «F.120», tildes, «ñ», espacios internos y más de 30 caracteres se rechazan con un mensaje que dice cómo escribirlo. Todo da lo mismo en el motor de pruebas y en el de los servidores (PA-20) | Por construir |
| CA-30 | **Nuevo.** Corregir la identificación o la caracterización deja en la auditoría general los nombres de los campos cambiados y el motivo, **sin los valores** anteriores ni nuevos; los valores se ven solo en el Historial de cambios, con el permiso de la caracterización | Por construir |
| CA-31 | **Nuevo.** Una ficha de otra dependencia da «No encontrado» al verla, al abrir su caracterización, su historial, su foto y su edición, y al guardar una corrección o una foto, también por dirección directa y aunque quien lo intenta sea coordinador líder en su dependencia | Por construir |
| CA-32 | **Nuevo.** Una foto que llega al servidor con coordenadas dentro (datos de ubicación de la cámara) se guarda sin ellas, también si no pasó por el navegador; al abrirla, la respuesta le dice al navegador que no la guarde en su caché | Por construir |
| CA-33 | Una ficha con un diagnóstico o una nacionalidad cuyo código después se renombró o se deshabilitó en el catálogo muestra, en la ficha y en el selector al editar, el texto con que se registró; guardar la edición sin tocar ese campo lo conserva | Por construir (después del 15/12) |
| CA-34 | Cambiar o quitar una respuesta de la caracterización (de una o de varias opciones, también un diagnóstico o la nacionalidad cuando existan) reemplaza la respuesta vigente, y la anterior queda completa en el historial con su opción o código y su texto; la ficha muestra solo la vigente | Por construir |
| CA-35 | **Nuevo.** Mientras el superadministrador no enciende Usuarios HC en un componente desde «Componentes y nodos», nadie de ese componente lo ve; al encenderlo, sí, sin otro paso | Por construir |
| CA-36 | **Nuevo.** Cargar el CIE-10 o los países desde un archivo fuera del repositorio llena el catálogo una sola vez; el comando deja en el registro del servidor qué archivo cargó, cuántos códigos y cuándo; la carga no aparece en la pantalla de Auditoría; los selectores de diagnóstico y nacionalidad ofrecen los códigos cargados | Por construir (después del 15/12) |
| CA-37 | **Nuevo.** Llenar por primera vez un campo vacío de la caracterización se guarda sin motivo y queda en el historial; cambiar o quitar un dato ya informado, o cualquier dato de la identificación, sin motivo no se guarda (PA-04) | Por construir |

## Reglas que cambian

Propuestas para `docs/context/domain.md` al aprobarse (el número lo fija quien lo actualice):

- **RN-52. Una ficha por persona.** El Usuario HC tiene una sola ficha en la dependencia; no
  pertenece a una sede ni a un componente, pero guarda su componente de registro. Buscarla está
  permitido entre componentes con el módulo Usuarios HC, y solo muestra la identificación.
  **Es la excepción a RN-01 que DOC-25 dejó anunciada;** no cruza dependencias, y toda acción
  sobre la ficha comprueba la dependencia.
- **RN-53. Identidad.** Tipo más número de documento es único; el número, en mayúsculas, solo
  lleva letras A–Z y números (1 a 20); sin documento no hay número; el número de ficha, en
  mayúsculas, lleva letras A–Z, números y guion (1 a 30, con al menos una letra o un número), se
  compara sin guiones («F-120» = «F120») y es único entre los vigentes; las
  identidades anteriores siguen buscables.
- **RN-54. La caracterización es opcional.** Vacío es «no informado».
- **RN-55. Corregir con motivo e historial.** Edita el componente donde la persona está activa
  o, sin estadías, el componente de registro; el coordinador líder y el superadministrador,
  siempre. Nunca se pierde el valor anterior: la respuesta vigente de la caracterización se
  reemplaza y la anterior queda completa en el historial, que es el registro de verdad; la foto
  anterior se conserva. **Esta es la forma de cumplir «nada se pierde» (constitución IV) en la
  caracterización y se nombra así en `domain.md`.**
- **RN-56. Datos sensibles del Usuario HC.** La caracterización y el historial los ven, desde
  Edición, los componentes que conocen a la persona (registro o estadía válida), el coordinador
  líder y el superadministrador; abrirlos y ver la foto queda auditado. La auditoría general
  guarda de cada corrección solo los campos y el motivo, no los valores. La foto se ve solo al
  abrir la ficha, sin datos de ubicación y sin quedar en la caché del navegador.
- **RN-49:** se agrega el módulo **Usuarios HC**, encendido en Básica y Estabilización por el
  superadministrador desde «Componentes y nodos»; se abre desde Lectura. El texto «Básica y
  Estabilización, ninguno por ahora» cambia.
- **RN-04 y RN-06:** se extienden a las fichas y fotos de Usuarios HC, incluidas las lecturas
  (abrir la caracterización o el historial, ver la foto). RN-04 aclara que, en Usuarios HC, la
  auditoría de una corrección no lleva los valores (están en el historial de la ficha).
  **Excepción nombrada:** la carga de los catálogos CIE-10 y países no va a la auditoría de
  Documenta (no pertenece a ninguna dependencia); queda en el registro del servidor (archivo,
  cantidad de códigos, fecha) y en el `CHANGELOG.md`.
- **RN-13:** excepción para la foto de la persona, sin sello (PA-15).
- **RN-38:** la campana necesita un tipo para los avisos de Usuarios HC (con DOC-27, PA-16).
- **«Parámetros del negocio»** de `domain.md`: dos filas nuevas, las listas de Usuarios HC (en
  la configuración, solo el superadministrador) y los catálogos CIE-10 y países (tablas de
  SISPRO, solo lectura, cargados una vez por Edwar con un comando, con el archivo fuera del
  repositorio) (PA-05, PA-13, PA-27). En la fila «Módulos del componente», Usuarios HC.
- **`glossary.md`:** Usuario HC (quitar «por construir»), Ficha, Caracterización, Estado vital,
  Componente de registro, Identidad anterior. En **Módulo**, agregar Usuarios HC.
- **Constitución V:** la aprobación de esta spec es la «funcionalidad aprobada» para la EPS
  **del Usuario HC** y nada más (PA-07); los datos de EPS de Nómina siguen fuera. PA-09
  (retención) y PA-18 (autorización de tratamiento) siguen abiertas y bloquean el paso a
  producción.
- **Constitución, fila «Parámetros» del stack:** no cambia (PA-13): los catálogos de referencia
  externos no son parámetros.

## Notas para el arquitecto

No cambian el comportamiento; son puntos que el `plan.md` debe resolver. **El plan y las tareas
de la 0.2 cambian** en lo que dice cada nota marcada «cambia».

- **Módulo (cambia):** la clave `usuarios_hc` con la etiqueta «Usuarios HC», siguiendo
  `Componente::MODULOS`. Revisar que no choque con un parámetro de ruta (la lección de
  `supervisiones`). **Ya no se enciende por migración ni por seeder** (PA-29): lo hace Edwar en
  «Componentes y nodos» (ACC-09, construida). La llegada (`InicioController`) hoy solo deja a
  Lectura llegar a Documentos: Usuarios HC lo abre Lectura.
- **Aislamiento:** la ficha lleva el filtro por dependencia, no el de componente (no pertenece a
  uno). La caracterización y el historial se protegen en la Policy con el componente de registro
  (y, con DOC-27, las estadías), más el módulo en la ruta y en la Policy. **La foto de la persona
  es parte de la identificación** (§6): la ve todo componente con el módulo; lo restringido son
  las fotos de estadías (DOC-27). Toda acción comprueba la dependencia (CA-31). Cualquier
  consulta que se salte el filtro de componente lo hace a propósito y por escrito, como en DOC-25
  D4.
- **Búsqueda (cambia):** la normalización a letras, números y espacios es ahora regla (§1,
  CA-03), y el documento queda en ASCII `[0-9A-Z]{1,20}` (CA-28). **La ficha acepta guion**
  (`[0-9A-Z-]{1,30}` con al menos un carácter que no sea guion, PA-20), se guarda en mayúsculas
  con sus guiones y **se compara sin guiones** en la unicidad y en la búsqueda (v1.1; CA-03,
  CA-29). Probar CA-03, CA-28 y CA-29 en SQLite y en MySQL.
- **Respuestas de listas (§7):** guardar la opción y el texto que se vio al registrarla; una
  opción retirada se marca, no se borra de la configuración. Al cambiar, la fila vigente se
  reemplaza y el historial guarda completa la respuesta anterior, con opción o código y texto
  (CA-34, P-17 del plan).
- **Selecciones múltiples y JSON:** si se guardan como JSON o con juntas, probar contra MySQL
  (constitución VII). Lo mismo la búsqueda por identidades anteriores.
- **Fotos (cambia):** sin miniaturas en la lista ni registro por página (PA-17). Los metadatos de
  ubicación se quitan también en el servidor (CA-32; el plan decía que solo el navegador). La
  respuesta no debe dejar guardarla en caché (el plan D10 decía `Cache-Control: private`, que sí
  lo deja). Hace falta un camino propio con permiso y auditoría, fuera de Documentos. El campo de
  archivo de la cámara sella siempre: necesita poder ir sin sello (PA-15).
- **Auditoría (cambia):** la acción de corrección no lleva valores (CA-30); el plan D4 los
  ponía.
- **Catálogos (cambia):** se cargan una sola vez (PA-06, PA-13); el plan D9 los hacía
  recargables y ponía el archivo dentro del repositorio: **va fuera** (PA-27), cargado con un
  comando por Edwar en cada servidor, que escribe en el registro del servidor y no en la
  auditoría (CA-36). Diagnósticos y nacionalidad se recortan del 15/12. Los selectores muestran
  el texto guardado (CA-33).
- **Datos de prueba:** fichas ficticias; nunca personas reales.

## Decisiones (Edwar, 2026-11-20)

- Se construye dentro de Documenta, con ingreso por Authentik.
- La persona atendida es «Usuario HC» y el módulo «Usuarios HC».
- No hay Parametrizador: las listas las cambia solo Edwar.
- Los perfiles y permisos del documento no se adoptan tal cual: se traducen a los roles de
  Documenta y al acceso por componente.
- Se empieza por Básica y Estabilización.

**Respuestas por defecto aceptadas para avanzar (Edwar, 2026-11-24):** catálogos cargados una
sola vez (PA-06, PA-13); fotos solo al abrir la ficha (PA-17); la respuesta anterior se conserva
en el historial (PA-28); PA-09 y PA-18 bloquean el paso a producción, no el desarrollo; DOC-26
termina hacia el 15/12/2026 y sale a producción con DOC-27 (PA-14); el módulo lo enciende Edwar
desde «Componentes y nodos» (PA-29). El Dueño de Producto es Edwar; José desarrolla.

## Decisiones al aprobar (Edwar, 2026-11-24)

- **EPS (PA-07):** aprobar esta spec autoriza guardar **solo la EPS del Usuario HC**. La regla de
  la constitución V («datos de salarios, EPS, cuentas bancarias no deben entrar… mientras no
  exista una funcionalidad aprobada») **nació para Nómina, que sigue fuera**: ningún dato de EPS
  de Nómina o de Talento Humano entra por esta spec.
- **Número de ficha con guion (PA-20):** acepta A–Z sin tildes ni «ñ», 0–9 y «-», de 1 a 30
  caracteres, sin distinguir mayúsculas y sin espacios en los extremos. **Cambiado por la
  decisión del 2026-11-24 (v1.1), abajo.**

## Decisión de Edwar (2026-11-24, v1.1)

- **La ficha se compara sin guion:** «F120» y «F-120» son la misma ficha. Se acepta escribir con
  guion (A–Z sin tildes ni «ñ», 0–9 y «-», de 1 a 30 caracteres, sin distinguir mayúsculas y sin
  espacios en los extremos), con al menos una letra o un número («---» no). Se guarda y se
  muestra en mayúsculas, como se escribió («F-120»). Para la unicidad entre vigentes de toda la
  dependencia y para la búsqueda se quitan los guiones: «f-120», «F-120», «F120» y «F--120» son
  la misma ficha.
- **Excel de los catálogos fuera del repositorio (PA-27):** el archivo del CIE-10 y de países no
  se versiona. Edwar lo carga con un comando en cada servidor, como enciende el módulo.
- **Registro de la carga de los catálogos:** no aparece en la pantalla de Auditoría, porque no
  pertenece a ninguna dependencia. El comando escribe en el registro del servidor qué archivo
  cargó, cuántos códigos y cuándo, y la carga se anota en el `CHANGELOG.md` (excepción nombrada
  a RN-04, ver «Reglas que cambian»).
- **Recortes del 15/12/2026 (PA-06, PA-13):** los tres campos de diagnóstico (tabla «CIE10» de
  SISPRO) y la nacionalidad (tabla «Pais» de SISPRO) salen después, cuando llegue el archivo y
  se cargue. El resto de DOC-26 sigue en el calendario.
- **Reemplazo con historial (P-17 del plan, PA-28):** al cambiar una respuesta de la
  caracterización, la fila vigente se reemplaza y la anterior queda completa en el historial,
  con su código y su texto. **El historial es el registro de verdad.** Se escribe en `domain.md`
  como forma de cumplir «nada se borra» (lo hace el plan en su lista de documentación).
- **Catálogos (PA-13):** de solo lectura, no son parámetros; no hace falta cambiar la
  constitución.
- **Sin carga inicial de fichas (PA-12):** si la coordinación de Básica o de Estabilización tiene
  fichas en Excel, va en una spec aparte, después de diciembre.
- **Foto de la persona (PA-15):** sin sello (excepción a RN-13) y sin datos de ubicación.
- **PA-01 a PA-05:** aprobadas como estaban propuestas (búsqueda que cruza componentes solo con
  la identificación; roles de §6 sin permisos sueltos; componente de registro; motivo según
  PA-04; listas cortas en la configuración, con opciones retiradas marcadas).
- **PA-14, PA-17, PA-28, PA-29** quedan aprobadas; **PA-08, PA-11, PA-16, PA-19 y PA-21 a
  PA-26**, con su respuesta por defecto.
- **PA-09 y PA-18 (Ley 1581) siguen abiertas:** bloquean el paso a producción, no el desarrollo.

## Candidatos para la siguiente versión

- Permisos sueltos por persona (editar identidad, editar caracterización) si los roles no alcanzan.
- Gráficas de caracterización (el documento las deja como desarrollo futuro).
- Ver las fotos anteriores de la persona (ya se conservan, §5; falta la pantalla).
- Fotos en la lista de búsqueda, con un solo registro de auditoría por página (alternativa de PA-17).
- Carga inicial de fichas existentes en Excel, en una spec aparte después de diciembre (PA-12).
- Atenciones y evoluciones por disciplina: desarrollo futuro en el documento; fuera de alcance.
- Autorización de tratamiento de datos en la ficha, si jurídica la pide (PA-18).
- Recargar el CIE-10 y los países cuando SISPRO publique otra tabla, y la CIE-11 o la doble
  codificación antes del 14-ago-2027 (PA-26).
- Aviso, sin bloquear, cuando un diagnóstico no corresponde a la edad o el sexo que indica el
  CIE-10 (PA-25).
- Búsqueda de nombres por cómo suenan («Yeison» y «Jeison») (PA-21).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.1 | 2026-11-24 | Decisión de Edwar 2026-11-24: la ficha se compara sin guion. «F-120» = «F120» = «F--120» en la unicidad y en la búsqueda; se guarda y se muestra en mayúsculas con sus guiones; se exige al menos una letra o un número. Cambian §1, §2, PA-20, PA-22, CA-03, CA-29, RN-53 y la nota de búsqueda. Sigue aprobada |
| 1.0 | 2026-11-24 | **Aprobada por Edwar.** Resueltas todas las PA salvo PA-09 y PA-18 (Ley 1581), que siguen abiertas y bloquean el paso a producción, no el desarrollo. Decisiones al aprobar: EPS solo del Usuario HC (Nómina sigue fuera); número de ficha con guion (`A–Z`, `0–9`, «-», 1 a 30) y búsqueda de ficha con su guion; Excel de los catálogos fuera del repositorio, cargado por Edwar con un comando; carga registrada en el registro del servidor y en el `CHANGELOG.md`, no en Auditoría (excepción nombrada a RN-04); diagnósticos y nacionalidad recortados del 15/12; al cambiar una respuesta, la vigente se reemplaza y la anterior queda completa en el historial (registro de verdad); sin carga inicial de fichas. CA-03, CA-29, CA-33 y CA-34 cambian; CA-36 (carga de catálogos) y CA-37 (motivo según PA-04) nuevos; criterios de catálogos marcados «después del 15/12». RN-53, RN-55, RN-04 y las filas de constitución ajustadas |
| 0.3 | 2026-11-24 | Revisión con el plan y las tareas: pasan a la spec las preguntas de comportamiento del plan (PA-19 a PA-26: documento, ficha, nombre parecido, varias palabras, «ñ», fecha de ingreso, edad y sexo del CIE-10, CIE-11) y se agregan PA-27 (dónde va el Excel), PA-28 (respuesta anterior) y PA-29 (cómo se enciende el módulo). Respuestas por defecto aceptadas por Edwar para avanzar: catálogos una sola vez (PA-06, PA-13), fotos solo al abrir la ficha (PA-17), respuesta anterior en el historial (PA-28), módulo encendido desde «Componentes y nodos» (PA-29), calendario al 15/12 y producción con DOC-27 (PA-14); PA-09 y PA-18 bloquean producción, no el desarrollo. Reglas nuevas: documento en `[0-9A-Z]` de 1 a 20; búsqueda normalizada; auditoría sin valores; toda acción comprueba la dependencia; foto sin ubicación también en el servidor y sin caché; tipo de documento retirado aceptado al corregir; selectores con el texto guardado. CA-03, CA-07, CA-17, CA-18 y CA-26 cambian; CA-28 a CA-35 nuevos. DOC-25 al día (ACC-07 a ACC-10 construidas); dependencia de DOC-27 al 30/11; corregida la nota que restringía la foto de la persona |
| 0.2 | 2026-11-24 | Alineada con DOC-25 construida (módulo Usuarios HC en Básica y Estabilización; coordinador líder = Administración; la excepción de búsqueda acotada en §6.1; aviso de «Fallecida» sujeto a DOC-27 y ACC-10); qué entrega DOC-26 antes de DOC-27 (§8, componente de registro) y criterios marcados «con DOC-27»; listas cortas en la configuración y catálogos CIE-10 y países de solo lectura (PA-05, PA-13 nueva); fotos con los formatos y tamaños que ya existen (PA-10 resuelta), sin sello (PA-15) y sin sobrescribir; auditoría de miniaturas (PA-17); producción junto con DOC-27 (PA-14); aviso también al coordinador líder (PA-16); autorización de tratamiento (PA-18); nota sobre búsqueda sin tildes; párrafo «Para aprobar» |
| 0.1 | 2026-11-20 | Borrador a partir del documento v1.6 y las decisiones de Edwar |
