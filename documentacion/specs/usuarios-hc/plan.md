# Plan: Usuarios HC · ficha, caracterización, búsqueda y edición con historial (DOC-26)

> **Estado: Borrador, sujeto a la aprobación de la spec DOC-26. No se escribe código hasta que
> Edwar la apruebe.**

Spec: [spec.md](spec.md) v0.2, en borrador (11 preguntas que bloquean). Tareas: [tasks.md](tasks.md).
Lo pidió José (Dueño de Producto) para adelantar trabajo. Donde una decisión depende de una
pregunta abierta se marca **[PA-NN]**: va la propuesta por defecto de la spec y, en una línea,
qué cambia si Edwar responde distinto.

Base: DOC-25 construida (ACC-01 a ACC-06, ACC-11 a ACC-13). Lo que este plan necesita de lo
que falta de DOC-25: **ACC-07 y ACC-08** para tener usuarios reales de Básica y Estabilización
en el servidor de pruebas (las pruebas automáticas no los necesitan: `usuarioCon` ya asigna
componente) y **ACC-10** solo para el aviso de «Fallecida» (con DOC-27).

## La idea en una línea

Una tabla `usuarios_hc` con el filtro de **dependencia** y sin el de componente; todo lo
sensible (caracterización, historial) cuelga de ella en tablas aparte que solo se abren después
de una Policy que pregunta «¿el componente donde estás parado conoce a esta persona?». La
búsqueda compara columnas normalizadas en PHP, así no depende del motor. Lo demás es lo que ya
existe: módulo de DOC-25, `Auditor`, el disco del repositorio, `x-campo-archivo` y Filament.

## Decisiones

### D1. Modelo de datos (una migración de esquema, nada se borra)

Todas las tablas nuevas cuelgan de `usuarios_hc`, que es la única con `dependencia_id`
(constitución III: «o colgar de algo que pertenece»). Ninguna lleva `SoftDeletes` ni se borra.

**`usuarios_hc`** (la ficha; modelo `UsuarioHc`, `#[ScopedBy([DependenciaScope::class])]`):

| Columna | Para qué |
|---|---|
| `dependencia_id` FK | Aislamiento por dependencia |
| `componente_registro_id` FK `componentes` | Componente de registro (PA-03). Se llena con `ContextoDependencia::componenteId()` al crear, **nunca desde el formulario**, y no está en `$fillable` de edición (CA-20) |
| `tipo_documento`, `tipo_documento_texto` | Código de la lista y el texto que se vio (§7) |
| `numero_documento` (20, nulo) · `documento_normalizado` (20, nulo) | Tal como se escribió (recortado: CA-08) · en mayúsculas, para comparar y buscar |
| `numero_ficha` (30, nulo) · `ficha_normalizada` (30, nulo) | Igual que el documento |
| `primer_nombre`, `segundo_nombre`, `primer_apellido`, `segundo_apellido`, `alias` | Identificación |
| `nombre_completo` | Se arma en `saving` (partes que existan, un espacio). Para mostrar y ordenar |
| `nombre_busqueda`, `alias_busqueda` | Normalizados (D5) |
| `estado_vital` (`viva`/`fallecida`, por defecto `viva`) · `fecha_fallecimiento` (nula) | Estados fijos, no lista configurable |
| `foto_id` (nulo, con índice, **sin FK**) | La foto vigente. Sin restricción porque `usuario_hc_fotos` apunta a `usuarios_hc` (circular); la relación exige además que la foto sea de la ficha |
| `revision` (entero, 0) | Concurrencia (D7) |
| `creado_por`, `actualizado_por` FK `users` · `timestamps` | Quién la creó, cuándo y en qué componente (con `componente_registro_id`) |

Índices: **único** `(dependencia_id, tipo_documento, documento_normalizado)` y **único**
`(dependencia_id, ficha_normalizada)`. Los dos admiten varios `NULL` en SQLite, MySQL y
PostgreSQL: «Sin documento» y la ficha vacía no chocan (CA-05, CA-06). El índice es la última
defensa; la validación da el mensaje claro antes. Índice `(dependencia_id, nombre_completo)`
para ordenar la lista.

**`usuario_hc_identidades`** (identidades anteriores, D5): `usuario_hc_id`, `campo`
(`nombre`, `documento`, `ficha`), `valor` (como se veía: «Juan Pérez», «CC 0012AB», «F-120»),
`valor_busqueda` (normalizado), `user_id`, `componente_id`, `created_at`. Índice
`(campo, valor_busqueda)`. Se escribe una fila cada vez que se corrige el nombre, el documento o
la ficha. DOC-31 agregará las de la ficha absorbida sin cambiar la tabla.

**`usuario_hc_caracterizaciones`** (1 a 1, solo los campos que no son listas):
`usuario_hc_id` único, `fecha_nacimiento`, `fecha_ingreso_sistema`, `calle_anios`,
`calle_meses`, `calle_semanas` (enteros sin signo, nulos: vacío es «no informado», CA-09 y
CA-11). En tabla aparte de la ficha **a propósito**: la lista y la búsqueda consultan
`usuarios_hc` y nunca tocan una columna sensible (CA-21).

**`usuario_hc_respuestas`** (todas las respuestas de listas, de una o de varias opciones):
`usuario_hc_id`, `campo`, `fuente` (de dónde salió la opción: el nombre de la lista o
`cie10`, `paises`, `comunas`), `opcion` (el código), `texto` (el texto que vio quien la
registró), `principal` (bool, solo diagnósticos de medicina general), `timestamps`. Único
`(usuario_hc_id, campo, opcion)`; índice `(campo, opcion)` para contar después (DOC-28/29).

- Cumple §7 sin JSON: el **código** permite contar una opción aunque cambie de nombre; el
  **texto** es lo que se muestra siempre, aunque la opción se renombre o se retire (CA-18).
- Una o varias opciones se guardan igual (una fila por opción elegida); limpiar una selección
  es no tener fila.
- Los diagnósticos guardan `fuente` = `cie10`, `opcion` = código SISPRO (`F200`) y `texto` = el
  nombre oficial del momento; nacionalidad, código ISO numérico (`170`) y nombre; comuna, número
  y nombre. Sin FK a los catálogos: recargar un catálogo no cambia lo ya registrado.
- **`fuente` es lo que deja la puerta abierta a la CIE-11** (D9): cada diagnóstico dice con qué
  clasificación se registró. Cuando llegue la CIE-11 (fin de la transición hacia el 14-ago-2027),
  sus códigos entran con `fuente = cie11`, en filas nuevas o con columnas opcionales para la
  doble codificación, sin migrar lo que ya hay. No se construye nada de la CIE-11 ahora.
