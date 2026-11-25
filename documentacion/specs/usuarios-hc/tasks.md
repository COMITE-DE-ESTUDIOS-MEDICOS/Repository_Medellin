# Tareas: Usuarios HC (DOC-26)

> **Estado: Aprobadas con la spec DOC-26 v1.1 (Edwar, 2026-11-24).** PA-09 y PA-18 bloquean solo
> el paso a producción.

Prefijo `UHC`. Plan: [plan.md](plan.md). Spec: [spec.md](spec.md) v1.1, aprobada. Pruebas en
`tests/Feature/UsuariosHc/`, con nombres `test_doc26_caNN_…`. Una tarea a la vez, cada una con la
suite completa pasando.

**Reparto:** Edwar es el Dueño de Producto y toma lo que toca archivos de DOC-25 (UHC-01) y la
Policy (UHC-04), que DOC-27 usa desde el primer día; desde el 1/12 está en DOC-27, donde también
caben UHC-19 y UHC-21. José lleva el resto. José tiene además ACC-15 (~0,5 días) y ayuda en
DOC-27 el 7, el 9 y el 10/12.

**Entrega del 30/11 a `develop`** (la necesita DOC-27): UHC-01, UHC-02, UHC-04 y UHC-05 (más
UHC-03 y UHC-10, que ya estarán). Columna **Entrega**.

Estimación en días-persona. La columna **PA** dice de qué pregunta depende la tarea (de la spec,
PA-NN; de este plan, P-NN); se construye con la respuesta por defecto.

## Con DOC-26 sola

