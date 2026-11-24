# Tareas: Usuarios HC (DOC-26)

> **Estado: Borrador, sujeto a la aprobación de la spec DOC-26. No se escribe código hasta que
> Edwar la apruebe.**

Prefijo `UHC`. Plan: [plan.md](plan.md). Pruebas en `tests/Feature/UsuariosHc/`, con nombres
`test_doc26_caNN_…`. Una tarea a la vez, cada una con la suite completa pasando.

José lleva DOC-26 de principio a fin (plan de trabajo). Edwar solo hace lo que únicamente él
puede: aprobar, desplegar y lo suyo de DOC-25 (ACC-07, ACC-08 para el servidor, ACC-10 para
UHC-21). Las tareas que tocan archivos que también toca Edwar dicen **«archivo compartido:
avisar a Edwar»** y cuáles; se entregan pequeñas a `develop` el mismo día.

La columna **PA** dice de qué pregunta abierta de la spec (o del plan, P-NN) depende la tarea; la
tarea se construye con la propuesta por defecto y el plan dice qué cambia si la respuesta es otra.

Estimación en días-persona.

## Con DOC-26 sola

| Tarea | Qué | CA | Tipo | Quién | Est. | Depende | PA | Estado |
|---|---|---|---|---|---|---|---|---|
| UHC-01 | **Módulo y llegada.** `usuarios_hc` al final de `Componente::MODULOS`; `MODULOS_DESDE_LECTURA`; `InicioController` la usa; `UsuariosHcModuloSeeder` + migración de datos (enciende el módulo en Básica y Estabilización, idempotente, `DB::table`); `DatabaseSeeder` lo llama; `routes/usuarios-hc.php` con solo la lista (página vacía) y su carga en `bootstrap/app.php` con `"can:modulo,'usuarios_hc'"`. Ajustar `AccesoPorComponenteTest` (Calle = `MODULOS_CALLE`) y `ModulosPorComponenteTest::parametrosEn()`. **Archivo compartido: avisar a Edwar** (`Componente`, `InicioController`, `bootstrap/app.php`, `DatabaseSeeder`, pruebas de DOC-25) | CA-01 (llegada y rechazo de Calle) | Backend | José | 0,5 | DOC-25 (ACC-01 a ACC-05, hechas) | P-01 | Pendiente |
| UHC-02 | **Esquema y modelos.** Migración de `usuarios_hc`, `usuario_hc_identidades`, `usuario_hc_caracterizaciones`, `usuario_hc_respuestas` (con `fuente`), `usuario_hc_cambios`, `usuario_hc_fotos` e índices únicos; modelos y relaciones (`componenteRegistro` sin `ComponenteScope`); `DependenciaScope` en `UsuarioHc`; `saving` arma `nombre_completo` y las columnas normalizadas (`normalizarTexto`, `normalizarCodigo`); accessor de edad; `UsuarioHcFactory` | CA-05, CA-08, CA-12 (edad), CA-20 (modelo) | Backend | José | 1 | — (puede ir junto a UHC-01) | PA-03, P-03 | Pendiente |
| UHC-03 | **Listas cortas.** Bloque `usuarios_hc` en `config/repositorio.php` (opciones, `retiradas`, `excluyentes`); `UsuarioHc::CAMPOS`; un método que da las opciones de un campo para una ficha (vigentes más las retiradas que ya tiene, con su texto). **Archivo compartido: avisar a Edwar** (`config/repositorio.php`) | CA-18 (opciones) | Backend | José | 0,5 | UHC-02 | PA-05, PA-07 | Pendiente |
| UHC-04 | **Permisos y auditoría.** `UsuarioHcPolicy` con `ExigeModulo` (`viewAny`, `view`, `create`, `verCaracterizacion`, `update`), `conocidaPor`/`editablePor` en el modelo; las cinco acciones de `AccionAuditoria`. Pruebas de la Policy: Calle sin módulo, Lectura sin caracterización, otro componente sin caracterización ni edición, líder y superadministrador sí. **Archivo compartido: avisar a Edwar** (`AccionAuditoria`) | CA-13 y CA-16 (Policy), CA-01 (Policy) | Backend | José | 0,5 | UHC-01, UHC-02 | PA-02, PA-03, PA-11 | Pendiente |
| UHC-05 | **Búsqueda.** `scopeBuscar` (palabras en nombre o alias, documento por parte, ficha exacta, identidades anteriores con la regla de la ficha vigente) y la marca «coincide con un dato anterior». Pruebas en SQLite con tildes, mayúsculas, «ñ», alias, documento con letras, ficha exacta, ficha reasignada | CA-03, CA-04 (búsqueda) | Backend | José | 0,5 | UHC-02 | PA-01, P-09, P-10 | Pendiente |
| UHC-06 | **Lista.** `Livewire\UsuariosHc\Listado` (tabla Filament): columnas, búsqueda con el scope, filtro de componente (salto de scope por escrito), páginas de 25, búsqueda, filtros y página que se conservan, acciones «Ver» y «Editar» con `authorize()`; vista `usuarios-hc/index`; entrada en el menú para todos los roles. Sin fotos todavía (las agrega UHC-09). **Archivo compartido: avisar a Edwar** (`sidebar.blade.php`) | CA-01 (menú), CA-02, CA-21 (lista) | Frontend | José | 0,75 | UHC-04, UHC-05 | PA-01 | Pendiente |
| UHC-07 | **Crear, servidor.** `UsuarioHcController@create/store`, `GuardarUsuarioHcRequest` (documento obligatorio salvo «Sin documento», solo letras y números, unicidad en toda la dependencia, ficha única), `QueryException` del índice → mismo mensaje, componente de registro del contexto, auditoría; ruta de coincidencias (HTML parcial, solo identificación) | CA-05, CA-06, CA-07 (servidor), CA-08, CA-20, CA-21 (mensajes) | Backend | José | 0,5 | UHC-03, UHC-04 | PA-01, P-02, P-03, P-08 | Pendiente |
| UHC-08 | **Crear, pantalla.** Formulario (parcial común con la corrección), número que se oculta con «Sin documento», aviso de coincidencias mientras se escribe, foto con `x-campo-archivo` sin sello. En claro y oscuro, en celular | CA-05, CA-07 | Frontend | José | 0,5 | UHC-07, UHC-10 | PA-15 | Pendiente |
| UHC-09 | **Fotos, servidor.** `FichasUsuariosHc::cambiarFoto` y quitar (versiones, nada se borra, historial, revisión); `App\Rules\LadoMaximoImagen` sacada de `EnvioPublicoController` (que pasa a usarla); `UsuarioHcFotoController` (ver auditada; miniatura firmada, ligada al usuario y sin auditoría propia); columna de miniaturas en la lista con un registro de auditoría por página. Pruebas: formatos, más de 4096 px (cabecera PNG hecha a mano), sin sesión, sin permiso, Calle, no aparece en Documentos, una página con fotos deja un registro | CA-17 (foto y página con fotos), CA-22, CA-01 (foto) | Backend | José | 0,75 | UHC-04, UHC-06 | PA-15, PA-17, P-04 | Pendiente |
| UHC-10 | **Cámara sin sello.** `x-campo-archivo` con `:sello="null"`: no estampa ni pide ubicación, solo reduce a 2560 px, y vuelve a codificar siempre para quitar el EXIF. Las fotos de turnos, derivaciones y supervisión siguen igual. **Archivo compartido: avisar a Edwar** (`campo-archivo.blade.php`, `campo-archivo.js`) | CA-22 (sin sello) | Frontend | José | 0,25 | — | PA-15, P-06 | Pendiente |
| UHC-11 | **Ver la ficha.** Identificación con foto, estado vital y componente de registro; botones a caracterización e historial solo si puede; rutas de caracterización e historial con `can:verCaracterizacion` y auditoría `UsuarioHcConsultado`; vacío = «No informado», edad = «Sin información» | CA-16, CA-17 (caracterización), CA-14 (se ve el historial), CA-12 (pantalla) | Backend + frontend | José | 0,75 | UHC-04, UHC-09 | PA-11, P-12 | Pendiente |
| UHC-12 | **Corregir identificación, servidor.** Servicio `FichasUsuariosHc` (autorizar, revisión atómica, comparar, motivo, historial por campo, identidades anteriores, auditoría); `edit/update`; estado vital y fecha de fallecimiento sin avisos; el componente de registro no cambia | CA-04 (de punta a punta), CA-13 (rutas y guardado), CA-14, CA-15, CA-19, CA-20 | Backend | José | 0,75 | UHC-07, UHC-09 | PA-04, P-04 | Pendiente |
| UHC-13 | **Corregir identificación, pantalla.** El parcial de UHC-08 con valores, campo de motivo, cambiar o quitar la foto y el aviso de «otra persona cambió la ficha» | CA-14, CA-15 | Frontend | José | 0,25 | UHC-12, UHC-08 | PA-04 | Pendiente |
| UHC-14 | **Caracterización.** `Livewire\UsuariosHc\Caracterizacion` con formulario Filament desde `CAMPOS` (selecciones que se limpian, varias, búsqueda de países y CIE-10 en el servidor, principal en medicina general, tiempo en calle en tres números, fechas); reglas (excluyentes, fecha no futura en Bogotá, principal); motivo solo al cambiar un dato informado; guarda con el servicio de UHC-12; abrir la edición queda auditado; campos del catálogo ocultos si el catálogo está vacío | CA-09, CA-10, CA-11, CA-12, CA-13 (caracterización), CA-14, CA-15, CA-18 | Backend + frontend | José | 1 | UHC-03, UHC-11, UHC-12 | PA-04, PA-06, PA-07, PA-08, P-05, P-07 | Pendiente |
| UHC-15 | **Catálogos CIE-10 y países.** Tablas, `App\Support\CatalogosHc` y comando `catalogos-hc:cargar` (Excel de SISPRO con OpenSpout; columnas por contenido; comprobación con F200, F524, F900, 170 antes de escribir; texto oficial tal cual; `upsert` en tandas; ausentes → deshabilitados; nunca borra; correcciones de nombres de países; auditoría `CatalogoHcCargado`); migración de datos que carga si el archivo está y no corre en pruebas; `CatalogosHcTest` con un archivo pequeño. **Dato pendiente:** los dos Excel de SISPRO (llegan por correo al exportar) | — (habilita CA-09 en diagnósticos y nacionalidad) | Backend | José | 0,75 | UHC-02 | PA-06, PA-13, P-13, P-14, P-15, P-16 | Pendiente · fuente elegida (investigación del 24/11); falta bajar los archivos |
| UHC-16 | **Datos de demostración.** `DemoUsuariosHcSeeder` con fichas ficticias en Básica y Estabilización (nunca personas reales), alguna con identidad anterior y alguna con foto de muestra | — | Backend | José | 0,25 | UHC-12, UHC-14 | — | Pendiente |
| UHC-17 | **Verificación.** Suite completa; las pruebas de búsqueda, crear (índices únicos) y lista contra MySQL/MariaDB con una base aparte (`DB_CONNECTION=mysql DB_DATABASE=documenta_pruebas`, sin tocar el `.env`); recorrido en el navegador con un usuario de cada rol en Básica, Estabilización y Calle; celular | CA-03 (en MySQL), todas | Backend | José | 0,5 | UHC-01 a UHC-15 | — | Pendiente |
| UHC-18 | **Documentación.** `docs/context/` (RN-52 a RN-56, RN-49, RN-04, RN-06, RN-13, parámetros, glosario), `docs/FILAMENT.md` (saltos de scope, tabla de pantallas), manual (Usuarios HC), `CHANGELOG.md`, fuentes de SISPRO, estado de la spec y `README` | — | Frontend (docs) | José | 0,5 | UHC-17 | — | Pendiente |