- Cambiar una respuesta reemplaza su fila (como cambiar una columna) y el valor anterior queda
  en el historial. Ver **P-05** en las preguntas.

**`usuario_hc_cambios`** (historial de la ficha): `usuario_hc_id`, `user_id`, `componente_id`
(nulo solo si quien edita no está parado en ninguno, que con el módulo no puede pasar),
`seccion` (`identificacion`, `caracterizacion`, `foto`), `campo`, `antes`, `despues` (texto
legible: los textos de las opciones, unidos con «, » en las de varias), `motivo` (nulo solo
donde PA-04 no lo pide), `created_at`. Una fila por campo cambiado; las de un mismo guardado
comparten autor, motivo y hora. Sin JSON y sin agrupación para mostrarlo (CA-14).

**`usuario_hc_fotos`**: `usuario_hc_id`, `ruta`, `mime`, `tamano`, `hash`, `ancho`, `alto`,
`subida_por`, `created_at`. Cambiar la foto crea una fila nueva y mueve `usuarios_hc.foto_id`;
quitarla pone `foto_id` en nulo. Ninguna fila ni archivo se borra (constitución IV, CA-22).

**Catálogos (D9):** sin `dependencia_id`: son datos de referencia externos, iguales para todos
**[PA-13]**.

- `catalogo_cie10`: `codigo` (4, único: `F200`, `F03X`), `nombre` (texto oficial tal cual,
  hasta 255), `categoria` (`F20`), `categoria_nombre`, `capitulo`, `sexo` (`A`, `M`, `F`),
  `edad_minima`, `edad_maxima` (nulos), `habilitado`, `nombre_busqueda`, `fecha_fuente`
  (la fecha de la tabla de SISPRO), `timestamps`.
- `catalogo_paises`: `codigo` (3, **texto**, único: `004`, `170`), `alfa2`, `alfa3`, `nombre`
  (el que se muestra), `nombre_oficial` (el de SISPRO), `habilitado`, `nombre_busqueda`,
  `fecha_fuente`, `timestamps`.

Modelos: `UsuarioHc`, `IdentidadUsuarioHc`, `CaracterizacionUsuarioHc`, `RespuestaUsuarioHc`,
`CambioUsuarioHc`, `FotoUsuarioHc`, `Cie10`, `Pais`. Solo `UsuarioHc` lleva scope; los demás se
leen siempre por su relación con la ficha (`$ficha->respuestas`), después de la Policy.

**Una trampa de DOC-25:** `Componente` lleva `ComponenteScope('id')`. La relación
`UsuarioHc::componenteRegistro()` tiene que ir con `->withoutGlobalScope(ComponenteScope::class)`:
si no, un usuario parado en Básica vería vacía la columna «componente» de las fichas creadas en
Estabilización (CA-02). Lo mismo el nombre del componente en el historial y las opciones del
filtro de componente. Son **saltos a propósito**, por escrito (DOC-25 D4): van a la lista de
`docs/FILAMENT.md`, regla 2.

### D2. El módulo `usuarios_hc`

- **`Componente::MODULOS`**: se agrega al final
  `'usuarios_hc' => ['Usuarios HC', 'usuarios-hc.index']`. La clave no choca con ningún
  parámetro de ruta (el de la ficha es `{usuarioHc}`) y en las rutas va entre comillas
  (`"can:modulo,'usuarios_hc'"`), la lección de `supervisiones`. Al final para que Calle (que no
  lo tiene) y las pruebas con todos los módulos sigan llegando a Documentos. La pantalla de
  módulos de ACC-09, cuando exista, lo ofrecerá sola: lee la constante.
- **Encenderlo en Básica y Estabilización** mientras no exista ACC-09: migración de datos que
  llama a `Database\Seeders\UsuariosHcModuloSeeder`, con el mismo patrón que
  `sembrar_basica_y_estabilizacion`: `DB::table`, idempotente, solo Inclusión Social, lee
  `modulos` en PHP, agrega `usuarios_hc` si falta y lo vuelve a guardar (sin consultas JSON).
  `down()` vacío. `DatabaseSeeder` lo llama después de `AccesoPorComponenteSeeder`.
- **Rutas:** archivo propio `routes/usuarios-hc.php`, cargado en `bootstrap/app.php` como el de
  turnos («uno por dueño, para que no choquen en git»), con
  `['web', 'auth', 'usuario.activo', 'dependencia', "can:modulo,'usuarios_hc'"]`, prefijo
  `usuarios-hc` y nombres `usuarios-hc.*`. Así José no vuelve a tocar `routes/web.php`.

  | Método y dirección | Qué | Permiso de la ruta |
  |---|---|---|
  | `GET usuarios-hc` | Lista (tabla Filament) | `can:viewAny,App\Models\UsuarioHc` |
  | `GET usuarios-hc/nuevo` · `POST usuarios-hc` | Crear | `can:create,…` |
  | `GET usuarios-hc/coincidencias` | Aviso de coincidencias mientras se escribe (HTML parcial, solo identificación) | `can:create,…` + `throttle` |
  | `GET usuarios-hc/{usuarioHc}` | Ficha: identificación | `can:view,usuarioHc` |
  | `GET usuarios-hc/{usuarioHc}/caracterizacion` | Caracterización (auditada) | `can:verCaracterizacion,usuarioHc` |
  | `GET usuarios-hc/{usuarioHc}/historial` | Historial (auditado) | `can:verCaracterizacion,usuarioHc` |
  | `GET …/{usuarioHc}/editar` · `PUT …/{usuarioHc}` | Corregir identificación y estado vital | `can:update,usuarioHc` |
  | `GET …/{usuarioHc}/caracterizacion/editar` | Editar caracterización (Livewire) | `can:update,usuarioHc` |
  | `POST …/{usuarioHc}/foto` · `PATCH …/{usuarioHc}/foto/quitar` | Cambiar o quitar la foto | `can:update,usuarioHc` |
  | `GET …/{usuarioHc}/foto` | Ver la foto vigente (auditada) | `can:view,usuarioHc` |
  | `GET …/{usuarioHc}/foto/miniatura` | Miniatura de la lista, firmada y con vencimiento **[PA-17]** | `signed` + `can:view,usuarioHc` |

  `{usuarioHc}` con `whereNumber`. La vinculación usa el scope de dependencia: una ficha de
  otra dependencia da 404.
