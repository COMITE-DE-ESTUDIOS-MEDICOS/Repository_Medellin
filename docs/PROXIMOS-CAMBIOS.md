# Próximos cambios

> Cola de trabajo acordada el 14 de octubre de 2026. **Nada de esto está construido.**
> Cada punto trae la recomendación y lo que hay que decidir antes de empezar.

---

## 1. Copiar y mover varios a la vez, marcando con un check (Edición) ✅ hecho (14 de octubre)

> **Implementado:** checks en el explorador (carpetas y documentos, con «marcar todos»), barra
> fija con la carpeta de destino por su ruta completa, «Copiar aquí» y «Mover aquí» (mover
> pide confirmación). `app/Http/Controllers/LoteController.php`,
> `resources/js/lote-explorador.js`. Hasta 200 documentos por operación, todo o nada, una
> entrada de auditoría por pieza.

**Qué se pide:** revisar el contenido de una carpeta, marcar lo que se quiere y mandarlo a
otra carpeta, sin descargar y volver a subir.

**Recomendación:**
- Un **check** en cada documento y carpeta del explorador, y un check «todos» arriba.
- Al marcar algo aparece una **barra de acciones**: «Copiar a…», «Mover a…» y
  «Descargar (ZIP)», con un selector de carpeta de destino.
- **Copiar** reutiliza lo que ya existe (`copiarDocumento` y la copia de carpetas): el
  archivo se duplica en el servidor, no pasa por el navegador.
- **Mover** solo cambia la carpeta (ningún archivo se mueve en disco).
- Todo en una sola operación: si uno falla, no queda la mitad copiada. En la auditoría
  queda una entrada por cada documento.
- Para Edición en adelante.

**Decidido:** copiar **y mover** son para Edición en adelante.

## 2. Edición inactiva documentos y carpetas · jornada semanal de 42 horas

### 2a. Inactivar desde Edición ✅ hecho (19 de octubre)

Edición inactiva documentos (con motivo) y carpetas que puede ver; la custodia aparta lo de
otros nodos. Reactivar: documentos, Administración (la única que ve lo inactivo); carpetas,
Coordinación.

Hoy Edición solo inactiva las carpetas que creó (y sin contenido ajeno), y los documentos
solo los inactiva Administración.

**Recomendación:** Edición inactiva **documentos y carpetas**, siempre con **motivo**
obligatorio; **reactivar y ver lo inactivo** sigue en Coordinación en adelante. Así un
error de Edición tiene quien lo deshaga, y nada se borra: queda para auditoría.

**Por decidir:** ¿cualquier documento o carpeta, o solo lo de su componente o lo que creó?

### 2b. Tope semanal de 42 horas (Ley 2101 de 2021)

La ley baja la jornada máxima por etapas, y desde el 15 de julio de 2026 es de
**42 horas semanales**.

**Recomendación:**
- Regla nueva del componente: **«Horas semanales máximas»**, con 42 por defecto.
- Se suma el **trabajo real** (ya con el descanso descontado) de lunes a domingo.
- **En el módulo:** en la ficha de la persona y en «En turno ahora», «Esta semana: 38 h de 42»
  con una barra que se pone en rojo al pasarse.
- **Novedad nueva «Excede la jornada semanal»** cuando el turno que cierra la semana
  pasa de 42 horas, que se valida como las demás.
- **En el reporte:** una hoja o un bloque de **resumen semanal** por persona (horas de
  la semana, tope, exceso), además del detalle por turno.

Ojo con cómo encaja con la jornada diaria: 5 días de 8 h son 40 h (dentro del tope), pero
6 días de 8 h son 48 h (6 por encima). La jornada diaria sigue sirviendo para las horas
extra de cada turno, y el tope semanal es el control legal.

**Decidido:** pasarse de las 42 horas **solo se señala**: nunca bloquea ni inactiva a la
persona. Bloquear obligaría a llamar a desarrollo cada vez que alguien se pase; señalado,
lo resuelve Edición o Coordinación como cualquier novedad.

**Por decidir:** ¿la semana va de lunes a domingo?

