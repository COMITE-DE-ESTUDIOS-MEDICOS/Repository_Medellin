# Investigación: fuente oficial del CIE-10 y de la lista de países

> Insumo para responder **PA-06** (y PA-13) de `spec.md`. Consultado el **24 de noviembre de 2026**.
> Solo texto: ningún archivo de datos se copió al repositorio. Lo que no se pudo comprobar en una
> fuente dice «no confirmado».

## 1. Resumen y recomendación

1. **CIE-10:** usar la tabla de referencia **«CIE10»** del Ministerio de Salud en **SISPRO** (web.sispro.gov.co): es pública, sin cuenta, es la que exige el RIPS y trae **12.634 códigos** de 4 caracteres, sin punto, en español y en MAYÚSCULAS sin tildes.
2. El capítulo V (psiquiatría, F00–F99) viene **completo**: 407 códigos en 78 categorías. No hay una tabla aparte de diagnósticos de salud mental.
3. **Ojo, CIE-11:** Colombia ya la adoptó (Resolución 1442 de 2024) y la transición **termina hacia el 14 de agosto de 2027**. El RIPS ya pide CIE-11 junto al CIE-10 desde junio de 2026. Recomendación: cargar ahora el CIE-10 de SISPRO, pero **no como «una sola vez»**: debe poder recargarse y dejar espacio para un código CIE-11 al lado (doble codificación).
4. **Países:** usar la tabla de referencia **«Pais»** de SISPRO, la misma que pide el RIPS: 249 países con código ISO 3166-1 numérico (170 = Colombia), alfa-2 y alfa-3, y nombres en español.
5. La tabla de países tiene nombres viejos o con errores (BRAZIL, SUAZILANDIA, GUADALUOE…). Se cargan los **códigos** tal cual y, si hace falta, se corrige solo el nombre que se muestra, dejando constancia.

---

## 2. Respuestas por pregunta

### 2.1 Fuente oficial del CIE-10 en Colombia

| Aspecto | Hallazgo |
|---|---|
| Norma que lo adopta | **Resolución 1895 de 2001** del Ministerio de Salud adopta la CIE-10 para codificar la morbilidad; sigue vigente durante la transición a la CIE-11 (Res. 1657 de 2025, art. 1, parágrafo). |
| Quién la publica | **Ministerio de Salud y Protección Social**, en el portal **SISPRO**, como «tabla de referencia» llamada **`CIE10`**. En el listado aparece como *Habilitada: Sí · Aplicación: SISPRO-TRV · Estándar Minsalud: Sí*. |
| Dirección exacta | Listado de tablas: <https://web.sispro.gov.co/WebPublico/Consultas/ConsultarReferenciaBasica.aspx> · Tabla CIE-10: <https://web.sispro.gov.co/WebPublico/Consultas/ConsultarDetalleReferenciaBasica.aspx?Code=CIE10> |
| Es la que exige el RIPS | Sí. El *Documento Técnico 1* del RIPS (versión 002, 26 de junio de 2026) dice en el diagnóstico principal: «Informar dato según tabla de referencia "CIE10" en web.sispro.gov.co o la tabla de referencia o herramienta que lo sustituya según la vigencia de la CIE». <https://www.minsalud.gov.co/sites/rid/Lists/BibliotecaDigital/RIDE/DE/OT/campos-datos-reglas-validacion-soporte-fev-salud.pdf> |
| Pública y sin cuenta | **Sí para consultar.** La página se abre sin iniciar sesión y deja buscar y paginar (hasta 2.000 filas por página). El botón «Exportar a Excel» pide un correo electrónico («Email para envío de datos exportados»): el archivo **se envía por correo**. Al probar sin correo, el servidor respondió con error. No se probó con un correo real. |
| Licencia o condiciones de uso | **No confirmado.** La página de SISPRO no muestra una licencia ni condiciones de uso. Es información pública del Estado, publicada para que todo el sector la use en el RIPS. El contenido de fondo de la CIE-10 es de la OMS. La página actual de la OMS sobre derechos de autor solo trata la licencia de la CIE-11, no la de la CIE-10. Un archivo de 2017 de la OMS decía que pedía una solicitud de licencia para productos de software. Para un uso interno, sin venta y con la tabla oficial colombiana, el riesgo es bajo, pero no está confirmado por escrito. <https://www.who.int/about/policies/publishing/copyright> |

