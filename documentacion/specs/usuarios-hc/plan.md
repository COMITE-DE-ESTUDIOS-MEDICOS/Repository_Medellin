# Plan: Usuarios HC · ficha, caracterización, búsqueda y edición con historial (DOC-26)

> **Estado: Aprobado con la spec DOC-26 v1.1 (Edwar, 2026-11-24).** PA-09 y PA-18 bloquean solo
> el paso a producción.

| | |
|---|---|
| Spec | [spec.md](spec.md) **v1.1, aprobada** (Edwar, 2026-11-24). Tareas: [tasks.md](tasks.md) |
| Dueño de Producto | Edwar (aprueba la spec y este plan) |
| Redactó | José (desarrollador), sobre la v0.2. Corregido el 2026-11-24 (arquitecto) para alinearlo con la v0.3 y después con la v1.0 |
| Decisiones de Edwar que este plan recoge (2026-11-24) | PA-06 y PA-13 (catálogos cargados **una sola vez**), PA-14 (termina hacia el 15/12, producción con DOC-27), PA-17 (fotos solo al abrir la ficha), PA-20 (la ficha se escribe con guion, pero «F120» y «F-120» son **la misma ficha**: ajuste de Edwar del 2026-11-24, spec v1.1), PA-27 (el archivo de SISPRO va **fuera** del repositorio y se carga con un comando), PA-28 y P-17 (la respuesta anterior queda completa en el historial), PA-29 (el módulo lo enciende Edwar desde «Componentes y nodos») |
| Bloquean producción, no el desarrollo | PA-09 (retención) y PA-18 (autorización de tratamiento) |

Donde una decisión sale de una pregunta de la spec se marca **[PA-NN]**. Las líneas «si Edwar
responde distinto» quedan como rastro de la alternativa descartada. Las preguntas técnicas de este
plan están al final (P-15, P-18 y P-19 abiertas; P-17 resuelta).

**Base: DOC-25 construida** en todo lo que este plan usa: módulos por componente y la pantalla
«Componentes y nodos» donde el superadministrador los enciende (ACC-09), usuarios con componente
(ACC-07, ACC-08), avisos por componente (ACC-10) y auditoría por componente (ACC-11). De DOC-25
queda ACC-14 en producción (Edwar) y ACC-15, documentación (José, ~0,5 días); ninguna bloquea
DOC-26.

**DOC-27 depende de este plan:** necesita en `develop`, **el 30/11/2026**, la tabla y el modelo
(UHC-02), la Policy (UHC-04) y `scopeBuscar` (UHC-05). Las tareas están ordenadas para que eso
salga primero (tasks.md, «Calendario»).

## La idea en una línea

Una tabla `usuarios_hc` con el filtro de **dependencia** y sin el de componente; todo lo sensible
(caracterización, historial) cuelga de ella en tablas aparte, que solo se abren después de una
Policy que pregunta «¿es de mi dependencia y el componente donde estoy parado conoce a esta
persona?». La búsqueda compara columnas normalizadas en PHP a `[a-z0-9 ]` (nombres) y `[A-Z0-9]`
(documento y ficha), así no depende del motor. Lo demás ya existe: el módulo de DOC-25, `Auditor`, el disco del repositorio,
`x-campo-archivo` y Filament.

## Decisiones

### D1. Modelo de datos (una migración de esquema, fechada antes que las de DOC-27)

Todas las tablas nuevas cuelgan de `usuarios_hc`, que es la única con `dependencia_id`
(constitución III: «o colgar de algo que pertenece»). Ninguna lleva `SoftDeletes`.

**Una sola migración de esquema**, `2026_11_25_000001_crear_usuarios_hc.php`, con todas las
tablas de DOC-26, **también las de los catálogos** (vacías hasta que se carguen, D9). Así todo el
esquema de DOC-26 queda fechado antes que las migraciones de DOC-27 (que van desde el 1/12). La
carga de los catálogos no es una migración: es un comando que Edwar corre en cada servidor (D9).

**`usuarios_hc`** (la ficha; modelo `UsuarioHc`, `#[ScopedBy([DependenciaScope::class])]`):

| Columna | Para qué |
|---|---|
| `dependencia_id` FK | Aislamiento por dependencia |
| `componente_registro_id` FK `componentes` | Componente de registro (PA-03). Se llena con `ContextoDependencia::componenteId()` al crear, **nunca desde el formulario**, y no está en `$fillable` (CA-20) |
| `tipo_documento`, `tipo_documento_texto` | Código de la lista y el texto que se vio (§7) |
| `numero_documento` (20, nulo) · `documento_normalizado` (20, nulo) | Como se escribió, sin espacios en los extremos (CA-08) · en mayúsculas, solo `[0-9A-Z]` (D5, CA-28) |
| `numero_ficha` (30, nulo) · `ficha_normalizada` (30, nulo) | Lo escrito, en mayúsculas, sin espacios en los extremos y **con sus guiones**: es lo que se muestra (PA-20) · igual que el documento, **sin guion**: solo `[0-9A-Z]` (D5, CA-29). «F-120» y «F120» dan `F120` |
| `primer_nombre`, `segundo_nombre`, `primer_apellido`, `segundo_apellido`, `alias` | Identificación |
| `nombre_completo` | Se arma en `saving` (partes que existan, un espacio). Para mostrar y ordenar |
| `nombre_busqueda`, `alias_busqueda` | Normalizados a `[a-z0-9 ]` (D5) |
| `estado_vital` (`viva`/`fallecida`, por defecto `viva`) · `fecha_fallecimiento` (nula) | Estados fijos, no lista configurable |
| `foto_id` (nulo, con índice, **sin FK**) | La foto vigente. Sin restricción porque `usuario_hc_fotos` apunta a `usuarios_hc` (circular); la relación exige además que la foto sea de la ficha |
| `revision` (entero, 0) | Concurrencia (D7) |
| `creado_por`, `actualizado_por` FK `users` · `timestamps` | Quién la creó y cuándo |

Índices: **único** `(dependencia_id, tipo_documento, documento_normalizado)` y **único**
`(dependencia_id, ficha_normalizada)`. Admiten varios `NULL` en SQLite y en MySQL: «Sin
documento» y la ficha vacía no chocan (CA-05, CA-06). Como las dos columnas solo guardan ASCII
en mayúsculas `[0-9A-Z]`, `utf8mb4_unicode_ci` y SQLite comparan igual (CA-28, CA-29). El guion
nunca llega a la columna única: «F-120» y «F120» chocan por igual en los dos motores; UHC-17 lo
comprueba contra MySQL. El índice es la última defensa; la validación da antes el mensaje claro. Índice `(dependencia_id,
nombre_completo)` para ordenar la lista.

**`usuario_hc_identidades`** (identidades anteriores, D5): `usuario_hc_id`, `campo` (`nombre`,
`documento`, `ficha`), `valor` (como se veía), `valor_busqueda` (normalizado), `user_id`,
`componente_id`, `created_at`. Índice `(campo, valor_busqueda)`. DOC-31 agregará las de la ficha
absorbida sin cambiar la tabla.