**Suma con DOC-26 sola: 10,5 días-persona.**

## Con DOC-27 (esperan a las estadías)

| Tarea | Qué | CA | Tipo | Quién | Est. | Depende | PA | Estado |
|---|---|---|---|---|---|---|---|---|
| UHC-19 | `conocidaPor` y `editablePor` con estadías válidas (activa o egresada; anulada no cuenta) | CA-24, CA-25 | Backend | José | 0,5 | DOC-27 (estadías) | PA-03 | Espera DOC-27 |
| UHC-20 | Lista: «componente o estado» (estadía activa o «Inactivo») y filtro de estado (Todos, Activos, Inactivos) | CA-23 | Frontend | José | 0,5 | DOC-27, UHC-06 | — | Espera DOC-27 |
| UHC-21 | Aviso por la campana al marcar «Fallecida» con estadía activa, a la Coordinación de ese componente y al líder; tipo nuevo en la campana; no egresa | CA-26 | Backend | José | 0,25 | DOC-27, **ACC-10 (Edwar)**, UHC-12 | PA-16 | Espera DOC-27 y ACC-10 |
| UHC-22 | Acción «Registrar ingreso» en la lista, solo para quien puede y para personas vivas sin estadía activa | CA-27 | Frontend | José | 0,25 | DOC-27, UHC-06 | — | Espera DOC-27 |

