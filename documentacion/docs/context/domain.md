# Dominio de Documenta

> **Estado:** borrador para aprobar por Edwar. Documenta lo construido hasta el 26 de
> octubre de 2026. Las specs citan estas reglas por su código (RN-NN) en vez de repetirlas.
> Cada regla dice de dónde sale (fuentes F-NN y decisiones D-NN en `../../fuentes/`) y
> dónde se cumple en el código.

## Áreas

| Área | De qué se encarga | Es dueña de |
|---|---|---|
| Repositorio | Documentos, carpetas, versiones, custodia, comentarios | `carpetas`, `documentos`, `documento_versiones`, `tipos_documento`, `etiquetas`, `grupos_custodia`, `comentarios_documento` |
| Recepción externa | Lo que llega de fuera por enlaces de carga | `enlaces_carga`, `recepciones` |
| Turnos | Personas de campo, marcación, novedades, catálogo de la malla, ausencias | `componentes`, `nodos`, `colaboradores`, `enlaces_turno`, `marcaciones`, `novedades_turno`, `tipos_turno`, `puntos_criticos`, `ausencias` |
| Atención territorial | Derivaciones y su respuesta; supervisión territorial | `derivaciones`, `supervisiones` |
| Administración | Dependencias, usuarios (con su rol y su componente, DOC-25), auditoría, avisos | `dependencias`, `users`, `dependencia_usuario`, `auditorias`, `avisos`, `notifications` |

`enlaces_turno` lo comparten Turnos y Atención territorial: el mismo enlace por cédula
sirve para marcar o para reportar una derivación según su `tipo`.

## Estados

Los estados reemplazan al borrado (constitución IV).

### Documento y carpeta

| Estado | Qué significa | Puede pasar a | Quién lo cambia |
|---|---|---|---|
| Activo | Se ve según permisos y custodia | Inactivo | Edición en adelante, con motivo |
| Inactivo | Retirado de la vista, intacto en la base | Activo | Documento: Administración. Carpeta: Coordinación en adelante |

### Recepción (lo que llega por un enlace de carga)

| Estado | Qué significa | Puede pasar a |
|---|---|---|
| Pendiente de clasificar | Llegó y espera revisión | Archivado, Descartado |
| Archivado en el repositorio | Quedó como documento en su carpeta | — |
| Descartado | No se archivó | — |

El análisis de seguridad del archivo tiene su propio estado: sin escanear, sin amenazas,
amenaza detectada, no se pudo verificar.

### Persona de campo

| Estado | Qué significa | Quién lo cambia |
|---|---|---|
| Por verificar | Se registró sola por el enlace | Edición en adelante la verifica |
| Verificada | Confirmada (o registrada por un líder) | — |
| Activa / Desactivada | Desactivada, los enlaces no reconocen su cédula; su historia queda | Edición en adelante |

### Marcación

| Estado | Qué significa |
|---|---|
| Vigente | Cuenta para las horas |
| Anulada | Se corrigió: queda tachada, con quién y por qué, y la reemplaza otra marcación |

### Novedad de turno

| Estado | Qué significa | Quién lo cambia |
|---|---|---|
| Pendiente | Nació al detectarse | — |
| Aprobada | Validada | Coordinación, Administración y Edición con «Aprueba novedades» (RN-23) |
| Rechazada | No se acepta; lleva comentario obligatorio | Los mismos |
| Corregida | La hora que la originó se corrigió, o la persona marcó después («no registró entrada») | El sistema |

### Derivación

| Estado | Qué significa | Puede pasar a | Quién lo cambia |
|---|---|---|---|
| Pendiente | Llegó; su tiempo corre desde la hora del requerimiento | Solucionada | Edición en adelante, al responder |
| Pendiente · vencida | Pendiente y ya pasó la meta de 3 h (se calcula, no se guarda) | Solucionada | Igual |
| Solucionada | Respondida: el tiempo quedó detenido | — | — |
| Eliminada | Retirada del reporte (borrado lógico) | — | Coordinación en adelante |

## Reglas de negocio

### Generales