**`usuario_hc_caracterizaciones`** (1 a 1, los campos que no son listas): `usuario_hc_id` único,
`fecha_nacimiento`, `fecha_ingreso_sistema`, `calle_anios`, `calle_meses`, `calle_semanas`
(enteros sin signo, nulos: vacío es «no informado», CA-09, CA-11). En tabla aparte **a
propósito**: la lista y la búsqueda consultan `usuarios_hc` y nunca tocan una columna sensible
(CA-21).

**`usuario_hc_respuestas`** (la respuesta **vigente** de cada lista, de una o de varias
opciones): `usuario_hc_id`, `campo`, `fuente` (la lista de la configuración, `cie10`, `paises`,
`comunas`), `opcion` (el código), `texto` (el que vio quien la registró), `principal` (bool,
solo medicina general), `timestamps`. Único `(usuario_hc_id, campo, opcion)`; índice `(campo,
opcion)` para contar después (DOC-28/29).

- Cumple §7 sin JSON: el **código** permite contar una opción aunque cambie de nombre; el
  **texto** se muestra siempre, aunque la opción se renombre o se retire (CA-18, CA-33).
- Una o varias opciones se guardan igual: una fila por opción elegida; limpiar es no tener fila.
- Sin FK a los catálogos: lo registrado no depende de lo que diga el catálogo después.
- `fuente` deja la puerta abierta a la CIE-11 (PA-26): cada diagnóstico dice con qué
  clasificación se registró. No se construye nada de la CIE-11 ahora.
- **Cambiar o quitar una respuesta [PA-28, P-17 resuelta]:** la fila de la respuesta vigente se
  reemplaza y la anterior queda completa en `usuario_hc_cambios`, con `codigo_antes`/`codigo_despues`
  y el texto (D7, CA-34). El historial es el registro de verdad: así se cumple «nada se borra».
  `usuario_hc_respuestas` no lleva columna de estado.

**`usuario_hc_cambios`** (historial de la ficha): `usuario_hc_id`, `user_id`, `componente_id`,
`seccion` (`identificacion`, `caracterizacion`, `foto`), `campo`, `codigo_antes`,
`codigo_despues` (nulos; el código de la opción en las listas, PA-28), `antes`, `despues` (texto
legible), `motivo` (nulo solo donde PA-04 no lo pide), `created_at`.

- Una fila por campo cambiado; en las listas de varias opciones, **una fila por opción que sale
  o entra** (§5). Las de un mismo guardado comparten autor, motivo y hora.
- Sin JSON y sin agrupación para mostrarlo (CA-14).

**`usuario_hc_fotos`**: `usuario_hc_id`, `ruta`, `tamano`, `subida_por`, `created_at`. Siempre
JPEG (D10), por eso no guarda el tipo. Cambiar la foto crea una fila y mueve
`usuarios_hc.foto_id`; quitarla pone `foto_id` en nulo. Ninguna fila ni archivo se borra
(constitución IV, CA-22).

**Catálogos (D9)**, sin `dependencia_id`: datos de referencia externos, iguales para todos
**[PA-13]**.

- `catalogo_cie10`: `codigo` (4, único: `F200`, `F03X`), `nombre` (texto oficial tal cual, 255),
  `nombre_busqueda`, `habilitado`.
- `catalogo_paises`: `codigo` (3, **texto**, único: `004`, `170`), `nombre` (el que se
  muestra), `nombre_oficial` (el de SISPRO), `nombre_busqueda`, `habilitado`.
- Sin sexo ni edad por código: la spec no los usa (PA-25). Si un día se avisa por ellos, se
  agregan con la recarga (candidato).

Modelos: `UsuarioHc`, `IdentidadUsuarioHc`, `CaracterizacionUsuarioHc`, `RespuestaUsuarioHc`,
`CambioUsuarioHc`, `FotoUsuarioHc`, `Cie10`, `Pais`. Solo `UsuarioHc` lleva scope; los demás se
leen siempre por su relación con la ficha, después de la Policy.

**Trampa de DOC-25:** `Componente` lleva `ComponenteScope('id')`. La relación
`UsuarioHc::componenteRegistro()` va con `->withoutGlobalScope(ComponenteScope::class)`; si no,
un usuario parado en Básica vería vacía la columna «componente» de las fichas de Estabilización
(CA-02). Lo mismo el componente en el historial y las opciones del filtro. Son **saltos a
propósito y por escrito** (DOC-25 D4): van a `docs/FILAMENT.md`, regla 2.

### D2. El módulo `usuarios_hc` [PA-29]

- **`Componente::MODULOS`**: al final, `'usuarios_hc' => ['Usuarios HC', 'usuarios-hc.index']`.
  La clave no choca con ningún parámetro de ruta (el de la ficha es `{usuarioHc}`) y en las rutas
  va entre comillas (`"can:modulo,'usuarios_hc'"`), la lección de `supervisiones`.
- **Encenderlo: sin migración ni seeder.** Lo hace Edwar como superadministrador en «Componentes
  y nodos» de cada servidor (ACC-09): la casilla sale sola porque
  `App\Livewire\Turnos\Componentes` arma las opciones desde `Componente::MODULOS` (CA-35).
- **Pruebas de DOC-25 que cambian** (archivos de Edwar; los toca él en UHC-01):
  - `tests/Feature/AccesoPorComponenteTest.php:45`,
    `assertSame(array_keys(Componente::MODULOS), $componentes['calle']->modulos)`, deja de ser
    cierta: Calle no tiene Usuarios HC. **Se quita la línea**: la 44 ya dice que Calle tiene sus
    cuatro (lo mismo que `AccesoPorComponenteSeeder::MODULOS_CALLE`). **Cambia lo que verifica**:
    de «Calle tiene todos los módulos que existen» a «Calle tiene sus cuatro», que es lo que dice
    RN-49.
  - `ModulosPorComponenteTest`: `parametrosEn()` agrega `usuarioHc` (una ficha en la dependencia)
    y la lista de Policies agrega `UsuarioHc::class`. Con eso, la prueba que recorre cada ruta
    `GET` de cada módulo cubre sola todas las de Usuarios HC (403 en Básica sin el módulo) y exige
    que el grupo exista (línea 112). Su Calle de fábrica tiene todos los módulos, así que
    `usuarios-hc.index` tiene que abrir para Coordinación de Calle (línea 151).
  - Sin cambio: `AccesoPorComponenteTest:139` y `:222` y `DatosDemoPorComponenteTest` (la fábrica
    sigue con todos los módulos).
- **Rutas:** archivo propio `routes/usuarios-hc.php`, cargado en `bootstrap/app.php` como el de
  turnos, con `['web', 'auth', 'usuario.activo', 'dependencia', "can:modulo,'usuarios_hc'"]`,
  prefijo `usuarios-hc` y nombres `usuarios-hc.*`.

  | Método y dirección | Qué | Permiso de la ruta | Tarea |
  |---|---|---|---|
  | `GET usuarios-hc` | Lista (tabla Filament) | `can:viewAny,App\Models\UsuarioHc` | UHC-01 (vacía), UHC-06 |
  | `GET usuarios-hc/nuevo` · `POST usuarios-hc` | Crear (con la foto, si viene) | `can:create,…` | UHC-07 |
  | `POST usuarios-hc/coincidencias` | Aviso de coincidencias mientras se escribe (HTML parcial, solo identificación). **`POST`**, para que documentos y nombres no queden en los registros de nginx | `can:create,…` + `throttle:30,1` | UHC-07 |
  | `GET usuarios-hc/{usuarioHc}` | Ficha: identificación con la foto | `can:view,usuarioHc` | UHC-11 |
  | `GET usuarios-hc/{usuarioHc}/caracterizacion` | Caracterización: **una sola pantalla** para ver y editar (D8), auditada | `can:verCaracterizacion,usuarioHc` | UHC-14 |
  | `GET usuarios-hc/{usuarioHc}/historial` | Historial, auditado | `can:verCaracterizacion,usuarioHc` | UHC-11 |
  | `GET …/{usuarioHc}/editar` · `PUT …/{usuarioHc}` | Corregir identificación, estado vital y foto | `can:update,usuarioHc` | UHC-12, UHC-09 |
  | `GET …/{usuarioHc}/foto` | La foto vigente, auditada, sin caché | `can:view,usuarioHc` | UHC-09 |

  `{usuarioHc}` con `whereNumber`. La vinculación usa el scope de dependencia: una ficha de otra
  dependencia da **404** (CA-31). No hay ruta firmada ni miniatura (PA-17).
