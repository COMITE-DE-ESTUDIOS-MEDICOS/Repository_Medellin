# Plan: Seguridad CCTV (DOC-33)

Spec: [spec.md](spec.md) v1.0, aprobada por Edwar el 2026-11-27. Tareas: [tasks.md](tasks.md).

Meta: la tanda 1 en el servidor de pruebas hoy (27/11). Por eso el plan **sigue el patrón de
Derivaciones en clases propias** y llama a lo que ya existe; no extrae nada del código de
Derivaciones (está en producción y no se toca hoy).

## Decisiones

| # | Decisión | Por qué |
|---|---|---|
| D1 | **Tabla propia `casos_cctv`** y modelo `CasoCctv`, no columnas nuevas en `derivaciones` | Estados, permisos, tiempos y número distintos (sin meta, con cerrar/anular, CCTV-NNN aparte). Mezclarlos obligaría a `if` por tipo en todo Derivaciones |
| D2 | **No se extrae código común de Derivaciones.** Se llaman los servicios que ya existen: `IdentificadorColaborador`, `Marcador::estaEnTurno`, `AlmacenamientoDocumentos`, `Auditor`, `EnlaceTurno` (token, QR, imprimir, revocar), `<x-campos.cedula>`, `<x-campo-archivo camara sello=…>` (foto con sello, RN-13), `Xlsx::libro` / `Xlsx::miniatura`, los limitadores `caso-consulta`, `cedula-consulta`, `turno-marca`, `envio-formulario` | Extraer un trait de `Publico\DerivacionController` es barato, pero tocaría un flujo en producción el mismo día. Las piezas que se repetirían son de 5 a 10 líneas |
| D3 | **El enlace es un `EnlaceTurno` de tipo `seguridad_cctv`** en `/c/{token}` | Reutiliza token cifrado, QR, imprimir y revocar. `tipo` es `string(20)`: cabe |
| D4 | **Casilla = columna `responsable_cctv` en `dependencia_usuario`**, como `aprueba_novedades`. `User::puedeGestionarCctvEn($dep)` = Coordinación/Administración, o Edición con la casilla | Mismo modelo que RN-23; la casilla solo la lee `CasoCctvPolicy` (no da poder fuera) |
| D5 | **Imágenes solo para responsables y Coordinación/Administración (CA-19)**, en dos capas: (a) en la app, por una ruta propia `cctv/{caso}/archivo/{campo}` con `authorize('verImagenes')` y auditoría; (b) en el repositorio, la carpeta «Seguridad CCTV · <componente>» queda bajo el grupo de custodia «Responsables de Seguridad CCTV», que se sincroniza al marcar o desmarcar la casilla | La custodia ya existe y `CarpetaPolicy` ya aparta lo custodiado (Coordinación ve todo). Así Edición sin casilla tampoco lo abre por el repositorio |
| D6 | **Cerrar y anular son formularios en la ficha** (controlador), no acciones de la tabla | Se cierra después de revisar las evidencias, que están en la ficha. Se prueban por HTTP. La lista queda solo de consulta |
| D7 | **Lista con Filament** (`App\Livewire\SeguridadCctv\Casos`); registrar, corregir, ficha y enlace en **Blade** | La tabla con filtros y 25/50/100 es lo que Filament da gratis; los formularios necesitan `<x-campo-archivo>` (cámara con sello) y el número en grande, que ya existen en Blade |
| D8 | **El reporte se calcula en PHP** sobre una sola consulta plana (`estado`, `entidad`, `comuna`, `tiempo_respuesta_min`) del rango, sin `GROUP BY` | La mediana no existe en MySQL ni en SQLite y el volumen es de cientos al mes. Quita el riesgo de `ONLY_FULL_GROUP_BY`. `ponytail:` si pasa de decenas de miles por rango, agrupar en SQL |
| D9 | **Entidades como constante del modelo** (`CasoCctv::ENTIDADES`: `camaras`, `telegram_123`, `policia`, `espacio_publico`, `otras`) + `entidad_otra` | Como `Derivacion::REMITENTES`. En el reporte, «Otras» suma en una fila (el texto se ve en la ficha y el Excel) |
| D10 | **El módulo se enciende en Calle por pantalla** (superadministrador, «Componentes y nodos» → Módulos), no por migración | Encender es dato (RN-49, DOC-25 D9); una migración tendría que buscar «calle» por slug |
| D11 | **El Excel lleva las fotos solo para quien puede verlas** (`verImagenes`); para Edición sin casilla, las celdas de imagen van vacías | Si no, el Excel sería la puerta trasera de CA-19 |
| D12 | **No hay `SoftDeletes`**: anular es un estado (`anulado`) con motivo | «Nada se borra del negocio»; la spec no pide borrar |

