# Plan: Acceso por componente · Básica y Estabilización (DOC-25)

Spec: [spec.md](spec.md) v1.0, aprobada por Edwar el 2026-11-23.
Tareas: [tasks.md](tasks.md). Va **primera y sola a producción**: toca los permisos de Calle.

## La idea en una línea

El contexto que hoy sabe en qué **dependencia** está parado el usuario pasa a saber también en
qué **componente**; un global scope nuevo filtra por él igual que `DependenciaScope` filtra por
dependencia, y un permiso `modulo` (Gate) decide qué módulos abre. Nada nuevo más que eso.

## Decisiones

### D1. El componente del usuario va en la pivote `dependencia_usuario`

Columna nueva `componente_id` (nullable, FK a `componentes`), junto a `rol` y `aprueba_novedades`.

- Es por dependencia, como el rol (§3: «un componente en cada dependencia»).
- `null` = Administración (coordinador líder) o «sin componente» (CA-15).
- `User::dependencias()` y `Dependencia::usuarios()` agregan `componente_id` a `withPivot`.
- `User::componenteEn($dependencia): ?int` lee la pivote ya cargada (`membresia()`), sin consulta extra.

Descartado: una tabla `componente_usuario` (PA-02 dice uno solo; sería una tabla para una columna).

### D2. Los módulos de un componente: columna `componentes.modulos` (JSON), no `config`

- Es dato (constitución III, RN-49), pero **no es una regla de turno**: `config` lo edita
  Coordinación desde «Componentes y nodos» (`Componentes::preparar()` mezcla todo `config`). Con
  los módulos dentro de `config`, la barrera «solo el superadministrador» dependería de que el
  formulario nunca deshidrate esa clave. En columna aparte, la barrera es explícita.
- Los módulos que **existen** sí son código (cada uno son rutas y políticas): constante
  `Componente::MODULOS` junto a `REGLAS`, con etiqueta y ruta de llegada, en el orden del menú:

  ```php
  public const MODULOS = [
      'documentos'    => ['Documentos', 'documentos.index'],
      'turnos'        => ['Turnos', 'admin.turnos.enlaces.index'],
      'derivaciones'  => ['Derivaciones', 'admin.turnos.derivaciones.index'],
      'supervisiones' => ['Supervisión', 'admin.turnos.supervisiones.index'],
  ];
  ```

  La clave es **`supervisiones`**, no `supervision`: ver D5 (choque con el parámetro `{supervision}`).
- `Componente::tieneModulo(string $m): bool`.
- **Nada cambia en `Componente::REGLAS` ni en `config/repositorio.php`.**

### D3. Contexto: `ContextoDependencia` también lleva el componente

Se extiende la clase que ya existe (singleton, la llenan los middlewares persistentes de
Livewire). Contrato, para que frontend trabaje en paralelo:

| Método | Qué devuelve |
|---|---|
| `establecerComponente(?Componente $c)` | Fija el componente y marca el contexto como «establecido». `null` = sin componente |
| `componente(): ?Componente`, `componenteId(): ?int` | El componente donde está parado |
| `filtraComponente(): bool` | `true` si se estableció (petición web); `false` en consola, colas, vía pública y `Livewire::test` sin establecer |
| `tieneModulo(string $m): bool` | Sin establecer: `true`. Establecido: `componente()?->tieneModulo($m) ?? false` |

`establecer($dependencia)` deja el componente sin establecer (lo vuelve a fijar el middleware).

**`EstablecerDependencia`** (ya es persistente en Livewire, no se agrega middleware):

- Usuario de Lectura, Edición o Coordinación: su `componente_id` de la pivote, si está activo
  y es de esa dependencia; si no, `null` (CA-15).
- Administración y superadministrador: el de `session('componente_id')` entre los componentes
  activos de la dependencia; si no hay o no vale, el primero por `id` (Calle). Se guarda en sesión.
- Comparte con las vistas `componenteActual` y `componentesDisponibles` (solo para quien elige).

### D4. El filtro por componente es un global scope, como `DependenciaScope`

