# Tareas: Casos · soporte opcional y carga única de casos ya atendidos (DOC-24)

Prefijo `CAR`. Pruebas `test_doc24_caNN_…`.

| Tarea | Qué | CA | Estado |
|---|---|---|---|
| CAR-01 | Soporte opcional en «Nuevo caso» (`CasoDerivacionController`, `create.blade.php`). Manual, CHANGELOG y RN-29 | CA-01 a CA-03 | Hecha |
| CAR-02 | Formato en Excel con la hoja de valores válidos e instrucciones (`CargaMasivaCasosController::formato`) | CA-04 | Hecha |
| CAR-03 | Pantalla de carga para Coordinación (`importar`): validación por fila, imágenes de las celdas (`XlsxImagenes`), casos atendidos con las horas del archivo, marca «Carga masiva» (migración `carga_masiva`), auditoría, cierre el 30/11/2026 | CA-05 a CA-10 | Hecha |
| CAR-04 | Probar con un Excel real de los coordinadores (PA-03), cargar en producción y retirar el código | — | Pendiente |