## 3. Cargar muchos archivos (200–300) en colas de 50 ✅ hecho (14 de octubre)

> **Implementado:** `resources/js/carga-tandas.js` (navegador) y `app/Services/CargaPorTandas.php`
> (servidor). Tope y tamaño de tanda en `config/repositorio.php` → `carga`
> (`REPOSITORIO_CARGA_MAXIMO_TOTAL`, `REPOSITORIO_CARGA_MB_POR_TANDA`). En producción,
> `mb_por_tanda` (150 MB) tiene que quedar por debajo de `post_max_size` de PHP y del
> `client_max_body_size` de nginx.

**Qué se pide:** subir cientos de archivos de una vez sin que falle, dividido en tandas
pequeñas, viendo «Paso 1 de 6», y con un mensaje claro si se pasa de la capacidad.

**Recomendación: dividir en el navegador, no subir el límite del servidor.**

Mandar 300 archivos en una sola petición es la forma más fácil de que falle todo: con
25 MB por archivo son hasta 7,5 GB, y un corte de red, un tiempo de espera o el límite de
PHP tumbarían la carga entera. Así sería con tandas:

1. La persona elige hasta **300 archivos** (el tope total, configurable).
2. El navegador los **divide en tandas de 50** (o menos, si pesan mucho: por ejemplo un
   máximo de unos 200 MB por tanda) y las envía **una detrás de otra**.
3. En pantalla, en un **toast que no se cierra solo** mientras dura la carga: **«Paso 2
   de 6 · 50 archivos · 38 MB»**, con barra de progreso. Al terminar, el toast se convierte
   en la confirmación (con el sonido de siempre) o en el aviso de lo que falló.
4. Si una tanda falla, **se reintenta solo esa**; lo que ya entró no se repite. Para que
   un reintento no duplique, el servidor salta los archivos que ya están en esa carpeta
   con el mismo contenido (mismo hash).
5. Al final, un resumen: «300 cargados · 0 con error», con la lista de los que fallaron y
   un botón para reintentarlos.
6. Mientras sube, si la persona intenta cerrar la pestaña, el navegador le advierte que
   se va a cortar la carga.

**Si se pasa del tope** (por ejemplo, elige 350): *«Has excedido la capacidad de carga:
máximo 300 archivos por vez. Se enviarán los primeros 300; vuelve a cargar los 50
restantes.»* (O no deja empezar, según se decida.)

**Por qué así:**
- **El servidor no cambia sus límites** (50 por petición, 25 MB por archivo), y esos
  límites ya están probados.
- **Cada tanda es independiente:** un error afecta a 50 archivos, no a 300.
- **Sirve igual** para la carga del personal y para los enlaces de evidencias con cédula.

**Por decidir:** ¿el tope total es 300? Si se pasa, ¿se cargan los primeros 300 y se avisa,
o no deja empezar hasta quitar los que sobran?

---

## 4. Carpetas bajo custodia: que solo su responsable las vea y las mueva ✅ hecho (14 de octubre)

> **Implementado, con las decisiones tomadas:** custodia por **grupo** (Administración →
> «Grupos de custodia»), asignada en «Editar carpeta» solo por Coordinación en adelante y
> heredada por las subcarpetas. Los miembros con **Lectura revisan** sin modificar. **Abrir**
> un documento custodiado queda en la auditoría (`documento.abierto`). Quien no es del
> grupo no ve la carpeta ni puede editarla, moverla, inactivarla ni meter nada en ella por
> ningún camino (regla única `App\Rules\CarpetaAccesible`).

**Qué se pide:** que la persona responsable de una carpeta la pueda «cerrar» para que solo
ella vea, mueva y modifique su contenido, y que entre editores no se muevan archivos
ajenos, pero siempre bajo la dirección de Coordinación y Administración.

**Recomendación: custodia por permisos, no por clave.** Una clave dinámica (un código,
un PIN) suena segura, pero en el día a día falla: se comparte por WhatsApp para «salir
del paso», se olvida, y entonces alguien llama a desarrollo para abrir la carpeta. Un
permiso no se puede compartir, no se olvida, y queda escrito quién lo dio.