- **Menú** (`sidebar.blade.php`): `$conUsuariosHc = Gate::allows('modulo', 'usuarios_hc')` y una
  línea en el grupo `principal`, para todos los roles (Lectura incluida).
- **Llegada** (`InicioController`): hoy solo Documentos se abre desde Lectura. Se cambia la
  condición fija por una constante junto a `MODULOS`:
  `Componente::MODULOS_DESDE_LECTURA = ['documentos', 'usuarios_hc']`. Un usuario de Básica de
  cualquier rol llega a Usuarios HC; Calle sigue llegando a Documentos (CA-01).

### D3. Permisos: `UsuarioHcPolicy`, con `ExigeModulo`

`const MODULO = 'usuarios_hc'`: sin el módulo se niega todo, también al superadministrador, y
cubre las acciones de Filament y el menú. Después, el rol **[PA-02]**:

| Método | Regla (DOC-26 sola) |
|---|---|
| `viewAny`, `view` | Pertenece a la dependencia (cualquier rol). `view` además exige `$ficha->dependencia_id` = la actual |
| `create` | `puedeEditarEn` y hay componente en el contexto (será el de registro) |
| `verCaracterizacion` (caracterización e historial) | `puedeEditarEn` **y** (es coordinador líder **o** `$ficha->conocidaPor($componenteId)`). Lectura, nunca **[PA-11]** |
| `update` (identificación, estado vital, foto y caracterización) | `puedeEditarEn` **y** (coordinador líder **o** `$ficha->editablePor($componenteId)`) |

- «Coordinador líder» = `rolEn(dependencia) === Administracion` (el superadministrador ya
  devuelve Administración en `rolEn`).
- Las dos preguntas del componente viven en el modelo, en un solo lugar, para que DOC-27 las
  cambie sin tocar la Policy:
  - `conocidaPor(int $id)`: con DOC-26 sola, `componente_registro_id === $id` **[PA-03]**. Con
    DOC-27 (UHC-19): o tiene una estadía activa o egresada en ese componente.
  - `editablePor(int $id)`: con DOC-26 sola, igual. Con DOC-27: la estadía activa está en ese
    componente; si nunca tuvo estadía válida, el de registro; si tuvo y hoy no está activa, nadie
    salvo el líder.
- Rutas, controladores (`$this->authorize`) y cada acción de Filament (`->authorize()`) pasan por
  aquí. El guardado vuelve a autorizar (CA-13: «al guardar»).
- Sin el módulo (Calle): 403 en la ruta y `false` en la Policy (CA-01).
- [PA-01] Si Edwar no acepta que la búsqueda cruce componentes: `viewAny`/`view` y la consulta
  de la lista se limitan a `conocidaPor`, y el aviso de duplicados solo vería el propio componente.
- [PA-02] Si pide permisos sueltos: una columna booleana en `dependencia_usuario` como
  `aprueba_novedades` y una condición más en `update`; es otra tarea.

### D4. Auditoría

Cinco acciones nuevas en `AccionAuditoria`, todas con `Auditor::registrar`: las cuatro de la
tabla, con la ficha como auditable, y `CatalogoHcCargado` para cada carga de un catálogo (D9).

| Acción | Cuándo | `datos` |
|---|---|---|
| `UsuarioHcCreado` («Creó un Usuario HC») | Al crear | Identificación guardada |
| `UsuarioHcActualizado` («Corrigió un Usuario HC») | Cada guardado de identificación, caracterización o foto | `['cambios' => [campo => ['antes' => …, 'despues' => …]], 'motivo' => …]` (lo mismo que el historial) |
| `UsuarioHcConsultado` («Abrió la caracterización de un Usuario HC») | Abrir caracterización, historial o la edición de la caracterización | `['parte' => 'caracterizacion'|'historial'|'edicion']` |
| `UsuarioHcFotoVista` («Vio la foto de un Usuario HC») | Abrir la foto; una página de la lista con fotos | `['fichas' => [ids]]` para la página |

- `auditorias.datos` ya es JSON y aquí solo se escribe; nada consulta dentro de él.
- La pantalla de Auditoría no cambia: Coordinación sigue viendo solo lo de los usuarios de su
  componente (ACC-11), que es lo que pide §6.1.
- **Miniaturas [PA-17]:** la lista arma, para cada fila con foto, una URL firmada con vencimiento
  de 10 minutos (`URL::temporarySignedRoute`) que además lleva el id del usuario y se compara con
  el de la sesión. La ruta de miniatura exige sesión, firma y `can:view`, y **no** audita por foto:
  el componente de la lista deja un solo registro `UsuarioHcFotoVista` por página mostrada (solo
  cuando cambia el conjunto de fichas con foto, para no repetir al abrir una ventana). La ruta
  normal de la foto sí audita cada vez. Sin la firma, ver una foto suelta siempre queda auditado.
  - Si Edwar elige la alternativa (sin fotos en la lista): se quitan la columna, la ruta de
    miniatura y el registro por página.

### D5. Búsqueda sin tildes ni mayúsculas, igual en cualquier motor

**Técnica: columnas normalizadas calculadas en PHP**, en `saving` del modelo, con una sola
función:

- `UsuarioHc::normalizarTexto($t) = Str::of($t)->ascii()->lower()->squish()`: «Peña Álvarez» →
  «pena alvarez». Para `nombre_busqueda`, `alias_busqueda`, `valor_busqueda` de nombres y
  `descripcion_busqueda` / `nombre_busqueda` de los catálogos.
- `UsuarioHc::normalizarCodigo($t) = mb_strtoupper(trim($t))`: documento y ficha.
- **La «ñ» se busca como «n»** (es lo que ya hace `utf8mb4_unicode_ci` en los servidores, y
  `Str::ascii` hace lo mismo). Ver **P-10**.

Como las columnas guardan ASCII en minúsculas (o mayúsculas, los códigos), `LIKE` se comporta
igual en SQLite, MariaDB 10.4, MySQL y PostgreSQL: el término se normaliza con la misma función y
se escapan `%` y `_`. No hace falta collation, extensión ni columna generada.

Scope `UsuarioHc::scopeBuscar(Builder $query, string $texto)`, que también usará DOC-27 para su
propia búsqueda:

- **Nombre y alias:** cada palabra del término debe aparecer en `nombre_busqueda` o en
  `alias_busqueda`, en cualquier orden («perez juan» encuentra a «Juan Carlos Pérez») (P-09).