- **Menú** (`sidebar.blade.php`): una línea en el grupo `principal`, con
  `Gate::allows('modulo', 'usuarios_hc')`, para todos los roles (Lectura incluida).
- **Llegada** (`InicioController`): hoy solo Documentos se abre desde Lectura. La condición pasa
  a `in_array($modulo, ['documentos', 'usuarios_hc'], true) || $contexto->rol()?->puedeEditar()`.
  Un usuario de Básica de cualquier rol llega a Usuarios HC; Calle sigue llegando a Documentos
  (CA-01).

### D3. Permisos: `UsuarioHcPolicy` [PA-02, PA-03, PA-11]

**Primero la dependencia y el módulo, en un `before` para todos los métodos (CA-31).** La
Policy usa `ExigeModulo` (`const MODULO = 'usuarios_hc'`) y redefine su `before` (Laravel le
pasa la ficha como tercer argumento):

```php
use ExigeModulo { before as exigeModulo; }

public function before(User $usuario, string $habilidad, mixed $ficha = null): ?bool
{
    if ($this->exigeModulo($usuario) === false) {
        return false;
    }

    return $ficha instanceof UsuarioHc && $ficha->dependencia_id !== app(ContextoDependencia::class)->id() ? false : null;
}
```

Sin el módulo se niega todo, también al superadministrador; una ficha de otra dependencia se
niega en **todos** los métodos (`view`, `verCaracterizacion`, `update`), no solo en `view`. Es la
segunda defensa: la primera es el 404 de la vinculación por el scope. Después, el rol:

| Método | Regla (DOC-26 sola) |
|---|---|
| `viewAny`, `view` | Cualquier rol de la dependencia. La foto va con `view`: es identificación (§6) |
| `create` | `puedeEditarEn` y hay componente en el contexto (será el de registro) |
| `verCaracterizacion` (caracterización e historial) | `puedeEditarEn` **y** (coordinador líder **o** `$ficha->conocidaPor($componenteId)`). Lectura, nunca **[PA-11]** |
| `update` (identificación, estado vital, foto y caracterización) | `puedeEditarEn` **y** (coordinador líder **o** `$ficha->editablePor($componenteId)`) |

- «Coordinador líder» = `rolEn(dependencia) === Administracion` (el superadministrador ya
  devuelve Administración en `rolEn`).
- Las dos preguntas del componente viven en el modelo, en un solo lugar, para que DOC-27 las
  cambie sin tocar la Policy:
  - `conocidaPor(int $id)`: con DOC-26 sola, `componente_registro_id === $id` **[PA-03]**. Con
    DOC-27 (UHC-19), también si tiene una estadía activa o egresada en ese componente.
  - `editablePor(int $id)`: con DOC-26 sola, igual. Con DOC-27: la estadía activa está en ese
    componente; si nunca tuvo estadía válida, el de registro; si tuvo y hoy no está activa,
    nadie salvo el líder.
- Rutas, controladores (`$this->authorize`) y cada acción de Filament (`->authorize()`) pasan
  por aquí. Guardar vuelve a autorizar (CA-13).
- **Livewire restaura los modelos sin los scopes globales** (`newQueryForRestoration`). Por eso
  los componentes de Usuarios HC no guardan la ficha como propiedad: guardan `public int
  $fichaId` y la buscan con `UsuarioHc::findOrFail()` (con scope) en cada petición. Una ficha de
  otra dependencia da 404 también al guardar (CA-31).
- [PA-01] Si Edwar no acepta que la búsqueda cruce componentes: `viewAny`/`view` y la consulta de
  la lista se limitan a `conocidaPor`, y el aviso de coincidencias solo ve su componente.
- [PA-02] Si pide permisos sueltos: una columna booleana en `dependencia_usuario` (como
  `aprueba_novedades`) y una condición más en `update`; es otra tarea.

### D4. Auditoría (CA-17, CA-30)

Cuatro acciones nuevas en `AccionAuditoria`, todas con `Auditor::registrar` y la ficha como
auditable:

| Acción | Cuándo | `datos` |
|---|---|---|
| `UsuarioHcCreado` («Creó un Usuario HC») | Al crear | `[]`; la descripción dice el nombre |
| `UsuarioHcActualizado` («Corrigió un Usuario HC») | Cada guardado de identificación, caracterización o foto | **`['cambios' => [etiquetas de los campos], 'motivo' => …]`**, sin valores |
| `UsuarioHcConsultado` («Abrió la caracterización de un Usuario HC») | Abrir la caracterización o el historial | `['parte' => 'caracterizacion'|'historial']` |
| `UsuarioHcFotoVista` («Vio la foto de un Usuario HC») | **Cada** vez que se pide la foto | `[]` |

- **Sin valores en la auditoría general (CA-30):** el mismo formato que
  `Admin\Turnos\DerivacionController:245`. Los valores quedan solo en `usuario_hc_cambios`,
  detrás de `verCaracterizacion`. Además, la pantalla de Auditoría
  (`resources/views/auditorias/index.blade.php:77`) hace `implode` de cada valor de `datos`: un
  arreglo anidado (`['cambios' => [campo => ['antes' => …]]]`, el formato del plan anterior) la
  rompe con un error 500. La prueba de CA-30 abre esa pantalla con un registro de Usuarios HC.
- `auditorias.datos` ya es JSON y aquí solo se escribe; nada consulta dentro de él.
- Sin miniaturas ni registro por página (PA-17): la lista no muestra fotos.
- La pantalla de Auditoría no cambia: Coordinación sigue viendo solo lo de los usuarios de su
  componente (ACC-11), que es lo que pide §6.1.
- **La carga de los catálogos no pasa por `Auditor::registrar` y no es una excepción a la
  constitución IV.** No la hace un usuario desde la aplicación, sino Edwar con un comando en el
  servidor (D9), y no cambia datos de nadie: llena una vez tablas de referencia vacías. El rastro
  queda en dos lugares: el log del servidor (`Log::info` con el archivo, la cantidad de códigos y
  la fecha) y la sección de la versión en `CHANGELOG.md`, que dice qué archivo de SISPRO se cargó
  y de qué fecha. No sale en la pantalla de Auditoría.

### D5. Búsqueda normalizada, igual en cualquier motor (CA-03, CA-04)

**Dos funciones, en `UsuarioHc`, que usan la ficha, las identidades y los catálogos:**

