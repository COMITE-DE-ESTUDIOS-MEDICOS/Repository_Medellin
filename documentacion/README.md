# Documentación de Documenta (SDD)

Documenta se documenta y se sigue construyendo con **Spec-Driven Development**: primero
se escribe qué debe hacer (spec), se aprueba, y después se construye. Como el sistema ya
existía cuando se adoptó el método (octubre de 2026), lo construido se documentó primero
«tal como está», y lo nuevo sigue el flujo completo.

## Orden de lectura

Cada capa es más específica que la anterior y nunca puede contradecirla.

| # | Capa | Archivo(s) | Pregunta que responde | Estado |
|---|---|---|---|---|
| 0 | Fuentes | [`fuentes/`](fuentes/README.md) | ¿Qué se pidió y qué se decidió? | Índice listo |
| 1 | Constitución | [`constitution.md`](constitution.md) | ¿Qué reglas aplican a todo? | Borrador para aprobar |
| 2 | Contexto | [`docs/context/`](docs/context/) | ¿Por qué existe, quién lo usa, qué reglas tiene el negocio? | Borrador para aprobar |
| 3 | Arquitectura | `docs/architecture/` | ¿Cómo está organizado técnicamente? | Fase 2 |
| 4 | Spec | `specs/<funcionalidad>/spec.md` | ¿Qué debe hacer esta funcionalidad? | Fase 3 (lo construido) y en adelante |
| 5 | Plan | `specs/<funcionalidad>/plan.md` | ¿Cómo la construimos? | Solo lo nuevo |
| 6 | Tareas | `specs/<funcionalidad>/tasks.md` | ¿Qué trabajo concreto hay? | Solo lo nuevo |

**Regla de oro:** si cambia una regla, se cambia primero la capa donde vive y después lo de
abajo. El código no es la fuente de verdad del comportamiento; la spec sí.

Las guías operativas que ya existían siguen en [`../docs/`](../docs/) (turnos, Filament,
geolocalización, interfaz, puesta en marcha) y se enlazan desde aquí; no se duplican.

## Códigos

| Código | Qué es | Dónde vive |
|---|---|---|
| `DOC-N` | Historia o mejora técnica | Cabecera de `spec.md` |
| `XXX-NN` | Tarea de una funcionalidad (prefijo de 3 o 4 letras por funcionalidad) | `tasks.md` |
| `F-NN` | Documento fuente | [`fuentes/README.md`](fuentes/README.md) |
| `D-NN` | Decisión de Edwar registrada en las fuentes | [`fuentes/decisiones-2026-10.md`](fuentes/decisiones-2026-10.md) |
| `RN-NN` | Regla de negocio | [`docs/context/domain.md`](docs/context/domain.md) |
| `CA-NN` | Criterio de aceptación | `spec.md` |
| `PA-NN` | Pregunta abierta: bloquea la construcción | `domain.md`, `spec.md` o `plan.md` |
| `C-NN` | Candidato para la siguiente versión: no bloquea | `spec.md`, `plan.md` |
| `ADR-NNNN` | Decisión de arquitectura | `docs/architecture/decisions/` |

## Plan de documentación

| Fase | Qué | Estado |
|---|---|---|
| 1 | `CLAUDE.md`, constitución, fuentes y contexto (proyecto, dominio, actores, glosario) | **Hecha, por aprobar** |
| 2 | Arquitectura tal como está: visión general, modelo de datos, seguridad y ADR de lo decidido | Pendiente |
| 3 | Una spec por módulo construido, con estado «Construida» y sus criterios ligados a las pruebas que ya existen | Pendiente |
| 4 | Lo pendiente como specs nuevas con el flujo completo (jornada por tipo de turno, planilla de nómina, recargos…) | Pendiente |

## Estado de las funcionalidades

| Funcionalidad | Historia | spec.md | Estado |
|---|---|---|---|
| Repositorio documental (carpetas, versiones, custodia, lotes) | DOC-1 | Fase 3 | Construida |
| Enlaces de carga y recepción externa | DOC-2 | Fase 3 | Construida |
| Usuarios, roles y auditoría | DOC-3 | Fase 3 | Construida |
| Turnos de personas de campo | DOC-4 | Fase 3 | Construida |
| Turnos automáticos y ausencias | DOC-5 | Fase 3 | Construida |
| Derivaciones y respuesta territorial | DOC-6 | Fase 3 | Construida |
| Notificaciones, avisos y manual | DOC-7 | Fase 3 | Construida |
| Jornada esperada por tipo de turno | DOC-8 | Por escribir | Por especificar |
| Planilla mensual con códigos para Nómina | DOC-9 | Por escribir | Por especificar |
| Recargos nocturno, dominical y festivo (RN, RFD, RFN) | DOC-10 | Por escribir | Por especificar |
| Registro de supervisión territorial | DOC-11 | [Aprobada](specs/supervision-territorial/spec.md) | Construida |
| EPP al marcar entrada y foto solo con la cámara | DOC-12 | [Aprobada](specs/turno-epp-y-camara/spec.md) | Construida |
| Ubicación imprecisa (iPhone) y nombres largos de archivo | DOC-15 | [Aprobada](specs/ubicacion-imprecisa/spec.md) | Construida |
| Analítica de supervisión y Excel con resumen | DOC-14 | [Aprobada](specs/analitica-supervision/spec.md) | Construida |
| Registrar solo en turno · avisos de supervisión | DOC-13 | [Aprobada](specs/registro-en-turno-y-avisos-supervision/spec.md) | Construida |
| Salida mínima: reintentos que no son salida | DOC-17 | [Aprobada](specs/salida-minima/spec.md) | Construida |
| Anular una marca o cambiarle el tipo | DOC-18 | [Aprobada](specs/anular-y-cambiar-tipo-de-marca/spec.md) | Construida |
| Reporte con el turno reconocido y sin ceros por nodo | DOC-19 | [Aprobada](specs/reporte-turno-reconocido-y-nodo/spec.md) | Construida |
Seguimiento: en el repositorio (`tasks.md`). Notion queda para cuando se conecte.
