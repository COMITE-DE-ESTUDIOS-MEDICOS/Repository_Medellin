# Spec: Registro de supervisión territorial

| | |
|---|---|
| Historia | DOC-11 |
| Tipo | Historia de usuario |
| Estado | **Aprobada · Construida** |
| Versión | 1.0 |
| Fuentes | F-11 (formulario «REGISTROS DE SUPERVISIÓN V6», hoja 1), pedido de Edwar del 26-10-2026 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 26-10-2026 («listo a programar», con las recomendaciones) |
| Valida en la Review | [completar: Coordinación de Calle] |

## Objetivo

Que el **Apoyo al control territorial** registre cada visita de supervisión a una dupla de
dinamizadores desde el celular, con su cédula, calificando de 1 a 5 y con fotos tomadas en
el sitio; y que los líderes y Coordinación consulten y descarguen esos registros.

## Proceso actual observado

Hoy se llena un formulario de Google Forms («REGISTROS DE SUPERVISIÓN V6»): nombre del
apoyo, punto crítico, hora de la visita, nombre y cédula de los dos dinamizadores, nodo,
link de Google Maps, turno, descripción del punto, nueve criterios de 1 a 5, incidencias,
casos escalados, casos prioritarios, recomendaciones, calificación global de la dupla,
dos evidencias y observaciones. Se lleva desde octubre: ~565 registros (F-11).

## Problema

- Los nombres y cédulas se escriben a mano: el mismo dinamizador aparece de cientos de
  formas (369 valores distintos en 520 registros), así que no se puede sacar su promedio.
- El link de ubicación se copia a mano y las fotos no prueban hora ni lugar.
- Los registros no se cruzan con los turnos ni con las personas de campo de Documenta.

## Usuarios y roles involucrados

- **Apoyo al control territorial** (persona de campo con ese cargo): registra la visita
  por el enlace, con su cédula. No tiene cuenta.
- **Edición, Coordinación y Administración:** consultan, filtran y descargan (CA-08).
- **Lectura:** no ve el módulo (CA-09).
- **Otra persona de campo** (otro cargo): no puede registrar supervisiones (CA-02).

## Historia de usuario

**DOC-11.** Como Apoyo al control territorial, quiero registrar cada supervisión desde mi
celular con mi cédula, calificando a la dupla y tomando fotos en el sitio, para que
Coordinación tenga el seguimiento de cada dinamizador sin depender de un Forms.

## Reglas de negocio que aplica

RN-01, RN-04, RN-13, RN-14, RN-15, RN-16 (ver `docs/context/domain.md`). Reglas nuevas
propuestas, para agregar al dominio al aprobar:

- **RN-39. Quién supervisa.** Solo registran supervisiones las personas de campo activas
  cuyo cargo esté en la lista «Cargos que supervisan» del componente. En Calle:
  «Apoyo al control territorial».
- **RN-40. A quién se supervisa.** Los dinamizadores se identifican por cédula y deben ser
  personas de campo registradas y activas de la dependencia. El enlace solo muestra su
  saludo recortado (RN-15).

## Criterios de aceptación

**CA-01. Registrar una supervisión.**
- **Dado** un enlace por cédula de tipo «Supervisión» vigente y un Apoyo al control
  territorial registrado,
- **cuando** escribe su cédula y completa: punto crítico, nodo, hora de la visita, cédula
  del dinamizador 1 (y del 2 si hay dupla), turno del dinamizador, descripción del punto,
  los nueve criterios de 1 a 5, incidencias, casos escalados, casos prioritarios,
  recomendaciones, calificación global de la dupla, la evidencia 1 y, si quiere, la
  evidencia 2 y observaciones,
- **entonces** queda registrada con su ubicación, la hora en que abrió y en que envió el
  formulario, y ve «Supervisión #N registrada. Gracias, <saludo>.». Queda en la auditoría.

Los nueve criterios son, en este orden: Cumplimiento horario; Presencia en la zona asignada
(ubicación y cumplimiento de la ruta); Conocimiento del territorio (puntos críticos); Uso
adecuado de aplicativos y registros digitales; Cumplimiento de protocolos, intervenciones
y derivaciones; Utiliza los EPP; Manejo de relaciones con la comunidad y actores locales;
Capacidad para mediar, orientar y tranquilizar situaciones; Presentación personal.

**CA-02. Solo el cargo que supervisa.**
- **Dado** una persona de campo con otro cargo,
- **cuando** escribe su cédula en el enlace de supervisión,
- **entonces** ve «Este enlace es solo para el Apoyo al control territorial.» y no puede
  seguir.

**CA-03. Dinamizador no registrado.**
- **Dado** una cédula de dinamizador que no está registrada o está desactivada,
- **cuando** se escribe,
- **entonces** se rechaza con «Esta cédula no está registrada como persona de campo.» y
  no se envía nada.

**CA-04. Calificaciones completas.**
- **Dado** el formulario con un criterio sin calificar o con un valor fuera de 1 a 5,
- **cuando** se envía,
- **entonces** se rechaza indicando el criterio que falta.

**CA-05. Casos escalados.**
- **Dado** «Casos escalados al enlace de nodo» en Sí,
- **cuando** «¿Cuál?» está vacío,
- **entonces** se rechaza con «Escribe cuál caso se escaló.».

