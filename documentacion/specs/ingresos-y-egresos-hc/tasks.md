# Tareas: Ingresos y egresos de Usuarios HC (DOC-27)

> **Estado: Aprobadas por Edwar (2026-11-24)**, con el plan (P-01 a P-07 por defecto; UHC-11 al 11/12). Spec v1.0 aprobada.
> **Ajustado tras revisión de QA (2026-11-25):** contenido de IYE-01, 02, 03, 04, 05, 07, 08, 09, 10, 12 y UHC-19 a 22;
> pruebas de CA-12, 15, 17, 20, 26 y 31; holgura recontada (1,0, no 1,25). Ninguna fecha de entrega cambia.

Prefijo `IYE`. Plan: [plan.md](plan.md). Spec: [spec.md](spec.md) v1.0. Pruebas en
`tests/Feature/IngresosEgresos/`, con nombres `test_doc27_caNN_…`. Una tarea a la vez, cada una
con la suite completa pasando.

**Reparto:** Edwar construye desde el 1/12 (antes está con DOC-26: UHC-01 y UHC-04). José ayuda el
**7, 9 y 10/12**; el resto del tiempo está en DOC-26 y no toca estos archivos. Las tareas UHC-19 a
UHC-22 son de DOC-26 pero se hacen aquí, porque las habilita DOC-27 (plan D10).

Estimación en días-persona. **Depende** nombra también las tareas de DOC-26 (UHC-NN) y la fecha en
que llegan a `develop`.

## Hasta el 15/12