| Tarea | Qué | CA | Tipo | Quién | Est. | Depende | PA | Entrega | Estado |
|---|---|---|---|---|---|---|---|---|---|
| UHC-01 | **Módulo y llegada.** `usuarios_hc` al final de `Componente::MODULOS`; `InicioController` deja llegar a Lectura a `documentos` y `usuarios_hc`; `routes/usuarios-hc.php` con solo la lista (página vacía) y su carga en `bootstrap/app.php` con `"can:modulo,'usuarios_hc'"`. **Sin migración ni seeder de encendido** (PA-29). Ajustar las pruebas de DOC-25: quitar `AccesoPorComponenteTest.php:45` (la 44 ya fija los cuatro de Calle; cambia lo que verifica, plan D2) y agregar `usuarioHc` a `ModulosPorComponenteTest::parametrosEn()` y `UsuarioHc::class` a su lista de Policies (esto último al llegar UHC-02) | CA-01 (llegada, rechazo de Calle), CA-35 | Backend | Edwar | 0,5 | — | PA-29 | 30/11 | Pendiente |
| UHC-02 | **Esquema y modelos.** Una migración, `2026_11_25_000001_crear_usuarios_hc` (antes que las de DOC-27), con `usuarios_hc`, `usuario_hc_identidades`, `usuario_hc_caracterizaciones`, `usuario_hc_respuestas`, `usuario_hc_cambios` (con `codigo_antes`/`codigo_despues`), `usuario_hc_fotos`, `catalogo_cie10` y `catalogo_paises` (vacíos) e índices únicos; modelos y relaciones (`componenteRegistro` sin `ComponenteScope`); `DependenciaScope` en `UsuarioHc`; `normalizarTexto` y `normalizarCodigo` (uno solo para documento y ficha, sin guion: «F-120» y «F120» dan `F120`; `numero_ficha` guarda lo escrito con sus guiones; plan D5); `saving` arma `nombre_completo` y las columnas normalizadas; accessor de edad; `UsuarioHcFactory`. **Primero esquema y modelo a `develop` el 26/11 a mediodía**, para que Edwar arranque UHC-04 | CA-12 (edad) | Backend | José | 1,25 | — | PA-03, PA-20, PA-28 (P-17 resuelta: sin columna de estado en las respuestas) | 30/11 | Pendiente |
| UHC-03 | **Listas cortas.** Bloque `usuarios_hc` en `config/repositorio.php` (opciones, `retiradas`, `excluyentes`); `UsuarioHc::CAMPOS`; un método que da las opciones de un campo para una ficha (vigentes más las retiradas que ya tiene, con su texto) | CA-18 (opciones) | Backend | José | 0,5 | UHC-02 | PA-05, PA-07 | 30/11 | Pendiente |
| UHC-04 | **Permisos y auditoría.** `UsuarioHcPolicy`: `before` con el módulo **y la dependencia de la ficha para todos los métodos**; `viewAny`, `view`, `create`, `verCaracterizacion`, `update`; `conocidaPor`/`editablePor` en el modelo; las cuatro acciones de `AccionAuditoria` (sin `CatalogoHcCargado`). Pruebas de la Policy: Calle sin módulo, Lectura sin caracterización, otro componente sin caracterización ni edición, líder y superadministrador sí, ficha de otra dependencia negada en cada método aunque sea el líder | CA-13 y CA-16 (Policy), CA-31 (Policy) | Backend | Edwar | 1 | UHC-02 | PA-02, PA-03, PA-11 | 30/11 | Pendiente |
| UHC-05 | **Búsqueda.** `scopeBuscar` (cada palabra en nombre o alias, documento por parte, ficha exacta, identidades anteriores con la regla de la ficha vigente, término vacío no filtra) y la marca «coincide con un dato anterior». Pruebas con tildes, mayúsculas, «ñ», signos, alias, documento escrito con puntos, ficha exacta (sin guion: «F120» y «f-120» encuentran «F-120») y reasignada; **la misma clase corrida también contra MySQL** (plan D5) | CA-03, CA-04 (búsqueda) | Backend | José | 1 | UHC-02 | PA-01, PA-21 a PA-23 | 30/11 | Pendiente |
| UHC-06 | **Lista.** `Livewire\UsuariosHc\Listado` (tabla Filament): columnas **sin foto**, búsqueda con el scope, filtro de componente (salto de scope por escrito), páginas de 25, búsqueda, filtros y página que se conservan, «Ver» y «Editar» con `->authorize()`; vista `usuarios-hc/index`; entrada en el menú para todos los roles (`sidebar.blade.php`, archivo compartido: avisar a Edwar) | CA-01 (menú), CA-02, CA-17 (sin fotos), CA-21 (lista) | Frontend | José | 0,75 | UHC-01, UHC-04, UHC-05 | PA-01, PA-17 | 1/12 | Pendiente |
| UHC-07 | **Crear, servidor.** `UsuarioHcController@create/store`; `GuardarUsuarioHcRequest`: documento obligatorio salvo «Sin documento», `regex:/^[0-9A-Z]{1,20}$/` sobre el texto en mayúsculas y recortado (**no** `alpha_num`), ficha `regex:/^[0-9A-Z-]{1,30}$/` sobre el texto en mayúsculas y recortado, más al menos una letra o número («---» se rechaza) (PA-20), unicidad en toda la dependencia sobre la ficha normalizada sin guion («F120» choca con «F-120»); `QueryException` del índice → mismo mensaje; componente de registro del contexto; auditoría; **`POST usuarios-hc/coincidencias` con `throttle`** (HTML parcial, solo identificación). Agrega sus rutas a la prueba de CA-31 | CA-05, CA-06, CA-07 (servidor), CA-08, CA-20 (al crear), CA-21 (mensajes), CA-28, CA-29, CA-31 (rutas) | Backend | José | 1 | UHC-03, UHC-04 | PA-19, PA-20, PA-21 | 2/12 | Pendiente |
| UHC-08 | **Crear, pantalla.** Formulario (parcial común con la corrección), número que se oculta con «Sin documento», aviso de coincidencias mientras se escribe (por `POST`). Sin el campo de foto si UHC-09 todavía no está. En claro y oscuro, en celular | CA-05, CA-07 (pantalla) | Frontend | José | 0,75 | UHC-07 | — | 4/12 | Pendiente |
| UHC-09 | **Fotos, servidor y formularios.** `App\Rules\LadoMaximoImagen` sacada de `EnvioPublicoController` (que pasa a usarla); recodificar con GD a JPEG bajo `function_exists` (sin GD se rechaza, P-18); `FichasUsuariosHc::cambiarFoto` y quitar (versiones, nada se borra, motivo, historial, revisión); `UsuarioHcFotoController@ver` con `no-store`, la CSP de `previsualizar` y auditoría cada vez; el campo en los formularios de crear y corregir (`x-campo-archivo` sin sello). Pruebas: formatos, más de 4096 px, JPEG con EXIF y GPS subido sin navegador, sin sesión, Calle, otra dependencia, no aparece en Documentos | CA-01 (foto), CA-17 (foto), CA-22, CA-31 (foto), CA-32 | Backend | José | 1 | UHC-07, UHC-10, UHC-12 | PA-10, PA-15, PA-17, P-18 | 11/12 (se queda, Edwar 2026-11-24; ver «Calendario») | Pendiente |
| UHC-10 | **Cámara sin sello.** Hoy `x-campo-archivo` sella aunque reciba `:sello="null"` (`campo-archivo.js:381`, `dataset.sello \|\| 'Evidencia'`). Cambiar: la vista no escribe `data-sello` si es `null`; sin él, no se estampa, el botón de cámara no pide ubicación y `reducirImagen` vuelve a codificar siempre (quita el EXIF en el navegador). Turnos, derivaciones y supervisión siguen igual. **La prueba automática solo revisa el HTML** (que no lleva `data-sello`); el JS se comprueba a mano en UHC-17. Archivo compartido: avisar a Edwar (`campo-archivo.blade.php`, `campo-archivo.js`) | CA-22 (sin sello, solo HTML) | Frontend | José | 0,5 | — | PA-15 | 30/11 | Pendiente |
| UHC-11 | **Ver la ficha e historial.** Ficha con identificación, foto (si hay), estado vital y componente de registro; botones a caracterización e historial solo con `@can('verCaracterizacion')`; ruta del historial con `can:verCaracterizacion` y `UsuarioHcConsultado`; horas con `->fechaHora()`; vacío = «No informado», edad = «Sin información». Agrega sus rutas a CA-31 | CA-12 (pantalla), CA-14 (se ve), CA-16, CA-17 (historial), CA-31 (rutas) | Backend + frontend | Edwar | 0,5 | UHC-04 | PA-11 | 11/12 (movida por Edwar 2026-11-24) | Pendiente |
| UHC-12 | **Corregir identificación, servidor.** Servicio `FichasUsuariosHc` (autorizar, revisión atómica, comparar, motivo, historial por campo, identidades anteriores, auditoría **solo con campos y motivo**); `edit/update`; **acepta el tipo de documento retirado que la ficha ya tenía**; estado vital y fecha de fallecimiento sin avisos; el componente de registro no cambia. La prueba de CA-30 abre además la pantalla de Auditoría con el registro (el formato anidado daba 500). Agrega sus rutas a CA-31 | CA-04 (de punta a punta), CA-13 (rutas y guardado), CA-14, CA-15, CA-18 (tipo de documento), CA-19, CA-20, CA-30, CA-31 (rutas), CA-37 (identificación) | Backend | José | 1,25 | UHC-07 | PA-04 | 3/12 | Pendiente |
| UHC-13 | **Corregir identificación, pantalla.** El parcial de UHC-08 con valores, motivo y el aviso de «otra persona cambió la ficha» | CA-14, CA-15 (pantalla) | Frontend | José | 0,5 | UHC-08, UHC-12 | PA-04 | 11/12 | Pendiente |
| UHC-14 | **Caracterización, una sola pantalla.** Ruta `…/caracterizacion` con `can:verCaracterizacion` y `UsuarioHcConsultado`; `Livewire\UsuariosHc\Caracterizacion` con `$fichaId` (busca con scope en cada petición) y formulario Filament desde `CAMPOS`, **deshabilitado si no puede `update`**; guardar con `->authorize('update')` y el servicio de UHC-12; selecciones que se limpian, varias, excluyentes, principal en medicina general, tiempo en calle en tres números, fecha no futura en Bogotá; motivo solo al cambiar un dato informado; historial con código y texto por opción que sale o entra; países y CIE-10 buscados en el servidor y **con el texto guardado** (`getOptionLabelUsing`, `getOptionLabelsUsing` en `Select::multiple()`); campos del catálogo ocultos si está vacío. Las pruebas crean los códigos del catálogo que necesitan (no dependen de UHC-15) | CA-09, CA-10, CA-11, CA-12 (fecha), CA-13 (guardar), CA-14, CA-15, CA-18, CA-30 (caracterización), CA-31 (Livewire), CA-33, CA-34, CA-37 | Backend + frontend | José | 2 | UHC-03, UHC-12 | PA-04, PA-06, PA-07, PA-08, PA-25, PA-28 | 15/12 | Pendiente |
| UHC-15 | **Catálogos CIE-10 y países, comando de carga única.** `php artisan catalogos-hc:cargar {cie10\|paises} {archivo}` (`App\Console\Commands\CargarCatalogoHc`), que Edwar corre en cada servidor con la ruta del archivo; **el archivo no entra al repositorio** (PA-27): ni `database/data/catalogos/` ni migración que cargue datos. Se niega si la tabla ya tiene filas; lee `.xlsx` o CSV con OpenSpout; columnas por contenido; **comprueba F200, 170 y la cantidad mínima antes de escribir**; texto oficial tal cual; tandas de 500 en una transacción; correcciones de nombres de países (P-15); al terminar, `Log::info` con el archivo, la cantidad de códigos y la fecha. `CatalogosHcTest` con CSV que escribe la prueba en un directorio temporal. **Estimación sujeta al archivo:** sigue pendiente su formato real; puede llegar `.xls` o HTML y habría que convertirlo. **No se escribe hasta tener el archivo en la mano** | CA-36 (habilita además en uso real los diagnósticos y la nacionalidad de CA-09 y CA-33, que se prueban sin él) | Backend | José | 0,75 (sujeta al archivo) | UHC-02, archivo de SISPRO | PA-06, PA-13, PA-27, P-15 | Recortada (Edwar, 2026-11-24): después del 15/12, cuando llegue el archivo | Pendiente · fuente elegida (investigación del 24/11); falta el archivo |
| UHC-16 | **Datos de demostración.** `DemoUsuariosHcSeeder` con fichas ficticias en Básica y Estabilización (nunca personas reales), alguna con identidad anterior y, si UHC-09 está, con foto de muestra | — (demostración, sin criterio) | Backend | José | 0,25 | UHC-12, UHC-14 | — | Recortada (Edwar, 2026-11-24): después del 15/12; mientras tanto, fichas a mano en pruebas | Pendiente |
| UHC-17 | **Verificación.** Suite completa; contra MySQL con una base aparte: crear e índices únicos (CA-28, CA-29, con «F-120» y «F120» chocando), lista, respuestas e historial; recorrido en el navegador con un usuario de cada rol en Básica, Estabilización y Calle, en celular; **a mano, la cámara sin sello ni pedido de ubicación** (UHC-10); encender el módulo desde «Componentes y nodos» en el servidor de pruebas; `memory_limit` de producción para GD | — (verificación; repite CA-03, CA-28 y CA-29 en MySQL) | Backend | Edwar | 0,75 | UHC-01 a UHC-14 | — | 15/12 (Edwar, con la verificación de DOC-27) | Pendiente |
| UHC-18 | **Documentación.** La lista del plan («Documentación»): RN-52 a RN-56; en `domain.md`, que el historial es el registro de verdad y así se cumple «nada se borra» (P-17, resuelta); RN-49, RN-04, RN-06, RN-13, RN-38; «Parámetros del negocio»; glosario; constitución V si PA-07, PA-09 o PA-18 lo piden (texto propuesto a Edwar); `docs/FILAMENT.md`; `CHANGELOG.md` (y la línea de cada catálogo cuando se cargue); referencia de SISPRO en fuentes (sin el archivo); estado de la spec y `README`. **El manual, después del 15/12** | — (documentación, sin criterio) | Frontend (docs) | José | 0,25 (0,5 con el manual) | UHC-14 | — | 16/12 (mañana); el manual, después del 15/12 (recorte 5, Edwar 2026-11-24) | Pendiente |