Cómo funcionaría:

1. **Coordinación o Administración** marcan una carpeta como **«bajo custodia»** y eligen
   a su **custodio** (una o varias personas, por ejemplo el líder del Nodo 3).
2. **Dentro de esa carpeta**, solo el custodio (y Coordinación y Administración) puede
   **ver, subir, mover, copiar, renombrar e inactivar**. Los demás editores no la ven, ni
   en el explorador, ni en la búsqueda, ni por enlace directo.
3. **El custodio no puede quitarse la custodia ni pasársela a otro:** eso lo decide
   Coordinación. Así nadie se queda con una carpeta «secuestrada».
4. **En la pantalla:** un candado en la carpeta, «Bajo custodia de: Laura Gómez», y para
   Coordinación un botón «Cambiar custodio».
5. **Se hereda:** las subcarpetas de una carpeta bajo custodia también lo están.

**¿Se ve en los registros (logs)?** Sí, y es la ventaja frente a la clave:
- **Hoy la auditoría ya registra** subir, descargar, mover, copiar, inactivar y reactivar,
  con quién y cuándo.
- **Se sumaría:** poner y quitar custodia, cambiar el custodio, y **abrir** un documento de
  una carpeta bajo custodia (hoy abrir sin descargar no queda registrado; para lo
  custodiado sí conviene).
- **Coordinación lo vería en Auditoría** filtrando por la carpeta.

**Por decidir:** ¿el custodio es una persona o puede ser un grupo (todos los líderes de un
nodo)? ¿Lectura puede ver lo custodiado, o tampoco?

> Esto reemplaza la idea anterior de «carpetas restringidas por usuario»: la custodia es esa
> misma restricción, pero con un responsable y con Coordinación al mando.

## 5. Recargos: nocturno, dominical y festivo · semana de 42 horas

**Respuesta de Talento Humano y Nómina (octubre de 2026):**

- Descanso semanal: rota según el turno (no siempre domingo).
- Más de 42 h: no se liquida, se revisa. En el componente no se generan horas extra.
- Nocturno 7 p. m. a 6 a. m., 35 %. Dominical/festivo diurno (6 a. m. a 7 p. m.) 90 %;
  nocturno 125 % (hasta medianoche, o hasta las 6 a. m. si el día siguiente es festivo).
- Valor hora en nómina = salario ÷ 210. **Salarios y valores los calcula Nómina**, no Documenta:
  el reporte entrega horas por tipo (RN, RFN, RFD) y el porcentaje que aplica.
- Descanso: se aceptan los tramos, pero un turno que empieza entre las 8 y las 11 a. m.
  debe descontar 1 h de almuerzo aunque dure hasta 6 h.
- **Pendiente (reunión del jueves):** turnos preestablecidos por componente. Calle: 7 h de
  labor + 30 min a 1 h de alimentación (8 h en el sitio); domingos 8 h. Con los turnos
  cargados en cada componente bajan las novedades falsas.
- Formatos de referencia: planilla mensual por persona y día con códigos (N, C, I, F…) y
  liquidación de recargos RN / RFN / RFD por persona (archivos de Nómina, no se versionan:
  traen datos personales).

**Qué se pide:** que el reporte (y el módulo) distinga lo que la ley paga distinto: horas
nocturnas, domingos y festivos, horas extra, y sus combinaciones; y que la semana
respete las **42 horas con un día de descanso**.

**Recomendación:** que el reporte **reparta cada turno por tramos de hora**, en vez de dar
un solo total. Por ejemplo, un turno del sábado de 18:00 a 03:00 se parte en la hora
diurna del sábado, las horas nocturnas del sábado y las horas nocturnas del domingo, y
cada tramo cae en su columna.

Columnas del reporte (horas de cada clase, para que nómina aplique sus porcentajes):