| Tarea | Qué | CA | Tipo | Quién | Est. | Depende | Estado |
|---|---|---|---|---|---|---|---|
| IYE-01 | **Módulo y rutas.** `ingresos_egresos` en `Componente::MODULOS` después de `usuarios_hc`; `routes/ingresos-egresos.php` con la pantalla vacía como `Route::view`, **sin `can:viewAny`** (la Policy llega en IYE-03; igual que UHC-01 en `routes/usuarios-hc.php`), y su carga en `bootstrap/app.php` con `"can:modulo,'ingresos_egresos'"`; línea en `sidebar.blade.php` para todos los roles **cuando UHC-06 (1/12) esté fusionada** (si no lo está, Edwar mete las dos líneas y avisa a José). `ModulosPorComponenteTest` no se edita aquí: ya exige el grupo de rutas y la llegada de Calle (plan D2). Sin migración de encendido | CA-21 | Backend | Edwar | 0,25 | UHC-01 (hecha), UHC-06 (1/12, solo el menú) | **Hecha 2026-11-26 (adelantada).** Falta la línea de `sidebar.blade.php`: espera a UHC-06 de José (no se tocó). |
| IYE-02 | **Esquema, modelos y días.** Migración `2026_12_01_000001_crear_ingresos_y_egresos` (`sedes`, `sede_capacidades`, `estadias` con `activa_usuario_hc_id` único, `estadia_respuestas`, `estadia_cambios`); modelos con `DependenciaScope` + `DelComponente` (`Sede`, `Estadia`); `Estadia::scopeEntreComponentes()` (**el único** salto de `ComponenteScope` de las estadías; devuelve la consulta) y `componente()` sin él; relación `UsuarioHc::estadiaActiva()` sobre ese scope (archivo de José: avisarle); cast `App\Casts\HoraColombia` (segundos en 00); `Estadia::diasDeEstadia()` y accessor; `Sede::capacidadEn()` con `whereDate` (prueba con una capacidad que empieza el mismo día); bloque `ingresos_egresos` en `config/repositorio.php` y constantes de los tres códigos con efecto; fábricas `Sede` y `Estadia` (`activa`, `egresada`, `anulada`). Prueba de días con `America/Bogota` y con `UTC` | CA-23, CA-02 (al minuto) | Backend | Edwar | 1 | UHC-02, UHC-03 (30/11) | **Hecha 2026-11-26 (adelantada).** `UsuarioHc::estadiaActiva()` agregada: avisar a José. |
| IYE-03 | **Permisos y auditoría.** `can:viewAny,App\Models\Estadia` en la ruta de la pantalla; `Estadia::class` y `Sede::class` en la lista de Policies de `ModulosPorComponenteTest`. `EstadiaPolicy` (`before` con módulo, dependencia y componente comparados con `(int)`; `viewAny`, `view`, `create`, `registrarEgreso`, `corregir`, `pasarDelMes`, `reabrir`, `anular`) y `SedePolicy` (solo superadministrador); acciones nuevas de `AccionAuditoria` (plan D8). Pruebas de la Policy: Lectura, Edición, Coordinación, líder y superadministrador; otro componente; otra dependencia en cada método; Calle sin módulo | CA-16, CA-18, CA-32 (Policy), CA-01 (Policy) | Backend | Edwar | 0,5 | IYE-02 | Pendiente |
| IYE-04 | **Registrar ingreso y egreso, servidor.** `App\Services\RegistroEstadias::registrarIngreso` y `registrarEgreso` (plan D5), en ese orden: transacción, bloqueo de la persona, relectura y **después** autorización, revisión, bloqueo de la sede (ingreso), cuenta activa, Viva, sede activa, opciones vigentes (`UsuarioHc::opciones`), una activa (mensaje con solo el componente), cruces, retroactiva, no futura, un mes (salvo `pasarDelMes`), salida ≥ llegada, comuna solo con calle, destino solo con derivación, excluyentes (`UsuarioHc::chocanExcluyentes`), `UniqueConstraintViolationException` → mismo mensaje; aviso de capacidad (sin aviso si no hay capacidad vigente, PA-11); egreso por Fallecimiento → `FichasUsuariosHc::cambiarEstadoVital` **después** de dejar la estadía egresada, con el motivo del plan D5.7 (si UHC-12 no está el 3/12, ese efecto se mueve a IYE-10: −0,25 aquí, +0,25 allá); auditoría. CA-17 comprueba además `Notification::assertNothingSent()` (el aviso de UHC-21 no sale al egresar) | CA-02 a CA-11, CA-17 (egreso), CA-20 (servidor), CA-25, CA-29, CA-30, CA-33, CA-34 | Backend | Edwar | 1,5 | IYE-03, UHC-03 y UHC-05 (30/11), UHC-12 (3/12; firma escrita en DOC-26 D7) | Pendiente |
| IYE-05 | **Pantalla mínima: tabla y ventanas de ingreso y egreso.** `index.blade.php` y `Livewire\IngresosEgresos\Estadias` (tabla Filament con los scopes, columnas de §2, días, orden por ingreso); acción de encabezado «Registrar ingreso» (persona buscada con `scopeBuscar` sin fallecidas ni activas, sede puesta si hay una, `datetime-local` vacío, comuna visible solo con calle); acción «Registrar egreso» (fecha vacía, destino visible solo con derivación, fecha de fallecimiento propuesta); `datetime-local` con o sin segundos (`date_format`, segundos descartados); «Ver usuario HC» **solo con `Route::has('usuarios-hc.show')`** (la ruta llega con UHC-11, 11/12); apertura desde la dirección con `tableAction`/`tableActionArguments`/`actionComponent` y `getDefaultActionUrlIdentifier()` = `'estadias'`, `fillForm` que vuelve a buscar la persona con el filtro de dependencia y comprueba Viva y sin activa, `getOptionLabelUsing` en el selector (plan D7); errores del servicio como notificación de error + `$action->halt()`; aviso de capacidad. Cada acción con `->authorize()`. **Con esto se mira el punto de control del 4/12** | CA-02 (formulario vacío), CA-03 (no se ofrece), CA-20 (texto del error visible y conserva lo escrito), CA-22 (solo el componente de la sesión) | Frontend | Edwar | 0,75 | IYE-04 | Pendiente |
| IYE-06 | **Lista completa.** En `Estadias.php`: búsqueda (`whereIn` sobre `UsuarioHc::buscar`), filtros de fechas (rango con permanencia, del 1 del mes a hoy en Colombia), estado (Todas sin anuladas, Activa, Egresada, Anuladas) y sede (con las inactivas); búsqueda, filtros y página que se conservan; «—» en días de una anulada. **Antes** de que Edwar agregue ahí las acciones de IYE-09 e IYE-10 | CA-22 | Frontend | José | 0,5 | IYE-05 | Pendiente |
| IYE-07 | **Sedes (superadministrador).** `Livewire\IngresosEgresos\Sedes` dentro de la pantalla, con `@can('viewAny', Sede::class)`: crear (nombre, capacidad, vigente desde), renombrar, cambiar capacidad (nueva, desde, motivo; muestra las anteriores), activar y desactivar (en transacción, bloqueando la sede antes de contar; rechazo con cuántas activas tiene); nombre único por componente con `Rule::unique` y captura de `UniqueConstraintViolationException` con el mismo mensaje; `SedeGuardada`; `->authorize()` en cada acción. Pieza aparte, para moverla si Edwar responde distinto a P-01 | CA-01, CA-26 | Backend + frontend | José | 0,75 | IYE-03 | Pendiente |
| IYE-08 | **Ver estadía.** `EstadiaController@show` con `can:view` y `EstadiaConsultada` cada vez; vista con ingreso (condiciones incluidas), egreso, quién registró y cuándo (`->setTimezone(zona)->fechaHora()`) e historial; Lectura recibe rechazo; otro componente u otra dependencia, 404; **`estadia` en `ModulosPorComponenteTest::parametrosEn()`** (la ruta `{estadia}` llega aquí) | CA-18, CA-19, CA-32 (ruta) | Backend + frontend | José | 0,5 | IYE-03 | Pendiente |
| IYE-09 | **Corregir ingreso y egreso.** `RegistroEstadias::corregirIngreso` y `corregirEgreso`: motivo obligatorio, revisión, límite del mes para la fecha nueva **y** la vieja (salvo `pasarDelMes`) **solo si cambia una fecha**, cruces, cambio de sede con bloqueo de la sede nueva (activa) y aviso de capacidad sobre ella, fecha de fallecimiento de la ficha editable mientras el motivo siga en Fallecimiento (cambiar la fecha del egreso no la mueve; plan D5.7), CA-12 comprueba que completar un dato opcional **no cambia** `ingreso_en` ni `created_at` (regla 10), opción retirada que la estadía ya tiene se acepta sin cambiarla, historial por campo (destino quitado incluido), corrección **a** Fallecimiento deja Fallecida y **desde** Fallecimiento pregunta «¿La persona está viva?»; `EstadiaCorregida` solo con campos y motivo (la prueba abre la pantalla de Auditoría). Acciones en la tabla | CA-10 (historial), CA-12, CA-13, CA-17 (corrección), CA-24, CA-28, CA-33 (corrección), CA-32 (acción) | Backend | Edwar | 1,25 | IYE-04, IYE-06 | Pendiente |
| IYE-10 | **Reabrir y anular.** `RegistroEstadias::reabrir` (bloquea la sede y exige que siga activa; otra activa o cruce sin fin → rechazo diciendo el conflicto; egreso al historial; Activa; aviso de capacidad) y `anular` (activa o egresada; motivo; fuera de activas y de la lista salvo con «Anuladas»; los indicadores y reportes que la excluyen se prueban en DOC-28/29); pregunta «¿La persona está viva?» con egreso por Fallecimiento (Sí → Viva con historial en la ficha; No → no se hace). Acciones en la tabla con confirmación, solo Coordinación en adelante | CA-14, CA-15, CA-16, CA-17 (reabrir y anular), CA-27, CA-32 (acción) | Backend | Edwar | 1 | IYE-09, UHC-19 | Pendiente |
| UHC-19 | (DOC-26) `conocidaPor` y `editablePor` con `Estadia::entreComponentes()`: válida = activa o egresada | DOC-26 CA-24, CA-25; DOC-27 CA-27, CA-30 | Backend | Edwar | 0,5 | IYE-02, UHC-04 | Pendiente |
| UHC-21 | (DOC-26) «Fallecida» desde la ficha con estadía activa: aviso **solo** a Coordinación de ese componente y a Administración, activos (`usuariosDelComponente(...)->wherePivotIn('rol', [Coordinacion, Administracion])->where('activo', true)`, como `DerivacionPendiente` sin Edición), tipo nuevo en la campana; no egresa. Prueba propia de DOC-27 además de la de DOC-26 | DOC-26 CA-26; DOC-27 CA-31 | Backend | Edwar | 0,25 | IYE-02, UHC-12 | Pendiente |
| UHC-20 | (DOC-26) Lista de Usuarios HC: «componente o estado» con `with('estadiaActiva…')` (columnas limitadas, solo el nombre del componente; sin N+1) y filtro de estado con subconsulta sobre `Estadia::entreComponentes()` | DOC-26 CA-23 | Frontend | José | 0,5 | IYE-02, UHC-06 | Pendiente |
| UHC-22 | (DOC-26) «Registrar ingreso» en la lista de Usuarios HC: enlace a `ingresos-egresos.index` con `tableAction=registrarIngreso`, `tableActionArguments[usuario_hc]=ID` y `actionComponent=estadias` (plan D7), solo para quien puede (módulo encendido y `create` de `Estadia`) y personas vivas sin activa. Prueba con `Livewire::withQueryParams(...)` que la ventana abre con la persona puesta, y que un ID de otra dependencia, fallecida o con activa deja el campo vacío | DOC-26 CA-27 | Frontend | José | 0,25 | IYE-05, UHC-06 | Pendiente |
| IYE-11 | **Verificación.** Suite completa; `IngresosEgresos` contra MySQL (`DB_CONNECTION=mysql DB_DATABASE=documenta_pruebas_mysql DB_URL= php artisan test --filter=IngresosEgresos`) con las 8 consultas del plan; zona horaria de cada servidor; encender el módulo en Básica y Estabilización del servidor de pruebas; recorrido con un usuario de cada rol en Básica, Estabilización y Calle, en celular | — (repite CA-04, CA-08, CA-22, CA-23 en MySQL) | Backend | Edwar | 0,5 | todas las anteriores | Pendiente |
| IYE-12 | **Documentación.** La lista del plan («Documentación»): RN-57 a RN-61, RN-49, RN-04, RN-06, RN-13, estados, glosario, `docs/FILAMENT.md` (saltos de scope, `datetime-local`, `actionComponent` para abrir una acción desde la dirección), `CHANGELOG.md`, estado de la spec, `README`. En «Nada se borra»: las `estadia_respuestas` se reemplazan al corregir y el historial de la estadía es el registro de verdad. Sin el manual | — | Frontend (docs) | José | 0,25 | IYE-10 (lo que falte lo cierra Edwar en IYE-11) | Pendiente |