## Base de datos (una migración, CCTV-01)

`2026_11_27_000001_crear_casos_cctv.php`:

- `dependencia_usuario.responsable_cctv` boolean, default false, después de `aprueba_novedades`.
- `casos_cctv`:

| Columna | Tipo | Para |
|---|---|---|
| `id`, `dependencia_id`, `componente_id` | FK | RN-01 (`DependenciaScope` + `DelComponente`) |
| `numero` | unsignedInteger | CCTV-NNN; `unique(dependencia_id, numero)` |
| `clave_formulario` | uuid, unique | Doble toque (CA-06) |
| `estado` | string(10), índice con dependencia y componente | `abierto` · `atendido` · `cerrado` · `anulado` |
| `entidad`, `entidad_otra` | string(20), string(255) null | D9 |
| `requerido_at` | datetime, índice | Fecha y hora del requerimiento (un solo campo) |
| `colaborador_asignado_id` | FK colaboradores | A quién se le pasó la novedad |
| `nodo_id` | FK nodos | Nodo que atiende |
| `descripcion` text, `ubicacion` string(500), `comuna` unsignedTinyInteger | | `config('repositorio.comunas')` |
| `soporte_documento_id`, `evidencia1..3_documento_id` | FK documentos null | Archivos como documentos (RN-06) |
| `creado_por`, `actualizado_por` | FK users | |
| `colaborador_id`, `cedula`, `nombre`, `cargo`, `enlace_turno_id` | null | Quién lo atendió (copia, como en derivaciones) |
| `atendido_at` datetime, `tiempo_respuesta_min` unsignedInteger, `justificacion` text | null | Tiempo fijo al atender; se recalcula si se corrige `requerido_at` |
| `cerrado_por`, `cerrado_at` | null | |
| `anulado_por`, `anulado_at`, `motivo_anulacion` | null | |
| `timestamps` | | |

## Piezas y archivos

### Base (CCTV-01, backend, va primero y sola)

| Archivo | Qué |
|---|---|
| migración | La de arriba |
| `app/Models/CasoCctv.php` | `#[ScopedBy(DependenciaScope)]`, `DelComponente`. Constantes `ENTIDADES`, estados. `codigo()` / `codigoDe()` («CCTV-007»), `entidadLegible()`, `nombreComuna()`, `tiempoLegible()` (llama a `Derivacion::duracion()`), `minutosEntre(requerido, atendido)`, relaciones, `archivos()`. `static carpetaPara(Componente)`: `firstOrCreate` del grupo de custodia «Responsables de Seguridad CCTV» de la dependencia y de la carpeta «Seguridad CCTV · <componente>» con ese grupo (D5); la usan registrar y atender |
| `database/factories/CasoCctvFactory.php` | Estados abierto/atendido para las pruebas |
| `app/Policies/CasoCctvPolicy.php` | `ExigeModulo`, `MODULO = 'seguridad_cctv'`. `viewAny`/`view`: Edición+. `create`, `update` (abierto o atendido), `compartirEnlace`, `verImagenes`: `puedeGestionarCctvEn`. `cerrar`: eso + atendido. `anular`: `puedeGestionarEn` + no anulado |
| `app/Models/User.php`, `app/Models/Dependencia.php` | `responsable_cctv` en `withPivot`; `User::puedeGestionarCctvEn()` (copia de la forma de `puedeAprobarNovedadesEn`) |
| `app/Models/Componente.php` | `MODULOS['seguridad_cctv'] = ['Seguridad CCTV', 'admin.turnos.cctv.index']`, después de `derivaciones` |
| `app/Models/EnlaceTurno.php` | `TIPOS['seguridad_cctv']`, `esSeguridadCctv()`, `url()` → `cctv.enlace`, relación `casosCctv()` |
| `app/Enums/AccionAuditoria.php` | `CasoCctvRegistrado`, `CasoCctvCorregido`, `CasoCctvConsultado`, `CasoCctvAtendido`, `CasoCctvCerrado`, `CasoCctvAnulado`, `CasoCctvArchivoAbierto`, con su etiqueta |
| `routes/turnos-admin.php` | Un bloque `Route::middleware("can:modulo,'seguridad_cctv'")` con **todas** las rutas admin (nombres `admin.turnos.cctv.*`): `index` (vista de la tabla, `can:viewAny`), `create`, `store`, `show`, `edit`, `update`, `cerrar`, `anular`, `archivo`, `reporte`, `exportar`. Parámetro `{caso}` (`->parameters`) |
| `routes/turnos-publico.php` | `c/{token}` (GET `cctv.enlace`, `throttle:envio-formulario`), `c/{token}/cedula` (`cedula-consulta`), `c/{token}/caso` (POST, `caso-consulta`), `c/{token}/soporte/{caso}` (`signed`), POST `c/{token}` (`turno-marca` + `caso-consulta`) |