```php
public static function normalizarTexto(?string $t): string   // nombres, alias, nombres de catálogo
{
    return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii((string) $t))));
}

public static function normalizarCodigo(?string $t): string  // documento, ficha y CIE-10
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii((string) $t)));
}
```

- «Peña Álvarez, J.» → `pena alvarez j`; «12.345 67» → `1234567`; «F20.0» → `F200`; la ficha
  «f-120 », «F-120» y «F120» → `F120` (PA-20, spec v1.1). La «ñ» cuenta como «n» (PA-23) y los
  signos como espacio (PA-22).
- **Un solo normalizador de código** para documento y ficha: no hay variante con guion. La ficha
  lo usa en `ficha_normalizada`, en el `valor_busqueda` de las identidades con campo `ficha`, en
  el término de la búsqueda y en la unicidad. `numero_ficha` guarda lo escrito con sus guiones
  (en mayúsculas y recortado, D6) solo para mostrarlo; nada compara contra esa columna.
- Se calculan en `saving`: `nombre_busqueda`, `alias_busqueda`, `valor_busqueda` de las
  identidades y `nombre_busqueda` de los dos catálogos (D1; no existe una columna
  `descripcion_busqueda`).
- **Nada que escapar en `LIKE`:** el término se normaliza con la misma función y solo puede
  traer `[a-z0-9 ]` o `[A-Z0-9]` (la ficha además se compara con `=`); nunca `%` ni `_` (su escape no funciona igual en SQLite y en
  MySQL). Las columnas guardan ASCII, así que `LIKE` da lo mismo en los dos motores sin collation
  ni columnas generadas.
- **La validación no usa estas funciones** (D6): el documento y la ficha se validan sin quitar
  tildes ni «ñ», para rechazarlas en vez de convertirlas en silencio (CA-28).
- Si el término queda vacío al normalizar («...»), la búsqueda no filtra.

`UsuarioHc::scopeBuscar(Builder $query, string $texto)`, que también usará DOC-27:

- **Nombre y alias** [PA-22]: cada palabra tiene que estar (`LIKE %palabra%`) en
  `nombre_busqueda` o en `alias_busqueda`, en cualquier orden.
- **Documento:** `documento_normalizado LIKE %TÉRMINO%`.
- **Ficha:** `ficha_normalizada = TÉRMINO` (exacta).
  Del mismo texto salen **dos términos**: palabras para nombre y alias, y código sin signos para
  el documento y la ficha. Buscar «F120» o «f-120» encuentra la ficha «F-120», que se muestra
  como se escribió (PA-20, spec v1.1).
- **Identidades anteriores:** `usuarios_hc.id IN (SELECT usuario_hc_id FROM
  usuario_hc_identidades WHERE …)`, con la misma regla por campo. Una subconsulta, sin juntas ni
  `GROUP BY`; el scope de dependencia queda en la consulta de afuera.
- **Ficha reasignada:** antes, un `exists` sobre `ficha_normalizada`. Si alguien tiene ese
  número vigente, las identidades con campo `ficha` no entran; si nadie lo tiene, sí.
- **«Coincide con un dato anterior»:** para las filas de la página, si el término no casa con
  sus datos vigentes, la lista dice «Coincide con un nombre anterior» (o documento, o ficha), sin
  mostrar el valor viejo (CA-04).

**Rendimiento:** `LIKE '%…%'` recorre la tabla; con miles de fichas basta. La ficha exacta usa
su índice.

**CA-03 en los dos motores:** UHC-05 corre `UsuariosHcBusquedaTest` también contra MySQL, con
una base de pruebas aparte y sin tocar el `.env`, como en ACC-14:
`DB_CONNECTION=mysql DB_DATABASE=documenta_pruebas php artisan test --filter=UsuariosHcBusquedaTest`.
Va antes del 30/11 porque DOC-27 usa este scope.

### D6. Crear, coincidencias y unicidad (CA-05 a CA-08, CA-21, CA-28, CA-29)

- **Pantalla:** Blade y controlador (`UsuarioHcController@create/store`), como Personas de campo,
  porque la foto va en el mismo formulario y `x-campo-archivo` ya reduce la imagen en el
  navegador. La foto es opcional.
- **Validación** (`GuardarUsuarioHcRequest`, compartida con la corrección):
  - `tipo_documento` en las opciones vigentes; **al corregir, también el tipo que la ficha ya
    tiene aunque se haya retirado** (CA-18).
  - `numero_documento`: obligatorio salvo «Sin documento», prohibido con «Sin documento»
    (`prohibited_if`). Antes de validar se recorta; la regla es
    `regex:/^[0-9A-Z]{1,20}$/` sobre `strtoupper(trim(...))`, **ASCII, sin la bandera `u`**: no
    `alpha_num`, que acepta letras Unicode (tildes, «ñ») y en MySQL `utf8mb4_unicode_ci` trata
    «Ñ» y «N» como iguales y SQLite no [PA-19]. Mensaje: «Escribe el número solo con letras y
    números, sin puntos, guiones ni espacios».
  - `numero_ficha`: antes de validar, `strtoupper(trim(...))`, y eso es lo que se guarda (con sus
    guiones). Reglas: `regex:/^[0-9A-Z-]{1,30}$/` [PA-20] (A–Z sin tildes ni «ñ», 0–9 y «-») y
    `regex:/[0-9A-Z]/` (al menos una letra o un número: «---» se rechaza). Mensajes: «Escribe la
    ficha solo con letras, números y guiones, sin puntos ni espacios» y «La ficha necesita al
    menos una letra o un número». `ficha_normalizada` es `normalizarCodigo($ficha)`: quita los
    guiones y cabe en 30.
  - Unicidad de tipo + documento normalizado y de ficha normalizada (sin guion: «F120» choca con
    «F-120») en **toda la dependencia**
    (la ficha no tiene `ComponenteScope`), ignorando la propia al corregir (CA-06). La
    `QueryException` del índice único (carrera entre dos personas) da el mismo mensaje.
- **Mensaje de repetido** (CA-21): «Ya existe una ficha con ese documento: Juan Pérez, ficha
  F-120» (la ficha como se escribió), con enlace a la ficha. Solo identificación.
- **Coincidencias mientras se escribe** (CA-07, PA-21): `POST usuarios-hc/coincidencias` con
  primer nombre, primer apellido, alias, tipo, documento y ficha; devuelve un HTML parcial con
  hasta 10 fichas (nombre, documento, ficha, componente de registro). Coinciden: el mismo
  documento con otro tipo, la misma ficha vigente, el mismo primer nombre y primer apellido
  normalizados, o el mismo alias. Avisa; solo bloquean el documento o la ficha repetidos, y eso
  lo hace la validación al guardar.
- **Al guardar:** `componente_registro_id`, `creado_por` y el texto del tipo de documento los
  pone el servidor; `estado_vital = viva`. Auditoría `UsuarioHcCreado`.

### D7. Editar con motivo, historial y concurrencia (CA-13 a CA-15, CA-19, CA-20, CA-30, CA-34, CA-37)

Un servicio, `App\Services\FichasUsuariosHc`, hace todo guardado en una transacción: autorizar →
comprobar la revisión → comparar → exigir motivo → guardar → historial → auditoría. Lo usan el
controlador (identificación y foto) y el componente Livewire (caracterización).