**RN-01. Cada dependencia, y dentro de ella cada componente, ve solo lo suyo.** Ningún
usuario ve datos de una dependencia a la que no pertenece, ni por enlace directo. El filtro
va en la consulta. Desde DOC-25, dentro de la dependencia cada usuario ve y trabaja **solo
los datos de su componente**: personas de campo, ausencias, enlaces por cédula, marcaciones,
novedades, tipos de turno, puntos críticos, nodos, derivaciones y supervisiones. Un dato de
otro componente da «No encontrado» o «No tienes permiso», también por dirección directa, y no
sale en listas, contadores ni reportes. Administración y el superadministrador ven el
componente que eligen en el menú (RN-50). Quien no tiene componente no ve ningún dato. Los
documentos, carpetas y enlaces de carga no tienen componente (RN-51). La vía pública (enlaces
por cédula) no cambia.
*Origen:* F-02, DOC-25 · *Código:* `DependenciaScope`, `ComponenteScope`, trait `DelComponente`, `ContextoDependencia`, `EstablecerDependencia`.

**RN-02. Rol por dependencia, dentro de un componente.** El rol (Lectura, Edición,
Coordinación, Administración) se asigna por dependencia. Nadie gestiona a un usuario con rol
mayor; la cuenta de plataforma solo la gestiona otro superadministrador. Desde DOC-25,
Lectura, Edición y Coordinación se ejercen **dentro de un componente**: Coordinación es la
coordinación de un componente y deja de ver toda la dependencia. Administración es el
coordinador líder (RN-50): sin componente, con acceso total y administra los usuarios. Que
solo el superadministrador dé el rol Administración y que Coordinación gestione solo los
usuarios de su componente se aplica con ACC-07 (pendiente).
*Origen:* F-02, DOC-25 · *Código:* `RolDependencia`, `UsuarioController::verificarRango`, `Livewire\Usuarios::alcanza`.

**RN-03. No hay registro público.** Las cuentas las crea Coordinación en adelante. Desde
DOC-25, dar acceso a una persona exige elegir **su componente** (uno solo por dependencia),
salvo con rol Administración, que no lleva componente (se aplica con ACC-07 y ACC-08,
pendientes; hoy el componente de los usuarios lo dejó la migración: todos en Calle). Desde
DOC-22 la cuenta de Authentik es **una por persona, compartida entre las apps**:

- su usuario es la cédula (`^[0-9A-Z]{3,20}$`);
- dar acceso la crea sin contraseña, o la agrega al grupo de Documenta si ya existía;
- desactivar a la persona la saca del grupo, sin desactivar su cuenta;
- Documenta nunca cambia el nombre, el correo, el usuario ni la contraseña de esa cuenta;
- la contraseña la define cada persona en Authentik, con un enlace de recuperación de un solo
  uso;
- se entra por Authentik: la primera vez por el usuario, después por el `sub`, nunca por el
  correo;
- el formulario propio queda solo para el superadministrador, como acceso de emergencia.

*Origen:* F-02, DOC-22, DOC-25 · *Código:* `UsuarioController`, `AuthentikProvisioner`, `Auth\AuthentikController`, `Auth\SesionController`.

**RN-04. Todo cambio deja auditoría.** Quién, qué, cuándo, IP y navegador. Inactivar
exige motivo.
*Origen:* F-01, F-02 · *Código:* `Auditor`, `AccionAuditoria`.
⚠️ Excepción encontrada: borrar un comentario no se audita (`ComentarioDocumentoController::destroy`). Ver C-01.

**RN-49. Módulos por componente.** Cada componente tiene su lista de módulos; un usuario
solo abre los módulos de su componente y, dentro de ellos, lo que su rol permite. La lista
la cambia solo el superadministrador. Los módulos son Documentos, Turnos, Derivaciones y
Supervisión; Calle tiene los cuatro y Básica y Estabilización, ninguno por ahora. El menú
muestra solo los módulos del componente, y abrir por dirección directa una pantalla, una
descarga o un reporte de un módulo que el componente no tiene da «No tienes permiso». Al
entrar, cada quien llega al primer módulo que puede abrir; si no hay ninguno, a una pantalla
de bienvenida que dice que su componente todavía no tiene módulos activos (o, sin componente,
que pida a su coordinación que se lo asignen). Perfil, campana, manual y tema siguen para
todos. La pantalla para que el superadministrador cambie los módulos llega con ACC-09
(pendiente); hasta entonces se cambian solo por migración.
*Origen:* DOC-25 · *Código:* `Componente::MODULOS`, `componentes.modulos`, Gate `modulo`, rutas `can:modulo,'…'`, trait `ExigeModulo` en las Policies, `InicioController`, `inicio.blade.php`.

