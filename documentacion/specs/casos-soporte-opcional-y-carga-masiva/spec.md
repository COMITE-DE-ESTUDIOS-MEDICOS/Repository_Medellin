# Spec: Casos de derivación · soporte opcional y carga única de casos ya atendidos

| | |
|---|---|
| Historia | DOC-24 |
| Tipo | Historia de usuario · cambia RN-29 y abre una excepción única a RN-30 y RN-32 (DOC-20) |
| Estado | **Aprobada y construida · falta la prueba con un Excel real (PA-03)** |
| Versión | 0.6 |
| Fuentes | Pedido de Edwar, 2026-11-17, y sus respuestas del mismo día |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-17 |

## Problema

1. Crear un caso exige un **soporte** (foto, imagen o PDF). Los usuarios dicen que no siempre
   lo hay: el caso llega por una llamada o un mensaje de voz.
2. Hay casos **represados**: ya se atendieron en la calle y tienen sus soportes, pero no se
   subieron a Documenta. «Nuevo caso» pone la hora del momento, y esos casos tienen su hora
   real de inicio y de cierre. Pasa **una sola vez**: el módulo de casos ya está en producción
   y desde ahora todo se crea ahí.

## Comportamiento

### 1. El soporte deja de ser obligatorio (permanente)

- En «Nuevo caso» el soporte es **opcional**. Lo demás sigue igual: mensaje, quién lo envía,
  comuna y ubicación son obligatorios.
- Un caso sin soporte dice **«Sin archivo»** en la ficha, no muestra el bloque de soporte a la
  persona de campo y deja vacía esa celda en el Excel.
- Las **3 evidencias** de la atención siguen siendo obligatorias.

### 2. Carga única de casos ya atendidos

Los coordinadores llenan un **formato en Excel**, una fila por caso:

| Columna | Obligatoria | Valores |
|---|---|---|
| Componente | Sí | Nombre de un componente activo de la dependencia |
| Mensaje para la atención | Sí | Hasta 5000 caracteres |
| Quién lo envía | Sí | Alcaldía · Autoridades locales del sector · Otra |
| ¿Cuál? | Solo con «Otra» | Texto |
| Comuna | Sí | Número de la lista (1–16, 50–90) |
| Ubicación | Sí | Hasta 500 caracteres |
| Fecha y hora de inicio | Sí | Cuándo llegó el requerimiento, así: 13/11/2026 14:30. **Libre: cualquier fecha pasada**; no futura |
| Fecha y hora de cierre | Sí | Cuándo se atendió. Posterior al inicio y no futura |
| Nodo que atendió | Sí | Nombre de un nodo del componente |
| Cédula de quien atendió | **Sí** | Persona de campo registrada en la dependencia, activa o no |
| Atención o gestión realizada | Sí | De 10 a 2000 caracteres |
| ¿Se cumplió? | Sí | Sí · No |
| Observación | Solo con «No» | Texto |
| Justificación | Solo si pasó de 3 horas | Texto |
| Soporte del caso | No | **Imagen dentro de la celda** (JPG, PNG o WEBP) |
| Evidencia 1, 2 y 3 | **Sí, las tres** | **Imagen dentro de la celda** (JPG, PNG o WEBP) |

Componente, Quién lo envía, Comuna, Nodo y ¿Se cumplió? son **listas desplegables** en la
celda. Una segunda hoja trae los valores de esas listas y los nodos de cada componente, y una
tercera las instrucciones. La carga **no mira turnos**: basta que la cédula sea de una persona
de campo registrada. Máximo 50 casos por archivo (las fotos pesan); si son más, se
suben varios archivos.

**Al cargar:**

- **El código lo pone el sistema**: cada fila recibe el siguiente consecutivo (DC-NNN) en el
  orden del archivo. El formato no tiene columna de código.
- **Las horas son las del archivo.** El caso nace **atendido**, con su hora de inicio y su hora
  de cierre; el tiempo es la diferencia. No corre temporizador ni sale ningún aviso.
- **Cuenta para la meta de 3 horas** con sus horas reales, como cualquier caso.
- **Las imágenes** de las celdas quedan como el soporte y las evidencias del caso, en la carpeta
  «Derivaciones · <componente>», igual que las de un caso normal.
- **Todo o nada:** si una fila tiene un error (o le falta una evidencia), no se crea ningún caso
  y se dice cuál fila y por qué.
- **Repetidos:** dos filas iguales, o una fila igual a un caso que ya existe (misma ubicación,
  mismo remitente y misma hora de inicio), rechazan el archivo y se dice cuáles.
- Cada caso queda marcado **«Carga masiva»** en la ficha y en el Excel, con quién lo cargó y
  cuándo. Todo se audita, con las horas que traía el archivo.

**Cómo se carga (decisión de Edwar, PA-01):** autogestión. En **Derivaciones**, quien tiene
rol de **Coordinación o Administración** ve dos botones: **Formato de casos atendidos**
(descarga el Excel) y **Cargar casos atendidos** (lo sube). Edición y Lectura no los ven.

- Si hay errores no se guarda nada y la pantalla lista cada fila con su causa: subir el archivo
  es también la forma de revisarlo.
- **Se cierra sola:** los botones desaparecen después del **30/11/2026**
  (`config/repositorio.php` → `casos_carga_masiva.abierta_hasta`). Así la excepción a RN-30 no
  queda abierta. Pasada la carga, el código se retira.