**Suma con DOC-27: 1,5 días-persona más** (pueden contarse dentro de DOC-27, que es quien las
habilita).

## Solo Edwar

| Qué | Para qué | Cuándo |
|---|---|---|
| Aprobar la spec (11 preguntas) y responder P-01 a P-16 del plan | Sin eso no se escribe código | Antes de UHC-01 |
| ACC-07 y ACC-08 (asignar componente a los usuarios) | Usuarios reales de Básica y Estabilización en el servidor de pruebas (UHC-17) | Antes de UHC-17 |
| ACC-10 (avisos por componente) | UHC-21 | Con DOC-27 |
| Fusionar a `develop` y desplegar; producción junto con DOC-27 (PA-14) | Servidor de pruebas y producción | Al cerrar UHC-17 |

## Orden y paralelo

```
(aprobación de Edwar)
UHC-01 + UHC-02 ─ UHC-04 ─┬─ UHC-05 ─ UHC-06 ─ UHC-09 ─ UHC-11
UHC-02 ─ UHC-03 ──────────┴─ UHC-07 ─ UHC-08 (con UHC-10)
UHC-07 + UHC-09 ─ UHC-12 ─┬─ UHC-13 (con UHC-08)
UHC-03 + UHC-11 + UHC-12 ─┴─ UHC-14 ─ UHC-16
UHC-02 ─ UHC-15 (cuando lleguen los Excel; independiente del resto)
UHC-10 (sin dependencias)
todo ─ UHC-17 ─ UHC-18
DOC-27 ─ UHC-19, UHC-20, UHC-22 ; DOC-27 + ACC-10 ─ UHC-21
```