**RN-50. Coordinador líder.** Es el rol Administración: no pertenece a ningún componente,
ve y trabaja todos los de la dependencia y da los permisos (crea usuarios y les asigna
componente y rol). Trabaja un componente a la vez: lo elige en el grupo «Componente» del
menú y ve lo que vería una persona de ese componente con acceso total. El superadministrador
hace lo mismo en todas las dependencias. El rol Administración lo da solo el
superadministrador. Asignar componente al crear o editar usuarios llega con ACC-07 y ACC-08
(pendientes).
*Origen:* DOC-25 (decisiones de Edwar del 2026-11-23, PA-01, PA-03, PA-05) · *Código:* `EstablecerDependencia` (componente de la sesión), `InicioController::cambiarComponente`.

**RN-51. Documentos es de Calle.** El repositorio documental pertenece a un solo componente
mientras documentos y carpetas no tengan componente. Encender el módulo Documentos en un
segundo componente mostraría los documentos de Calle; impedirlo en la pantalla de módulos
llega con ACC-09 (pendiente).
*Origen:* DOC-25 (decisión de Edwar del 2026-11-20) · *Código:* rutas `can:modulo,'documentos'`, `DocumentoPolicy`, `CarpetaPolicy`, `EnlaceCargaPolicy` con `ExigeModulo`.

### Repositorio

**RN-05. Una carga nunca sobrescribe.** Subir sobre un documento crea una versión nueva
con su autor y comentario; el historial completo se puede descargar.
*Origen:* F-01, F-02.

**RN-06. Ningún archivo por URL directa.** Toda descarga y vista previa pasa por un
control de permisos y queda registrada.
*Origen:* F-02 · *Código:* `DescargaController`, `AlmacenamientoDocumentos`.

**RN-07. Inactivar en vez de borrar.** Edición inactiva documentos (con motivo) y
carpetas que puede ver. Reactivar documentos: Administración (la única que ve lo
inactivo). Reactivar carpetas: Coordinación en adelante.
*Origen:* F-02, F-05 §2a, D-03.

**RN-08. Custodia por grupo.** Coordinación en adelante asigna una carpeta a un grupo; las
subcarpetas lo heredan. Solo el grupo (y Coordinación y Administración) la ve y la
trabaja; los miembros con Lectura revisan sin modificar. Abrir un documento custodiado
queda en la auditoría.
*Origen:* F-05 §4 · *Código:* `App\Rules\CarpetaAccesible`, `CarpetaPolicy`.

**RN-09. Copiar y mover por lotes.** Edición en adelante; hasta 200 documentos por
operación, todo o nada, una entrada de auditoría por pieza. Copiar duplica en el servidor.
*Origen:* F-05 §1 · *Código:* `LoteController`.

**RN-10. Cargas grandes por tandas.** Hasta 300 archivos por vez, enviados en tandas de
50 (o menos por peso). Si el servidor recibe menos archivos de los enviados, la tanda se
rechaza entera con un aviso; reintentar no duplica (mismo contenido en la misma carpeta).
*Origen:* F-05 §3 · *Código:* `CargaPorTandas`, `resources/js/carga-tandas.js`.

**RN-11. Comentarios.** Cualquiera que vea el archivo comenta; avisa a quien lo subió (o a
quien creó el enlace por el que llegó) y a quienes ya comentaron, nunca al autor. Lo borra
quien lo escribió o Coordinación en adelante.
*Origen:* D-05 · *Código:* `ComentarioDocumentoController`.

### Recepción externa

**RN-12. Enlace de carga.** Temporal, con vencimiento, tope de usos y revocable. Quien
envía declara quién es (o solo su cédula, si es persona de campo registrada). Se guarda
IP, navegador, huella SHA-256, si llegó en horario hábil y cuánto tardó desde la entrega
del enlace.
*Origen:* F-01, F-03 · *Código:* `EnlaceCarga`, `Recepcion`, `EnvioPublicoController`.