**Suma hasta el 15/12:** IYE 8,75 días-persona (Edwar 6,75; José 2) + UHC de DOC-27 1,5 (Edwar
0,75; José 0,75) = **10,25**.

## Después del 15/12 (recortes de la spec, §9)

| Tarea | Qué | CA | Tipo | Quién | Est. | Depende | Estado |
|---|---|---|---|---|---|---|---|
| IYE-13 | **Fotos de ingreso y egreso.** Migración `estadia_fotos`; recodificación GD compartida con UHC-09 (se extrae si quedó dentro de `FichasUsuariosHc`); campo sin sello (UHC-10); ruta con `verFotos`, `no-store`, CSP, `EstadiaFotoVista` cada vez; reabrir conserva la foto de egreso en el historial. Prueba con JPEG con EXIF y GPS subido sin navegador | CA-35, CA-19 (foto), CA-32 (foto) | Backend + frontend | Por asignar | 1 | UHC-09, UHC-10 | Recortada |
| IYE-14 | **Diagnósticos de ingreso.** `estadia_respuestas` con `campo = diagnosticos`, `fuente = cie10`; selector como UHC-14 con el texto guardado; oculto sin catálogo; en ingreso y en corregir ingreso | CA-36 | Backend + frontend | Por asignar | 0,5 | UHC-14 (y UHC-15 para el uso real) | Recortada |
| IYE-15 | **Manual y datos de demostración.** Sección del manual; `DemoIngresosEgresosSeeder` (local, ficticio) | — | Frontend (docs) + backend | Por asignar | 0,5 | IYE-12 | Recortada |