- Una sola persona: el orden sugerido es 01 → 02 → 03 → 04 → 05 → 06 → 07 → 10 → 08 → 09 → 11 →
  12 → 13 → 14 → 15 → 16 → 17 → 18. UHC-15 puede adelantarse apenas lleguen los Excel.
- Archivos compartidos con Edwar: UHC-01, UHC-03, UHC-04, UHC-06, UHC-10 (y UHC-09 toca
  `EnvioPublicoController`, que no es de nadie en particular pero tiene pruebas de la vía
  pública). Conviene juntar UHC-01, UHC-03 y UHC-04 en una entrega temprana a `develop`, para
  que Edwar los vea antes de seguir con ACC-07 y ACC-09.

## Calendario

El plan de trabajo le dio a DOC-26 **7 a 8 días-persona**: «avanzada el 30/11, terminada el
10/12». Este plan suma **10,5** (DOC-26 sola) **+ 1,5** (con DOC-27) = **12**. **No cabe.**

Días de José desde la aprobación (si llega el 25/11): 25, 26, 27 y 30/11; 1, 2, 3, 4, 7, 9 y 10/12
= **11 días**, de los que salen además lo que le queda de DOC-25 (ACC-08 y el cierre de ACC-15,
unos 0,75) y la ayuda en DOC-27 que el plan de trabajo le pone el 7, 9 y 10/12.