**Suma con DOC-26 sola: 14,75 días-persona** (Edwar 1,5; José 13,25). **Tras los recortes y el
reparto del 2026-11-24: 13,5 hasta el 15/12** (Edwar 2,75; José 10,75), más 1,25 después del
15/12 (UHC-15, UHC-16 y el manual). La cuenta, en «Calendario». La estimación anterior,
10,5, no contaba los cambios de la v0.3 (GD, CSP, Policy con dependencia, texto guardado en los
selectores, historial con códigos, JS del sello) y se quedaba corta en la caracterización (≈ 2
días, no 1).

## Con DOC-27 (esperan a las estadías)

| Tarea | Qué | CA | Tipo | Quién | Est. | Depende | PA | Estado |
|---|---|---|---|---|---|---|---|---|
| UHC-19 | `conocidaPor` y `editablePor` con estadías válidas (activa o egresada; anulada no cuenta) | CA-24, CA-25 | Backend | Edwar (dentro de DOC-27) | 0,5 | DOC-27 (estadías), UHC-04 | PA-03 | Espera DOC-27 |
| UHC-20 | Lista: «componente o estado» (estadía activa o «Inactivo») y filtro de estado (Todos, Activos, Inactivos) | CA-23 | Frontend | José (días de DOC-27) | 0,5 | DOC-27, UHC-06 | — | Espera DOC-27 |
| UHC-21 | Aviso por la campana al marcar «Fallecida» con estadía activa, a la Coordinación de ese componente y al líder (ACC-10, hecha); tipo nuevo en la campana; no egresa | CA-26 | Backend | Edwar (dentro de DOC-27) | 0,25 | DOC-27, UHC-12 | PA-16 | Espera DOC-27 |
| UHC-22 | Acción «Registrar ingreso» en la lista, solo para quien puede y para personas vivas sin estadía activa | CA-27 | Frontend | José (días de DOC-27) | 0,25 | DOC-27, UHC-06 | — | Espera DOC-27 |