**2 días-persona** después del 15/12, antes de producción (que espera además a jurídica y a las
sedes reales).

## Criterios y pruebas

| CA | Tarea | Prueba |
|---|---|---|
| CA-01 | IYE-07, IYE-03 | `test_doc27_ca01_solo_el_superadministrador_gestiona_sedes_y_capacidad_con_motivo_e_historial` |
| CA-02 | IYE-04, IYE-05, IYE-02 | `test_doc27_ca02_el_ingreso_abre_vacio_y_se_guarda_al_minuto` |
| CA-03 | IYE-04, IYE-05 | `test_doc27_ca03_activa_en_otro_componente_o_fallecida_no_ingresa_y_solo_dice_el_componente` |
| CA-04 | IYE-04 (+ MySQL) | `test_doc27_ca04_estadias_que_se_cruzan_se_rechazan_y_la_hora_exacta_se_acepta` |
| CA-05 | IYE-04 | `test_doc27_ca05_futuro_salida_antes_de_llegada_y_mes_para_edicion_se_rechazan` |
| CA-06 | IYE-04 | `test_doc27_ca06_el_mes_cuenta_el_dia_completo_y_el_ultimo_dia_del_mes_corto` |
| CA-07 | IYE-04 | `test_doc27_ca07_la_capacidad_avisa_y_deja_guardar` |
| CA-08 | IYE-04 (+ MySQL) | `test_doc27_ca08_dos_envios_crean_un_solo_movimiento` |
| CA-09 | IYE-04 | `test_doc27_ca09_condiciones_excluyentes_no_se_combinan` |
| CA-10 | IYE-04, IYE-09 | `test_doc27_ca10_comuna_solo_con_calle_y_destino_solo_con_derivacion` · `test_doc27_ca10_cambiar_el_motivo_quita_el_destino_y_queda_en_el_historial` |
| CA-11 | IYE-04 | `test_doc27_ca11_el_egreso_deja_egresada_y_la_derivacion_no_crea_ingreso` |
| CA-12 | IYE-09 | `test_doc27_ca12_corregir_exige_motivo_y_deja_antes_y_despues_en_el_historial` (afirma además que completar un dato opcional no cambia `ingreso_en` ni `created_at`) |
| CA-13 | IYE-09 | `test_doc27_ca13_el_segundo_guardado_se_rechaza_con_aviso` |
| CA-14 | IYE-10 | `test_doc27_ca14_reabrir_conserva_el_egreso_y_se_bloquea_con_conflicto` |
| CA-15 | IYE-10 | `test_doc27_ca15_anular_una_activa_la_saca_de_activas_y_de_la_lista` (hoy: fuera de activas, de «Todas» y presente en «Anuladas»; indicadores y reportes, en DOC-28/29) |
| CA-16 | IYE-03, IYE-10 | `test_doc27_ca16_edicion_y_coordinacion_de_otro_componente_no_reabren_ni_anulan` |
| CA-17 | IYE-04, IYE-09, IYE-10 | `test_doc27_ca17_egreso_por_fallecimiento_y_la_pregunta_de_si_esta_viva` (con `Notification::assertNothingSent()`: egresar por fallecimiento no dispara el aviso de UHC-21; el motivo que llega al historial de la ficha) |
| CA-18 | IYE-03, IYE-05, IYE-08 | `test_doc27_ca18_otro_componente_recibe_rechazo_y_lectura_solo_ve_la_lista` |
| CA-19 | IYE-08, IYE-13 | `test_doc27_ca19_abrir_una_estadia_queda_auditado_cada_vez` (la foto, en IYE-13) |
| CA-20 | IYE-04, IYE-05 | `test_doc27_ca20_si_falla_una_regla_no_se_guarda_nada_y_se_conserva_lo_escrito` (en la ventana: `assertNotified` con el texto del error y los datos del formulario intactos) |
| CA-21 | IYE-01 | `test_doc27_ca21_sin_el_modulo_no_hay_menu_ni_direccion_y_calle_no_lo_tiene` (además, `ModulosPorComponenteTest`) |
| CA-22 | IYE-05, IYE-06 (+ MySQL) | `test_doc27_ca22_solo_el_componente_de_la_sesion_rango_con_permanencia_y_anuladas_aparte` |
| CA-23 | IYE-02 | `test_doc27_ca23_dias_de_estadia_en_hora_de_colombia_con_cualquier_zona_del_servidor` |
| CA-24 | IYE-09 | `test_doc27_ca24_la_auditoria_lleva_campos_y_motivo_sin_valores` (abre la pantalla de Auditoría) |
| CA-25 | IYE-04 | `test_doc27_ca25_traslado_de_sede_cierra_y_el_ingreso_a_la_misma_hora_se_acepta` |
| CA-26 | IYE-07, IYE-04, IYE-10 | `test_doc27_ca26_sede_con_personas_no_se_desactiva_e_inactiva_sigue_en_lo_anterior` (hoy: en la lista y en «Ver estadía» con su nombre guardado; informes, en DOC-29; reabrir en una sede inactiva se rechaza) |
| CA-27 | IYE-10, UHC-19 | `test_doc27_ca27_coordinacion_anula_una_egresada_y_quita_el_acceso_a_la_caracterizacion` |
| CA-28 | IYE-09 | `test_doc27_ca28_edicion_no_corrige_una_fecha_de_hace_mas_de_un_mes` |
| CA-29 | IYE-04 | `test_doc27_ca29_ingreso_antes_del_ultimo_egreso_se_rechaza` |
| CA-30 | IYE-04, UHC-19 | `test_doc27_ca30_otro_componente_ingresa_a_la_persona_queda_auditado_y_ve_la_caracterizacion` |
| CA-31 | UHC-21 | `test_doc27_ca31_fallecida_desde_la_ficha_con_estadia_activa_no_egresa_y_avisa_solo_a_coordinacion_y_lider` (propia; además la de DOC-26 CA-26, `test_doc26_ca26_fallecida_con_estadia_activa_avisa_y_no_egresa`) |
| CA-32 | IYE-03, IYE-08, IYE-09, IYE-10, IYE-13 | `test_doc27_ca32_otra_dependencia_da_no_encontrado_lectura_y_sin_sesion_rechazo` (cada tarea agrega su caso) |
| CA-33 | IYE-04, IYE-09 | `test_doc27_ca33_opcion_renombrada_o_retirada_conserva_su_texto` |
| CA-34 | IYE-04 | `test_doc27_ca34_cambios_entre_abrir_y_guardar_rechazan_con_el_motivo` |
| CA-35 | IYE-13 (después del 15/12) | `test_doc27_ca35_fotos_sin_exif_solo_del_componente_y_sin_cache` |
| CA-36 | IYE-14 (después del 15/12) | `test_doc27_ca36_diagnosticos_por_estadia_con_el_texto_guardado` |