- **Documento:** `documento_normalizado LIKE %TÉRMINO%` (parte del número).
- **Ficha:** `ficha_normalizada = TÉRMINO` (exacto).
- **Identidades anteriores:** `usuarios_hc.id IN (SELECT usuario_hc_id FROM usuario_hc_identidades WHERE …)`
  con la misma regla por campo. Una subconsulta `whereIn`, sin juntas ni `GROUP BY`; el scope de
  dependencia queda en la consulta de afuera.
- **Ficha reasignada:** antes de armar la consulta, un `exists` sobre `ficha_normalizada`. Si
  alguien tiene ese número vigente, las identidades con campo `ficha` no entran; si nadie lo
  tiene, sí.
- **«Coincide con un dato anterior»:** para las filas de la página, si el término no casa con
  sus datos vigentes, la lista dice «Coincide con un nombre anterior» (o documento, o ficha). No
  muestra el valor viejo (CA-04).

**Rendimiento:** `LIKE '%…%'` no usa índice; se recorre la tabla. Con las fichas que puede tener
Medellín (miles, no millones) y una sola tabla, es suficiente. Si un día no lo es: tabla de
palabras o búsqueda de texto completo (candidato). La ficha exacta sí usa su índice.

**Cómo se prueba CA-03:** las pruebas (SQLite) cubren tildes, mayúsculas, «ñ», partes del
nombre, alias, documento con letras y ficha exacta. UHC-17 corre **la misma clase de pruebas
contra MySQL o MariaDB** con una base de pruebas aparte, sin tocar el `.env`:
`DB_CONNECTION=mysql DB_DATABASE=documenta_pruebas php artisan test --filter=UsuariosHcBusquedaTest`.

### D6. Crear, coincidencias y unicidad

- **Pantalla:** Blade y controlador (`UsuarioHcController@create/store`), como Personas de campo,
  porque la foto va en el mismo formulario y `x-campo-archivo` ya reduce la imagen en el
  navegador. La foto es opcional (§2).
- **Validación** (`GuardarUsuarioHcRequest`, compartida con la corrección):
  - `tipo_documento` en las opciones vigentes de la lista;
  - `numero_documento` obligatorio salvo «Sin documento», prohibido con «Sin documento»
    (`prohibited_if`), solo letras y números, hasta 20 (P-02);
  - unicidad de tipo + documento normalizado y de ficha normalizada en la dependencia,
    ignorando la propia ficha al corregir. **La consulta de unicidad mira toda la dependencia**
    (no hay `ComponenteScope` en la ficha): CA-06 con fichas de otro componente. El índice único
    respalda una carrera entre dos personas; la `QueryException` se traduce al mismo mensaje.
- **Mensajes de repetido** (CA-21): «Ya existe una ficha con ese documento: Juan Pérez, ficha
  F-120», con un enlace a la ficha. Solo identificación.
- **Coincidencias mientras se escribe** (CA-07): `GET usuarios-hc/coincidencias` con primer
  nombre, primer apellido, tipo, documento y ficha; devuelve un HTML parcial con hasta 10 fichas
  (nombre, documento, ficha, componente de registro). Coinciden: mismo documento (con cualquier
  tipo), misma ficha vigente, o `nombre_busqueda` que contiene el primer nombre y el primer
  apellido normalizados, o el alias igual (P-08). Avisa; solo el documento o la ficha repetidos
  bloquean, y eso lo hace la validación al guardar.
- **Al guardar:** `componente_registro_id`, `creado_por` y el texto del tipo de documento se
  ponen en el servidor; `estado_vital = viva`. Auditoría `UsuarioHcCreado`.

### D7. Editar con motivo, historial y concurrencia (CA-13 a CA-15)

Un servicio, `App\Services\FichasUsuariosHc`, hace todo guardado de la ficha en una
transacción, en este orden: autorizar → comprobar la revisión → comparar valores → exigir motivo
→ guardar → historial → auditoría. Lo usan el controlador (identificación, foto) y el componente
Livewire (caracterización).

- **Concurrencia, sin depender del motor:** cada formulario lleva la `revision` que tenía la
  ficha al abrirse. Al guardar:
  `UsuarioHc::whereKey($id)->where('revision', $enviada)->increment('revision')`. Es una sola
  sentencia `UPDATE … WHERE revision = ?`, atómica en cualquier motor: si devuelve 0, alguien
  guardó antes y se rechaza con «Otra persona cambió esta ficha mientras la editabas. Recarga la
  página para ver sus cambios», sin guardar nada (CA-15). Cubre identificación, caracterización y
  foto: cualquiera de las tres sube la revisión. Sin bloqueos de fila ni fechas con segundos.
- **Motivo [PA-04]:** identificación (incluidos foto y estado vital): siempre. Caracterización:
  si cambia un campo que ya tenía valor; llenar uno vacío no lo pide, pero queda en el historial.
  - Si Edwar responde «motivo siempre»: se quita la comparación y el motivo es obligatorio en
    todo guardado.
- **Historial:** una fila de `usuario_hc_cambios` por campo cambiado, con el componente del
  contexto (para el líder, en el que está parado) (CA-14).
- **Identidades anteriores:** si cambian el nombre completo, el documento (tipo o número) o la
  ficha, se guarda el valor anterior en `usuario_hc_identidades` (CA-04). El número de ficha
  viejo queda libre para otra persona (§1).
- **Componente de registro:** no se toca al editar (CA-20).
- **Estado vital [con DOC-26 sola]:** «Fallecida» con motivo e historial y sin ningún aviso
  (CA-19). El aviso llega con UHC-21 (DOC-27 y ACC-10).

### D8. Caracterización: listas en la configuración y formulario de Filament

- **Qué campos hay** es código (un campo nuevo es un cambio de la spec, §7): constante
  `UsuarioHc::CAMPOS`, con etiqueta, tipo (`una`, `varias`, `varias_con_principal`) y de dónde
  salen las opciones (una lista de la configuración, `comunas`, `catalogo:paises`,
  `catalogo:cie10`). Las fechas y el tiempo en calle son columnas de
  `usuario_hc_caracterizaciones`.