**1,5 días-persona más**, contados dentro de DOC-27, que es la que las habilita.

## Criterios y pruebas

Cada criterio de la v1.0 con su tarea y su prueba. Donde hay varias tareas, la primera es la
dueña de la prueba principal y las demás agregan casos.

| CA | Tarea | Prueba |
|---|---|---|
| CA-01 | UHC-01, UHC-06, UHC-09 | `test_doc26_ca01_basica_y_estabilizacion_llegan_con_cualquier_rol_y_calle_recibe_rechazo` (además, `ModulosPorComponenteTest` recorre cada ruta `GET`) · `test_doc26_ca01_el_menu_muestra_usuarios_hc_segun_el_componente` · `test_doc26_ca01_calle_no_abre_la_foto` |
| CA-02 | UHC-06 | `test_doc26_ca02_la_lista_incluye_fichas_del_otro_componente_y_nunca_de_otra_dependencia` |
| CA-03 | UHC-05 (+ MySQL) | `test_doc26_ca03_busca_por_palabras_documento_y_ficha_normalizados` |
| CA-04 | UHC-05, UHC-12 | `test_doc26_ca04_un_dato_anterior_lleva_a_la_ficha_vigente` · `test_doc26_ca04_corregir_el_nombre_deja_buscable_el_anterior` |
| CA-05 | UHC-07 | `test_doc26_ca05_se_crea_sin_documento_con_nombre_provisional` |
| CA-06 | UHC-07 | `test_doc26_ca06_no_se_repite_documento_ni_ficha_en_la_dependencia` |
| CA-07 | UHC-07 | `test_doc26_ca07_nombre_parecido_avisa_con_solo_identificacion_y_deja_crear` |
| CA-08 | UHC-07 | `test_doc26_ca08_documento_con_letras_y_ceros_se_guarda_tal_cual` |
| CA-09 | UHC-14 | `test_doc26_ca09_la_caracterizacion_se_guarda_vacia_o_a_medias` |
| CA-10 | UHC-14 | `test_doc26_ca10_opciones_excluyentes_no_se_combinan` |
| CA-11 | UHC-14 | `test_doc26_ca11_tiempo_en_calle_por_separado_sin_rellenar` |
| CA-12 | UHC-02, UHC-14, UHC-11 | `test_doc26_ca12_edad_en_anios_cumplidos_o_sin_informacion` · `test_doc26_ca12_fecha_de_nacimiento_futura_se_rechaza` |
| CA-13 | UHC-04, UHC-12, UHC-14 | `test_doc26_ca13_solo_el_componente_de_registro_el_lider_y_el_superadministrador_editan` · `test_doc26_ca13_otro_componente_recibe_rechazo_al_abrir_y_al_guardar` |
| CA-14 | UHC-12, UHC-11, UHC-14 | `test_doc26_ca14_cada_correccion_deja_historial_y_sin_motivo_no_se_guarda` |
| CA-15 | UHC-12, UHC-14 | `test_doc26_ca15_el_segundo_guardado_se_rechaza_con_aviso` |
| CA-16 | UHC-04, UHC-11 | `test_doc26_ca16_otro_componente_y_lectura_no_ven_caracterizacion_ni_historial` |
| CA-17 | UHC-11, UHC-09, UHC-06 | `test_doc26_ca17_abrir_caracterizacion_e_historial_queda_auditado_cada_vez` · `test_doc26_ca17_cada_apertura_de_la_foto_queda_auditada_y_sin_sesion_no_abre` · `test_doc26_ca17_la_lista_no_muestra_fotos` |
| CA-18 | UHC-03, UHC-12, UHC-14 | `test_doc26_ca18_opcion_retirada_no_se_ofrece_salvo_a_la_ficha_que_la_tiene` · `test_doc26_ca18_tipo_de_documento_retirado_se_conserva_al_corregir` |
| CA-19 | UHC-12 | `test_doc26_ca19_fallecida_sin_estadias_pide_motivo_y_no_avisa` |
| CA-20 | UHC-07, UHC-12 | `test_doc26_ca20_la_ficha_guarda_el_componente_de_registro_y_editar_no_lo_cambia` |
| CA-21 | UHC-06, UHC-07 | `test_doc26_ca21_lista_coincidencias_y_mensajes_solo_muestran_identificacion` |
| CA-22 | UHC-09, UHC-10 | `test_doc26_ca22_formatos_tamano_y_versiones_de_la_foto` · `test_doc26_ca22_el_campo_de_foto_va_sin_sello` (solo HTML) |
| CA-23 a CA-27 | UHC-19 a UHC-22 | Con DOC-27 |
| CA-28 | UHC-07 (+ MySQL en UHC-17) | `test_doc26_ca28_documento_solo_letras_y_numeros_ascii_de_1_a_20` |
| CA-29 | UHC-07 (+ MySQL en UHC-17) | `test_doc26_ca29_ficha_igual_sin_mayusculas_espacios_ni_guion` («a12» = « A12 »; «f-120», «F-120» y «F120» chocan; buscar «F120» encuentra «F-120»; se muestra «F-120»; «---», «F.120» y tildes se rechazan; también en MySQL, UHC-17) |
| CA-30 | UHC-12, UHC-14 | `test_doc26_ca30_la_auditoria_lleva_campos_y_motivo_sin_valores` (abre también la pantalla de Auditoría) |
| CA-31 | UHC-04, UHC-07, UHC-09, UHC-11, UHC-12, UHC-14 | `test_doc26_ca31_la_policy_niega_todo_sobre_una_ficha_de_otra_dependencia` · `test_doc26_ca31_ficha_de_otra_dependencia_da_no_encontrado` (cada tarea agrega sus rutas) |
| CA-32 | UHC-09 | `test_doc26_ca32_la_foto_se_guarda_sin_exif_y_se_sirve_sin_cache` |
| CA-33 | UHC-14 | `test_doc26_ca33_el_selector_muestra_el_texto_guardado_de_un_codigo_renombrado_o_deshabilitado` |
| CA-34 | UHC-14 | `test_doc26_ca34_cambiar_o_quitar_una_respuesta_deja_la_anterior_con_codigo_y_texto` |
| CA-35 | UHC-01 | `test_doc26_ca35_el_modulo_aparece_solo_cuando_el_superadministrador_lo_enciende` |
| CA-36 | UHC-15 (después del 15/12) | `test_doc26_ca36_el_comando_carga_una_vez_comprueba_y_deja_su_linea_en_el_log` (`CatalogosHcTest`) |
| CA-37 | UHC-14, UHC-12 | `test_doc26_ca37_llenar_un_campo_vacio_no_pide_motivo_y_cambiar_uno_informado_si` · en UHC-12, la identificación siempre pide motivo |