## Orden y paralelo

```
(DOC-26 en develop el 30/11: UHC-01 a UHC-05, UHC-10)
IYE-01 ─ IYE-02 ─ IYE-03 ─ IYE-04 ─ IYE-05 ── punto de control 4/12 (con UHC-07/UHC-08 o fichas por tinker)
                     │                 └─ IYE-06 (José) ─ IYE-09 ─ IYE-10
                     ├─ IYE-07 (José), IYE-08 (José)
UHC-06 (1/12) ─ línea del menú de IYE-01 · UHC-03 (27/11) y UHC-12 (3/12) ─ IYE-04
IYE-02 ─ UHC-19 ─ IYE-10 · IYE-02 ─ UHC-21 · IYE-02 ─ UHC-20 (José) · IYE-05 ─ UHC-22 (José)
todo ─ IYE-11 (Edwar) · IYE-10 ─ IYE-12 (José)
```

**Archivos compartidos:** `Componente.php` y `bootstrap/app.php` en IYE-01 (Edwar);
`sidebar.blade.php` en IYE-01, después de que UHC-06 (José) esté fusionada. `ModulosPorComponenteTest`
(prueba de DOC-25): IYE-03 (Edwar, lista de Policies) e IYE-08 (José, `estadia` en `parametrosEn()`),
en ese orden; IYE-01 no la edita. `UsuarioHc.php` (relación `estadiaActiva`) en IYE-02, avisando a
José. `Estadias.php`: Edwar hasta IYE-05; José en IYE-06 el 7/12; Edwar de nuevo desde IYE-09
(9/12). IYE-07 e IYE-08 son archivos nuevos de José. UHC-20 y UHC-22 tocan `Listado.php` de
DOC-26, que para entonces José ya terminó (UHC-06, 1/12).

