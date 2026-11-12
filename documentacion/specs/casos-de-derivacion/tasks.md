# Tareas: Casos de derivación (DOC-20)

Prefijo `DCA`. Las pruebas van en `tests/Feature/Turnos/CasosDerivacion{Crear,Atender,Panel}Test.php`, con
nombres `test_doc20_caNN_…`.

| Tarea | Qué | CA | Depende | Estado |
|---|---|---|---|---|
| DCA-01 | Base: migración, modelo, auditoría, política, rutas, limitador, `CasoAtendido` | — | — | Hecha |
| DCA-02 | La oficina crea el caso (pantalla para el celular, código, clave de un solo uso, aviso de duplicado) | CA-01, CA-02 | DCA-01 | Hecha |
| DCA-03 | «Atender caso» en `/d/{token}` (consulta segura, soporte firmado, envío) | CA-03 a CA-08 | DCA-01 | Hecha |
| DCA-04 | Panel de trazabilidad, correcciones, Excel/ZIP y avisos | CA-09 a CA-12 | DCA-01 | Hecha |
| DCA-05 | RN, manual, CHANGELOG, MySQL y la suite completa | — | DCA-02 a DCA-04 | Hecha |

## Pendiente para salir a producción

- `CHANGELOG.md`: agregar la sección de la versión.
- Probar en el servidor de pruebas que dos personas no puedan atender el mismo caso a la vez: SQLite no prueba `lockForUpdate`.
- Confirmar con Edwar: si se envía un caso ya atendido, solo quien lo atendió ve «Este caso ya fue atendido»; cualquier otra persona ve el mensaje único.

## Candidatos

- C-01: después del aviso de duplicado, hay que volver a elegir el soporte.
- C-02: mostrar en la ficha el historial de auditoría de la contingencia.