**RN-13. Fotos con sello.** Las fotos tomadas con la cámara llevan hora grande, fecha,
dirección y coordenadas, sin logos.
*Origen:* D-01 · *Código:* `resources/js/foto-georreferencial.js`.

### Turnos

**RN-14. La persona de campo no es usuario.** Se identifica por cédula normalizada (sin
puntos), única por dependencia. Sus datos se llenan una vez.
*Origen:* F-03 §1.

**RN-15. El enlace por cédula no revela datos.** Al reconocer una cédula solo muestra el
saludo recortado («Hola, Jos…»); la consulta tiene límite de intentos.
*Origen:* F-03 §2 · *Código:* `Colaborador::saludo`, limitador `cedula-consulta`.

**RN-16. El servidor decide y su hora vale.** El sistema decide si toca entrada o salida;
la hora que cuenta es la del servidor (la del teléfono se guarda aparte).
*Origen:* F-03 §1, F-04 §5.

**RN-17. Las marcaciones no se editan.** Corregir anula la original y crea otra con
motivo y autor. No se permite una salida antes de su entrada, una hora del futuro ni
corregir dos veces la misma marca. Las novedades que dependían de ella pasan a
«Corregida» y el turno se vuelve a juzgar. Coordinación y Administración además pueden
**cambiarle el tipo** (entrada ↔ salida, misma hora, sin exigir coherencia con las vecinas) o
**anularla** sin reemplazo; tampoco se borra: queda tachada con quién y por qué (DOC-18).
*Origen:* F-03 §1, F-04 §2, DOC-18 · *Código:* `Marcador::corregir`, `Marcador::anular`, `Marcacion::vigentes`.

**RN-18. Sin señal se marca igual.** Sin ubicación se marca y queda la novedad «Sin
ubicación», salvo que el componente exija ubicación. Fuera del radio del nodo: «Fuera de
zona».
*Origen:* F-03 §1.

**RN-19. Turnos por día.** Cumplidos los turnos del día que permite el componente, el
enlace ya no deja marcar; lo que falte lo corrige un líder.
*Origen:* F-04 §1.

**RN-20. Cierre automático.** Una entrada sin salida se cierra sola y queda la novedad «Sin
salida». El turno aparece en el reporte desde ese momento, marcado «Cierre automático · por
corregir», hasta que se revisa. Se cierra así:

- si el cargo tiene un máximo propio (Duración máxima por cargo; los conductores, 15 h), a esas
  horas de la entrada;
- si no, al fin del turno más largo que esa entrada podría ser, más la tolerancia de horas extra
  (60 min);
- si ningún turno encaja, a la duración máxima del componente.

No depende del cron: quien marca con una entrada vencida la cierra primero y lo nuevo queda como
entrada.
*Origen:* F-04 §4, DOC-21 · *Código:* `Marcador::tope`, `cerrarSinSalida`, `turnos:cerrar-abiertas` (cada hora).

**RN-21. Trabajo real = permanencia − descanso.** El descanso no remunerado va por tramos,
configurables por componente. Valores de fábrica: hasta 4 h, nada; hasta 6 h, 30 min; más
de 6 h, 1 h. En Calle se usan 30 min en los dos tramos. Desde DOC-8, el tramo se toma
del **horario del turno reconocido**: un T4 (4 h) no descuenta nada aunque se quede de más,
y un N1 (7 h 30 min) descuenta 30 min. Nunca se descuenta más de lo que daría el tiempo en
el sitio: un N1 que se va a las 3 h no descuenta. Sin turno con horario, va por el tiempo en
el sitio.
*Origen:* F-04 §2, F-05 §5 · *Código:* `Componente::minutosTrabajados`, `descansoPara`.
Ver PA-01 y PA-02.

**RN-22. Horas extra y turno corto.** La jornada esperada es la del turno reconocido: su
horario menos el descanso que le toca (T4 → 4 h; N1 → 7 h). Sin turno con horario, la del
componente. Si el trabajo real pasa de esa jornada más la **tolerancia de horas extra** (40 a
60 min, por componente), nace «Horas extra». Si queda por debajo de la jornada menos la
tolerancia, nace «Turno corto». Las dos quedan pendientes.
*Origen:* F-04 §2, DOC-8 · *Código:* `Componente::jornadaPara`, `Novedades::evaluarDuracion`.