Las rutas se escriben todas aquí para que ninguna otra tarea toque `routes/`. Apuntan a
controladores que crean las tareas siguientes; hasta entonces esas rutas no responden (no
se corre `route:cache` en local).

### Controladores y vistas (en paralelo después de la base)

| Tarea | Archivos propios |
|---|---|
| CCTV-02 Casilla | `Admin\UsuarioController` (store/update: guarda `responsable_cctv`, audita el cambio con antes y después en `UsuarioActualizado`, sincroniza el grupo de custodia), `admin/usuarios/_formulario.blade.php` (casilla junto a «Aprueba novedades») |
| CCTV-03 Registrar y corregir | `Admin\SeguridadCctv\CasoController` (`create`, `store`, `edit`, `update`), vistas `admin/seguridad-cctv/{create,edit,_campos}.blade.php`. `store` copia el patrón de `CasoDerivacionController::store`: clave de un solo uso, `lockForUpdate()->max('numero')+1` por dependencia, reintento ante `UniqueConstraintViolationException`, borrado del archivo si falla. Sin aviso de duplicado (la spec no lo pide) |
| CCTV-04 Ficha | `Admin\SeguridadCctv\FichaController` (`show`, `cerrar`, `anular`, `archivo`), vista `admin/seguridad-cctv/show.blade.php`. `archivo` sirve `soporte`/`evidencia1..3` en línea con `no-store` tras `authorize('verImagenes')` y audita |
| CCTV-05 Lista | `app/Livewire/SeguridadCctv/Casos.php` (partir de `Turnos/PuntosCriticos.php`), `admin/seguridad-cctv/index.blade.php` (con los `@push` de Filament), botones «Nuevo caso» (`@can('create')`) y «Enlace para atender» (`@can('compartirEnlace')` → `admin.turnos.enlaces.create?tipo=seguridad_cctv`) |
| CCTV-06 Enlace público | `Publico\SeguridadCctvController` (`mostrar`, `cedula`, `caso`, `soporte`, `atender`), vista `publico/seguridad-cctv.blade.php` (partir de `publico/derivacion.blade.php`: cédula, número con «CCTV-» fijo, caso, evidencias #1 requerida y #2-#3 opcionales con `sello="Seguridad CCTV"`, justificación) |
| CCTV-07 Crear y revocar el enlace | `EnlaceTurnoController` (`store` y `revocar`: si el tipo es `seguridad_cctv`, además `authorize('compartirEnlace', CasoCctv::class)`), `enlaces/create.blade.php` (la opción solo si `@can('compartirEnlace', CasoCctv::class)`), `enlaces/index.blade.php` (contador) |
| CCTV-08 Menú | `components/sidebar.blade.php`: `$conSeguridadCctv` y la entrada debajo de Derivaciones con `$puedeEditar` |
| CCTV-11/12 Reporte y Excel | `Admin\SeguridadCctv\ReporteController` (`index`, `exportar`), `admin/seguridad-cctv/reporte.blade.php`, botón «Reporte» en `index.blade.php` |

### La vía pública (CCTV-06): misma excepción RN-48

- `enlaceVigente()`: `EnlaceTurno::porToken`, vigente, `esSeguridadCctv()`, componente activo
  **y `$enlace->componente->tieneModulo('seguridad_cctv')`**. Ojo: sin sesión
  `ContextoDependencia::tieneModulo()` devuelve `true`; aquí se pregunta al componente.
- `cedula`: igual que derivaciones (saludo recortado; fuera de turno, el aviso).
- `caso` (POST): siempre las dos búsquedas (colaborador en turno y caso abierto del enlace),
  auditoría `CasoCctvConsultado` con cédula, número e IP, el mismo 422 «No hay un caso abierto
  con ese número» para todo lo demás. Si hay caso, responde **solo** `codigo`, `descripcion`,
  `ubicacion`, `comuna` y `soporte` (`url` firmada 10 min). Nada de entidad, creador,
  asignado ni ids.
- `soporte`: `signed`, vuelve a buscar el caso abierto del enlace por id, `Cache-Control:
  no-store, private`, `nosniff`.
- `atender`: revalida enlace, cédula activa y en turno, caso abierto; valida
  `evidencia1` requerida, `evidencia2`/`evidencia3` `nullable`, `justificacion` nullable;
  transacción con `lockForUpdate`; guarda evidencias en `CasoCctv::carpetaPara()`;
  `atendido_at = now()`, `tiempo_respuesta_min`; borra los archivos si algo falla; audita
  `CasoCctvAtendido`. El segundo envío: «Este caso ya fue atendido» (como en derivaciones).

## Permisos (todo en `CasoCctvPolicy`)

| Habilidad | Regla |
|---|---|
| `before` | `ExigeModulo` (`seguridad_cctv`): sin el módulo, nadie, ni el superadministrador |
| `viewAny`, `view` | `puedeEditarEn` (Lectura no entra) |
| `create`, `compartirEnlace`, `verImagenes` | `puedeGestionarCctvEn` |
| `update` | lo anterior + estado `abierto` o `atendido` |
| `cerrar` | lo anterior + estado `atendido` |
| `anular` | `puedeGestionarEn` + estado distinto de `anulado` |

El aislamiento por componente lo dan los scopes; en Filament cada enlace de la tabla lleva su
`authorize` (no hay acciones que cambien datos en la tabla).

## Auditoría

`Auditor::registrar` en: registrar, corregir (antes y después de cada campo cambiado, y el
motivo si cambia `requerido_at`), consultar desde el enlace (encontrado o no), atender,
cerrar, anular (motivo), abrir un archivo, marcar o desmarcar la casilla.

## Configuración

Nada cambia en `config/repositorio.php` ni en `Componente::REGLAS`. Se reutilizan
`repositorio.comunas`, `repositorio.formatos.imagenes`, `repositorio.tamano_maximo_kb`.

## Riesgos

| Riesgo | Qué se hace |
|---|---|
| Archivos compartidos con José: `sidebar.blade.php` (UHC-06) y `Componente::MODULOS` | Una línea cada uno; CCTV-08 y CCTV-01 avisan a José antes de subir y resuelven el choque al fusionar |
| Agregar un módulo cambia `ComponenteFactory` (enciende todos): pruebas que cuentan módulos o entradas del menú pueden romperse | CCTV-01 corre toda la suite y ajusta esas pruebas |
| MySQL frente a SQLite: `unique(dependencia_id, numero)` + `lockForUpdate` (dos casos a la vez, dos atenciones a la vez) y el filtro de rango sobre `requerido_at` | CCTV-10 lo prueba contra MySQL local. El reporte no usa `GROUP BY` (D8), pero su consulta y la del Excel también se corren en MySQL (CCTV-11/12) |
| Vía pública sin sesión: `tieneModulo()` responde `true` | `enlaceVigente()` pregunta al componente del enlace (CA-01 en la prueba de la vía pública) |
| El grupo de custodia también se edita en «Grupos de custodia» y puede desalinearse de la casilla | La casilla manda en el módulo; la custodia solo protege el repositorio. Se dice en el manual. `ponytail:` si molesta, ocultar ese grupo de la pantalla de custodia |
| El limitador `caso-consulta` lo comparten `/d` y `/c` (misma clave por cédula) | Aceptado: el cupo combinado es más estricto, no más laxo |
| Las rutas existen antes que sus controladores (entre CCTV-01 y el resto) | Mismo día; no se despliega hasta que pase CCTV-10 |
| Retención de imágenes de cámaras (PA-10, Ley 1581) | Sin cambio: queda con el plazo de las fotos de turnos |