| Clase | Qué es |
|---|---|
| Ordinaria diurna | Dentro de la jornada, de día, en día hábil |
| Recargo nocturno | Dentro de la jornada, de noche |
| Dominical/festivo diurno | Dentro de la jornada, domingo o festivo, de día |
| Dominical/festivo nocturno | Dentro de la jornada, domingo o festivo, de noche |
| Extra diurna | Pasado la jornada (diaria o semanal), de día |
| Extra nocturna | Pasado la jornada, de noche |
| Extra dominical/festiva diurna | Pasado la jornada, domingo o festivo, de día |
| Extra dominical/festiva nocturna | Pasado la jornada, domingo o festivo, de noche |

**Los porcentajes y los horarios van como parámetros con fecha de vigencia,** no fijos en
el código, porque la ley los está cambiando por etapas. Lo que tengo entendido hoy
(**a validar con Talento Humano y nómina antes de construirlo**):

| Parámetro | Valor que tengo entendido | Norma |
|---|---|---|
| Jornada máxima semanal | 42 h desde el 15 de julio de 2026 | Ley 2101 de 2021 |
| Trabajo nocturno | De 7:00 p. m. a 6:00 a. m. | Ley 2466 de 2025 |
| Recargo nocturno | 35 % | Código Sustantivo del Trabajo |
| Recargo dominical y festivo | Sube por etapas: 80 % (jul. 2025), 90 % (jul. 2026), 100 % (jul. 2027) | Ley 2466 de 2025 |
| Hora extra diurna / nocturna | 25 % / 75 % | Código Sustantivo del Trabajo |

Con parámetros por vigencia, cuando la ley suba el dominical al 100 % en julio de 2027 se
cambia una cifra y una fecha, sin llamar a desarrollo; y un reporte de junio sigue
calculado con la cifra de junio.

**La semana:** 42 horas en 6 días, con 1 de descanso, son 7 horas por día (o 5 días de 8 h
y 24 min). La **jornada diaria del componente** se ajusta a eso, y el **tope semanal** se
señala como novedad sin bloquear a nadie (ya decidido en el punto 2b).

**Festivos:** el sistema ya tiene la lista de festivos en `config/repositorio.php` (la usa
el horario hábil). Sirve la misma, cargando los de cada año.

### Preguntas concretas para Talento Humano

**Jornada y semana**
1. ¿La jornada máxima es 42 h semanales? ¿Se reparte en 6 días de 7 h o en 5 días de 8 h
   24 min? ¿Es igual para todos los cargos de Calle?
2. ¿El día de descanso es siempre el domingo, o se rota según el turno de cada persona?
3. Si alguien pasa de 42 h en la semana, ¿esas horas se pagan como extra o solo se
   reportan para revisión?

**Recargos (porcentaje y desde qué fecha rige cada uno)**
4. Nocturno: ¿de qué hora a qué hora (¿7 p. m. a 6 a. m.?) y qué porcentaje (¿35 %?)?
5. Dominical y festivo: ¿qué porcentaje aplica hoy (¿90 % desde julio de 2026?) y cuándo
   cambia?
6. Horas extra: ¿diurna 25 % y nocturna 75 %?
7. Extras en domingo o festivo: ¿cómo se combinan? (por ejemplo, ¿extra diurna dominical
   = 25 % + recargo dominical?)

**Descanso no remunerado**
8. ¿Confirman los tramos: hasta 4 h sin descuento; más de 4 h y hasta 6 h, 30 min; más de
   6 h, 1 h? ¿El turno largo empieza a las 5 h o a las 6 h?

**Reporte**
9. ¿Nómina necesita solo las horas de cada clase (y aplica los valores ella), o también
   los porcentajes aplicados?
10. ¿Hay un formato de Excel que deba seguir? Si es así, un ejemplo.

**Por decidir (con Talento Humano):** confirmar los valores de la tabla; si los domingos
son siempre el día de descanso o el descanso se rota; y si el reporte trae los
porcentajes aplicados o solo las horas de cada clase (recomiendo solo las horas: los
valores en pesos los calcula nómina).

---

**Orden sugerido:** 3 (es lo que más reduce el error en el día a día) → 5 junto con 2b (son el
mismo trabajo sobre las horas, y es un tema legal) → 1 → 4 (custodia, que incluye lo de
2a: quién puede inactivar qué).