- **Concurrencia:** cada formulario lleva la `revision` con que se abrió. Al guardar,
  `UsuarioHc::whereKey($id)->where('revision', $enviada)->increment('revision')`: una sola
  sentencia atómica en cualquier motor. Si devuelve 0, se rechaza con «Otra persona cambió esta
  ficha mientras la editabas. Recarga la página para ver sus cambios», sin guardar (CA-15).
- **Motivo [PA-04]:** identificación (incluidos foto y estado vital), siempre. Caracterización,
  si cambia un campo que ya tenía valor; llenar uno vacío no lo pide, pero queda en el historial.
  Si Edwar responde «motivo siempre», se quita la comparación.
- **Historial:** una fila de `usuario_hc_cambios` por campo, o por opción que sale o entra en las
  de varias, con `codigo_antes`/`codigo_despues` y los textos (CA-14, CA-34). Componente: el del
  contexto (para el líder, en el que está parado).
- **Respuestas de listas [PA-28, P-17 resuelta]:** en la misma transacción, primero la fila del
  historial con `codigo_antes`/`codigo_despues` y los textos, después se reemplaza la fila vigente.
  Si falla una, no queda ninguna.
- **Identidades anteriores:** si cambian el nombre completo, el documento (tipo o número) o la
  ficha, el valor anterior va a `usuario_hc_identidades` (CA-04). El número de ficha viejo queda
  libre para otra persona (§1).
- **Auditoría:** `UsuarioHcActualizado` con las etiquetas de los campos y el motivo (D4).
- **Componente de registro:** no se toca (CA-20).
- **Estado vital, con DOC-26 sola:** «Fallecida» con motivo e historial, sin avisos (CA-19). El
  aviso llega con UHC-21.

### D8. Caracterización: listas en la configuración y una sola pantalla (CA-09 a CA-12, CA-18, CA-33, CA-34)

- **Qué campos hay** es código (un campo nuevo cambia la spec, §7): constante
  `UsuarioHc::CAMPOS`, con etiqueta, tipo (`una`, `varias`, `varias_con_principal`) y de dónde
  salen las opciones (una lista de la configuración, `comunas`, `catalogo:paises`,
  `catalogo:cie10`). Las fechas y el tiempo en calle son columnas de
  `usuario_hc_caracterizaciones`.
- **Listas cortas** en `config/repositorio.php` **[PA-05]**, un bloque `usuarios_hc` junto a
  `comunas`:

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
    «Ninguna»/«Sin información» entre sí (CA-10), sin un `if` por campo.
  - Opciones que se ofrecen = `opciones` − `retiradas` + las que **esta ficha ya tiene** (con su
    texto guardado y «(retirada)»). La validación acepta un código vigente o uno que la ficha ya
    tenía (CA-18).
  - Comuna: `repositorio.comunas`, la que ya usan los casos (DOC-23), sin copiarla.
  - EPS **[PA-07]**: si Edwar no autoriza la EPS, se quitan la lista y el campo.
- **Una sola pantalla** (`GET …/caracterizacion`): el controlador autoriza
  `verCaracterizacion`, registra `UsuarioHcConsultado` una vez por apertura y muestra
  `App\Livewire\UsuariosHc\Caracterizacion` (con `$fichaId`, D3), un formulario de Filament
  dentro del layout de Documenta. Si el usuario no puede `update`, el formulario va
  **deshabilitado** (`->disabled()`) y sin botón de guardar. Con DOC-26 sola, quien ve la
  caracterización también la edita; la pantalla deshabilitada sirve desde DOC-27 (quien conoce a
  la persona por una estadía egresada). La acción de guardar lleva su `->authorize('update', …)`
  (fuera de un panel, cada acción necesita la suya).
- **Campos:** `Select` con `placeholder` (se limpia), `CheckboxList` para las de varias,
  `Select::searchable()` con `getSearchResultsUsing` para países y CIE-10 (busca en el servidor
  por `nombre_busqueda` y por `codigo` con `normalizarCodigo`; solo `habilitado`), medicina
  general con `Select::multiple()` más un `Radio` de «principal». Los closures reciben `$state`,
  `$get`, `$search` por nombre (lección de Filament).
- **Texto guardado en los selectores (CA-33):** para los códigos que la ficha ya tiene, la
  etiqueta sale de `usuario_hc_respuestas.texto`, no del catálogo: `getOptionLabelUsing` en los
  de una opción y **`getOptionLabelsUsing`** en los `Select::multiple()` (sin él, un código que
  ya no está habilitado sale como el código pelado o vacío). La validación acepta un código
  habilitado o uno que la ficha ya tiene. Guardar sin tocar el campo lo conserva.
- **Reglas:** todo opcional; fecha de nacimiento no futura (el día en `America/Bogota`,
  `repositorio.horario.zona`); fecha de ingreso al sistema sin regla (PA-24); tiempos enteros
  desde 0, sin convertir ni rellenar; medicina general con diagnósticos exige un principal;
  «Otros diagnósticos de psiquiatría» sin principal **[PA-08]**; los diagnósticos no se validan
  por edad ni sexo (PA-25). La **edad** es un accessor (años cumplidos, o «Sin información»).
- **Sin catálogo cargado,** los tres campos de diagnóstico y la nacionalidad no se muestran
  (`Cie10::query()->exists()`, `Pais::query()->exists()`): DOC-26 puede salir sin ellos (PA-06).

### D9. Catálogos CIE-10 y países: un comando, una sola vez, archivo fuera del repositorio [PA-06, PA-13, PA-27] (CA-36)

Base: [investigacion-cie10-y-paises.md](investigacion-cie10-y-paises.md) (2026-11-24).

- **Fuentes:** tablas de referencia **«CIE10»** (12.634 códigos, 22-oct-2026) y **«Pais»** (249
  países, 3-oct-2018) de SISPRO, las del RIPS. **El archivo no entra al repositorio [PA-27]**: en
  `documentacion/fuentes/` se anota solo la referencia (nombre, fecha de SISPRO, de dónde se bajó
  y cómo se convirtió). No hay `database/data/catalogos/` ni migración que cargue datos.
- **Una sola vez, sin recarga:** no hay `upsert`, ni desactivación de códigos ausentes, ni
  auditoría `CatalogoHcCargado`. El comando **se niega si la tabla ya tiene filas** («El
  catálogo ya está cargado; recargarlo es una spec aparte, PA-26»). La recarga es candidata; si
  un día se aprueba, lleva un tope (ver «Candidatos»).
- **Nadie ha visto el archivo todavía; sigue pendiente su formato real.** SISPRO lo manda por
  correo al exportar; puede llegar como `.xls` antiguo o como HTML con extensión de Excel, y
  **OpenSpout (que ya está, vía Filament) no lee `.xls`**. Por eso **no se escribe la carga hasta
  tener el archivo en la mano**. Si llega en `.xls` o HTML, se abre en Excel o LibreOffice y se
  guarda como `.xlsx` o CSV UTF-8 (un paso de una persona, anotado en `fuentes/`). El esquema de
  las tablas ya está en UHC-02.