**RN-23. Quién aprueba novedades de turno.** Coordinación y Administración siempre; de
Edición, solo quien tenga «Aprueba novedades de turno» (se marca por persona en
Usuarios). Rechazar exige comentario. A quien aprueba le llegan las novedades por la
campana. Desde DOC-25, cada quien aprueba solo las novedades de su componente (RN-01), y el
aviso va a quienes aprueban **en el componente de la persona de campo**, más el coordinador
líder (RN-50), no a toda la dependencia. *El cambio del aviso se aplica con ACC-10
(pendiente): hoy sigue llegando a quienes aprueban en toda la dependencia.*
*Origen:* F-04 §2, D-13, DOC-25 · *Código:* `User::puedeAprobarNovedadesEn`, `Novedades::avisar`.

**RN-24. El turno se reconoce por la entrada y la salida.** Entran los turnos cuyo inicio cae
entre 90 min antes y 180 min después de la entrada. Al marcar la entrada se elige el de inicio
más cercano (provisional, para la tardanza). Con la salida, el que mejor encaja con las dos
horas, y ese es el que va al reporte. El turno habitual solo desempata. En festivo solo cuentan
los turnos de domingos y festivos o de todos los días. Pasada la tolerancia queda «Tardanza», que
se corrige o se ajusta sola cuando la salida cambia el turno.
*Origen:* F-04, D-06, DOC-21 · *Código:* `Programacion::reconocer`, `Novedades::revisarTardanza`.

**RN-25. «No registró entrada».** A quien se esperaba (turno habitual que aplica ese día,
sin día de descanso ni ausencia) y 30 min después del inicio no ha marcado, le nace la
novedad y se avisa a Edición, Coordinación y Administración. Si marca después, se cierra
sola como «Corregida» y queda la tardanza. Desde DOC-25 el aviso va a Edición y Coordinación
**del componente de la persona** y al coordinador líder (RN-50), no a toda la dependencia.
*Se aplica con ACC-10 (pendiente): hoy sigue llegando a toda la dependencia.*
*Origen:* F-04, D-08, DOC-25 · *Código:* `RevisorEntradas`, `turnos:revisar-entradas` (cada 15 min).

**RN-26. Ausencias.** Por rango de fechas, con un código que no es turno (D, VAC, I, P,
PNR, F, EVENT). Esos días no se espera a la persona.
*Origen:* D-09.

**RN-27. 42 horas semanales: se señala, no se bloquea.** El reporte compara cada semana
(lunes a domingo) con 42 h y la marca en rojo si se pasa. Nunca bloquea ni inactiva.
*Origen:* F-05 §2b y §5.

**RN-28. Documenta no calcula salarios.** Entrega horas por tipo y el porcentaje que
aplica; los valores en pesos los calcula Nómina.
*Origen:* F-05 §5, D-11.

### Atención territorial

**RN-29. El caso lo crea la oficina y lo atiende la persona de campo.** Edición en
adelante crea el caso con el mensaje, quién lo envía (Alcaldía, Autoridades locales del
sector u Otra), la comuna de Medellín (lista en `config/repositorio.php` → `comunas`, DOC-23),
la ubicación y un soporte opcional (DOC-24). Recibe un código consecutivo por
dependencia (DC-001, DC-1000…), que nunca se repite ni se reinicia. La persona de campo lo
atiende desde su enlace de derivaciones: cédula registrada, activa y en turno, código
exacto, nodo que atiende, la atención o gestión realizada (obligatoria, DOC-23), tres
evidencias fotográficas y «¿Se cumplió?» (con No, la observación es obligatoria). Un caso se atiende una sola vez. Las derivaciones del flujo
anterior (sin código) se conservan como «registro anterior».
*Origen:* D-16, D-17, DOC-20 · *Código:* `CasoDerivacionController`, `Publico\DerivacionController`.