- **Las opciones de las listas cortas** son dato, en `config/repositorio.php` **[PA-05]**, un
  bloque `usuarios_hc` junto a `comunas`:

  ```php
  'usuarios_hc' => [
      // código => texto. Renombrar: cambiar el texto (cada ficha guarda el que vio).
      // Retirar: poner el código en 'retiradas' (no se ofrece, pero se sigue mostrando).
      'tipos_documento' => ['opciones' => ['CC' => 'Cédula de ciudadanía', /* … */, 'SD' => 'Sin documento', 'OTRO' => 'Otro'], 'retiradas' => []],
      'sustancias' => ['opciones' => [/* … */, 'no_consume' => 'No consume'], 'excluyentes' => ['no_consume']],
      'discapacidad' => ['opciones' => [/* … */], 'excluyentes' => ['ninguna', 'sin_informacion']],
      'si_no' => ['opciones' => ['si' => 'Sí', 'no' => 'No']],
      // escolaridad, nivel_apoyo, clasificacion, sexo, orientacion, eps, autonomia, independencia
  ],
  ```

  - `excluyentes`: una opción así no se guarda con ninguna otra; cubre «No consume» y
    «Ninguna»/«Sin información» entre sí (CA-10), sin `if` por campo.
  - Opciones que se ofrecen = `opciones` menos `retiradas`, más las que **esta ficha ya tiene**
    (con su texto guardado y «(retirada)»), para que editar no las borre en silencio. La
    validación acepta un código si está vigente o si la ficha ya lo tenía (CA-18).
  - Comuna: la lista `repositorio.comunas` que ya usan los casos (DOC-23), sin copiarla.
  - EPS **[PA-07]**: si Edwar no autoriza la EPS, se quitan la lista y el campo.
  - Si Edwar prefiere otra cosa que la configuración: tabla de solo lectura cargada por
    despliegue, como los catálogos (D9).
- **Formulario:** componente Livewire `App\Livewire\UsuariosHc\Caracterizacion` con un
  formulario de Filament dentro de la página de Documenta: `Select` con
  `placeholder` (se puede limpiar), `CheckboxList` para las de varias, `Select::searchable()`
  con `getSearchResultsUsing` para países y CIE-10 (no hace falta escribir un buscador en JS),
  `Repeater` simple o `Select::multiple()` más un `Radio` de «principal» para medicina general.
  Recordar la lección: los closures reciben `$state`, `$get`, `$search` por nombre.
- **Reglas** (CA-09 a CA-12): todo opcional; fecha de nacimiento no futura (comparada con el día
  en `America/Bogota`, la zona de `repositorio.horario.zona`); tiempos enteros desde 0, sin
  convertir ni rellenar; medicina general con diagnósticos exige uno principal; «Otros
  diagnósticos de psiquiatría» sin principal **[PA-08]**. La **edad** es un accessor
  (`fecha_nacimiento->age`, o «Sin información»).
- Abrir la edición registra `UsuarioHcConsultado` (muestra la caracterización). Guardar usa el
  servicio de D7.

### D9. Catálogos CIE-10 y países: recargables, no «una sola vez» [PA-13, PA-06]

Base: [investigacion-cie10-y-paises.md](investigacion-cie10-y-paises.md) (2026-11-24).

- **Fuentes:** tablas de referencia **«CIE10»** (12.634 códigos, 22-oct-2026) y **«Pais»** (249
  países, 3-oct-2018) de SISPRO, las mismas del RIPS. Se registran en `documentacion/fuentes/`
  con su fecha.
- **Por qué recargable:** la CIE-11 reemplaza a la CIE-10 hacia el **14-ago-2027** (Res. 1442 de
  2024 y 1657 de 2025) y SISPRO no publica cada cuánto actualiza la tabla. Recargar no rompe nada
  porque cada respuesta guarda su `fuente`, su código y el texto con que se registró (§7, D1).
- **Cómo se carga:** una clase `App\Support\CatalogosHc` y un comando
  `php artisan catalogos-hc:cargar {cie10|paises} {archivo}`. Lee el Excel exportado de SISPRO
  con **OpenSpout** (ya se usa en las cargas masivas; también lee CSV si el Excel llega en otro
  formato y hay que convertirlo).
  - **Columnas por su contenido, no por el encabezado:** los `Extra_…` de SISPRO no traen lo que
    dice su etiqueta. El mapa vive en la clase (CIE-10: `Codigo`, `Nombre`, `Descripcion` =
    nombre de la categoría, `Habilitado`, `Extra_I` = sexo 1/2/3, `Extra_II`/`Extra_III` = edad
    mínima y máxima, `Extra_V` = capítulo, `Extra_VI` = categoría; países: `Codigo` = ISO
    numérico, `Extra_II` = alfa-2, `Extra_III` = alfa-3, `Nombre`).
  - **Se comprueba antes de escribir:** si `F200` no es «ESQUIZOFRENIA PARANOIDE», `F524` no es
    solo para hombres, `F900` no va de 1 a 17 años o `170` no es Colombia/`CO`/`COL`, la carga se
    detiene sin tocar nada y dice qué columna no cuadra. Así un cambio de formato de SISPRO no
    llena el catálogo de basura.
  - **Texto oficial tal cual:** con sus mayúsculas, sin tildes y con sus errores
    («DEPENDENDENCIA», el carácter dañado de `U85X`). No se corrige el CIE-10. Solo se agrega
    `nombre_busqueda` normalizado. Los códigos se guardan como SISPRO (4 caracteres, sin punto,
    con X); la búsqueda acepta también «F20.0», «f20» o parte del nombre.
  - **`upsert` por código** en tandas de 200 filas (SQLite no acepta más de 999 variables por
    sentencia), en una transacción. Un código que estaba y ya no viene en el archivo queda
    `habilitado = false`: deja de ofrecerse, pero las fichas que lo tienen lo siguen mostrando con
    su texto (como una opción retirada, §7). **Nunca se borra una fila.**
  - Cada carga deja auditoría (`CatalogoHcCargado`, con fuente, fecha de la tabla y cuántos
    códigos nuevos, cambiados y deshabilitados) y su línea en la consola.
- **Dónde vive el archivo:** **en el repositorio**, en `database/data/catalogos/`, con la fecha
  en el nombre (`sispro-cie10-2026-10-22.xlsx`, `sispro-pais-2018-10-03.xlsx`). No trae datos
  personales; así el despliegue, el servidor de pruebas y cada equipo local cargan lo mismo, y
  cada recarga se revisa en un PR. **En producción no se hace nada a mano:** una migración de
  datos llama al cargador (el despliegue solo corre `migrate`). Cada recarga futura es un archivo
  nuevo más una migración nueva de una línea (nunca se edita una migración desplegada,
  constitución VIII). Ver **P-14** (licencia).
- **El Excel llega por correo** (SISPRO lo envía al exportar): bajarlo con un correo
  institucional es un paso de una persona, no de código. Mientras no esté, la migración no hace
  nada y el formulario **esconde los campos de ese catálogo** (`Cie10::habilitados()->exists()`):
  DOC-26 puede salir sin diagnósticos, como prevé PA-06.