`App\Models\Scopes\ComponenteScope` (recibe la columna, por defecto `componente_id`):

- Contexto sin establecer → no filtra (consola, comandos programados, vía pública, seeders).
- Establecido con componente → `where <tabla>.<columna> = id` (columna calificada con la tabla,
  por las juntas).
- Establecido sin componente → `whereRaw('1 = 0')`: quien no tiene componente no ve nada.

Trait `App\Models\Concerns\DelComponente`: agrega el scope y, en `saving`, rechaza con 403 si el
contexto está establecido y `componente_id` es otro. Es la defensa de escritura en un solo
lugar: los formularios validan `componente_id` con `Rule::exists('componentes')->where('dependencia_id')`,
que acepta cualquier componente de la dependencia (Colaborador, EnlaceTurno, caso de
derivación, tipos de turno, puntos críticos); con el trait no hay que tocarlos uno por uno.

| Modelo | Cómo |
|---|---|
| Colaborador, Marcacion, NovedadTurno, EnlaceTurno, Derivacion, Supervision, TipoTurno, PuntoCritico, Nodo | `use DelComponente` |
| Componente | `ComponenteScope('id')` en `booted()` (sin la defensa de escritura: crear uno es del superadministrador) |
| Ausencia (no tiene `componente_id`) | Scope anónimo `whereHas('colaborador')`, que hereda el de Colaborador |
| ComentarioDerivacion | Nada: la ruta ya verifica que el comentario sea de la derivación, que sí está aislada |
| EnlaceCarga, Documento, Carpeta, Recepcion, TipoDocumento, GrupoCustodia | Nada: son del módulo Documentos, que solo tiene Calle (RN-51). Los protege D5 |

Con el scope, la vinculación de rutas (`{colaborador}`, `{marcacion}`, `{derivacion}`…)
devuelve 404 para un registro de otro componente, los listados, contadores del menú, filtros,
Excel y ZIP salen solo con lo suyo, y las listas para elegir componente o nodo ofrecen solo el
suyo (todas usan `Componente::…` y `$componente->nodos`). Cubre las tablas de Filament porque
cada clic de Livewire pasa por `EstablecerDependencia`.

**Quién se salta el scope a propósito** (`withoutGlobalScope(ComponenteScope::class)`, nunca
`withoutGlobalScopes()`, para no perder el de dependencia):

1. El selector de componente y su validación (middleware y controlador).
2. El campo «Componente» del formulario de usuarios cuando lo usa Administración.
3. Los componentes a elegir en «Enviar aviso».
4. `Componentes::nombreUnico()` (si no, el superadministrador podría duplicar el nombre de un
   componente en el que no está parado y reventar contra el índice único).

**Consultas que hoy usan `withoutGlobalScopes()` en la administración y deben quedar en el
componente:** el aviso de caso duplicado (`CasoDerivacionController::avisarDuplicado`) y la
huella de la carga masiva (`CargaMasivaCasosController`): se agrega `where('componente_id', …)`.
Las demás (`numero` máximo de DC-NNN, carpetas del sistema, nombres de nodo en la analítica de
supervisión) no muestran datos de otro componente.

### D5. Módulos: Gate `modulo` en las rutas **y** en las Policies

- `Gate::define('modulo', fn (User $u, string $m) => app(ContextoDependencia::class)->tieneModulo($m))`
  en `AppServiceProvider`.
- **Rutas:** grupos con `->middleware("can:modulo,'documentos'")`, `can:modulo,'turnos'`,
  `can:modulo,'derivaciones'`, `can:modulo,'supervisiones'`, con la clave **entre comillas simples** (ver la fila de riesgos). Cubre también lo que hoy no tiene
  Policy (índice de documentos, carpetas nuevas, lote, tipos de documento, grupos de custodia,
  descargas, Excel). **Cuidado:** `Authorize` toma el argumento como parámetro de ruta si
  existe uno con ese nombre; por eso `supervisiones` y no `supervision`.