## Solo Edwar

| Qué | Para qué | Cuándo |
|---|---|---|
| ~~Aprobar la spec y responder P-17~~ | Hecho: spec v1.0 aprobada y P-17 resuelta (2026-11-24) | — |
| Confirmar P-19 (dos días antes del 1/12) | Si son menos, UHC-04 pasa a José | 25/11 |
| Bajar el Excel de SISPRO con un correo institucional y guardarlo **fuera** del repositorio | UHC-15 (sigue pendiente ver su formato real) | Cuando pueda; no bloquea el resto |
| Correr `php artisan catalogos-hc:cargar cie10 <archivo>` y `… paises <archivo>` en cada servidor, y anotar la carga en `CHANGELOG.md` | Diagnósticos y nacionalidad (después del 15/12) | Al desplegar UHC-15 |
| Encender Usuarios HC en Básica y Estabilización desde «Componentes y nodos» | Servidor de pruebas (después del 30/11) y producción (con DOC-27) | Al desplegar |
| Fusionar a `develop` el 30/11 y desplegar; producción junto con DOC-27 (PA-14) | DOC-27 arranca sobre esto | 30/11 |

## Orden y paralelo

```
(spec v1.0 aprobada, 24/11)
UHC-02 ─┬─ UHC-04 (Edwar) ─┐
        ├─ UHC-05 ─────────┼─ UHC-06
        └─ UHC-03 ─────────┴─ UHC-07 ─┬─ UHC-08 ─ UHC-13
UHC-01 (Edwar)                        ├─ UHC-12 ─┬─ UHC-13
UHC-10 (sin dependencias)             │          ├─ UHC-14 ─ UHC-16
UHC-04 ─ UHC-11                       │          └─ UHC-09 (con UHC-10)
UHC-02 + archivo de SISPRO ─ UHC-15 (después del 15/12)
UHC-14 ─ UHC-18 (sin manual) · todo ─ UHC-17 (Edwar)
DOC-27 ─ UHC-19, UHC-20, UHC-21, UHC-22
```