- **Países: nombres con errores** (BRAZIL, GUADALUOE, SUAZILANDIA, MACEDONIA…). El código es lo
  que vale y se carga tal cual. **Propuesta:** se guarda `nombre_oficial` como viene y se muestra
  `nombre`, que es el oficial salvo una lista corta de correcciones en `CatalogosHc`
  (código ⇒ nombre, con la razón al lado: `076` ⇒ «BRASIL», `312` ⇒ «GUADALUPE», `748` ⇒
  «ESUATINI»…). La lista se vuelve a aplicar en cada recarga y queda en el PR. Ver **P-15**.
  Ni «apátrida» ni «desconocido» se inventan como país: vacío es «no informado» (§3).
- **Edad y sexo por código** (por ejemplo, F900 de 1 a 17 años; 885 códigos para un solo sexo):
  se **guardan** en el catálogo, pero **DOC-26 no valida ni avisa con ellos**, porque la spec no
  lo pide. Ver **P-13**: es Edwar quien decide si bloquean, avisan o nada.
- **En las pruebas la migración no carga el archivo completo** (`app()->runningUnitTests()`): la
  suite corre en SQLite en memoria y no debe insertar 12.634 filas en cada corrida. Cada prueba
  crea los códigos que necesita; `CatalogosHcTest` carga un archivo de ejemplo pequeño
  (`tests/Fixtures/`) con F200, F524, F900, F03X, U85X y 170, y prueba la comprobación, la
  recarga (cambia un nombre, deshabilita un código ausente, no borra) y que una respuesta ya
  guardada conserva su texto.
- **CIE-11:** no se construye. Lo que este diseño deja listo: la `fuente` en cada respuesta, la
  carga genérica por fuente, y un catálogo por clasificación (una tabla `catalogo_cie11` futura no
  toca la del CIE-10). Lo que **no** resuelve: la spec dice «Catálogo CIE-10» en tres campos;
  registrar en CIE-11 o con doble codificación será un cambio de spec (P-16).
- [PA-13] Si Edwar los quiere en la configuración: los países caben (249); el CIE-10 no (12.634
  códigos): habría que limitarlo a una lista corta, lo que cambia la spec.

### D10. Fotos [PA-15, PA-10 resuelta]

- **Guardar:** el servicio `FichasUsuariosHc::cambiarFoto` guarda en
  `Storage::disk(config('repositorio.disco'))`, en `usuarios-hc/{dependencia}/{ficha}/{uuid}.{ext}`,
  fuera de la web. Nada en `documentos` ni en carpetas: no aparece en Documentos (RN-51).
  Fila nueva en `usuario_hc_fotos`, `foto_id` nuevo, historial «Foto: cambió» con motivo, revisión.
- **Validar:** `mimetypes` y extensiones de `repositorio.formatos.imagenes` (JPG, JPEG, JFIF,
  PNG, WEBP), `max:repositorio.tamano_maximo_kb`, y lado máximo
  `repositorio.imagen.lado_maximo_servidor`. La regla del lado hoy es un closure protegido de
  `EnvioPublicoController`; se saca a `App\Rules\LadoMaximoImagen` y ese controlador la usa (mismo
  mensaje, sus pruebas lo cubren). Así no hay dos copias.
- **Servir:** `UsuarioHcFotoController@ver` responde el archivo con `Content-Type` del mime
  guardado, `X-Content-Type-Options: nosniff` y `Cache-Control: private`, como
  `DescargaController::previsualizar`. Audita (D4). La miniatura es el mismo archivo por la ruta
  firmada (no hay GD en el entorno de pruebas para generar miniaturas; candidato).
- **Sin sello [PA-15]:** `x-campo-archivo` recibe una propiedad `sello` que hoy siempre tiene
  valor. Con `:sello="null"`: la foto de cámara **no** se estampa ni pide ubicación; solo se
  reduce a 2560 px. Además, para esta foto el navegador **siempre** vuelve a codificar la imagen,
  aunque ya sea pequeña: así se le quitan los metadatos EXIF (que pueden traer coordenadas del
  GPS). Ver **P-06**.
  - Si Edwar dice que la foto lleve sello: `sello="Usuario HC"` y no hay cambio en el componente.

### D11. Pantallas

| Pantalla | Cómo | Quién la ve |
|---|---|---|
| Lista | `App\Livewire\UsuariosHc\Listado` (tabla Filament, copia de `PuntosCriticos`) en `usuarios-hc/index.blade.php` | Todos los roles del componente con el módulo |
| Crear, corregir identificación | Blade + `UsuarioHcController`, con un parcial de formulario común | Edición en adelante |
| Ficha | Blade: identificación con foto y estado vital; botones a caracterización e historial solo si `@can('verCaracterizacion')` | Todos |
| Caracterización e historial | Blade, rutas propias auditadas | D3 |
| Editar caracterización | Livewire + formulario Filament (D8) | D3 |

- **Lista:** columnas foto (miniatura), nombre completo, tipo y número de documento, ficha,
  componente de registro, estado vital. Búsqueda con
  `->searchable(query: fn (Builder $query, string $search) => $query->buscar($search))`
  (parámetros por nombre). Filtro «Componente» con los componentes del módulo (salto de scope a
  propósito, D1). Paginación de 25. `persistSearchInSession()`, `persistFiltersInSession()` y la
  página en la dirección, para volver como se dejó (§1). Acciones «Ver» y «Editar» como enlaces,
  cada una con `->authorize()`. La consulta de la tabla es `UsuarioHc::query()` con
  `componenteRegistro` cargado; **nunca** carga relaciones sensibles (CA-21).
- **Con DOC-26 sola** la columna dice el componente de registro y no hay filtro de estado
  (UHC-20 lo cambia).

### D12. Lo que espera a DOC-27 (y no se construye ahora)

| Qué | Dónde se engancha | Tarea |
|---|---|---|
| Ver caracterización e historial por estadía válida (CA-25) | `UsuarioHc::conocidaPor()` | UHC-19 |
| Editar según la estadía activa (CA-24) | `UsuarioHc::editablePor()` | UHC-19 |
| Columna «componente o estado», filtro de estado (CA-23) | `Listado` | UHC-20 |
| Aviso al marcar «Fallecida» con estadía activa (CA-26) | `FichasUsuariosHc`, con `Dependencia::usuariosDelComponente()` de ACC-10 y un tipo nuevo en la campana (RN-38) **[PA-16]** | UHC-21 |
| «Registrar ingreso» en la lista (CA-27) | `Listado`, acción que abre la pantalla de DOC-27 | UHC-22 |
| «Unificar» (DOC-31) | `Listado` | Spec DOC-31 |