- **Policies:** trait `App\Policies\Concerns\ExigeModulo` con `before()` que devuelve `false`
  si el módulo no está y `null` si sí (sigue la regla del rol). Una constante por Policy:

  | Módulo | Policies |
  |---|---|
  | documentos | DocumentoPolicy, CarpetaPolicy, EnlaceCargaPolicy |
  | turnos | ColaboradorPolicy, EnlaceTurnoPolicy, MarcacionPolicy, TipoTurnoPolicy, PuntoCriticoPolicy |
  | derivaciones | DerivacionPolicy |
  | supervisiones | SupervisionPolicy |

  Esto cubre las acciones de Filament (cada una con su `authorize()`), que no pasan por los
  middlewares de ruta, y el menú (que pregunta con `can`).
- ComponentePolicy no exige módulo (es de Administración).

**Por qué los dos:** la ruta corta todo un módulo de una vez, incluso pantallas sin Policy; la
Policy cubre Livewire y los botones. La regla de cada rol no cambia.

### D6. Rutas por grupo

- `routes/web.php`: documentos, carpetas, lote, comentarios de documento, `admin/enlaces`,
  `admin/tipos`, `admin/custodia` → grupo `can:modulo,'documentos'`.
- `routes/turnos-admin.php`: tres grupos (`turnos`, `derivaciones`, `supervisiones`).
  `componentes` queda fuera de todo grupo.
- Sin cambios: perfil, campana, manual, salir, cambio de dependencia, usuarios, auditoría,
  avisos, y **todo `routes/turnos-publico.php`** y `enviar/{token}` (CA-14): no llevan sesión,
  así que el contexto queda sin establecer y ni el scope ni el Gate actúan. Las direcciones no
  cambian.
- Nuevas: `GET inicio` (llegada) y `PUT componente/{componente}` (selector).

### D7. Llegada, menú y selector

- **`inicio`**: redirige a la ruta del primer módulo del componente que el usuario pueda abrir
  (Documentos para todos los roles; Turnos, Derivaciones y Supervisión desde Edición, como hoy).
  Si no hay ninguno, vista `inicio.blade.php`: «Tu componente todavía no tiene módulos activos»,
  o, sin componente, «Pide a tu coordinación que te asigne un componente». El login (formulario y
  Authentik), el cambio de dependencia y el logotipo del menú van a `inicio` en vez de a
  `documentos.index`.
- **Menú** (`sidebar.blade.php`): cada enlace de módulo suma `Gate::allows('modulo', …)` a la
  condición de rol que ya tiene. Tipos de documento y Grupos de custodia piden también
  `documentos`. Los grupos vacíos ya no se pintan (`@continue(empty(...))`).
- **Selector de componente** (PA-05): debajo del de dependencia, solo para Administración y el
  superadministrador, y solo si hay más de un componente. Mismo patrón que el de dependencia
  (formulario `PUT`, `DependenciaActualController` como modelo). Para Lectura, Edición y
  Coordinación se muestra el nombre del componente, sin selector.

### D8. Administración (coordinador líder) y usuarios

- `RolDependencia::asignables()` deja de incluir Administración. Un método
  `User::rolesAsignablesEn($dependencia)` devuelve todos los roles para el superadministrador y
  `asignables()` para los demás; lo usan `UsuarioController` y `GuardarUsuarioRequest` (hoy cada
  uno calcula lo suyo).
- `verificarRango`: además de lo de hoy, solo el superadministrador gestiona a un usuario de
  Administración (ver pregunta P-02).
- Campo `componente_id` en el formulario: obligatorio salvo con rol Administración (que guarda
  `null`). Coordinación: `Rule::in([su componente])`. Administración y superadministrador:
  cualquier componente activo de la dependencia.
- `verificarPertenencia`: si quien gestiona no es Administración, el usuario debe ser de su
  componente (si no, 404).
- **Hueco que hoy existe y aquí se vuelve grave:** «Dar acceso» con la cédula de alguien que **ya
  está** en la dependencia hace `syncWithoutDetaching` y le pisa rol (y ahora componente). Con
  componentes, Coordinación de Calle podría quitarle una persona a Básica. Se rechaza: «Esa
  persona ya tiene acceso aquí; edítala desde la lista».
