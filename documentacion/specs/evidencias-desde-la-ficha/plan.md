# Plan: Cargar evidencias desde la ficha (DOC-34)

| | |
|---|---|
| Spec | `spec.md` v1.0, aprobada por Edwar el 2026-11-27 |
| Prefijo de tareas | `EVF` |
| Redactó | Claude (arquitecto) |

## Resumen

Una ruta `POST` por módulo, un controlador nuevo para las dos, una habilidad nueva en cada
Policy y una tarjeta Blade que usan las dos fichas. **Sin migraciones, sin columnas nuevas, sin
cambios en `config/repositorio.php` ni en `Componente::REGLAS`.** El enlace público no se toca.

## Lo que ya existe y se reutiliza

| Pieza | Dónde | Uso aquí |
|---|---|---|
| Guardado de un archivo como versión | `AlmacenamientoDocumentos::guardarVersion()` | Igual que el enlace; ya pone `subido_por = auth()->id()` |
| Carpeta de CCTV con custodia | `CasoCctv::carpetaPara(Componente)` | Tal cual |
| Carpeta «Derivaciones · <componente>» | `firstOrCreate` copiado en 3 controladores | Se agrega `Derivacion::carpetaPara(Componente)` con la misma firma que la de CCTV (ver Decisiones) |
| Reglas de imagen | `['image', 'mimetypes:'.config('repositorio.formatos.imagenes.mimetypes'), 'max:'.config('repositorio.tamano_maximo_kb')]` | Las mismas (25 600 KB) |
| Campo de foto con sello | `<x-campo-archivo camara formatos="imagenes" sello="…">` | Tal cual; `nombre="evidencia{n}"` para que cada tarjeta tenga su `id` |
| Bloqueo de dos envíos | patrón `DB::transaction` + `lockForUpdate` + borrar `$rutas` en el `catch` de `Publico\*Controller::atender` | El mismo patrón |
| Lo que muestran ficha, Excel y ZIP | `versionActual` en `DerivacionController::fotos/excel`, `ReporteController` y `FichaController::archivo` de CCTV | Ya sale la versión vigente: CA-11 solo necesita prueba |
| Ver el documento | `DocumentoPolicy::view` (módulo Documentos + custodia de la carpeta) | `@can('view', $documento)` para «Abrir en el repositorio» |

**Por qué no se extrae `guardarEvidencia` de los controladores públicos:** son métodos
`protected` de 15 líneas que nombran el documento con quien atendió y no ponen `creado_por`.
Moverlos obliga a tocar el enlace público para ganar poco; el controlador nuevo hace lo suyo en
un método privado (crear `Documento` + `guardarVersion`). Las tres copias del `firstOrCreate` de
Derivaciones quedan como están (candidato a unificar con `Derivacion::carpetaPara` cuando se
toque ese código, sin cambiar comportamiento).

## Confirmación: no hace falta columna nueva

- `documentos.creado_por`: **nulo** en todas las evidencias de hoy (enlace público, carga única
  de DOC-24 y soporte de la oficina no lo llenan; se verificó en `Publico\DerivacionController`,
  `Publico\SeguridadCctvController`, `CargaMasivaCasosController` y `CasoDerivacionController`).
  La carga desde la ficha lo llena con el usuario. Entonces:
  **«Cargada desde la ficha por `creador->name` el `created_at->fechaHora()`»** se muestra si
  `creado_por` no es nulo.
- `documento_versiones.subido_por` y `created_at`: quién y cuándo de cada versión.
  **«Versión N · cambiada el `versionActual->created_at->fechaHora()` por `versionActual->autor->name`»**
  se muestra si `versionActual->numero > 1`.

## Archivos que se tocan

### Backend