Hay otra tabla relacionada, **`CIE10Clasificacion2036`** (actualizada el 11 de diciembre de 2025). Trae **2.062 categorías de 3 caracteres** (A00, A01…), con su nombre. Sirve para agrupar o hacer informes, no para registrar el diagnóstico.

### 2.2 Formato

Se revisó la tabla completa en la página pública (las 12.634 filas, en 7 páginas de 2.000).

- **Tipo:** página web con tabla (HTML, UTF-8) y exportación a Excel enviada por correo. **No hay un CSV público ni un servicio web abierto** para esta tabla (no confirmado que exista otro canal).
- **Columnas:** las de todas las tablas de referencia de SISPRO. En la tabla CIE10 se llenan así:

| Columna en SISPRO | Qué trae (ejemplo F200) |
|---|---|
| `Codigo` | Código de 4 caracteres sin punto: `F200` |
| `Nombre` | Nombre completo: `ESQUIZOFRENIA PARANOIDE` (máximo observado: 241 caracteres) |
| `Descripcion` | Nombre de la categoría de 3 caracteres: `ESQUIZOFRENIA` (máximo 187 caracteres) |
| `Habilitado` | `SI` / `NO` |
| `Extra_I: AplicaASexo` | `1` = hombres, `2` = mujeres, `3` = ambos (deducido de los datos: F524 «eyaculación precoz» = 1; F530 «puerperio» = 2) |
| `Extra_II: EdadMinima` / `Extra_III: EdadMaxima` | Rango de edad en el que el código es válido (p. ej. 0–999, 10–120, 1–17). La unidad parece años; no confirmado |
| `Extra_IV: GrupoMortalidad` | Número de grupo (1, 5, 10…) |
| `Extra_V` | Nombre del capítulo: `TRASTORNOS MENTALES Y DEL COMPORTAMIENTO (F00-F99)` |
| `Extra_VI` | Categoría de 3 caracteres: `F20` (la etiqueta dice «Capitulo», pero trae la categoría) |
| `Extra_VIII` | Número interno de grupo (en el capítulo V vale 5) |
| `Extra_X: Sexo` | `A` (ambos), `M`, `F`: repite en letra lo de `AplicaASexo` |
| `Fecha_Actualizacion` | Fecha de la última carga |

  Las etiquetas `Extra_…` no coinciden del todo con lo que traen: al cargarla hay que asignar las columnas por su contenido, no por el nombre del encabezado.
- **Cantidad:** **12.634 códigos**, sin duplicados, de 22 capítulos (A00 a Z99, más U00–U99).
- **3 o 4 caracteres:** **todos tienen 4 caracteres**. Las categorías que no se subdividen se completan con **X**: `F03X`, `F99X`, `F21X`… (266 códigos así). No hay códigos de 3 caracteres sueltos; esos están en la tabla aparte `CIE10Clasificacion2036`.
- **Vigentes o deshabilitados:** existe la columna `Habilitado`. Hoy **las 12.634 filas dicen `SI`**: no se vio ningún código deshabilitado.
- **Codificación de caracteres:** la página es UTF-8. Los textos van en **MAYÚSCULAS y casi sin tildes** («COLERA», «NINEZ» en vez de «NIÑEZ»). Solo 3 filas tienen «Ñ» (O960, O961, O969: «AÑO»), y una tiene un carácter dañado: `U85X RESISTENCIA A DROGAS ANTINEOPL?SICAS`. La codificación del Excel exportado no se pudo ver.

### 2.3 Versión y actualizaciones

- **Versión:** es la adaptación colombiana de la CIE-10 de la OMS (Res. 1895 de 2001). SISPRO no publica un número de edición. La tabla incluye los códigos de emergencia que la OMS agregó en 2020–2021 (COVID-19: U071, U072, U089, U099, U109, U119, U129), así que está al día con las últimas actualizaciones de la CIE-10.
- **Última actualización:** **22 de octubre de 2026**, la misma fecha en todas las filas (se recarga completa).
- **Periodicidad:** **no confirmada.** Ninguna fuente oficial dice cada cuánto se actualiza. Por eso conviene poder **recargarla**, no cargarla «una sola vez».

### 2.4 CIE-11