- **El comando** `php artisan catalogos-hc:cargar {cie10|paises} {archivo}`
  (`App\Console\Commands\CargarCatalogoHc`, en UHC-15), que Edwar corre a mano una vez en cada
  servidor con la ruta del archivo, como enciende el módulo. Toda la lógica vive en el comando;
  no hay clase aparte:
  - se niega si la tabla ya tiene filas (arriba);
  - lee `.xlsx` o CSV con OpenSpout;
  - **columnas por su contenido, no por el encabezado** (los `Extra_…` de SISPRO no traen lo que
    dice su etiqueta): CIE-10 `Codigo`, `Nombre`, `Habilitado`; países `Codigo` (ISO numérico,
    texto de 3), `Nombre`;
  - **comprueba antes de escribir:** `F200` es «ESQUIZOFRENIA PARANOIDE», `170` es Colombia, y el
    archivo trae al menos 12.000 códigos (CIE-10) o 240 países. Si algo no cuadra, se detiene sin
    tocar nada y dice qué;
  - texto oficial tal cual (mayúsculas, sin tildes, con sus errores); solo agrega
    `nombre_busqueda`. Códigos como SISPRO (4 caracteres, sin punto, con X);
  - inserta por tandas de 500 filas (`chunk`) en una transacción, para no armar una sentencia
    gigante;
  - al terminar, `Log::info('Catálogo cargado', ['catalogo' => …, 'archivo' => …, 'codigos' =>
    …])` (el log trae la fecha) y la misma línea en la consola. Ese es el rastro, junto con la
    nota en `CHANGELOG.md` de la versión (D4).
- **Países: nombres con errores** (P-15): las correcciones son una constante del comando.
- **Pruebas:** la suite no carga el archivo de SISPRO (no está en el repositorio, y serían 12.634
  filas en cada corrida). Cada prueba de la caracterización crea los códigos que necesita.
  `CatalogosHcTest` llama al comando con `$this->artisan(...)` sobre CSV que la prueba escribe en
  un directorio temporal: uno válido (sintético, con F200, F03X, U85X y 170, y relleno hasta la
  cantidad mínima), uno sin F200, uno corto. Prueba la comprobación (no escribe nada si falla), que
  no carga dos veces, la línea del log (`Log::spy()`) y que una respuesta guardada conserva su
  texto. Sin opción oculta para bajar el mínimo.

### D10. Fotos [PA-15, PA-17, PA-10 resuelta] (CA-17, CA-22, CA-32)

- **Validar:** `mimetypes` y extensiones de `repositorio.formatos.imagenes` (JPG, JPEG, JFIF, PNG,
  WEBP), `max:repositorio.tamano_maximo_kb` y lado máximo
  `repositorio.imagen.lado_maximo_servidor`. La regla del lado hoy es un closure protegido de
  `EnvioPublicoController::ladoMaximoDeImagen()`; se saca a `App\Rules\LadoMaximoImagen` y ese
  controlador la usa (mismo mensaje; sus pruebas lo cubren).
- **Quitar los metadatos en el servidor (CA-32):** antes de guardar, la imagen se vuelve a
  codificar con GD (`imagecreatefromstring` → `imagejpeg($imagen, null, 85)`), bajo
  `function_exists('imagecreatefromstring')` como en `app/Support/Xlsx.php:220`. Volver a
  codificar descarta el EXIF (coordenadas incluidas). Siempre se guarda JPEG, también si llegó
  PNG o WEBP. GD está en local y en producción (checklist de CLAUDE.md). **Sin GD, la foto se
  rechaza** («El servidor no puede procesar fotos en este momento; avisa a soporte») en vez de
  guardarla con su ubicación (P-18).
- **Guardar:** `FichasUsuariosHc::cambiarFoto` en `Storage::disk(config('repositorio.disco'))`,
  ruta `usuarios-hc/{dependencia}/{ficha}/{uuid}.jpg`, fuera de la web. Nada en `documentos` ni en
  carpetas: no aparece en Documentos (RN-51). Fila nueva en `usuario_hc_fotos`, `foto_id` nuevo,
  historial «Foto: cambió» con motivo, revisión. Quitar: `foto_id` a nulo, con motivo e historial.
- **Servir:** `UsuarioHcFotoController@ver` responde el archivo con `Content-Type: image/jpeg`,
  `Content-Disposition: inline`, `X-Content-Type-Options: nosniff`, la CSP de
  `DescargaController::previsualizar` (`default-src 'none'; object-src 'self'; img-src 'self'`)
  y **`Cache-Control: no-store`** (el plan anterior decía `private`, que sí deja guardarla).
  Audita `UsuarioHcFotoVista` cada vez (D4). La ficha la muestra con un `<img>` a esta ruta: cada
  apertura de la ficha con foto deja un registro.
- **Sin sello [PA-15], UHC-10:** `x-campo-archivo` **sella aunque se le pase `:sello="null"`**:
  la vista escribe `data-sello=""` y `resources/js/campo-archivo.js:381` hace
  `campo.dataset.sello || 'Evidencia'`. Hay que cambiar la vista y el JS:
  - la vista no escribe `data-sello` cuando `$sello` es `null`;
  - sin `data-sello`, la foto de cámara no se estampa y el botón de cámara no pide ubicación
    (hoy la pide al entrar y al tocar, líneas 314 a 355);
  - sin `data-sello`, `reducirImagen` vuelve a codificar **siempre**, aunque la foto ya sea
    pequeña (hoy devuelve el archivo original si no hay que reducir, y con él su EXIF);
  - las fotos de turnos, derivaciones y supervisión siguen igual.
  - **La prueba automática solo puede revisar el HTML** (que no lleva `data-sello`); el
    comportamiento del JS se comprueba a mano en el navegador (UHC-17). Lo que sí se prueba en el
    servidor es CA-32: la foto se guarda sin EXIF aunque no pase por el navegador.
- Si Edwar dice que la foto lleve sello: `sello="Usuario HC"`, y UHC-10 se reduce a la recodificación.

### D11. Pantallas

| Pantalla | Cómo | Quién la ve |
|---|---|---|
| Lista | `App\Livewire\UsuariosHc\Listado` (tabla Filament, como `PuntosCriticos`) en `usuarios-hc/index.blade.php` | Todos los roles del componente con el módulo |
| Crear, corregir identificación | Blade + `UsuarioHcController`, un parcial de formulario común | Edición en adelante |
| Ficha | Blade: identificación con la foto y el estado vital; botones a caracterización e historial solo con `@can('verCaracterizacion')` | Todos |
| Caracterización | Una sola pantalla Livewire + Filament, deshabilitada si no puede editar (D8) | D3 |
| Historial | Blade, auditada; horas con `->fechaHora()` | D3 |

- **Lista:** nombre completo, tipo y número de documento, ficha, componente de registro, estado
  vital. **Sin fotos** (PA-17). Búsqueda con
  `->searchable(query: fn (Builder $query, string $search) => $query->buscar($search))`. Filtro
  «Componente» con los componentes de la dependencia que tienen el módulo (salto de scope a
  propósito, D1). Páginas de 25. `persistSearchInSession()`, `persistFiltersInSession()` y la
  página en la dirección (§1). Acciones «Ver» y «Editar» como enlaces, cada una con su
  `->authorize()`. La consulta es `UsuarioHc::query()` con `componenteRegistro`; **nunca** carga
  relaciones sensibles (CA-21).
- Con DOC-26 sola, la columna dice el componente de registro y no hay filtro de estado (UHC-20 lo
  cambia).