**RN-30. El tiempo corre desde la creación.** La hora del servidor al crear el caso es el
inicio. Nadie la escribe. **Excepción única (DOC-24, noviembre de 2026):** los casos
represados, ya atendidos, entran por un Excel que sube Coordinación, con su hora de inicio
y de cierre; quedan marcados «Carga masiva» y auditados. La carga se cierra sola el
30/11/2026.
*Origen:* D-19, DOC-20, DOC-24 · *Código:* `Derivacion::requeridaAt`, `minutos`, `CargaMasivaCasosController`, `DerivacionPolicy::cargarMasivo`.

**RN-31. Meta de 3 horas.** Atender después de 3 h exige justificación.
*Origen:* D-18 · *Código:* `Derivacion::META_MINUTOS`.

**RN-32. Enviar la atención detiene el tiempo.** El envío de la persona de campo es la
marca de cierre, con la hora del servidor. Edición y Coordinación solo corrigen textos.
Las horas, el «¿Se cumplió?» y el cierre a mano (plan de contingencia) son solo del
superadministrador de la plataforma, con motivo y auditoría. Lo mismo vale para la hora
de cierre de los registros anteriores. Los casos de la carga única (DOC-24, ver RN-30)
nacen con las horas del archivo; corregirlas después sigue siendo del superadministrador.
*Origen:* D-19, DOC-20, DOC-24 · *Código:* `DerivacionPolicy::corregirHoras`.

**RN-33. Avisos de derivaciones.** Un solo aviso cuando el caso pasa 3 h sin atención,
para Edición y Coordinación de la dependencia. Al atenderse, quien lo creó recibe el suyo
y se retira el de vencido. Ya no hay recordatorio cada 30 min. Desde DOC-25 el aviso de
vencido va a Edición y Coordinación **del componente del caso** y al coordinador líder
(RN-50): Administración pasa a recibirlo. *Se aplica con ACC-10 (pendiente): hoy sigue
llegando a Edición y Coordinación de toda la dependencia.*
*Origen:* D-19, D-21, DOC-20, DOC-25 · *Código:* `DerivacionPendiente`, `CasoAtendido`, `derivaciones:recordar`.

**RN-34. Trazabilidad.** La lista y la ficha muestran quién creó el caso y cuándo, quién
lo atendió (persona de campo y nodo) y cuándo, el tiempo, la meta y el «¿Se cumplió?». Los
indicadores, con los filtros aplicados, dan los abiertos y vencidos, el cumplimiento, los
nodos con casos abiertos y los casos atendidos por cada persona de campo con su tiempo
promedio. Desde DOC-23 se filtra también por comuna, y la ficha trae la gestión realizada y
los comentarios: escribe Edición en adelante, avisa por la campana a quien creó el caso y a
quienes ya comentaron, y la vía pública no los ve. La ficha de un caso de la carga única
(DOC-24) dice «Carga masiva», quién lo cargó y cuándo.
*Origen:* D-20, DOC-20, DOC-23, DOC-24 · *Código:* `Admin\Turnos\DerivacionController::trazabilidad`, `ComentarioDerivacionController`.

**RN-35. Eliminar derivaciones.** Solo Coordinación en adelante, como borrado lógico: sale
del reporte y sus fotos siguen en el repositorio. Es una excepción explícita a «no borrar».
*Origen:* código (`DerivacionPolicy::delete`).

**RN-36. Reporte de derivaciones.** Excel con encabezado negro y las columnas de la hoja
original, las fotos dentro de sus celdas y el soporte en PDF como enlace firmado que vence
a los 30 días; un ZIP con las fotos originales. Desde DOC-20, al final se agregan el
código, quién lo creó, la creación, la atención, el «¿Se cumplió?» y la observación; desde
DOC-23, la comuna y la gestión realizada; desde DOC-24, el origen («Carga masiva»).
*Origen:* D-16, DOC-20, DOC-23, DOC-24.

**RN-39. Quién supervisa.** Solo registran supervisiones las personas de campo activas
cuyo cargo está en «Cargos que supervisan» del componente (en Calle: «Apoyo al control
territorial»), sin importar mayúsculas ni espacios.
*Origen:* DOC-11 · *Código:* `Componente::supervisaConCargo`.