**Sí, Colombia ya la adoptó, y la transición termina pronto. Esto cambia la recomendación.**

- **Resolución 1442 de 2024** (14 de agosto de 2024, Diario Oficial 52.848) adopta la CIE-11 para la morbilidad y la mortalidad y fija un periodo de transición. <https://sidn.ramajudicial.gov.co/SIDN/NORMATIVA/TEXTOS_COMPLETOS/8_RESOLUCIONES/RESOLUCIONES%202024/MSPS%20Resolucion%2000001442%20de%202024.pdf>
- **Resolución 1657 de 2025** (14 de agosto de 2025, Diario Oficial 53.217) amplía la transición a **36 meses «contados desde la publicación»**. Durante ese tiempo se sigue codificando con CIE-10, y quien termine antes debe usar **doble codificación (CIE-10 y CIE-11)**. La Res. 1895 de 2001 (CIE-10) queda derogada **al terminar los 36 meses**. <https://sidn.ramajudicial.gov.co/SIDN/NORMATIVA/TEXTOS_COMPLETOS/8_RESOLUCIONES/RESOLUCIONES%202025/MSPS%20RESOLUCION%2000001657%20DE%202025.PDF>
- **Fecha de cierre:** el micrositio oficial de SISPRO cuenta los 36 meses **desde el 14 de agosto de 2024**, así que la transición termina hacia el **14 de agosto de 2027**. Si se contaran desde la Res. 1657, sería agosto de 2028. El texto modifica la Res. 1442, por eso la lectura de SISPRO (2027) es la más segura.
- **Versión aprobada:** CIE-11 **2025-01, en español** (`2025-01_en-es`), consultada con la API de la OMS. El Ministerio publica instaladores, una herramienta de codificación adaptada (<https://transicioncie11.sispro.gov.co/>) y una **tabla de correspondencia CIE-10 ↔ CIE-11** (ZIP de 6,8 MB, publicado el 19 de febrero de 2025). <https://www.sispro.gov.co/central-prestadores-de-servicios/Pages/Transicion-de-la-CIE-10-a-la-CIE-11-en-las-IPS.aspx>
- **RIPS:** el Documento Técnico 1, versión 002 (26 de junio de 2026), ya trae campos nuevos `codDiagnosticoPrincipalCIE11`, `nomCodDiagnosticoPrincipalCIE11`, etc., junto a los de CIE-10. El código CIE-11 se informa «según codificación de referencia "CIE11"». **No hay tabla `CIE11` en SISPRO**: la búsqueda en el listado no la encontró, porque la fuente es la OMS o la herramienta del Ministerio.
- **Circular de fases:** el proyecto de la Res. 1657 anunciaba una circular con un plan por fases «a más tardar el 30 de septiembre de 2025». **No se encontró** esa circular (no confirmado que exista).
- **Licencia CIE-11:** CC BY-ND 3.0 IGO: se puede usar, citando la fuente, sin modificar los códigos. Las correspondencias con otras clasificaciones necesitan un acuerdo aparte con la OMS. <https://www.who.int/about/policies/publishing/copyright>

**Qué significa para Documenta:** registrar en CIE-10 sigue siendo lo correcto y lo obligatorio hasta mediados de 2027. Pero el diseño debe aceptar **más de una clasificación** y no dar por hecho que el catálogo nunca cambia. Por ejemplo, guardar la clasificación junto al código, poder recargar el catálogo y dejar previsto un código CIE-11 opcional. Si no, en menos de un año habrá que migrar los datos.

### 2.5 Psiquiatría (capítulo V, F00–F99)

- **Viene completo:** 407 códigos, todos habilitados, en **78 categorías**: F00–F07, F09, F10–F19, F20–F25, F28–F34, F38–F45, F48, F50–F55, F59–F66, F68–F73, F78–F84, F88–F95, F98 y F99. Son las categorías del capítulo V de la CIE-10. Los números que faltan (F08, F26, F35…) no existen en la CIE-10.
- Trae completos los trastornos por **sustancias** (F10–F19, cada uno con sus 10 subcódigos: intoxicación, uso nocivo, dependencia, abstinencia…), que son los más frecuentes en habitantes de calle.
- **Límites de edad que importan:** varios códigos tienen edad máxima. Por ejemplo, **F900 (TDAH) vale de 1 a 17 años**, F911 de 1 a 17 y **F840 (autismo) de 1 a 9 años**. Si Documenta valida la edad como el RIPS, a un adulto no se le podría registrar F900. Hay que decidir si la edad **bloquea** o solo **avisa**.
- Para medicina general sirve todo el catálogo. Entre los códigos útiles están **Z590** «Problemas relacionados con la falta de vivienda» y **Z595** «pobreza extrema».
- **No hay una tabla aparte de diagnósticos de salud mental.** En SISPRO existen `CPTISaludMental` (7 opciones de un tamizaje: «dificultades para dormir», «ninguno»…) y `SGDEnfMental` (9 códigos numéricos sin descripción). Ninguna de las dos sirve para diagnósticos.

### 2.6 Países

- **Lo que usa el sistema de salud:** el RIPS pide `codPaisResidencia` y `codPaisOrigen` como «Código a tres caracteres según estándar **ISO 3166-1**. Informar dato según tabla de referencia **"Pais"** en web.sispro.gov.co» (Documento Técnico 1, v. 002, campos U06 y U11). Ejemplo del propio documento: `"codPaisResidencia": "170"`.
- **Dirección:** <https://web.sispro.gov.co/WebPublico/Consultas/ConsultarDetalleReferenciaBasica.aspx?Code=Pais>. Es pública y sin cuenta, igual que la del CIE-10. En el listado figura como *Estándar GEL: Sí · Estándar Minsalud: Sí*.
- **Contenido:** **249 países**, todos habilitados, sin duplicados, con fecha de actualización del **3 de octubre de 2018**.
  - `Codigo` = **ISO 3166-1 numérico**, de 3 dígitos con ceros a la izquierda (`004` Afganistán, `170` Colombia, `862` Venezuela).
  - `Extra_II` = **alfa-2** (`CO`) y `Extra_III` = **alfa-3** (`COL`).
  - `Nombre` = nombre **en español, en mayúsculas y con tildes** (`PERÚ`, `ESPAÑA`, `MÉXICO`).
- **Otras listas de países en SISPRO** (no son las del RIPS): `DMESPais` (dispositivos médicos), `Pais1` «País origen población migrante» (actualizada el 11 de diciembre de 2025) y `PaisReferencia` (medicamentos). No se revisó su contenido.
- **DANE:** la **Resolución DANE 2386 de 2023** (21 de diciembre de 2023) adopta para la estadística oficial la nomenclatura **M49 de la ONU**, cuyos números coinciden con el ISO 3166-1 numérico de los países. No se encontró una lista descargable del DANE con nombres en español y códigos ISO. <https://normograma.mintic.gov.co/mintic/compilacion/docs/resolucion_dane_2386_2023.htm>
- **Otras fuentes en español:** la lista **M49 de la División de Estadística de la ONU** (<https://unstats.un.org/unsd/methodology/m49/overview/>) trae M49, ISO alfa-2 y alfa-3, y tiene una pestaña en español. No se confirmó su formato de descarga. Migración Colombia usa ISO 3166-1 alfa-3 para las nacionalidades (Res. 452 de 2013). La ISO publica los nombres oficiales solo en inglés y francés (no confirmado: el sitio iso.org no dejó consultarlo).

### 2.7 Riesgos para cargarlo en la base de datos

| Riesgo | Detalle | Cómo manejarlo |
|---|---|---|
| Código con o sin punto | SISPRO usa `F200` y `F03X`. Los médicos escriben «F20.0» o «F03» | Guardar como SISPRO (4 caracteres, sin punto, con X). En pantalla se puede mostrar «F20.0», y la búsqueda debe aceptar ambas formas |
| Relleno con X | `F99X` no existe en la CIE-10 de la OMS (allá es «F99») | Tratarlo como código oficial colombiano. No quitar la X, porque el RIPS la espera |
| Mayúsculas sin tildes | «ESQUIZOFRENIA HEBEFRENICA», «NINEZ» | Mostrar tal cual (es el texto oficial) o con un formato de presentación. No «corregir» el texto guardado |
| Errores del texto oficial | `F192 …DEPENDENDENCIA`, `O969 MUERTEPOR…`, `U85X …ANTINEOPL?SICAS` (carácter dañado) | Cargar tal cual y anotar la fuente. Si se corrige algo para mostrar, que quede auditado |
| Encabezados engañosos | Las columnas `Extra_…` no traen lo que dice su etiqueta | Mapear por contenido (ver 2.2) y probar la carga con casos conocidos (F200, F524, F900) |
| Sexo y edad | 885 códigos solo para un sexo y varios con edad máxima (F900: 1–17 años) | Decidir si la validación bloquea o solo avisa |
| Tamaño | 12.634 filas, nombre de hasta 241 caracteres | Cabe sin problema en MySQL y en SQLite (columna de 255 o `text`) |
| Descarga | No hay CSV ni API abiertos. El Excel llega por correo y la página es un formulario ASP.NET | Bajar el Excel una vez con un correo institucional y guardarlo **fuera del repositorio**, o cargar desde un archivo revisado. Registrar la fecha de la tabla (hoy, 22-oct-2026) |
| Sin licencia escrita | Ni SISPRO ni la OMS publican una licencia clara para la CIE-10 | Uso interno, sin venta y citando «Tabla de referencia CIE10, SISPRO, MinSalud, fecha». Si se quiere certeza, preguntar al Ministerio |
| Países: nombres viejos o con errores | `BRAZIL`, `MYANMAR (FORMER BURMA)`, `ISLAS SOLOMON`, `GUADALUOE`, `SUAZILANDIA` (hoy Esuatini), `MACEDONIA` (hoy Macedonia del Norte), `REPÚBLICA CHECA` (hoy Chequia), `HAITI` sin tilde, `San Martín (parte holandesa)` en minúsculas | El **código** es lo que vale. Si se corrige el nombre que se muestra, que conste |
| Países: ceros a la izquierda | `004`, `008`… | Guardar como texto de 3 caracteres, no como número |
| Países sin «apátrida» ni «desconocido» | La tabla solo trae países ISO | Si la spec necesita «sin dato» o «apátrida», es una opción aparte, no un país inventado |
| CIE-11 | Fin de la transición: agosto de 2027 | Ver 2.4 |

---

## 3. Lo que falta confirmar

1. La **licencia o condiciones de uso** de las tablas de SISPRO y de la CIE-10 para un sistema interno. Ninguna fuente lo dice; se le puede preguntar al Ministerio.
2. **Cada cuánto** se actualiza la tabla CIE10. Solo se sabe la fecha de la última carga: 22-oct-2026.
3. El **formato y la codificación del Excel exportado**. Pide correo y no se probó.
4. Si **existe** la circular del plan de implementación de la CIE-11 por fases, anunciada para el 30-sep-2025.
5. La fecha exacta de cierre de la transición. Según SISPRO, 36 meses desde el 14-ago-2024, es decir, **14-ago-2027**. El texto «desde la publicación» admite otra lectura (agosto de 2028).
6. La unidad de `EdadMinima`/`EdadMaxima` (parecen años) y el significado de `GrupoMortalidad`.
7. Si alguna fila de la CIE10 puede estar **deshabilitada** pero oculta en la consulta pública. Hoy todas dicen `SI`.
8. El formato de descarga y la licencia de la lista **M49 de la ONU en español**. Solo importa si no se usa la tabla «Pais» de SISPRO.

---

## 4. Línea para la spec (respuesta a PA-06)

> **Fuente:** tabla de referencia «CIE10» del Ministerio de Salud en SISPRO (web.sispro.gov.co, la misma del RIPS; Res. 1895 de 2001) · **Formato:** tabla web con exportación a Excel por correo; código de 4 caracteres sin punto (con X en las categorías sin subdivisión), nombre en mayúsculas sin tildes, sexo y edad aplicables, capítulo y «habilitado» · **Códigos:** 12.634, el capítulo V (F00–F99) completo con 407 · **Se actualiza:** sin periodicidad publicada; última carga 22-oct-2026; la CIE-11 la reemplaza al terminar la transición (≈ 14-ago-2027, Res. 1442 de 2024 y 1657 de 2025), así que el catálogo debe poder recargarse y convivir con CIE-11.
>
> **Países:** tabla de referencia «Pais» de SISPRO (la del RIPS): 249 países, código ISO 3166-1 numérico (170 = Colombia) más alfa-2 y alfa-3, nombres en español; última actualización 3-oct-2018.