### D12. Lo que espera a DOC-27 (y no se construye ahora)

| Qué | Dónde se engancha | Tarea |
|---|---|---|
| Ver caracterización e historial por estadía válida (CA-25) | `UsuarioHc::conocidaPor()` | UHC-19 |
| Editar según la estadía activa (CA-24) | `UsuarioHc::editablePor()` | UHC-19 |
| Columna «componente o estado», filtro de estado (CA-23) | `Listado` | UHC-20 |
| Aviso al marcar «Fallecida» con estadía activa (CA-26) | `FichasUsuariosHc`, con `Dependencia::usuariosDelComponente()` (ACC-10, hecha) y un tipo nuevo en la campana (RN-38) **[PA-16]** | UHC-21 |
| «Registrar ingreso» en la lista (CA-27) | `Listado` | UHC-22 |
| «Unificar» (DOC-31) | `Listado` | Spec DOC-31 |

Nada de DOC-26 asume cómo se llamará la tabla de estadías: solo esos dos métodos y esas dos
acciones cambian. Las migraciones de DOC-27 van fechadas después de `2026_11_25_000001` (D1).

## Qué cambia en la configuración y en `Componente`

- `Componente::MODULOS`: `usuarios_hc` al final. Sin migración ni seeder de encendido (PA-29).
- `Componente::REGLAS`: **nada** (nada de esto cambia entre Básica y Estabilización).
- `config/repositorio.php`: bloque `usuarios_hc` con las listas cortas (D8). Se reutilizan
  `disco`, `formatos.imagenes`, `tamano_maximo_kb`, `imagen.*`, `comunas`, `horario.zona`.

## Pruebas

- Archivos nuevos en `tests/Feature/UsuariosHc/`: `UsuariosHcModuloTest`, `UsuariosHcAccesoTest`
  (Policy, CA-16, CA-31), `UsuariosHcBusquedaTest`, `UsuariosHcCrearTest`, `UsuariosHcEdicionTest`,
  `UsuariosHcCaracterizacionTest`, `UsuariosHcFotosTest`, `CatalogosHcTest`. Nombres
  `test_doc26_caNN_…`; la tabla «Criterios y pruebas» de tasks.md dice cuál va dónde.
- Básica y Estabilización con `Componente::factory()->create(['modulos' => ['usuarios_hc']])`;
  Calle con `AccesoPorComponenteSeeder::MODULOS_CALLE`. Ojo: `darRol` crea «Calle» con **todos**
  los módulos de la fábrica, que ahora incluyen `usuarios_hc`; las pruebas de rechazo de Calle
  fijan sus módulos.
- **CA-31** crece con cada tarea: cada ruta nueva agrega su caso «ficha de otra dependencia → 404,
  también para el coordinador líder». UHC-04 deja la prueba de la Policy (todos los métodos).
- Livewire (lista y caracterización): `Livewire::test` no pasa por middlewares; se fijan
  dependencia y componente a mano (`docs/FILAMENT.md`).
- Fotos: `TestCase::archivoJpg()` para lo válido; «más de 4096 px» con una cabecera PNG armada a
  mano (`getimagesize` solo lee la cabecera). **CA-32:** un JPEG de GD con un segmento APP1
  `Exif` (con GPS) insertado a mano después de `FFD8`; se sube sin pasar por el navegador y se
  comprueba que el archivo guardado ya no contiene `Exif`, y que la respuesta trae
  `Cache-Control: no-store`.
- CA-19: `Notification::fake()` y `assertNothingSent()`.
- **CA-29 (PA-20, spec v1.1):** `test_doc26_ca29_ficha_igual_sin_mayusculas_espacios_ni_guion`:
  «a12» = « A12 »; «f-120», «F-120» y «F120» chocan entre sí; buscar «F120» encuentra la ficha
  «F-120»; la ficha se muestra «F-120»; «---», «F.120», «F 120» y tildes se rechazan. La misma
  prueba corre contra MySQL en UHC-17.
- **MySQL** (constitución VII): búsqueda en UHC-05 (antes del 30/11); crear e índices únicos
  (CA-28, CA-29), lista y respuestas en UHC-17.
- `ConsultasEficientesTest`: el menú suma un `Gate::allows` sin consulta; no debería moverse.

## Riesgos

| Riesgo | Qué pasa | Cómo se baja |
|---|---|---|
| Relación a `Componente` con su `ComponenteScope('id')` | Columna y filtro de componente vacíos para fichas de otro componente | `withoutGlobalScope(ComponenteScope::class)` en la relación y las opciones (D1); prueba CA-02 desde Básica con una ficha de Estabilización |
| Consultas que SQLite deja pasar y MySQL no | Falla en el servidor | Sin JSON consultado, sin `GROUP BY`, una subconsulta `whereIn`, columnas calificadas. UHC-05 y UHC-17 corren contra MySQL |
| Comparación de texto distinta en los dos motores (`utf8mb4_unicode_ci` iguala «Ñ» y «N», tildes, espacios finales) | Un documento repetido en un motor y no en el otro | Columnas únicas y de búsqueda solo en ASCII `[0-9A-Z]` y `[a-z0-9 ]` (D5, D6); CA-28 y CA-29 en MySQL |
| `alpha_num` o `LIKE` con `%`/`_` | Acepta Unicode / escape distinto por motor | Regex ASCII sin `u`; términos normalizados sin nada que escapar (D5) |
| Datos de salud vistos desde otro componente | Fuga sin vuelta atrás | Caracterización en otras tablas que la lista no toca; Policy en ruta, controlador y cada acción; pruebas por dirección directa (CA-16, CA-21) |
| Valores de salud en la auditoría general | Los lee quien no puede ver la caracterización; además, error 500 en la pantalla | Solo nombres de campo y motivo (D4, CA-30) |
| Livewire restaura la ficha sin scopes | Una ficha de otra dependencia se cargaría al guardar | `$fichaId` y `findOrFail` con scope en cada petición; `before` de la Policy (D3) |
| El archivo de SISPRO llega en `.xls` o HTML | OpenSpout no lo lee | Convertirlo a `.xlsx`/CSV a mano; la carga se escribe con el archivo en la mano (D9) |
| SISPRO cambia el formato | Catálogo con columnas cruzadas | Columnas por contenido, comprobación con códigos conocidos y cantidad mínima (D9) |
| El comando no se corre en un servidor | Diagnósticos y nacionalidad no aparecen allí | Los campos se ocultan sin catálogo (D8), así que nada se rompe; el paso va en la lista de despliegue de Edwar y en `CHANGELOG.md` |
| Un lugar compara la ficha con guion (contra `numero_ficha` o con el término sin normalizar) | «F120» no encuentra «F-120», o las dos se guardan como fichas distintas | Todo compara contra `ficha_normalizada` con el mismo `normalizarCodigo` del documento: columna, identidades, búsqueda, coincidencias y unicidad (D5); `numero_ficha` solo se muestra; CA-29 en SQLite y MySQL |
| La CIE-11 reemplaza a la CIE-10 (≈ 14-ago-2027) | Diagnósticos en una clasificación derogada | `fuente` en cada respuesta; la recarga y la CIE-11 son una spec aparte (PA-26) |
| GD con fotos grandes | Memoria: una foto de 4096 px son ~64 MB al decodificarla | Tope de 4096 px en la validación; `memory_limit` local 512 MB; revisar el de producción en UHC-17 |
| Recodificar en el servidor pierde la orientación EXIF | Una foto que no pasó por el navegador puede quedar girada | El navegador ya la endereza al dibujarla; la que llega sin navegador es un caso raro. Si molesta, girar con `exif_read_data` antes (candidato) |
| Archivos compartidos con Edwar | Conflictos en git | Rutas en archivo propio; los archivos de DOC-25 (`Componente`, `InicioController`, `bootstrap/app.php`, sus pruebas) los toca Edwar en UHC-01 |
| Calendario apretado (José: 10,75 días de trabajo en 10,5 disponibles, tras los recortes) | DOC-26 cierra el 15/12 o la mañana del 16/12, sin holgura | Recortes y reparto de tasks.md («Calendario»); producción va con DOC-27 y espera PA-09/PA-18 |
| DOC-26 en producción sin DOC-27 | Un componente no completa a una persona que creó el otro | PA-14: salen juntas |