## Calendario

Días hábiles del 1 al 15/12 (el 8/12 es festivo): 1, 2, 3, 4, 7, 9, 10, 11, 14 y 15 = **10 días
de Edwar**. José: **7, 9 y 10/12 = 3 días**. Edwar tiene además en esos días UHC-11 (0,5) y
UHC-17 (0,75) de DOC-26.

| Fecha | Edwar | José |
|---|---|---|
| 1/12 | IYE-01 (0,25), IYE-02 (0,75) | DOC-26 |
| 2/12 | IYE-02 (0,25), IYE-03 (0,5), IYE-04 (0,25) | DOC-26 (UHC-12) |
| 3/12 | IYE-04 (1) | DOC-26 (UHC-12 a `develop`) |
| **4/12** | IYE-04 (0,25), IYE-05 (0,75); fusionar a `develop`, sedes de prueba con `tinker` (P-07: `user_id` de Edwar a mano, sin auditoría a propósito); fichas de prueba desde la pantalla si UHC-07 (2/12) y UHC-08 (4/12) están en `develop`, si no por `tinker`. **Punto de control** | DOC-26 (UHC-08 a `develop`) |
| 7/12 | IYE-09, servicio y pruebas (1) | IYE-06 (0,5), IYE-07 (0,5) |
| 9/12 | IYE-09, acciones en la tabla (0,25), UHC-19 (0,5), IYE-10 (0,25) | IYE-07 (0,25), IYE-08 (0,5), UHC-22 (0,25) |
| 10/12 | IYE-10 (0,75), UHC-21 (0,25) | UHC-20 (0,5), IYE-12 (0,25); holgura 0,25 |
| 11/12 | UHC-11 de DOC-26 (0,5, P-03); holgura 0,5 | DOC-26 |
| 14/12 | IYE-11 (0,5); holgura 0,5 (correcciones de QA) | DOC-26 |
| 15/12 | UHC-17 de DOC-26 (0,75; cierra el 16/12 en la mañana, después de UHC-14, como dice usuarios-hc/tasks.md); cierre de DOC-27: fusión a `develop` (0,25) | DOC-26 |