Nada de DOC-26 asume cómo se llamará la tabla de estadías: solo esos dos métodos y esas dos
acciones cambian.

## Qué cambia en la configuración y en `Componente`

- `Componente::MODULOS`: `usuarios_hc` al final. Nueva `Componente::MODULOS_DESDE_LECTURA`.
- `Componente::REGLAS`: **nada** (nada de esto cambia entre Básica y Estabilización).
- `config/repositorio.php`: bloque `usuarios_hc` con las listas cortas (D8). Nada más cambia: se
  reutilizan `disco`, `formatos.imagenes`, `tamano_maximo_kb`, `imagen.*`, `comunas`, `por_pagina`.

## Pruebas

- Archivos nuevos: `tests/Feature/UsuariosHc/` con `UsuariosHcModuloTest`, `UsuariosHcAccesoTest`,
  `UsuariosHcBusquedaTest`, `UsuariosHcCrearTest`, `UsuariosHcEdicionTest`,
  `UsuariosHcCaracterizacionTest`, `UsuariosHcFotosTest`, `CatalogosHcTest`. Nombres
  `test_doc26_caNN_…`, como `test_doc25_…`.
- Modelo: `AislamientoPorComponenteTest` (componentes con todos los módulos, datos sembrados en
  dos) y `ModulosPorComponenteTest` (recorre las rutas de cada grupo).
- Básica y Estabilización con `Componente::factory()->create(['modulos' => ['usuarios_hc']])`;
  Calle sin él (`['documentos', …]`). Ojo: `darRol` crea «Calle» con **todos** los módulos de la
  fábrica, que ahora incluyen `usuarios_hc`; las pruebas de rechazo de Calle fijan sus módulos.
- Livewire (lista y caracterización): `Livewire::test` no pasa por middlewares; se fijan
  dependencia y componente a mano (`docs/FILAMENT.md`).
- **Pruebas de DOC-25 que hay que tocar** (de Edwar):
  - `AccesoPorComponenteTest::test_doc25_ca01_…`, línea con
    `assertSame(array_keys(Componente::MODULOS), $componentes['calle']->modulos)`: deja de ser
    cierto porque Calle no tiene Usuarios HC. Pasa a comparar con
    `AccesoPorComponenteSeeder::MODULOS_CALLE`. **Cambia lo que verifica** (ver P-01).
  - `ModulosPorComponenteTest::parametrosEn()`: agregar `usuarioHc` (una ficha en Básica). Con
    eso, la prueba que recorre «cada ruta de cada módulo» cubre sola todas las rutas de Usuarios HC
    (403 para un componente sin el módulo) y exige que exista el grupo.
- Fotos: `TestCase::archivoJpg()` sirve para lo válido; para «más de 4096 px» basta una cabecera
  PNG armada a mano con esas medidas (`getimagesize` solo lee la cabecera; no hay GD).
- CA-19: `Notification::fake()` y `assertNothingSent()`.
- `ConsultasEficientesTest`: el menú suma un `Gate::allows` sin consulta; no debería moverse.

## Riesgos

| Riesgo | Qué pasa | Cómo se baja |
|---|---|---|
| Relación a `Componente` con su `ComponenteScope('id')` | La columna y el filtro de componente salen vacíos para fichas de otro componente | `withoutGlobalScope(ComponenteScope::class)` en la relación y las opciones (D1); prueba CA-02 desde Básica con una ficha de Estabilización |
| Consultas que SQLite deja pasar y MySQL no | Falla en el servidor | Sin JSON consultado, sin `GROUP BY`; una subconsulta `whereIn` y columnas calificadas. Aun así UHC-17 corre las pruebas de búsqueda, crear (índices únicos) y lista contra MySQL/MariaDB |
| Índice único con `utf8mb4_unicode_ci` | MySQL podría tratar como iguales valores que SQLite ve distintos | Las columnas únicas ya van normalizadas (mayúsculas, sin espacios en los extremos): los dos motores comparan lo mismo |
| Una persona con datos de salud vista desde otro componente | Fuga sin vuelta atrás | La caracterización está en otras tablas que la lista no toca; Policy en ruta, controlador y Livewire; pruebas de rechazo por dirección directa (CA-16), en la lista y en los mensajes (CA-21) |
| Tamaño del CIE-10 | Despliegue lento; pruebas lentas | 12.634 filas, nombres de hasta 241 caracteres: inserción en tandas, solo en el despliegue; las pruebas no lo cargan (D9). Unos segundos |
| SISPRO cambia el formato o el encabezado de su Excel | Catálogo con columnas cruzadas | Columnas por contenido y comprobación con códigos conocidos antes de escribir (D9) |
| La CIE-11 reemplaza a la CIE-10 (≈ 14-ago-2027) | Los diagnósticos quedan en una clasificación derogada | `fuente` en cada respuesta y catálogo recargable (D1, D9); el cambio de los campos es una spec nueva (P-16) |
| Uso del texto de la CIE-10 sin licencia escrita | Reclamo de la OMS o del Ministerio | Uso interno, sin venta, citando «Tabla de referencia CIE10, SISPRO, MinSalud, fecha» (P-14) |
| Búsqueda con `LIKE '%…%'` | Lenta con muchas fichas | Miles de filas: aceptable. Candidato si crece |
| Fotos de galería con coordenadas en el EXIF | Dato personal que la spec no quiere guardar (PA-15) | El navegador vuelve a codificar siempre (D10); P-06 |
| Livewire o Filament con muchos campos | Formulario lento en el celular | Secciones plegables; búsqueda de catálogos en el servidor, no listas de miles en el HTML |
| Archivos compartidos con Edwar | Conflictos en git | Rutas en archivo propio; las tareas que tocan `Componente`, `InicioController`, `sidebar`, `config/repositorio.php`, `bootstrap/app.php`, `DatabaseSeeder`, `AccionAuditoria`, `x-campo-archivo` y las pruebas de DOC-25 están marcadas; entregas pequeñas a `develop` |
| DOC-26 en producción sin DOC-27 | Un componente no completa a una persona que creó el otro | PA-14: salen juntas; DOC-26 sola solo en el servidor de pruebas |

## Documentación (al cerrar)