**CA-06. Fotos solo con la cámara.**
- **Dado** el paso de evidencias,
- **entonces** solo aparece «Tomar foto» (no «Elegir archivo»), la foto lleva el sello de
  hora y lugar (RN-13), y la evidencia 1 es obligatoria.

**CA-07. Sin señal de GPS.**
- **Dado** un celular sin ubicación,
- **cuando** se envía,
- **entonces** se registra igual y queda marcada «Sin ubicación» en la lista y en el Excel.

**CA-08. Consultar y descargar.**
- **Dado** un usuario de Edición en adelante,
- **cuando** abre Atención territorial → Supervisión,
- **entonces** ve la lista con filtros por fechas, nodo, punto crítico, quién supervisó y
  cédula del dinamizador, el detalle de cada registro con sus fotos y su ubicación en el
  mapa, y puede descargar un Excel con las columnas del Forms, las fotos dentro de sus
  celdas y el promedio de cada registro.

**CA-09. Sin permiso.**
- **Dado** un usuario de Lectura o de otra dependencia,
- **cuando** intenta abrir la lista, un registro o el Excel,
- **entonces** se le niega (RN-01).

**CA-10. Crear el enlace.**
- **Dado** un usuario de Edición en adelante,
- **cuando** crea un enlace por cédula,
- **entonces** puede elegir para qué es: Turnos, Derivaciones o **Supervisión**, y obtiene
  su link y su QR.

## Orden de validación

1. Enlace no vigente o de otro tipo. 2. Cédula no registrada. 3. Cargo que no supervisa.
4. Datos inválidos (en el orden del formulario).

## Casos límite

- **Un solo dinamizador:** el dinamizador 2 es opcional; la calificación global sigue
  siendo de la visita.
- **El dinamizador es de otro componente:** se acepta si es de la misma dependencia.
- **Doble envío por doble toque:** el segundo envío idéntico en menos de un minuto se
  ignora.
- **Hora de la visita futura:** se rechaza con «La hora de la visita no puede ser
  posterior a la hora actual.».

## Datos que crea y consume

| Dato | Acción | Área dueña |
|---|---|---|
| Supervisión (con sus calificaciones) | Crea | Atención territorial |
| Evidencias (como documentos del repositorio) | Crea | Repositorio |
| Personas de campo, puntos críticos, nodos, tipos de turno | Consulta | Turnos |
| Enlaces por cédula (tipo Supervisión) | Crea y consulta | Turnos |

## Datos sensibles

Cédulas de supervisores y dinamizadores, fotos de personas y ubicación. Solo los ve
Edición en adelante de la dependencia; el enlace público no muestra nombres (RN-15).

## Métrica de éxito

- Promedio por dinamizador calculable: de imposible (nombres escritos a mano) a 100 % de
  los registros nuevos ligados a una persona de campo.

## Fuera de alcance

- Migrar los ~565 registros del Forms (se pueden importar después; ver C-02).
- Tablero de promedios por dinamizador (C-01).
- Que el supervisado vea su calificación.

## Decisiones (antes preguntas abiertas)

Se aprobaron las recomendaciones. PA-04: un solo campo «Observaciones» de quien supervisa; la nota de revisión pasa a C-04.

| # | Pregunta | Recomendación aprobada | Dueño |
|---|---|---|---|
| PA-01 | ¿Dónde va el módulo? | **Atención territorial → Supervisión**, al lado de Derivaciones: mismos actores, mismo tipo de enlace y de reporte. Si crecen los módulos de campo, el grupo se puede llamar «Control territorial» | Edwar |
| PA-02 | ¿El dinamizador se identifica por cédula? (en el Forms escriben nombre y cédula) | Sí, por cédula, para ligarlo a su ficha | Edwar |
| PA-03 | ¿El punto crítico se elige del catálogo, o se permite «Otro» escrito? (en el Forms hay 49 nombres distintos; el catálogo tiene 40) | Del catálogo, con «Otro» escrito como salida | Coordinación |
| PA-04 | ¿Qué es «OBSERVACIONES 2»? ¿La escribe quien supervisa o quien revisa después? | Si es de quien revisa: un campo «Observación de revisión» en Documenta | Coordinación |
| PA-05 | ¿«Cargos que supervisan» queda como regla del componente? | Sí, en Componentes y nodos, con «Apoyo al control territorial» en Calle | Edwar |

## Candidatos para la siguiente versión

| # | Candidato |
|---|---|
| C-01 | Promedio por dinamizador y por criterio, por mes, en la ficha de la persona y en una pestaña del Excel |
| C-02 | Importar los registros del Forms (los que se puedan ligar por cédula) |
| C-03 | Aviso a los líderes cuando una supervisión trae incidencia «Crítica» |
| C-04 | «Observación de revisión» que escribe un líder en Documenta (el «OBSERVACIONES 2» del Forms) |

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-10-26 | Borrador inicial a partir del Forms V6 |
| 1.0 | 2026-10-26 | Aprobada y construida. Pruebas: `tests/Feature/Turnos/SupervisionTerritorialTest.php` (`test_ca01` a `test_ca10` y casos límite) |