**La cuenta:**

| | Edwar | José |
|---|---|---|
| Disponible | 10 | 3 |
| IYE | 6,75 | 2 |
| UHC dentro de DOC-27 (19, 21 · 20, 22) | 0,75 | 0,75 |
| UHC-11 y UHC-17 (DOC-26) | 1,25 | — |
| Cierre del 15/12 (fusión a `develop`) | 0,25 | — |
| **Holgura** | **1,0**: 0,5 el 11/12 (semana del 7/12) y 0,5 el 14/12 (correcciones de QA) | **0,25** |

Edwar: 6,75 + 0,75 + 1,25 + 0,25 + 1,0 = 10. José: 2 + 0,75 + 0,25 = 3. (La cuenta anterior daba
1,25 porque contaba como holgura el 0,25 del cierre del 15/12, y la ponía toda en la semana del 7/12
cuando la mitad está el 14/12.)

**¿Cabe al 15/12?** **Sí, con los recortes de la spec** (fotos, diagnósticos y manual después), y
con una condición: **la semana del 1 al 4/12 no tiene holgura**. Suma 4 días de trabajo en 4 días
**solo si UHC-11 sale de esa semana** (P-03); con UHC-11 dentro, serían 4,5 y el punto de control
del 4/12 no llega. La fusión a `develop` y el despliegue del 4/12 tampoco están contados.