## Excepción a las reglas

- **RN-30** dice que nadie escribe la hora de inicio, y **RN-32** que las horas solo las corrige
  el superadministrador. Esta carga es una **excepción única** (noviembre de 2026), autorizada
  por Edwar: las horas vienen del archivo que preparan los coordinadores.
- Después de creados, corregir las horas de esos casos sigue siendo solo del
  superadministrador, con motivo.

## Preguntas abiertas

| PA | Pregunta | Estado |
|---|---|---|
| PA-01 | ¿Comando en el servidor o pantalla para los coordinadores? | **Resuelta (Edwar, 2026-11-17): pantalla, autogestión** |
| PA-02 | «Máximo hasta el jueves pasado»: ¿la fecha de inicio más antigua permitida es el **jueves 12/11/2026**? | **Resuelta (Edwar, 2026-11-17): no hay fecha más antigua.** La fecha y hora de inicio es libre, para que el caso quede con su fecha real; solo no puede ser futura |
| PA-03 | Un archivo real de muestra, con 2 o 3 casos y sus imágenes puestas como las pondrán los coordinadores | **Pendiente.** Se leen las imágenes «en la celda» y las flotantes «sobre la celda», probadas con archivos armados en las pruebas; falta uno hecho en el Excel de los coordinadores. Las imágenes de WPS Office (`=DISPIMG`) no se leen |
| PA-04 | ¿Hasta cuándo queda abierta la carga? | Se puso el **30/11/2026**; es un valor de configuración. Falta que Edwar lo confirme |

## Criterios de aceptación

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Se crea un caso sin soporte; con soporte sigue funcionando igual | `CasosDerivacionCrearTest::test_doc24_ca01_ca02_…` |
| CA-02 | Un caso sin soporte dice «Sin archivo» en la ficha | la misma |
| CA-03 | Atender un caso normal sigue exigiendo las 3 evidencias | `CasosDerivacionAtenderTest` (ya existía) |
| CA-04 | El formato se descarga con las columnas y la hoja de valores válidos. Solo Coordinación en adelante ve y usa la carga, y solo hasta la fecha de cierre | `CargaMasivaCasosTest::test_doc24_ca04_…` |
| CA-05 | Un archivo válido crea un caso atendido por fila: códigos consecutivos en el orden del archivo, horas del archivo, tiempo calculado, soporte y evidencias tomados de las celdas (en la celda o flotantes) | `test_doc24_ca05_…` (dos pruebas) |
| CA-06 | Una fila con error (componente, nodo o cédula que no existe, comuna fuera de la lista, cierre anterior al inicio, fecha futura o que no se entiende, «No» sin observación, más de 3 h sin justificación, campo vacío, evidencia que falta) rechaza todo y dice la fila y la causa | `test_doc24_ca06_…` |
| CA-07 | Filas repetidas, entre sí o con un caso existente, rechazan el archivo | `test_doc24_ca07_…` |
| CA-08 | Retirado en v0.4: no hay comando ni `--simular`; un archivo con errores no guarda nada (CA-06) | — |
| CA-09 | Los casos cargados no generan avisos ni recordatorios, y cuentan en el indicador de 3 horas | `test_doc24_ca05_ca09_ca10_…` |
| CA-10 | Cada caso queda marcado «Carga masiva» en la ficha y el Excel (columna ORIGEN), y auditado con sus horas | la misma |

## Reglas que cambian

- **RN-29:** el soporte pasa a opcional (hecho).
- **RN-30 y RN-32:** excepción única para esta carga.
- **RN-34 y RN-36:** la ficha y el Excel muestran el origen «Carga masiva».

## Decisiones (Edwar, 2026-11-17)

- El soporte al crear el caso **no es obligatorio**.
- Los casos represados **ya están atendidos**: se cargan con hora de inicio y de cierre.
- Es **una sola vez**.
- La **cédula de quien atendió es obligatoria**.
- **Cuentan para el cumplimiento de 3 horas.**
- Las **imágenes van en las celdas** del Excel: el soporte y las 3 evidencias, que son
  obligatorias en los casos cerrados.
- Filas repetidas: se rechaza el archivo y se dice cuáles.
- La carga es **autogestión**: los coordinadores suben el Excel desde una pantalla.

## Candidatos para la siguiente versión

- Agregar o cambiar el soporte de un caso ya creado, desde «Editar».
- Leer las imágenes de WPS Office (`=DISPIMG`), si el archivo de muestra lo pide.
- Retirar el código de la carga (controlador, dos rutas, botones, `cargarMasivo`) cuando termine.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.6 | 2026-11-17 | La fecha de inicio es libre: se quita el tope del 12/11/2026 (PA-02) |
| 0.5 | 2026-11-17 | Listas desplegables en el formato; se aclara que la carga no mira turnos |
| 0.4 | 2026-11-17 | §2 construida como pantalla de autogestión para Coordinación (PA-01); se cierra sola el 30/11/2026; sin comando ni `--simular` |
| 0.3 | 2026-11-17 | §1 aprobada y construida. §2: una sola vez, cédula obligatoria, cuenta para la meta, imágenes en las celdas |
| 0.2 | 2026-11-17 | Los casos ya están atendidos: horas de inicio y cierre en el archivo; repetidos rechazan |
| 0.1 | 2026-11-17 | Borrador con el pedido de Edwar |
