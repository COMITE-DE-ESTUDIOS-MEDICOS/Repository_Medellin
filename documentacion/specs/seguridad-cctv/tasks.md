# Tareas: Seguridad CCTV (DOC-33)

Prefijo `CCTV`. Plan: [plan.md](plan.md). Pruebas en `tests/Feature/SeguridadCctv/`, un archivo
por tarea (para que las tareas en paralelo no choquen), con nombres `test_doc33_caNN_…`.

| Tarea | Qué | CA | Quién | Estado |
|---|---|---|---|---|
| CCTV-01 | **Base.** Migración (`casos_cctv` + `dependencia_usuario.responsable_cctv`), `CasoCctv` (+ `carpetaPara()` con la custodia), factory, `CasoCctvPolicy`, `User::puedeGestionarCctvEn` y `withPivot` en `User`/`Dependencia`, `Componente::MODULOS`, `EnlaceTurno` (tipo `seguridad_cctv`, `url()`, `esSeguridadCctv()`), `AccionAuditoria`, **todas** las rutas de `routes/turnos-admin.php` y `routes/turnos-publico.php`. Suite completa en verde (ajustar las pruebas que cuentan módulos). Prueba: `BaseTest` | CA-01 (política sin módulo), CA-04 (matriz de la política) | backend | Hecha |
| CCTV-02 | **Casilla en usuarios.** `UsuarioController` store/update + `_formulario.blade.php`: «Responsable de Seguridad CCTV» junto a «Aprueba novedades», auditada con antes/después; sincroniza el grupo de custodia «Responsables de Seguridad CCTV». Prueba: `CasillaTest` (marca Coordinación/Administración, Edición no llega al formulario, no da poder fuera del módulo, Edición sin casilla no abre la carpeta en el repositorio y con casilla sí) | CA-03, CA-04, CA-19 (repositorio) | backend | Hecha |
| CCTV-03 | **Registrar y corregir.** `Admin\SeguridadCctv\CasoController` (create/store/edit/update) + vistas `create`, `edit`, `_campos` (celular, `datetime-local`, `<x-campo-archivo nombre="soporte" camara formatos="imagenes" sello="Seguridad CCTV">`, número en grande al guardar). Prueba: `RegistrarTest` | CA-04 (registra), CA-05, CA-06, CA-13, CA-15 (registrar, corregir) | backend | Hecha |
| CCTV-04 | **Ficha, cerrar, anular, imágenes.** `Admin\SeguridadCctv\FichaController` (show/cerrar/anular/archivo) + vista `show` (asignado y quien atendió, tiempos, quién cerró o anuló; imágenes solo con `@can('verImagenes')`). Prueba: `FichaTest` | CA-01 y CA-02 (ficha), CA-04 (cierra), CA-10 (se ve «1 h 25 min», sin «vencido»), CA-12, CA-14, CA-15 (cerrar, anular, archivo), CA-19 (app) | backend | Hecha |
| CCTV-05 | **Lista con Filament.** `App\Livewire\SeguridadCctv\Casos` (columnas y filtros de la spec, 25/50/100, fila → ficha) + página `index.blade.php` con «Nuevo caso» y «Enlace para atender». Prueba: `ListaTest` (`Livewire::test` con dependencia y componente a mano) | CA-01 y CA-02 (lista) | frontend | Hecha |
| CCTV-06 | **Enlace público `/c/{token}`.** `Publico\SeguridadCctvController` (mostrar/cedula/caso/soporte/atender) + `publico/seguridad-cctv.blade.php` (de `publico/derivacion.blade.php`). Prueba: `EnlaceTest` | CA-01 (enlace de un componente sin el módulo), CA-07, CA-08, CA-18, CA-09, CA-10 (cálculo), CA-11, CA-15 (consultar, atender) | backend | Hecha |
| CCTV-07 | **Crear y revocar el enlace CCTV.** `EnlaceTurnoController` (store/revocar: `authorize('compartirEnlace')` si el tipo es CCTV), `enlaces/create.blade.php` (opción solo para quien puede), `enlaces/index.blade.php` (contador). Prueba: `EnlaceAdminTest` | CA-04 (comparte el enlace; Edición sin casilla no) | backend | Hecha |
| CCTV-08 | **Menú.** `sidebar.blade.php`: «Seguridad CCTV» en «Atención territorial», debajo de Derivaciones. **Avisar a José (UHC-06 toca el mismo archivo).** Prueba: `MenuTest` | CA-01 (menú) | frontend | Hecha |
| CCTV-09 | **Manual mínimo y documentación.** Sección del manual (`manual/_contenido.blade.php`): registrar, compartir el enlace, atender, cerrar. `CHANGELOG.md`. En `documentacion/docs/context/`: RN-01, RN-43, RN-48, RN-49 cambiadas, RN-67 y RN-68 nuevas, glosario y tabla de estados. `docs/` sin cambios | — | frontend | Hecha |
| CCTV-10 | **Cierre de la tanda 1.** Suite completa; contra MySQL local: dos registros a la vez (número único) y dos atenciones a la vez; `npm run build`. Después del despliegue: el superadministrador enciende el módulo en Calle y marca a Freddy (Edición + casilla). Estado en la spec y en `documentacion/README.md` | — | qa | Pendiente |
| CCTV-11 | **Reporte ejecutivo.** `Admin\SeguridadCctv\ReporteController@index` + `reporte.blade.php` + botón «Reporte» en `index.blade.php`. Cálculo en PHP sobre una consulta plana (plan D8). Prueba: `ReporteTest` con tiempos conocidos (p. ej. 30, 60, 90, 240 min → promedio 1 h 45 min, mediana 1 h 15 min, mín 30 min, máx 4 h). Verificar la consulta en MySQL | CA-16, CA-01 y CA-02 (reporte), CA-14 (anulado no cuenta) | backend | Hecha |
| CCTV-12 | **Excel.** `ReporteController@exportar` con `Xlsx::libro`: hoja «Casos» (todas las columnas de la ficha, fotos con `Xlsx::miniatura` solo si `verImagenes`) y hoja «Resumen» (las cifras de CCTV-11). Prueba: `ExcelTest` | CA-17, CA-01 y CA-02 (Excel), CA-19 (Excel sin fotos para Edición sin casilla) | backend | Hecha |