**Si el 4/12 no llega:**

- Retraso de medio día: se come **toda** la holgura de la semana del 7/12 (0,5); todo sigue al
  15/12, pero desde ahí solo queda el 0,5 del 14/12 para las correcciones de QA.
- Retraso de más de medio día y hasta un día: también se va el 0,5 del 14/12; QA queda sin margen.
- Si además UHC-12 no llegó el 3/12, el efecto de fallecimiento se mueve a IYE-10 (plan, «Lo que
  se toma de DOC-26»): el 10/12 pasa a 1,25 y toma 0,25 del 11/12, así que la semana del 7/12
  queda con 0,25 y medio día de retraso el 4/12 ya no cabe en ella.
- Retraso de más de un día: se aplica el recorte de la spec (P-04): IYE-09 e IYE-10 (corregir,
  reabrir y anular, 2,25 días) pasan a la semana del 14 al 18/12. El 15/12 sale ingreso, egreso,
  lista, sedes y ver estadía. **Ojo:** sin IYE-10 no hay prueba de CA-27 y UHC-19 queda probada
  solo con estadías activas y egresadas.
- Lo que no se recorta nunca: rechazos, aislamiento, auditoría, historial y las reglas de §7.

**Riesgo propio de José:** su parte de DOC-26 ya iba con 0,25 días de falta (usuarios-hc/tasks.md,
«Calendario»). Si DOC-26 lo necesita el 7, 9 o 10/12, lo primero que se corre es IYE-12
(documentación, la cierra Edwar) y después UHC-20; IYE-06, IYE-07 e IYE-08 no deberían moverse.

## Solo Edwar

| Qué | Para qué | Cuándo |
|---|---|---|
| ~~Aprobar este plan; responder P-01 a P-07~~ Hecho (Edwar, 2026-11-24) | Arrancar el 1/12 | Antes del 1/12 |
| ~~Acordar con José las dos firmas~~ Escritas en DOC-26 el 25/11 (usuarios-hc/plan.md D7 y D8; UHC-03 se construye el 27/11, UHC-12 el 2 y 3/12) | IYE-04 | Hecho |
| Confirmar `APP_TIMEZONE=America/Bogota` en pruebas y producción | Turnos (DOC-27 ya no depende) | Al desplegar |
| Encender «Ingresos y egresos» en Básica y Estabilización desde «Componentes y nodos» | Servidor de pruebas (4/12) y producción (con DOC-26) | Al desplegar |
| Crear sedes de prueba en el servidor de pruebas (`tinker` el 4/12, o la pantalla desde el 9/12) | Punto de control | 4/12 |
| Guardar en `fuentes/` las sedes reales (PA-01) y la confirmación escrita del mes y la capacidad (PA-09) | Producción | Cuando lleguen |

## Pendiente para salir a producción

- PA-01 (sedes reales) y PA-09 (confirmación escrita).
- DOC-26 completa y las respuestas de jurídica (DOC-26 PA-09 y PA-18): salen juntas.
- IYE-13, IYE-14 e IYE-15 si las fotos, los diagnósticos y el manual salen con esta versión.
- `CHANGELOG.md` con la sección de la versión.