## Documentación (al cerrar, UHC-18)

- **`documentacion/docs/context/domain.md`:**
  - **RN-52 a RN-56** (el número lo fija quien actualice).
  - **«Nada se borra»** (P-17, resuelta): escribir que en Usuarios HC **el historial
    (`usuario_hc_cambios`) es el registro de verdad**. Al cambiar o quitar una respuesta, la fila
    vigente se reemplaza y la anterior queda completa en el historial, con su código antes y
    después y su texto; así se cumple «nada se borra» y no es una excepción. La ficha, sus
    identidades, sus fotos y su historial no se borran nunca.
  - **RN-49:** módulo Usuarios HC, encendido en Básica y Estabilización por el superadministrador
    desde «Componentes y nodos»; se abre desde Lectura. Cambia «Básica y Estabilización, ninguno
    por ahora».
  - **RN-04:** las lecturas de Usuarios HC (caracterización, historial, foto) se auditan; la
    corrección lleva solo campos y motivo, los valores están en el historial de la ficha.
  - **RN-06:** se extiende a las fotos de Usuarios HC, que además no se guardan en la caché.
  - **RN-13:** excepción, la foto de la persona va sin sello ni coordenadas (PA-15).
  - **RN-38:** tipo nuevo en la campana para Usuarios HC (con DOC-27, UHC-21, PA-16).
  - **«Parámetros del negocio»:** dos filas (listas de Usuarios HC en la configuración; catálogos
    CIE-10 y países, solo lectura, cargados una vez) y Usuarios HC en «Módulos del componente».
- **`glossary.md`:** Usuario HC (sin «por construir»), Ficha, Caracterización, Estado vital,
  Componente de registro, Identidad anterior; Usuarios HC en **Módulo**.
- **Constitución V**, solo si las respuestas lo piden: PA-07 (la EPS del Usuario HC queda
  aprobada), PA-09 (retención) y PA-18 (autorización de tratamiento). La fila «Parámetros» del
  stack, aclaración opcional (PA-13). La constitución la cambia Edwar; aquí se propone el texto.
- `docs/FILAMENT.md`: regla 2 (los saltos de `ComponenteScope` de D1) y la tabla de pantallas.
- Manual de usuario (sección Usuarios HC), **después del 15/12** (recorte aceptado por Edwar).
  `CHANGELOG.md`, con una línea por catálogo cuando se cargue (archivo de SISPRO, su fecha y la
  cantidad de códigos; D4, D9). Estado de la spec y `documentacion/README.md`.
  `documentacion/fuentes/`: la **referencia** de las dos tablas de SISPRO (nombre, fecha, de dónde
  y cómo se convirtieron; el archivo no se versiona, PA-27) y el documento v1.6 como F-12.

## Preguntas técnicas para Edwar

Las preguntas de comportamiento del plan anterior pasaron a la spec v0.3: P-02 → PA-19, P-03 →
PA-20, P-05 → PA-28, P-08 → PA-21, P-09 → PA-22, P-10 → PA-23, P-11 → PA-24, P-13 → PA-25, P-14
→ PA-27, P-16 → PA-26. La spec v0.3 resolvió además P-04 (PA-04: todo dato de identificación
pide motivo), P-06 (§6.3: también en el servidor), P-12 (la foto es identificación) y P-07 (§3:
el catálogo completo). P-01 ya no es pregunta: es el ajuste de D2, que hace Edwar en UHC-01.

Quedan estas; P-17 ya está resuelta y las demás tienen respuesta por defecto y no bloquean:

- **P-15. Nombres de países con errores en SISPRO** (enlazada a **PA-13**). La tabla trae
  BRAZIL, GUADALUOE, SUAZILANDIA, MACEDONIA, MYANMAR (FORMER BURMA), ISLAS SOLOMON, HAITI.
  **Por defecto:** se guardan el código y el nombre oficial como vienen (`nombre_oficial`) y se
  muestra `nombre`, igual al oficial salvo una lista corta de correcciones en el comando de carga
  (código ⇒ nombre, con la razón al lado: `076` ⇒ «BRASIL», `312` ⇒ «GUADALUPE», `748` ⇒
  «ESUATINI»…). La otra opción es mostrarlos tal cual. Ni «apátrida» ni «desconocido» se
  inventan como país: vacío es «no informado».
- ~~**P-17. La respuesta anterior de una lista**~~ **Resuelta (Edwar, 2026-11-24):** al cambiar
  una respuesta, la fila vigente se reemplaza y la anterior queda completa en `usuario_hc_cambios`,
  con `codigo_antes`/`codigo_despues`, el texto, quién, cuándo y por qué. No se marca la fila como
  reemplazada. El historial es el registro de verdad (D1, D7; `domain.md` en «Documentación»).
- **P-18. Servidor sin GD.** **Por defecto:** la foto se rechaza con un mensaje, en vez de
  guardarla con sus metadatos. GD está en local y en producción; esto solo protege de un servidor
  mal configurado.
- **P-19. Días de Edwar antes del 1/12.** El calendario cuenta con **dos días de Edwar** entre
  el 25 y el 30/11 (UHC-01, UHC-04 y fusionar a `develop`). Si son menos, UHC-04 pasa a José y lo
  del 30/11 se aprieta (tasks.md, «Calendario»).

## Candidatos

- Recargar el CIE-10 y los países (PA-26). **Si se aprueba, con un tope:** la recarga se detiene
  sin tocar nada si el archivo nuevo trae menos del ~95 % de los códigos habilitados que hay
  cargados (un archivo cortado no puede deshabilitar media tabla). Además, `upsert` por código,
  los ausentes deshabilitados y nunca borrados, y auditoría de cada recarga.
- CIE-11 o doble codificación antes del 14-ago-2027 (PA-26).
- Aviso, sin bloquear, por la edad o el sexo del código CIE-10 (PA-25): exige cargar esas
  columnas con la recarga.
- Ofrecer en los campos de psiquiatría solo el capítulo F, si Edwar lo pide (hoy, todo el
  catálogo, como dice la spec).
- Fotos en la lista con un registro de auditoría por página (alternativa de PA-17).
- Ver las fotos anteriores (ya se guardan).
- Enderezar en el servidor las fotos que llegan sin pasar por el navegador (orientación EXIF).
- Búsqueda fonética o de texto completo si la lista crece (PA-21).
