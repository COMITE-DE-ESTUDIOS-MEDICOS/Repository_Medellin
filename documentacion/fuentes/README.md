# Fuentes

Evidencia de entrada: lo que se pidió, lo que respondieron las áreas y lo que se decidió.
**No se modifican**; las capas de arriba las citan por su código.

| Código | Fuente | Qué aporta |
|---|---|---|
| F-01 | [`README.md`](../../README.md) (raíz) | El problema original: versiones perdidas, sin rastro, recepción desordenada; lo que no hace |
| F-02 | [`docs/INICIO.md`](../../docs/INICIO.md) | Decisiones fundacionales: inactivar en vez de borrar, aislamiento, UUID, roles por dependencia, versionado, almacenamiento |
| F-03 | [`docs/turnos/PLAN.md`](../../docs/turnos/PLAN.md) | Plan del módulo de turnos: principios, flujo de la persona de campo, privacidad, decisiones abiertas |
| F-04 | [`docs/turnos/README.md`](../../docs/turnos/README.md) | Turnos tal como quedaron: uso, horas extra, descanso por tramos, turnos automáticos |
| F-05 | [`docs/PROXIMOS-CAMBIOS.md`](../../docs/PROXIMOS-CAMBIOS.md) | Cola acordada el 14-10-2026 y **§5: respuesta de Talento Humano y Nómina** (recargos, 42 h, descanso, almuerzo) |
| F-06 | [`docs/reuniones/2026-10-09-inclusion-social.md`](../../docs/reuniones/2026-10-09-inclusion-social.md) | Reunión del 9-10-2026: componentes (Calle, Básica, Estabilización), posibles módulos |
| F-07 | [`decisiones-2026-10.md`](decisiones-2026-10.md) | Decisiones de Edwar en las sesiones de desarrollo de octubre de 2026 |
| F-08 | Malla de turnos «MG OCTUBRE 2026» y formulario del QR (Google Sheets, privados) | Códigos de turno, horarios, horas de nómina y puntos críticos de Calle. Transcritos en `database/seeders/CatalogoTurnosCalleSeeder.php` |
| F-09 | Ejemplos de Nómina: reporte de novedades, liquidación de recargos, liquidación de nómina (Excel, **no se versionan**: datos personales) | Planilla por persona y día con códigos; RN / RFN / RFD; valor hora = salario ÷ 210 |
| F-11 | Formulario «REGISTROS DE SUPERVISIÓN V6 (respuestas)» (Google Forms, Excel en Descargas, **no se versiona**: nombres y cédulas) | Las 31 preguntas de la supervisión territorial; ~565 registros desde octubre |
| F-10 | [`CHANGELOG.md`](../../CHANGELOG.md) | Lo publicado, en palabras para el usuario |

## Cómo se agrega una fuente

1. Actas de reunión: en `docs/reuniones/AAAA-MM-DD-tema.md`, y una fila aquí.
2. Respuestas escritas de un área: se copian tal cual a un archivo de esta carpeta.
3. Decisiones en una sesión de trabajo: al final de `decisiones-2026-10.md` (o del mes),
   con fecha y quién decidió.
4. Archivos con datos personales: **no se copian**; se describe qué traen y quién los tiene.