| Archivo | Cambio |
|---|---|
| `app/Models/Derivacion.php` | `public static function carpetaPara(Componente $componente): Carpeta` (el mismo `firstOrCreate` sin custodia de los otros controladores) |
| `app/Policies/DerivacionPolicy.php` | `cargarEvidencia(User, Derivacion)`: `$this->view(...)` (Edición en adelante; el superadmin entra por `rolEn`) **y** `! $derivacion->estaPendiente()`. Eliminada no llega: el binding excluye `SoftDeletes` (404). **`corregirHoras` no se toca** |
| `app/Policies/CasoCctvPolicy.php` | `cargarEvidencia(User, CasoCctv)`: `verImagenes(...)` **y** estado `ATENDIDO` o `CERRADO` |
| `app/Http/Controllers/Admin/EvidenciaFichaController.php` (nuevo) | `derivacion(Request, Derivacion, int $n)` y `cctv(Request, CasoCctv, int $n)`: `Gate::authorize('cargarEvidencia', $caso)` y llaman a un `cargar()` privado común (ver Flujo) |
| `routes/turnos-admin.php` | En el grupo de derivaciones: `POST derivaciones/{derivacion}/evidencias/{n}` → `derivaciones.evidencia`. En el de CCTV: `POST cctv/{caso}/evidencias/{n}` → `cctv.evidencia`. Ambas `->whereIn('n', ['1','2','3'])` y `throttle:30,1` |
| `app/Enums/AccionAuditoria.php` | `EvidenciaCargadaDesdeFicha = 'caso.evidencia_ficha'` con etiqueta «Cargó una evidencia desde la ficha» (una sola para los dos módulos; el `auditable` dice cuál) |

### Flujo de `cargar($caso, $n)` (el mismo para los dos modelos)

1. Validar `evidencia{$n}`: `required` + reglas de imagen. Mensajes: «La evidencia debe ser una
   imagen (JPG, PNG…)» (`image`, `mimetypes`) y «La evidencia no puede pasar de 25 MB» (`max`).
2. `DB::transaction`: releer el caso con `$caso::whereKey($caso->id)->lockForUpdate()->firstOrFail()`;
   si `evidencia{$n}_documento_id` no es nulo → `ValidationException` en `evidencia{$n}` con
   «Esta evidencia ya tiene un archivo; no se reemplaza». (El estado ya lo validó la Policy antes
   de la transacción; es suficiente porque ningún estado vuelve a Pendiente/Abierto y la
   anulación de CCTV no toca evidencias.)
3. `Documento::create([... 'carpeta_id' => $caso::carpetaPara($caso->componente)->id,
   'nombre' => "Caso {codigo} · Evidencia {n} · {nombre de quien atendió}",
   'fecha_documento' => today, 'creado_por' => auth, 'actualizado_por' => auth])` y
   `guardarVersion($documento, $archivo, "Evidencia {n} del caso {codigo} (cargada desde la ficha)")`.
4. Solo la columna: `$caso->forceFill(["evidencia{$n}_documento_id" => $documento->id])->save()`.
   Nada más del caso cambia (estado, horas, tiempo, `cumplio_requerimiento`, quién atendió/cerró,
   `actualizado_por`).
5. `catch`: borrar el archivo físico (`$this->almacenamiento->eliminar($ruta)`) y relanzar, como
   en el enlace.
6. Después de la transacción: `Auditor::registrar(EvidenciaCargadaDesdeFicha, $caso,
   "Cargó la evidencia {n} de {codigo} desde la ficha", ['evidencia' => $n, 'documento' => $documento->id])`
   (IP y navegador los pone el `Auditor`).
7. `redirect()->route('…show', $caso)->with('exito', "Evidencia {n} cargada.")`.

### Frontend

