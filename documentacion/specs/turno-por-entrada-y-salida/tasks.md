# Tareas: Turno por entrada y salida (DOC-21)

Prefijo `TES`. Las pruebas, `test_doc21_caNN_…`, van en
`tests/Feature/Turnos/TurnoPorEntradaYSalidaTest.php` (TES-01) y en
`tests/Feature/Turnos/CierreSinSalidaTest.php` (TES-02).

| Tarea | Qué | CA | Estado |
|---|---|---|---|
| TES-01 | Reconocimiento por entrada y salida, el habitual solo desempata, la tardanza al salir | CA-01 a CA-03 | Hecha |
| TES-02 | Tope por cargo y por turno corto, cierre al marcar, marca en el reporte, campo en el componente | CA-04 a CA-08 | Hecha (v0.4: cierre por horario + 60 min) |
| TES-03 | RN, manual, suite completa y MySQL | — | Hecha |

## Prueba de estrés

`php artisan turnos:simular-semana 2026-11-02 --personas=60` (solo desarrollo; `--sin-cron` simula un servidor sin cron).
Resultado con v0.4 y DOC-8: turno reconocido 245 de 246 (99,6 %), tardanza 246 de 246, 0 entradas
tomadas como salida, 0 turnos cortados mientras se trabajaba, 18 de 18 olvidos cerrados, 11 de 11
jornadas largas de conductores sin cortar.