## Calendario

Días hábiles del 25/11 al 15/12: 25, 26, 27 y 30/11; 1, 2, 3, 4, 7, 9, 10, 11, 14 y 15/12 (el
8/12 es festivo) = **14 días**. José: 14 − ACC-15 (0,5) − DOC-27 (7, 9 y 10/12) = **10,5 días**
para DOC-26. Edwar: **2 días** antes del 1/12 (P-19), después en DOC-27.

| Fecha | José | Edwar |
|---|---|---|
| 25/11 | UHC-02 (1) | Aprueba la spec; UHC-01 (0,5) |
| 26/11 | UHC-02 (0,25: esquema y modelo a `develop` a mediodía), UHC-05 (0,75) | UHC-04 (desde la tarde) |
| 27/11 | UHC-05 (0,25: su corrida en MySQL), UHC-03 (0,5), UHC-10 (0,25) | UHC-04 |
| **30/11** | UHC-10 (0,25), UHC-07 (0,75) | **Fusiona a `develop`: UHC-01 a UHC-05 y UHC-10.** DOC-27 parte de ahí |
| 1/12 | UHC-07 (0,25), UHC-06 (0,75) | DOC-27 |
| 2/12 | UHC-12 (1) | DOC-27 |
| 3/12 | UHC-12 (0,25), UHC-08 (0,75) | DOC-27 |
| 4/12 | ACC-15 (0,5), UHC-09 (0,5) | DOC-27 |
| 7, 9 y 10/12 | DOC-27 (puede tomar UHC-20 y UHC-22) | DOC-27 (UHC-19, UHC-21) |
| 11/12 | UHC-09 (0,5), UHC-13 (0,5) | UHC-11 (0,5, movida desde el 3/12 para el punto de control de DOC-27); DOC-27 |
| 14/12 | UHC-14 (1) | DOC-27 |
| 15/12 | UHC-14 (1) | DOC-27; UHC-17 (0,75, verificación de DOC-26 con la de DOC-27) |
| 16/12, mañana | UHC-18 sin el manual (0,25) | Cierra UHC-17 si UHC-14 termina al final del 15/12 |