| Archivo | Cambio |
|---|---|
| `resources/views/components/tarjeta-evidencia.blade.php` (nuevo) | Props: `nombre`, `documento`, `n` (null para el soporte), `accion` (URL del POST o null), `ver` (URL de la imagen en CCTV o null), `sello`. Con archivo: nombre; «Ver imagen» si hay `ver`; «Abrir en el repositorio» dentro de `@can('view', $documento)`; «Cargada desde la ficha por…» y «Versión N · cambiada el… por…». Sin archivo: «Sin archivo» y, si hay `accion`, `<details>` «Cargar evidencia» con `<form enctype>` + `<x-campo-archivo nombre="evidencia{n}" requerido camara formatos="imagenes" sello=…>` + «Guardar»; abierto si `$errors->has("evidencia{n}")` |
| `resources/views/admin/turnos/derivaciones/show.blade.php` | El `@foreach` de tarjetas usa el componente. `accion` solo si `@can('cargarEvidencia')` y es evidencia (no soporte). Hoy el enlace al documento se muestra sin `@can`: pasa a respetar `view` |
| `app/Http/Controllers/Admin/Turnos/DerivacionController.php` (`show`) | Cargar también `soporte.versionActual.autor`, `evidenciaN.versionActual.autor`, `evidenciaN.creador` para no hacer N+1. Nada más |
| `resources/views/admin/seguridad-cctv/show.blade.php` | La sección «Imágenes» (sigue dentro de `@can('verImagenes')`) muestra las 4 tarjetas con el componente; `ver` = `route('admin.turnos.cctv.archivo', …)`; `accion` si `@can('cargarEvidencia')` |
| `app/Http/Controllers/Admin/SeguridadCctv/FichaController.php` (`show`) | `'archivos' => $caso->archivos()` sin `array_filter` (para las vacías) y eager load como arriba |
| `resources/views/manual/` | Una sección corta: completar evidencias desde la ficha y cambiar una existente por el repositorio |

### Documentación

| Archivo | Cambio |
|---|---|
| `documentacion/docs/context/domain.md` | RN-32 y RN-67 con el texto de «Reglas que cambian» de la spec |
| `CHANGELOG.md` | Entrada de la versión |

## Permisos (resumen)

| Quién | Derivaciones (Solucionado) | CCTV (Atendido / Cerrado) | Cierre a mano Derivaciones |
|---|---|---|---|
| Lectura | No | No | No |
| Edición | **Sí** | Solo con la casilla | No |
| Coordinación / Administración | Sí | Sí | No |
| Superadministrador | Sí | Sí | Sí (`corregirHoras`, sin cambios) |

Todo en Policies; las rutas y las vistas preguntan a la Policy. `ExigeModulo` ya niega si el
componente no tiene `derivaciones` / `seguridad_cctv`. El aislamiento (CA-06) lo dan
`DependenciaScope` + `DelComponente` al resolver `{derivacion}` / `{caso}`: otro componente u
otra dependencia → 404.

## Riesgos

1. **Bloqueo (CA-08) no se puede demostrar en SQLite:** `lockForUpdate` no hace nada ahí. La
   prueba automática cubre que la verificación se hace sobre una relectura dentro de la
   transacción (el modelo de la ruta puede estar viejo). La concurrencia real se verifica **a
   mano en MySQL** (dos `tinker` o dos pestañas con una pausa): queda una evidencia, la segunda
   recibe el mensaje y no deja archivo en `storage`.
2. **Archivo huérfano** si falla algo después de `storeAs`: cubierto con el `catch` que borra la
   ruta (mismo patrón del enlace). Si el rechazo es por tarjeta ya llena, el chequeo va **antes**
   de guardar, así que no se escribe nada en disco.
3. **Sin consultas con agrupación ni JSON nuevas**; solo eager loads. Nada especial para MySQL
   fuera del bloqueo.
4. **«Abrir en el repositorio» en Derivaciones** antes se mostraba a todos los que ven la ficha;
   ahora se condiciona a `DocumentoPolicy::view`. Si el componente no tiene Documentos, el enlace
   desaparece (hoy daba 403 al pulsarlo). Es lo que pide la spec.
5. **Ver versiones anteriores** en el repositorio es de Coordinación en adelante
   (`DocumentoPolicy::verVersiones`); Edición sube versión pero no ve el historial. Es la regla
   actual; la spec no la cambia.
6. **`max_file_uploads`/tamaño**: un solo archivo por envío; los 25 MB dependen de
   `upload_max_filesize`/`post_max_size` del servidor, como el enlace.