`documentacion/docs/context/domain.md`: RN-52 a RN-56 (el número lo fija quien actualice),
RN-49 (módulo nuevo, encendido en Básica y Estabilización), RN-04 y RN-06 (lecturas), RN-13
(excepción, si PA-15 queda en «sí»), «Parámetros del negocio» (dos filas). `glossary.md`.
`docs/FILAMENT.md`: regla 2 (los saltos de `ComponenteScope` de D1) y la tabla de pantallas.
Manual de usuario (sección Usuarios HC). `CHANGELOG.md`. Estado de la spec y
`documentacion/README.md`. `documentacion/fuentes/`: las dos tablas de SISPRO con su fecha (la
propone quien registre la fuente; las fuentes no se modifican después).

## Preguntas para Edwar

Del plan, no de la spec. Ninguna bloquea el arranque; cada una tiene su respuesta por defecto.

- **P-01. Pruebas de DOC-25.** Agregar un módulo que Calle no tiene rompe una aserción de
  `AccesoPorComponenteTest` («Calle tiene **todos** los módulos que existen»). DOC-25 dice que si
  una prueba cambia lo que verifica, se reporta. **Por defecto:** pasa a verificar «Calle tiene
  sus cuatro» (`MODULOS_CALLE`), que es lo que dice RN-49. UHC-01 la cambia, junto con
  `ModulosPorComponenteTest::parametrosEn()`.
- **P-02. Número de documento.** La spec dice «letras y números». **Por defecto:** solo letras y
  números, sin espacios, puntos ni guiones, hasta 20 caracteres. ¿Algún documento (pasaporte, PPT)
  trae guion?
- **P-03. Número de ficha.** ¿«a12» y «A12» son el mismo número? **Por defecto:** sí, como el
  documento (se ignoran mayúsculas y espacios de los extremos). Así el índice único se comporta
  igual en SQLite y en MySQL.
- **P-04. Motivo en la identificación (PA-04 al pie de la letra).** Agregar foto o alias a una
  ficha que no los tenía, ¿pide motivo? **Por defecto:** sí, todo cambio de identificación lo pide.
- **P-05. Constitución IV en las respuestas.** Cambiar una respuesta de la caracterización
  reemplaza su fila y el valor anterior queda en el historial (igual que cambiar una columna).
  **Por defecto:** así. La otra forma (marcar la fila como reemplazada) suma estado y consultas
  sin ganar nada que el historial no tenga.
- **P-06. Metadatos de la foto.** Las fotos de galería pueden traer coordenadas en el EXIF.
  **Por defecto:** el navegador vuelve a codificar siempre esta foto y eso las quita. Si el
  navegador no corre el script, la foto entra con sus metadatos; quitarlos en el servidor exige GD
  (está en producción, no en las pruebas). ¿Basta con el navegador?
- **P-07. CIE-10 en psiquiatría.** ¿Los dos campos de psiquiatría ofrecen todo el catálogo o
  solo el capítulo F (trastornos mentales)? **Por defecto:** todo, como dice la spec.
- **P-08. «Nombre parecido».** **Por defecto:** el mismo primer nombre y primer apellido (sin
  tildes ni mayúsculas), o el mismo alias, o el mismo número de documento con otro tipo. Sin
  comparación fonética (no hay una que funcione igual en todos los motores; candidato).
- **P-09. Búsqueda con varias palabras.** **Por defecto:** cada palabra tiene que estar en el
  nombre o el alias, en cualquier orden.
- **P-10. La «ñ».** **Por defecto:** se busca como «n» («Pena» encuentra a «Peña»), como hoy en
  MySQL. La spec pide decidirlo.
- **P-11. Fecha de ingreso al Sistema HC.** La spec no dice si puede ser futura. **Por defecto:**
  sin regla (la spec manda); si debe ser no futura, es una línea.
- **P-12. Las fotos en la spec.** §6 pone la foto de la persona en la **identificación** (la ve
  cualquier rol de un componente con el módulo), pero «Notas para el arquitecto» dice que «la
  caracterización, el historial **y las fotos**» se protegen con el componente de registro. El
  plan sigue §6 (el comportamiento manda sobre las notas): **la foto de la persona la ve todo
  componente con el módulo**; lo restringido son las fotos de estadías, que son de DOC-27. Si
  Edwar quiere lo de las notas, cambian `verFoto`, la lista (sin foto para quien no conoce a la
  persona) y CA-01.

De la investigación del CIE-10 y los países (2026-11-24):

- **P-13. Edad y sexo de cada código CIE-10.** SISPRO trae para cada código el sexo y el rango de
  edad en que vale (F900, TDAH, de 1 a 17 años; F840, autismo, de 1 a 9; 885 códigos para un solo
  sexo). Si se **bloquea** como el RIPS, a un adulto no se le podría registrar un TDAH, y no hay
  cómo cruzar el sexo con «No binario» o «Transgénero» ni la edad sin fecha de nacimiento. **Por
  defecto (lo que dice la spec):** los límites se guardan en el catálogo, pero no se valida ni se
  avisa. **Recomendación:** a lo sumo **avisar** («Este código suele usarse de 1 a 17 años»), sin
  bloquear. Avisar o bloquear es un cambio de la spec (un CA nuevo).
- **P-14. El Excel de SISPRO en el repositorio.** Se propone versionarlo en
  `database/data/catalogos/` (sin datos personales; así todos los ambientes cargan lo mismo y cada
  recarga se revisa). Ni SISPRO ni la OMS publican una licencia escrita para la CIE-10. **Por
  defecto:** se versiona, con la cita «Tabla de referencia CIE10, SISPRO, MinSalud, fecha», para
  uso interno. La otra opción es dejarlo en el servidor y cargarlo con el comando a mano, lo que
  rompe «el despliegue solo corre `migrate`».
- **P-15. Nombres de países con errores.** **Por defecto:** el código y el nombre oficial se
  guardan como vienen; se muestra un nombre corregido solo en los errores evidentes (BRAZIL,
  GUADALUOE, SUAZILANDIA, MACEDONIA, MYANMAR (FORMER BURMA), ISLAS SOLOMON, HAITI), con una lista
  corta y su razón en el código, que se reaplica en cada recarga. La otra opción es mostrarlos
  tal cual.
- **P-16. La spec y la CIE-11 (cambia la spec).** PA-06 y PA-13 dicen «cargada una vez»; con la
  transición a la CIE-11 (≈ 14-ago-2027) el plan los hace **recargables**. Se pide ajustar ese
  texto al aprobar. Además, los tres campos de diagnóstico dicen «Catálogo CIE-10»: antes de
  agosto de 2027 hará falta una spec que diga si se registra en CIE-11, en las dos (doble
  codificación, como el RIPS desde junio de 2026) o se convierten. Va a «Candidatos»; el plan no
  la construye, solo no la impide.