**RN-40. A quién se supervisa.** Los dinamizadores se identifican por cédula y deben ser
personas de campo registradas y activas de la dependencia; no pueden ser quien supervisa.
El enlace solo muestra su saludo recortado.
*Origen:* DOC-11 · *Código:* `Publico\SupervisionController`.

**RN-41. EPP al entrar.** Toda entrada por el enlace de turnos responde si recibió los
elementos de protección personal. Un «No» crea la novedad «Sin EPP», pendiente.
*Origen:* DOC-12 · *Código:* `TurnoController::marcar`, `Novedades::evaluar`.

**RN-42. Foto de turno solo con la cámara.** En el enlace de turnos (y en el de
supervisión) la foto se toma en el momento; no se elige de la galería.
*Origen:* DOC-12, DOC-11 · *Código:* `<x-campo-archivo :galeria="false">`.

**RN-43. Registrar solo en turno.** Para registrar una supervisión, una derivación o un
envío de evidencias por cédula, la persona tiene que estar en turno: su última marcación
vigente es una entrada. Una cédula nueva no está en turno (se registra marcando entrada).
*Origen:* DOC-13 · *Código:* `Marcador::estaEnTurno`.

**RN-44. Se supervisa a quien está en turno.** Los dinamizadores de una supervisión
también tienen que estar en turno.
*Origen:* DOC-13.

**RN-45. Avisos de supervisión.** Incidencia Relevante o Crítica, casos escalados o
calificación global de 1 o 2 avisan por la campana a Edición y Coordinación, en la pestaña
«Supervisión». Desde DOC-25 el aviso va a Edición y Coordinación **del componente de la
supervisión** y al coordinador líder (RN-50): Administración pasa a recibirlo. *Se aplica con
ACC-10 (pendiente): hoy sigue llegando a Edición y Coordinación de toda la dependencia.*
*Origen:* DOC-13, DOC-25 · *Código:* `SupervisionImportante`.

**RN-46. Ubicación imprecisa.** Una ubicación con error mayor a la «Precisión mínima» del
componente (500 m de fábrica; típico de un iPhone con «Ubicación precisa» apagada) se
guarda y cuenta como ubicación, pero no se juzga «fuera de zona»: queda la novedad
«Ubicación imprecisa». El teléfono avisa antes de enviar, con las instrucciones de su
sistema, y deja «Registrar de todos modos».
*Origen:* DOC-15 · *Código:* `Marcador::marcar`, `resources/js/turno.js`.

**RN-47. Salida mínima.** Una marca antes de la «Salida mínima» del componente (30 min de
fábrica) después de la última marca —entrada o salida— no cambia el tipo: es un reintento de
quien creyó que la anterior no llegó. Se responde «Ya estaba registrada tu entrada/salida» y
no se crea nada; al poner la cédula, el botón dice «Tu marca ya quedó registrada ✓» y no deja
enviar. Una salida o entrada real antes de ese tiempo la agrega Coordinación.
*Origen:* DOC-17 · *Código:* `Marcador::esReintento`, `resources/js/turno.js`.

### Comunicación

**RN-48. Ver un caso desde la vía pública (excepción).** Es la única vez que la vía
pública muestra contenido de un caso, y solo en el enlace de derivaciones. Primero va la
cédula activa y en turno. Después, el código exacto de un caso abierto del mismo
componente; cualquier otro resultado recibe el mismo mensaje. La consulta va por POST,
con un límite de 10 cada 10 min por IP y por cédula, y cada consulta queda auditada. El
soporte se abre por una URL firmada que vence a los 10 min, sin caché. Se muestra solo el
mensaje, quién lo envía, la ubicación y el soporte. El servidor revalida todo al enviar y
nada se guarda en el navegador.
*Origen:* DOC-20 (P-2, autorizada por Edwar) · *Código:* `Publico\DerivacionController::caso`, `soporte`.

**RN-37. Avisos de versión.** Al desplegar, si `CHANGELOG.md` tiene la sección de esa
versión, se anuncia una vez por la campana a todos los usuarios activos.
*Origen:* F-03 §8.4, F-04 §4.

**RN-38. Notificaciones por tipo.** La campana separa derivaciones, turnos, documentos y
avisos.
*Origen:* D-21 · *Código:* `NotificacionController::TIPOS`.