Qué entra en cada fecha, en este orden y sin recortar controles (rechazos, auditoría, historial):

| Fecha | Qué | Días |
|---|---|---|
| Al 30/11 | UHC-01 a UHC-06: el módulo, el esquema, las listas, los permisos, la búsqueda y la lista. **Todavía no se crea ni se ve una ficha desde la pantalla** (el plan de trabajo pedía crear, buscar y ver con caracterización: no alcanza) | 3,75 |
| 1 al 4/12 | UHC-07, UHC-10, UHC-08, UHC-09, UHC-11, UHC-12 (crear, fotos, ver, corregir). El medio día que sobra es para ACC-08 y el cierre de ACC-15 | 3,5 |
| 7, 9 y 10/12 | UHC-13, UHC-14, UHC-17, UHC-18 (caracterización, verificación y documentación) | 2,25 |
| Después del 10/12 | UHC-15 (si los Excel no llegaron), UHC-16, y UHC-19 a UHC-22 con DOC-27 | 0,75 + 0,25 + 1,5 |

Así DOC-26 llega al 10/12 **sin diagnósticos ni nacionalidad** (lo que PA-06 ya prevé si el
catálogo no está), sin datos de demostración y **sin la ayuda de José en DOC-27**. Si José tiene
que ayudar en DOC-27 esos tres días, DOC-26 termina hacia el 15/12.

Cómo bajar días, de menor a mayor costo (lo decide Edwar):

1. **Diagnósticos y nacionalidad después** (UHC-15, −0,75): PA-06 ya lo permite.
2. **Sin fotos en la lista** (alternativa de PA-17, −0,25): la foto se ve al abrir la ficha.
3. **Sin datos de demostración** (UHC-16, −0,25): se crean fichas a mano en el servidor de pruebas.
4. **UHC-19 a UHC-22 dentro de la estimación de DOC-27**, que es la que las habilita.

Con 1 a 3: 9,25 días; sigue sin caber en 7 a 8. Como PA-14 propone que DOC-26 y DOC-27 vayan
juntas a producción, que DOC-26 termine unos días después del 10/12 no retrasa la producción si
DOC-27 tampoco está.

## Pendiente para salir a producción

- Aprobación de la spec y respuestas a P-01 a P-16 del plan.
- Los dos Excel de SISPRO en `database/data/catalogos/` (P-14) y su registro en `fuentes/`.
- ACC-07 y ACC-08 en producción (sin usuarios de Básica y Estabilización no hay a quién darle el
  módulo).
- DOC-27 (PA-14) y, para el aviso de «Fallecida», ACC-10.
- `CHANGELOG.md` con la sección de la versión.

## Candidatos

- C-01: ver las fotos anteriores de una persona (ya se guardan).
- C-02: miniaturas generadas en el servidor (hoy la lista baja la foto reducida entera).
- C-03: búsqueda por parecido fonético o de texto completo, si la lista crece o los nombres
  vienen mal escritos.
- C-04: CIE-11 y doble codificación antes de agosto de 2027 (P-16).
- C-05: aviso de edad o sexo del código CIE-10, si Edwar lo pide (P-13).