- Auditoría: `UsuarioCreado` / `UsuarioActualizado` llevan el componente en la descripción y,
  al cambiar, `datos: ['componente' => ['antes' => …, 'despues' => …]]`.
- Tabla `App\Livewire\Usuarios`: columna «Componente» (`du.componente_id`, con `leftJoin` a
  `componentes` para el nombre); Coordinación filtrada a `du.componente_id = su componente`;
  Administración ve todos y tiene filtro por componente.

### D9. Componentes y nodos

- `ComponentePolicy::create` → solo `es_superadmin` (CA-10). `update` sigue en Coordinación,
  que por el scope solo alcanza el suyo.
- Campo «Módulos» (`CheckboxList` sobre `Componente::MODULOS`) en el panel, **visible solo
  para el superadministrador**: un campo oculto no se deshidrata, así que nadie más puede
  enviarlo. Además, la acción de editar quita `modulos` de `$data` si quien guarda no es
  superadministrador (defensa en el servidor).
- Regla: Documentos no se enciende si otro componente de la dependencia ya lo tiene (se
  revisa en PHP, sin consultas JSON). Mensaje: «Documentos ya es de Calle: no se puede usar
  en dos componentes (RN-51)».
- La auditoría ya existe (`ComponenteGuardado` con `cambios`); `modulos` sale ahí.
- Administración ve el componente donde está parado; para trabajar otro, lo cambia en el
  selector (ver P-01).

### D10. Auditoría y avisos

- **Auditoría (PA-06, cambiada por Edwar el 2026-11-24):** `auditorias.componente_id`
  (migración `2026_11_24_000003`). `Auditor::registrar` guarda el del dato auditado o, si no
  tiene, el componente donde está parado el usuario. Coordinación ve los de su componente y,
  si su componente tiene Documentos, también los sin componente (anteriores a DOC-25 y cargas
  por enlace). Así ve lo que llega por la vía pública y lo automático, y nada de otro componente.
  Administración y superadministrador, todo. El filtro «Usuario» ofrece las personas de su
  componente. Antes era `whereIn('user_id', …)`: dejaba fuera todo lo que no tiene usuario,
  casi toda la auditoría de Calle, contra CA-03.
- **Enviar aviso:** casillas de componentes (al menos uno, marcado por defecto el actual).
  `Avisos::enviar(..., ?array $componentes = null)` y `destinatarios()` con
  esos componentes **más Administración** (P-04); los roles marcados siguen filtrando. Si no le
  llega a nadie, se rechaza con un mensaje y no se guarda. Los avisos de versión (RN-37) no pasan
  componentes: siguen llegando a todos.
- **Campana (RN-23, RN-25, RN-33, RN-45):** `Dependencia::usuariosDelComponente(int $id)` =
  `usuarios()->where(fn ($q) => $q->where('dependencia_usuario.componente_id', $id)->orWhere('dependencia_usuario.rol', 'administracion'))`.
  Se usa en `Novedades::avisar()` (cubre novedades y «no registró entrada»),
  `DerivacionPendiente::enviar()` y `SupervisionImportante::enviarSiHace()`. **Cambio visible:**
  derivación vencida y supervisión importante hoy van solo a Edición y Coordinación; ahora
  también a Administración (el coordinador líder, como dice la spec).

### D11. Migraciones

1. `2026_11_24_000001_acceso_por_componente.php` (esquema, reversible):
   `componentes.modulos` JSON nullable; `dependencia_usuario.componente_id` FK nullable
   (`nullOnDelete`).