**Lo que sale el 30/11:** el módulo (encendible desde «Componentes y nodos»), el esquema completo
de DOC-26, las listas cortas, la Policy con sus pruebas, la búsqueda probada en SQLite y MySQL y
el campo de foto sin sello. Todavía no se crea ni se ve una ficha desde la pantalla: DOC-27
trabaja con la fábrica de fichas.

**Lo que no cabía:** José tiene 10,5 días y su parte sumaba 13,25. Faltaban **2,75 días**. Sin
recortes, DOC-26 completa (sin el catálogo, que depende del archivo) terminaba hacia el **18/12**.

**Recortes propuestos**, en este orden (los decidió Edwar, abajo). Los controles (rechazos,
auditoría, historial) no se recortan:

1. **Catálogos (UHC-15, −0,75).** PA-06 ya lo permite: los diagnósticos y la nacionalidad salen
   después, y el archivo ni siquiera está.
2. **Datos de demostración (UHC-16, −0,25).** Se crean fichas a mano en el servidor de pruebas.
3. **Fotos (UHC-09, −1).** La ficha sale sin foto; UHC-10 ya queda hecho. Es lo único recortado
   que la spec pone en lo que entrega DOC-26 sola (§8): Edwar decide.
4. **La corrida de UHC-17 contra MySQL la hace Edwar** junto con la verificación de DOC-27
   (−0,5 para José).