## Parámetros del negocio

No hay tabla `parametros` (constitución, stack). Los valores viven en tres lugares:

| Dónde | Qué controla | Quién lo cambia |
|---|---|---|
| `config/repositorio.php` | Formatos y tamaño máximo (25 MB), cargas por tandas (300 / 50 / 150 MB), lado máximo de las fotos, horario hábil, festivos 2026–2027, elementos por página | Desarrollo (y variables del `.env`) |
| Reglas del componente (`Componente::REGLAS`, pantalla «Componentes y nodos») | Jornada esperada, tolerancia, duración máxima, turnos por día, tramos de descanso, ventanas de reconocimiento (90/180 min), aviso «no registró entrada» (30 min), foto y ubicación obligatorias, autorregistro, cargos | Coordinación en adelante |
| Módulos del componente (`componentes.modulos`, DOC-25) | Qué módulos abre cada componente: Documentos, Turnos, Derivaciones, Supervisión (RN-49) | Solo el superadministrador. Hoy por migración; la pantalla llega con ACC-09 |
| Catálogo de la malla («Tipos de turno», «Puntos críticos») | Códigos, horarios, días y horas de nómina; puntos con ubicación | Edición en adelante |
| Constantes en código | Meta de derivaciones (180 min), recordatorio (30 min), 42 h semanales, 200 documentos por lote | Desarrollo. Candidatas a parámetro (C-02) |

## Preguntas abiertas

| # | Pregunta | Dueño | Bloquea |
|---|---|---|---|
| ~~PA-01~~ | Resuelta en DOC-8: cada turno se compara con la jornada de su horario | Edwar | DOC-8 |
| PA-02 | Calle: ¿mañana, tarde y noche descuentan 30 min (7 h 30 en el sitio = 7 h)? Con los tramos de fábrica descuentan 1 h. ¿El tramo largo pasa a «más de 8 h»? (D-15) | Talento Humano | DOC-8 |
| PA-03 | Domingos (C13): ¿de 7:00 a 18:00 con 1 h de almuerzo, 10 h de nómina? Hoy está cargado de 6:00 a 18:00 con 12 h (D-15) | Talento Humano | DOC-8 |
| PA-04 | Talento Humano pidió 1 h de almuerzo a los turnos que empiezan entre las 8 y las 11 a. m. ¿Aplica a T3 (10:00–17:30) aunque los demás turnos descuenten 30 min? (F-05 §5) | Talento Humano | DOC-8 |
| PA-05 | ¿Qué significan los códigos C («corrió 12»), U, R, H y Z de la malla y de Nómina? ¿Se agregan IA (incapacidad ARL) y LR (licencia remunerada)? (F-09) | Nómina | DOC-9 |
| PA-06 | EVENT tiene 0 horas de nómina: ¿cuenta como día laborado? | Nómina | DOC-9 |
| PA-07 | ¿Las horas de 12 a 6 a. m. de un domingo, en un turno que empezó el sábado, van al 125 %? ¿Cómo se combinan extras y recargos? (F-05 §5) | Nómina | DOC-10 |
| PA-08 | ¿N1 y N1. pasan a N1 y N2 para no confundirse? | Nómina | No |
| ~~PA-09~~ | Resuelta en DOC-25: el aviso de derivación vencida va a Edición y Coordinación del componente del caso y al coordinador líder (Administración). Se aplica con ACC-10 (RN-33) | Edwar | No |
| PA-10 | Plazos de retención de las fotos de turnos (se propuso: reducir a los 3 meses, borrar a los 3 años) | Talento Humano / jurídica | No |

## Candidatos para la siguiente versión

| # | Candidato |
|---|---|
| C-01 | Auditar el borrado de comentarios (RN-04) |
| C-02 | Pasar a parámetros editables la meta de derivaciones, el recordatorio y el tope semanal |
| C-03 | PIN de 4 dígitos además de la cédula, si aparece suplantación (F-03 §7) |
| C-04 | Malla mensual editable en Documenta e importador del Excel de la malla (F-08) |
| C-05 | Quitar la carga masiva de personas de campo cuando termine el ingreso inicial (D-14) |
