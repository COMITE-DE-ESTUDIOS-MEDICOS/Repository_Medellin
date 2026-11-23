# Decisiones de octubre de 2026

> Registro de lo que Edwar decidió en las sesiones de desarrollo con Claude (14 al 26 de
> octubre de 2026). **Lo transcribió Claude a partir de las conversaciones: Edwar debe
> revisarlo** y corregir lo que no refleje bien lo decidido. Donde la fecha exacta no
> quedó registrada, dice «octubre».

## Documentos y repositorio

**D-01 · Sello de las fotos.** Las fotos tomadas con la cámara llevan un sello como el de
Timemark, **sin logos del Comité**: hora grande, fecha, dirección (calle, barrio,
municipio) y coordenadas. *(octubre)*

**D-02 · Rastro de cada carga.** La auditoría muestra por dónde entró cada archivo
(aplicación o enlace de carga) y a qué carpeta fue, también para las cargas anteriores.
*(octubre)*

**D-03 · Editores inactivan.** Los editores pueden inactivar documentos (con motivo) y
carpetas. Reactivar documentos sigue siendo de Administración. *(reunión, octubre)*

**D-04 · Ruta completa al copiar o mover.** Los selectores de carpeta muestran la ruta
(«Calle / Nodo 2 / Octubre») y un buscador. *(reunión, octubre)*

**D-05 · Navegar y comentar archivos.** Dentro de una carpeta se pasa de un archivo a otro
con flechas; cada archivo admite comentarios que avisan a quien lo subió y a quienes ya
comentaron. *(octubre)*

## Turnos

**D-06 · Turno habitual y reconocimiento automático.** Cada persona tiene su turno,
punto crítico y días de descanso habituales en su ficha; al marcar, el turno se reconoce
solo por la hora. No se elige el turno en el formulario. *(octubre)*

**D-07 · QR de punto crítico.** El QR puede ser de un punto crítico: quien marca con él
queda en ese punto ese día. *(octubre)*

**D-08 · «No registró entrada».** Si alguien con turno no marca a los 30 minutos, el aviso
llega a Edición y Coordinación. *(octubre)*

**D-09 · Ausencias.** D, VAC, I, P… se registran por fechas desde la ficha de la persona o
justificando la novedad. *(octubre)*

**D-10 · Resumen mensual contra 42 h.** El reporte de turnos trae una pestaña con el
resumen por persona y las horas de cada semana contra 42 h. *(octubre)*

**D-11 · Salarios y valores los calcula Nómina.** Documenta entrega horas por tipo y el
porcentaje que aplica; no guarda salarios ni calcula pesos. *(octubre)*

**D-12 · Catálogos dentro de Documenta.** Tipos de turno y puntos críticos se manejan con
tablas de Filament dentro del diseño de Documenta, sin panel aparte; se abren con botones
desde Personas de campo y En turno ahora. Los editores tienen acceso. «Componentes y nodos»
va en Administración. *(octubre)*

**D-13 · Aprobar novedades de turno se elige por persona.** Coordinación y Administración
siempre aprueban; de Edición, solo quien tenga marcada la casilla «Aprueba novedades de
turno» (por ejemplo, Deisy). Los demás editores las ven sin aprobarlas. *(24 de octubre)*

**D-14 · Carga masiva de personas de campo (temporal).** Botones «Descargar Excel de
carga» y «Carga masiva» en Personas de campo, para el ingreso inicial; se quitan después.
*(23 de octubre)*

**D-15 · Jornadas de Calle (por confirmar).** Mañana, tarde y noche: 7 h 30 min en el
sitio con 30 min de descanso (7 h de nómina). Domingos de 7:00 a. m. a 6:00 p. m. con 1 h
de almuerzo. Hay que confirmar con Talento Humano y cargarlo en el sistema; ver PA-01 a
PA-03 en el dominio. *(26 de octubre)*

## Atención territorial (derivaciones)

**D-16 · Formulario de derivaciones.** Por enlace o QR, solo para cédulas registradas, por
pasos con barra de progreso, con las columnas de la hoja «Derivaciones y respuesta
territorial». Reporte en Excel con encabezado negro y las fotos dentro de sus celdas, y un
ZIP con las fotos originales. *(octubre)*

**D-17 · Evidencias obligatorias.** Tres evidencias fotográficas obligatorias. *(reunión,
octubre)*

**D-18 · Meta de respuesta: 3 horas.** Si se pasa, hay que justificar. *(reunión, octubre)*

**D-19 · Las derivaciones llegan pendientes y las responden los editores.** El tiempo corre
desde la hora del requerimiento hasta que un editor registra la respuesta en Documenta;
eso la deja «solucionada» y detiene el contador. Los editores reciben un aviso al llegar y
cada 30 minutos mientras siga sin respuesta. *(23 de octubre)*

**D-20 · Trazabilidad para el seguimiento.** Quien hace seguimiento (Deisy, rol Edición)
necesita ver quién ha respondido y quién no, con el tiempo desde la solicitud. No aprueba
derivaciones: las sigue. *(24 de octubre)*

**D-21 · Notificaciones separadas.** Las de derivaciones no se mezclan con las de turnos.
*(24 de octubre)*

## Interfaz

**D-22 · Filament con los colores de Documenta.** Las piezas de Filament usan la paleta,
la letra y los estilos de Documenta; las fichas largas (usuarios, perfil, persona de
campo) van por tarjetas con panel lateral. *(23 al 26 de octubre)*

**D-23 · Manual de usuario.** En Administración y en el menú de la cuenta, con la imagen
del Comité y descarga en PDF. *(octubre)*

## Del 26 de octubre

**D-24 · Supervisión territorial en la aplicación.** El Forms «Registros de supervisión V6»
pasa a Documenta (Atención territorial → Supervisión), por enlace por cédula, para el
cargo «Apoyo al control territorial». Los dinamizadores se identifican por cédula; si el
punto no está en el catálogo, «Otro». El sello de sus fotos dice «Apoyo Territorial».

**D-25 · EPP y solo cámara en turnos.** Al marcar entrada se responde si recibió los EPP
(un «No» es novedad «Sin EPP»), y la foto del turno solo se toma con la cámara.

**D-26 · Registrar solo en turno.** Supervisión, derivaciones y evidencias por cédula
exigen estar en turno; en supervisión, también los dinamizadores. Lo importante de una
supervisión avisa a Edición y Coordinación.

**D-28 · Ubicación imprecisa.** Ante los problemas de ubicación con iPhone: avisar a la persona cómo activar la ubicación precisa, no marcar «fuera de zona» con una ubicación aproximada (novedad «Ubicación imprecisa») y no enviar sin ubicación cuando es obligatoria. Edwar revisará cómo sale en producción.

**D-29 · Salida mínima de 30 minutos.** Quien reintenta marcar porque creyó que su entrada
no llegó terminaba con una salida falsa. Una marca a menos de 30 minutos de la entrada se
toma como reintento; el tiempo es editable por componente en Componentes y nodos.
*(29 de octubre, reporte del equipo de campo)*

**D-30 · Anular y cambiar el tipo de una marca, sin borrar.** Ante un turno N7 invertido,
Coordinación y Administración pueden anular una marca o pasarla de entrada a salida (o al
revés); se prefirió a borrarla para no perder la traza ante Nómina. El reintento dentro de
la salida mínima tampoco abre una entrada después de una salida. *(30 de octubre)*

**D-27 · Sin exigir verificación (por ahora).** Quien se registró sola por el QR y está
«por verificar» puede marcar y registrar igual, para no interrumpir la operación. Se
revisará más adelante (exigirla para registrar, no para marcar, sería lo recomendado).