5. **El manual de usuario de UHC-18 después del 15/12**, antes de producción (−0,25).

**Decisión de Edwar (2026-11-24):** se aceptan los recortes **1, 2 y 5** (después del 15/12);
**las fotos (3) se quedan** y se entregan el 11/12. Edwar toma además **UHC-11 (0,5) y toda la
verificación UHC-17 (0,75)**, que cubre el recorte 4 y algo más.

**La cuenta, con UHC-09 dentro:**

| José | Días |
|---|---|
| Parte original | 13,25 |
| − UHC-15 (catálogos), UHC-16 (demostración), manual de UHC-18 | − 1,25 |
| − UHC-11 y UHC-17, a Edwar | − 1,25 |
| **Trabajo hasta el cierre** (UHC-02, 03, 05 a 10, 12 a 14 y UHC-18 sin manual; UHC-09 incluida, 1 día) | **10,75** |
| Disponible (14 − ACC-15 0,5 − DOC-27 3) | 10,5 |
| **Falta** | **0,25** |

Edwar: UHC-01 (0,5) + UHC-04 (1) + UHC-11 (0,5) + UHC-17 (0,75) = **2,75**.

Cierre el **15/12 o el 16/12 en la mañana, sin holgura**: cualquier imprevisto (un día de José
en otra cosa) lo lleva al 16 o 17/12. Como DOC-26 sale a producción con DOC-27 y la producción
espera además PA-09 y PA-18, unos días de más en DOC-26 no la retrasan si DOC-27 tampoco está
lista.
## Pendiente para salir a producción

- Respuestas a P-15, P-18 y P-19 del plan (tienen respuesta por defecto).
- PA-09 y PA-18 (jurídica): bloquean producción.
- El archivo de SISPRO y el comando corrido en producción, si los diagnósticos y la nacionalidad
  salen con esta versión.
- DOC-27 (PA-14).
- Encender el módulo en producción desde «Componentes y nodos» (Edwar).
- `CHANGELOG.md` con la sección de la versión.

## Candidatos

En el plan, «Candidatos»: recarga de los catálogos con el tope del 95 %, CIE-11, aviso por edad
o sexo del CIE-10, solo el capítulo F en psiquiatría, fotos en la lista, fotos anteriores,
orientación de las fotos en el servidor, búsqueda fonética.