2. `2026_11_24_000002_sembrar_basica_y_estabilizacion.php`: llama a
   `Database\Seeders\AccesoPorComponenteSeeder` (mismo patrón que `sembrar_componente_calle`:
   el despliegue solo corre `migrate`). `down()` vacío: para entonces puede haber usuarios en
   esos componentes. El seeder, **idempotente y con `DB::table`** (no con modelos, para que un
   cambio futuro del modelo no rompa la migración):
   - Si no existe `inclusion-social` o su `calle`, no hace nada.
   - Calle: `modulos = ["documentos","turnos","derivaciones","supervisiones"]` si está en `null`.
   - Crea `basica` («Básica») y `estabilizacion` («Estabilización») si no existen: activos,
     `config` `null` (= reglas de fábrica, `regla()` cae en `REGLAS`), `modulos` `[]`, sin nodos.
   - `dependencia_usuario`: `componente_id = calle` donde `rol` en Lectura/Edición/Coordinación
     y `componente_id` es `null` (Administración queda en `null`).
   - `colaboradores` de Inclusión Social con `componente_id` `null` → Calle (ver P-03): con el
     scope, una persona sin componente desaparecería de todas las listas.
   - `DatabaseSeeder` lo llama después de `CalleSeeder` (instalaciones nuevas).

## Pruebas

- **Helper (`tests/TestCase.php`):** `usuarioCon($rol, $dependencia, ?Componente $componente = null)`
  y `darRol(...)` igual. Si la dependencia no tiene componentes, crea uno («Calle», todos los
  módulos). Lectura, Edición y Coordinación quedan en `$componente` o, si no se pasa, en el
  primer componente de la dependencia por `id`. Administración, sin componente.
- **`ComponenteFactory`:** `modulos` por defecto = todos (`array_keys(Componente::MODULOS)`), para
  que las pruebas de hoy sigan viendo sus módulos. Estado `sinModulos()` para las nuevas.
- Lo que se rompa después del cambio son pruebas que crean el usuario **antes** que su
  componente, o personas de campo sin `->en($componente)` (9 casos): se arreglan pasando el
  componente al helper o con `->en()`, no cambiando lo que verifican. Si alguna prueba de hoy
  cambia lo que verifica, es una señal: se reporta.
- `Livewire::test` sin `establecerComponente` no filtra (igual que hoy con la dependencia); las
  pruebas nuevas de Livewire fijan el componente a mano.
- Pruebas nuevas en `tests/Feature/AccesoPorComponenteTest.php` (y `…UsuariosTest`, `…AvisosTest`
  si crece), nombres `test_doc25_caNN_…`. CA-02 corre el seeder sobre datos sembrados a mano.
- `ConsultasEficientesTest` cuenta consultas: el middleware suma como mucho una (el componente);
  si el tope se pasa, se sube con la razón escrita, no se quita la prueba.

## Riesgos

| Riesgo | Qué pasa | Cómo se baja |
|---|---|---|
| Calle en producción ve menos de lo que veía | Se frena la operación de turnos y derivaciones | CA-03: las 555 pruebas siguen pasando; revisión en el servidor de pruebas con usuarios reales de cada rol antes de `main` |
| Columna sin calificar en el scope | MySQL: «ambiguous column» en consultas con juntas (Usuarios, reportes); SQLite a veces lo deja pasar | `ComponenteScope` usa `$model->getTable().'.'.$columna`. Probar en MySQL: Marcaciones y reporte, Novedades, Derivaciones (lista, Excel, ZIP, indicadores), Supervisión (analítica con `GROUP BY`), Usuarios (junta con la pivote), Auditoría |
| `withCount`/`whereHas` con el scope dentro | Subconsultas distintas a las de hoy | Mismas pantallas, contra MySQL |
| Consultas con `withoutGlobalScopes()` en la administración | Muestran datos de otro componente | Revisadas en D4; QA las busca con `grep` |
| Personas de campo con `componente_id` `null` en producción | Desaparecen de las listas | La migración las pasa a Calle (P-03); verificar el conteo antes |
| Otra dependencia con usuarios o componentes en producción | Sus usuarios quedan sin componente y sin módulos | Consultas previas al despliegue (abajo); el seeder solo toca Inclusión Social |
| Administración pasa a ver todos los componentes | Quien hoy tenga Administración verá Básica y Estabilización | PA-03: Edwar revisa la lista antes de desplegar |
| Estado del singleton entre peticiones en una misma prueba | Después de un `get()`, el scope sigue filtrando las aserciones con el componente de esa petición | Ya pasa con la dependencia; en las pruebas nuevas, aserciones con `withoutGlobalScopes()` |
| `can:modulo,<clave>` sin comillas | En Laravel 12, `Authorize` toma un argumento sin comillas como parámetro de ruta: si no existe pasa `null` y el Gate falla con un 500; si coincide con uno (`{supervision}`) recibe un modelo. **Corregido en ACC-04:** la clave va entre comillas simples (`"can:modulo,'turnos'"`) | Una prueba recorre todas las rutas de cada grupo |
| Mover una persona de campo de componente | Deja de ser posible (cada quien solo ve el suyo) | Hoy solo Calle tiene Turnos; va a «Candidatos» |