## Orden, tandas y paralelo

| Tarea | Tanda | Tipo | Horas | Depende de | En paralelo con |
|---|---|---|---|---|---|
| CCTV-01 | 1 | backend | 1,5 | — | nada: va sola y primero |
| CCTV-02 | 1 | backend | 1 | 01 | 03, 04, 05, 06, 07, 08, 09 |
| CCTV-03 | 1 | backend | 2 | 01 | 02, 04, 05, 06, 07, 08, 09 |
| CCTV-04 | 1 | backend | 1,5 | 01 | 02, 03, 05, 06, 07, 08, 09 |
| CCTV-05 | 1 | frontend | 1,5 | 01 | 02, 03, 04, 06, 07, 08, 09 |
| CCTV-06 | 1 | backend | 2,5 | 01 | 02, 03, 04, 05, 07, 08, 09 |
| CCTV-07 | 1 | backend | 0,5 | 01 | 02, 03, 04, 05, 06, 08, 09 |
| CCTV-08 | 1 | frontend | 0,25 | 01 | todas las de la tanda 1 |
| CCTV-09 | 1 | frontend | 1 | 01 | todas las de la tanda 1 |
| CCTV-10 | 1 | qa | 0,75 | 02 a 09 | — |
| CCTV-11 | 2 | backend | 2 | 05 (botón en `index.blade.php`) | puede empezar en paralelo con la tanda 1 si deja el botón para el final |
| CCTV-12 | 2 | backend | 1,5 | 11 (mismo controlador) | — |

Tanda 1: unas 12,5 h de trabajo; en reloj, con 02 a 09 en paralelo, unas 5 h
(01 → 06, la más larga → 10). Tanda 2: 3,5 h seguidas.

**Archivos de cada tarea (ninguno se repite entre 02 y 09):**

- 01: migración, `CasoCctv`, factory, `CasoCctvPolicy`, `User`, `Dependencia`, `Componente`,
  `EnlaceTurno`, `AccionAuditoria`, `routes/turnos-admin.php`, `routes/turnos-publico.php`.
- 02: `UsuarioController`, `admin/usuarios/_formulario.blade.php`.
- 03: `SeguridadCctv/CasoController`, `seguridad-cctv/{create,edit,_campos}`.
- 04: `SeguridadCctv/FichaController`, `seguridad-cctv/show`.
- 05: `Livewire/SeguridadCctv/Casos`, `seguridad-cctv/index`.
- 06: `Publico/SeguridadCctvController`, `publico/seguridad-cctv`.
- 07: `EnlaceTurnoController`, `enlaces/{create,index}`.
- 08: `sidebar.blade.php`.
- 09: manual, `CHANGELOG.md`, `documentacion/docs/context/`.
- 11-12: `SeguridadCctv/ReporteController`, `seguridad-cctv/reporte`, una línea en `seguridad-cctv/index`.

Nadie fuera de CCTV-01 toca `routes/`, `Componente.php`, `User.php`, `EnlaceTurno.php` ni
`AccionAuditoria.php`. Si una tarea necesita algo ahí, lo pide; no lo agrega.

## Consultas a verificar en MySQL

- CCTV-03: `lockForUpdate()->max('numero')` con el índice único `(dependencia_id, numero)`.
- CCTV-06: `lockForUpdate` del caso al atender (SQLite no lo prueba).
- CCTV-05, 11, 12: filtro de rango sobre `requerido_at` (datetime) y el orden de la lista.
- CCTV-11: la consulta del reporte (plana, sin `GROUP BY`; si alguien la pasa a SQL agrupado,
  `reorder()` y verificar `ONLY_FULL_GROUP_BY`).

## Candidatos

Los de la spec (C-01 a C-07). Del plan: ocultar el grupo de custodia de CCTV de la pantalla
de grupos si se desalinea de la casilla.