### Antes de desplegar (Edwar, en producción, solo lectura)

```sql
-- ¿Hay otras dependencias con usuarios? (si sí, quedan sin componente)
SELECT d.slug, du.rol, COUNT(*) FROM dependencia_usuario du JOIN dependencias d ON d.id = du.dependencia_id GROUP BY d.slug, du.rol;
-- ¿Qué componentes existen? (¿ya hay alguno llamado Básica o Estabilización?)
SELECT id, dependencia_id, slug, activo FROM componentes;
-- Quiénes pasan a ser coordinador líder (PA-03)
SELECT u.name, u.documento, d.slug FROM dependencia_usuario du JOIN users u ON u.id = du.user_id JOIN dependencias d ON d.id = du.dependencia_id WHERE du.rol = 'administracion';
-- Personas de campo sin componente (P-03)
SELECT COUNT(*) FROM colaboradores WHERE componente_id IS NULL;
```

## Documentación (al cerrar)

`documentacion/docs/context/`: RN-01, RN-02, RN-03, RN-23, RN-25, RN-33, RN-45; nuevas RN-49 a
RN-51; `stakeholders.md`; `glossary.md` (Coordinador líder, Módulo, Componente, Usuario HC).
`docs/FILAMENT.md` regla 2 (la consulta también filtra por componente; quién se salta el scope).
Manual (usuarios: el campo Componente; el selector; la pantalla de bienvenida). `CHANGELOG.md`.
Estado de la spec y `documentacion/README.md`.

## Preguntas para Edwar (no bloquean; cada una tiene su respuesta por defecto)

- **P-01.** La spec dice que en «Componentes y nodos» Administración ve «todos». El plan lo
  hace con el selector: parado en Calle ve Calle; para Básica, cambia el selector. Ver todos a
  la vez obligaría a que los nodos de un componente se editen sin estar parado en él, que es
  justo lo que el aislamiento impide. **Por defecto: con el selector.**
- **P-02.** ¿Administración puede editar o desactivar a otro usuario de Administración? Como
  solo el superadministrador da ese rol, **por defecto: no, solo el superadministrador**
  gestiona cuentas de Administración (bajarlas a Coordinación incluido, CA-09).
  **Edwar (2026-11-24):** Administración sí edita su propia ficha (nombre, correo, cargo),
  sin cambiar su acceso: en la propia ficha, de cualquier rol, el rol, el componente y el
  estado se quedan como están. El acceso de Administración lo controla el superadministrador.
- **P-03.** Las personas de campo sin componente en Inclusión Social: **por defecto la
  migración las pasa a Calle** (era el único). Si el conteo de arriba da 0, no aplica.
- **P-04.** «Enviar aviso» (§6 y CA-13): **por defecto, casillas de componentes, al menos una**,
  y llega a los usuarios de esos componentes. **Respondida por Edwar (2026-11-24):**
  Administración también lo recibe, como antes de DOC-25.
- **P-05.** Superadministrador: se comporta como Administración (selector de componente,
  empieza en Calle). En Básica ve lo que ve Básica (nada por ahora). **Por defecto: así.**
