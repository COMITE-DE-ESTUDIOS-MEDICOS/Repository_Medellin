# Plan: Ingresos y egresos de Usuarios HC · estadías, sedes y capacidad (DOC-27)

> **Estado: Aprobado por Edwar (2026-11-24)** con las respuestas por defecto de P-01 a P-07 (al final);
> UHC-11 de DOC-26 pasa al 11/12 (P-03). Arranca el 1/12.
> **Ajustado tras revisión de QA (2026-11-25):** firmas de DOC-26 escritas en su plan, rutas y
> pruebas de DOC-25 por tarea, un solo salto de `ComponenteScope`, orden de bloqueos, errores en
> las ventanas, apertura desde Usuarios HC y holgura recontada. Ninguna regla de negocio cambia.

| | |
|---|---|
| Spec | [spec.md](spec.md) **v1.0, aprobada** (Edwar, 2026-11-24). Tareas: [tasks.md](tasks.md) |
| Dueño de Producto | Edwar (aprueba la spec y este plan) |
| Redactó | Claude (arquitecto), 2026-11-24 |
| Base | **DOC-25 construida** (`Componente::MODULOS`, `ContextoDependencia`, `ComponenteScope`, `DelComponente`, Gate `modulo`, `ExigeModulo`, `Dependencia::usuariosDelComponente`, `auditorias.componente_id`). **DOC-26 aprobada, sin construir**: este plan usa lo que su plan promete y lo nombra con su UHC-NN (abajo, «Lo que se toma de DOC-26») |
| Bloquean producción, no el desarrollo | PA-01 (sedes reales), PA-09 (confirmación escrita) y las de jurídica de DOC-26 (PA-09, PA-18) |

## La idea en una línea

Dos tablas con dueño (`sedes` y `estadias`, cada una con `DependenciaScope` y `DelComponente`) y
tres que cuelgan de ellas (capacidades, respuestas de listas, historial de la estadía); **un solo
servicio** (`App\Services\RegistroEstadias`) por donde pasa toda escritura, en una transacción que bloquea
la fila de la persona y vuelve a comprobarlo todo; las fechas reales se guardan como hora de
Colombia pase lo que pase con la zona del servidor, y los días de estadía salen de **una sola
función** en PHP que usan la lista, el tablero (DOC-28) y el reporte (DOC-29).

## Lo que se toma de DOC-26 (y cuándo llega)

| Qué | Tarea de DOC-26 | En `develop` | Lo usa |
|---|---|---|---|
| Tabla `usuarios_hc`, modelo `UsuarioHc` con `DependenciaScope`, `estado_vital`, `fecha_fallecimiento`, `UsuarioHcFactory` | UHC-02 | 30/11 | IYE-02 en adelante |
| `UsuarioHc::scopeBuscar` (búsqueda normalizada, probada en MySQL) | UHC-05 | 30/11 | IYE-05 (elegir persona), IYE-06 (búsqueda de la lista) |
| `UsuarioHcPolicy` con `conocidaPor`/`editablePor` en el modelo | UHC-04 | 30/11 | UHC-19 (dentro de DOC-27) |
| `UsuarioHc::opciones(array $lista, array $yaTiene = [])` y `UsuarioHc::chocanExcluyentes(array $lista, array $codigos)`, genéricas (firma en DOC-26 plan D8) | UHC-03 | 30/11 (se construye el 27/11) | IYE-04, IYE-05 |
| `FichasUsuariosHc::cambiarEstadoVital(UsuarioHc $ficha, string $estado, ?CarbonInterface $fecha, string $motivo): void`, sin autorizar adentro (firma en DOC-26 plan D7) | UHC-12 | 3/12 | IYE-04 (egreso por fallecimiento), IYE-09, IYE-10 (vuelve a Viva) |
| Crear fichas desde la pantalla (`UsuarioHcController@create/store` y su formulario) | UHC-07, UHC-08 | 2/12 · 4/12 | Punto de control del 4/12 (o fichas por `tinker`, P-07) |
| Lista de Usuarios HC (`Livewire\UsuariosHc\Listado`) y su línea del menú | UHC-06 | 1/12 | IYE-01 (menú), UHC-20, UHC-22 |
| Foto sin sello (`x-campo-archivo` sin `data-sello`) | UHC-10 | 30/11 | IYE-13 (después del 15/12) |
| Recodificar con GD, `no-store`, CSP de `previsualizar` | UHC-09 | 11/12 | IYE-13 (después del 15/12) |
| Selector CIE-10 con el texto guardado (`getOptionLabelsUsing`), catálogo cargado | UHC-14, UHC-15 | 15/12 · después del 15/12 | IYE-14 (después del 15/12) |

**Las dos firmas que usa DOC-27 están escritas en DOC-26 el 25/11** (usuarios-hc/plan.md D7 y D8;
filas UHC-03 y UHC-12 de su tasks.md, con su prueba). No hay acuerdo pendiente:

1. **UHC-03 (27/11):** `UsuarioHc::opciones(array $lista, array $yaTiene = []): array` recibe el
   bloque de la configuración (`opciones`, `retiradas`) y los códigos que el registro ya tiene con
   su texto guardado; `UsuarioHc::chocanExcluyentes(array $lista, array $codigos): bool`. Ninguna
   se ata a `UsuarioHc::CAMPOS`: la estadía las llama con su bloque `ingresos_egresos`.
2. **UHC-12 (3/12):** `FichasUsuariosHc::cambiarEstadoVital(UsuarioHc $ficha, string $estado,
   ?CarbonInterface $fecha, string $motivo): void`, **sin autorizar adentro y sin comprobar la
   revisión del formulario** (quien llama ya autorizó la estadía y bloqueó la ficha): deja las
   filas en `usuario_hc_cambios`, sube `revision` y audita `UsuarioHcActualizado` con campos y
   motivo. Corre dentro de la transacción de quien llama. Si nada cambia, no escribe nada.

**Si UHC-12 no está en `develop` el 3/12:** el efecto en la ficha (D5.7) **se mueve** de IYE-04 a
IYE-10, no se agrega: IYE-04 −0,25 y IYE-10 +0,25. La suma no cambia, pero el 10/12 pasa a 1,25 y
toma 0,25 de la holgura del 11/12 (tasks.md, «Calendario»). CA-17 (egreso) se prueba entonces en
IYE-10.

Para la foto (IYE-13): si UHC-09 deja la recodificación dentro de `FichasUsuariosHc::cambiarFoto`,
IYE-13 la saca a una función compartida; no se copia.

## Decisiones

### D1. Modelo de datos (una migración, `2026_12_01_000001_crear_ingresos_y_egresos.php`)

Fechada después de `2026_11_25_000001_crear_usuarios_hc` (DOC-26 D1). Reversible. Ninguna tabla
lleva `SoftDeletes` (constitución IV: se anula, no se borra). Sin JSON.

**`sedes`** (modelo `Sede`, `#[ScopedBy([DependenciaScope::class])]` + `use DelComponente`):
`dependencia_id`, `componente_id`, `nombre` (120), `activa` (bool, 1), `timestamps`. Único
`(componente_id, nombre)`. Al crear y al renombrar, `Rule::unique('sedes', 'nombre')->where('componente_id', …)`
(ignorando la propia al renombrar) da el mensaje «Ya hay una sede con ese nombre en el componente»;
la `UniqueConstraintViolationException` del índice (carrera, o MySQL que iguala tildes y mayúsculas
donde SQLite no) se captura y da el mismo mensaje, nunca un error 500.

**`sede_capacidades`** (`SedeCapacidad`, sin scope; se lee por la sede): `sede_id`, `capacidad`
(entero sin signo, > 0), `vigente_desde` (date), `motivo` (texto, nulo solo en la primera),
`user_id`, `created_at`. Nunca se edita ni se borra una fila.

- `Sede::capacidadEn(CarbonInterface $dia): ?int` = la fila con el mayor `vigente_desde <= $dia`;
  a igual fecha, la de mayor `id` (la última que se escribió). `null` si el día es anterior a la
  primera (PA-11: DOC-28 muestra «sin capacidad»). La comparación va con
  `whereDate('vigente_desde', '<=', $dia->toDateString())`, no con el Carbon pelado: SQLite guarda
  la columna `date` como `2026-12-01 00:00:00` y la compara como texto, así que `<= '2026-12-01'`
  dejaría fuera la capacidad que empieza ese mismo día.

**`estadias`** (modelo `Estadia`, `DependenciaScope` + `DelComponente`):

| Columna | Para qué |
|---|---|
| `dependencia_id`, `componente_id` | Aislamiento. `componente_id` lo pone el servidor desde `ContextoDependencia::componenteId()`; no está en `$fillable` |
| `usuario_hc_id` FK, `sede_id` FK | La persona y la sede |
| `sede_nombre` (120) | El nombre de la sede **al registrar**; renombrar no lo cambia (CA-26). Lo que se muestra |
| `estado` (`activa`/`egresada`/`anulada`) | Estados de la spec |
| `activa_usuario_hc_id` (nulo, **único**) | `= usuario_hc_id` mientras está activa; `null` egresada o anulada. **El índice único es la garantía de «una activa por persona»** (CA-03, CA-08) en los dos motores: los dos admiten varios `NULL` |
| `ingreso_en` (datetime) | Fecha real del ingreso, al minuto, hora de Colombia (D3) |
| `procedencia`, `procedencia_texto` · `comuna`, `comuna_texto` · `proyeccion`, `proyeccion_texto` · `orientacion`, `orientacion_texto` | Listas de una opción: código + texto que se vio (§3). Columnas, no tabla aparte: DOC-28/29 cuentan por ellas sin juntas |
| `observacion_ingreso` (texto) | |
| `egreso_en` (datetime, nulo) · `motivo_egreso`, `motivo_egreso_texto` · `destino`, `destino_texto` · `observacion_egreso` | El egreso vigente |
| `revision` (entero, 0) | Concurrencia (D5), como `usuarios_hc.revision` |
| `registrado_por`, `egreso_registrado_por` (FK `users`), `egreso_registrado_en` (timestamp) | Quién y cuándo se registró (la «fecha de registro» de la regla 10 la pone el sistema: `created_at` y `egreso_registrado_en`) |
| `anulada_por`, `anulada_en`, `motivo_anulacion` | La anulación |
| `timestamps` | |

Índices: `(dependencia_id, componente_id, ingreso_en)` para la lista; `(usuario_hc_id, estado)`
para cruces y retroactivas; `(sede_id, estado)` para ocupación y desactivar.

**Sin columna de fecha de fallecimiento en la estadía:** se escribe en la ficha
(`usuarios_hc.fecha_fallecimiento`, DOC-26) con su historial (§6: «independiente de la fecha y
hora del egreso»).

**`estadia_respuestas`** (listas de varias opciones de la estadía; mismo formato que
`usuario_hc_respuestas` de DOC-26): `estadia_id`, `campo` (`condiciones`; después del 15/12,
`diagnosticos`), `fuente` (`condiciones`, `cie10`), `opcion`, `texto`, `timestamps`. Único
`(estadia_id, campo, opcion)`; índice `(campo, opcion)` para DOC-28/29. Son **de la estadía**, no
de la persona (§3): un reingreso trae las suyas. Al corregir, la fila vigente se reemplaza y la
anterior queda completa en `estadia_cambios` (código y texto antes y después): el historial es el
registro de verdad; sin columna de estado.

**`estadia_cambios`** (historial de la estadía; mismo formato que `usuario_hc_cambios`):
`estadia_id`, `user_id`, `componente_id`, `accion` (`corregir_ingreso`, `corregir_egreso`,
`reabrir`, `anular`), `campo` (nulo en anular), `codigo_antes`, `codigo_despues`, `antes`,
`despues` (texto legible; fechas con `->fechaHora()`), `motivo`, `created_at`.

- Una fila por campo cambiado, y por opción que sale o entra en las de varias.
- **Reabrir** escribe una fila por cada dato del egreso que se vacía (fecha, motivo, destino,
  observación, quién lo registró y cuándo), con el valor en `antes`. Así «el egreso anulado
  conserva todos sus datos» sin otra tabla: el historial es el registro de verdad, como decidió
  Edwar para DOC-26 (P-17). La fila de la estadía guarda solo el egreso vigente.
- **Quitar el destino** al cambiar el motivo (CA-10) es una fila más de la corrección.

**Fotos** (después del 15/12): tabla `estadia_fotos` en **su propia migración** con IYE-13 (no
se crea ahora una tabla que nadie usa).

Modelos: `Sede`, `SedeCapacidad`, `Estadia`, `RespuestaEstadia`, `CambioEstadia`. Fábricas de
`Sede` (con su primera capacidad) y `Estadia` (estados `activa()`, `egresada()`, `anulada()`).

**Saltos de `ComponenteScope` a propósito, por escrito** (van a `docs/FILAMENT.md`, regla 2):

1. **El único salto de estadías:** el scope local `Estadia::scopeEntreComponentes(Builder $query)`
   = `$query->withoutGlobalScope(ComponenteScope::class)`. Devuelve la **consulta**, no
   resultados: se encadena (`Estadia::entreComponentes()->where('usuario_hc_id', $id)`), sirve de
   subconsulta (`whereIn('usuarios_hc.id', Estadia::entreComponentes()->where('estado', 'activa')->select('usuario_hc_id'))`)
   y alimenta la relación `UsuarioHc::estadiaActiva(): HasOne` =
   `$this->hasOne(Estadia::class)->entreComponentes()->where('estado', 'activa')`, que se carga con
   `with()` (sin N+1 en UHC-20). Es la única consulta que cruza componentes (spec §8, DOC-26 §6.1);
   QA la encuentra con `grep -rn "withoutGlobalScope(ComponenteScope" app/Models/Estadia.php` (dos
   líneas: esta y la del punto 2). La usan: «ya tiene estadía activa en X» (CA-03), cruces (CA-04),
   retroactivas (CA-29), reabrir (CA-14), `conocidaPor`/`editablePor` (UHC-19), la persona que no
   se ofrece al registrar (IYE-05, UHC-22) y la columna de UHC-20.
   **Lo que sale al usuario:** de una estadía de otro componente, **solo el nombre del componente**
   (y en el conflicto de reabrir, la fecha y la sede solo si es del mismo componente, D5). Quien la
   carga con `with()` limita las columnas (`select(['id', 'usuario_hc_id', 'componente_id', 'estado'])`)
   para no traer al navegador más de lo que muestra.
2. `Estadia::componente()` con `withoutGlobalScope(ComponenteScope::class)` (para ese nombre).
3. Nunca `withoutGlobalScopes()`: el de dependencia se queda siempre.

### D2. El módulo `ingresos_egresos`

- **`Componente::MODULOS`**, después de `usuarios_hc`:
  `'ingresos_egresos' => ['Ingresos y egresos', 'ingresos-egresos.index']`. La clave no choca con
  ningún parámetro de ruta (`{estadia}`) y en las rutas va entre comillas
  (`"can:modulo,'ingresos_egresos'"`).
- **Encenderlo: sin migración ni seeder.** Edwar, desde «Componentes y nodos», en Básica y
  Estabilización (la casilla sale sola de `MODULOS`). Calle no lo tiene.
- **Llegada (`InicioController`): sin cambio.** `usuarios_hc` va antes en `MODULOS` y abre desde
  Lectura (UHC-01); como Edwar enciende los dos juntos, todos llegan a Usuarios HC.
- **Rutas:** `routes/ingresos-egresos.php`, cargado en `bootstrap/app.php` como `usuarios-hc.php`,
  con `['web', 'auth', 'usuario.activo', 'dependencia', "can:modulo,'ingresos_egresos'"]`,
  prefijo `ingresos-egresos`, nombres `ingresos-egresos.*`:

  | Método y dirección | Qué | Permiso | Tarea |
  |---|---|---|---|
  | `GET ingresos-egresos` | Pantalla: tabla de estadías y, solo para el superadministrador, la sección «Sedes» | IYE-01: `Route::view` vacía, **sin** `can:viewAny` (la Policy no existe todavía; como UHC-01, ver `routes/usuarios-hc.php`). IYE-03 le agrega `can:viewAny,App\Models\Estadia` | IYE-01 (vacía), IYE-03 (permiso), IYE-05, IYE-06 |
  | `GET ingresos-egresos/estadias/{estadia}` | «Ver estadía»: ingreso, egreso e historial; auditada cada vez | `can:view,estadia` | IYE-08 |
  | `GET ingresos-egresos/estadias/{estadia}/fotos/{tipo}` | Foto de ingreso o egreso, auditada, `no-store` | `can:verFotos,estadia` | IYE-13 (después del 15/12) |

  `{estadia}` con `whereNumber`. La vinculación pasa por los dos scopes: una estadía de otra
  dependencia **o de otro componente** da 404 (CA-18, CA-32). Registrar, corregir, reabrir y anular
  son acciones de Filament (D7), no rutas.
- **Menú** (`resources/views/components/sidebar.blade.php`, grupo `principal`, debajo de Usuarios
  HC): `Gate::allows('modulo', 'ingresos_egresos')`, todos los roles. Hoy el menú no tiene la línea
  de Usuarios HC (llega con UHC-06, José, 1/12): IYE-01 pone la suya **después de que UHC-06 esté
  fusionada**; si el 1/12 no lo está, Edwar mete las dos líneas en IYE-01 y le avisa a José.
- **Pruebas de DOC-25 que se tocan, cada cosa en la tarea que la hace necesaria:**
  - IYE-01: nada que editar. `ModulosPorComponenteTest` ya exige que cada clave de `MODULOS` tenga
    su grupo de rutas `GET` (lo cumple la `Route::view`) y que Calle de fábrica abra su llegada.
  - IYE-03: su lista de Policies agrega `Estadia::class` y `Sede::class`, y la ruta recibe
    `can:viewAny`.
  - IYE-08: `parametrosEn()` agrega `estadia` (una estadía del componente dado), porque la ruta
    `{estadia}` llega ahí y la prueba exige un valor para cada parámetro.
  Con eso la prueba que recorre cada `GET` cubre el rechazo de Básica sin el módulo (CA-21). La
  fábrica de componentes trae todos los módulos: las pruebas de rechazo de Calle fijan
  `AccesoPorComponenteSeeder::MODULOS_CALLE`.

### D3. Hora de Colombia, al minuto, sin depender del servidor (CA-02, CA-05, CA-06, CA-23)

**El riesgo:** Documenta hoy confía en `APP_TIMEZONE=America/Bogota` del `.env`
(`config/app.php` cae en `UTC` si falta; `Marcador` usa `config('app.timezone')`). Un servidor sin
esa línea correría todas las horas 5 h, y un ingreso a las 11:00 p. m. caería en el día siguiente:
días de estadía, cruces, «no futura» y el límite del mes, todos mal, sin error visible.

**La decisión:** las fechas reales de la estadía son **hora de pared de Colombia**, siempre, sin
importar `app.timezone`:

- **Zona:** `config('repositorio.horario.zona')` (`America/Bogota`), la que ya usa
  `CalendarioHabil`. Nada nuevo en la configuración.
- **Cast `App\Casts\HoraColombia`** para `ingreso_en` y `egreso_en` (unas 15 líneas): al leer,
  `Carbon::createFromFormat('Y-m-d H:i:s', $valor, $zona)`; al escribir, convierte a la zona
  (`->setTimezone($zona)`) y guarda `format('Y-m-d H:i:00')`. **Los segundos siempre en 00**
  (decisión 9, CA-02). Columnas `datetime`, no `timestamp`: MySQL no las convierte con la zona de
  la sesión.
- **Entradas:** el formulario usa `<input type="datetime-local">` (un `TextInput` de Filament con
  `->type('datetime-local')`), **no `DateTimePicker`**: este convierte con `app.timezone` por su
  cuenta y se sumaría al cast. El navegador manda `2026-12-03T23:50` o, según el navegador y el
  `step`, `2026-12-03T23:50:00`: la validación acepta los dos
  (`date_format:Y-m-d\TH:i,Y-m-d\TH:i:s`), se interpreta en la zona con el formato que casó y los
  segundos se descartan (`->second(0)`). Vacío al abrir (CA-02, CA-14); `max` = ahora en Colombia.
- **«Ahora»** para «no futura» y el límite del mes: siempre `now($zona)`.
- **Fechas del sistema** (`created_at`, `egreso_registrado_en`, historial): `timestamps` normales
  en `app.timezone`; al mostrarlas, `->setTimezone($zona)->fechaHora()`. Correcto con cualquier
  `app.timezone`.
- **Mostrar:** `->hora()` / `->fechaHora()` (macros de `AppServiceProvider`) sobre el Carbon del
  cast, que ya está en Colombia: «11:50 p. m.».
- **Límite del mes (regla 5, CA-06):** `now($zona)->startOfDay()->subMonthNoOverflow()`. 3/11 →
  3/10 a las 12:00 a. m.; 31/3 → 28/2 o 29/2. Vale para la fecha nueva y la reemplazada (CA-28).
  `puedeGestionar()` (Coordinación en adelante) lo salta; ninguna otra regla. **Al corregir, solo
  se mira si cambia una fecha** (regla 5: «correcciones de fecha»): completar la observación o
  cambiar la sede de una estadía de hace dos meses no choca con el límite; cambiar su fecha, sí.
- **Prueba de la trampa (CA-23):** la prueba de días corre una vez con `app.timezone`
  `America/Bogota` y otra con `UTC` (`config([...])` + `date_default_timezone_set`), y el resultado
  y la hora guardada son los mismos.
- **Antes de desplegar:** Edwar comprueba `php artisan tinker --execute="echo config('app.timezone');"`
  en cada servidor (debe decir `America/Bogota`, por los turnos). Con este plan, DOC-27 no depende
  de eso, pero los turnos sí.

### D4. Días de estadía: una sola función (CA-23)

`Estadia::diasDeEstadia(CarbonInterface $ingreso, ?CarbonInterface $egreso, ?CarbonInterface $hoy = null): int`,
estática y pura, en la zona de Colombia:

- `$egreso` igual a `$ingreso` → **0** (duración cero, regla 3).
- Si no, `fin` = fecha de `$egreso`, o de `$hoy ?? now($zona)` si sigue activa; resultado =
  días entre `ingreso->toDateString()` y `fin->toDateString()` **+ 1**. 3 a las 11:50 p. m. → 4 a
  las 12:10 a. m. = 2; mismo día = 1.
- Accessor `$estadia->dias` la llama. **Anulada: no se cuenta** (la lista muestra «—»).
- **Se calcula en PHP, nunca en SQL:** sin `DATEDIFF`/`julianday` (que difieren entre MySQL y
  SQLite) y sin zonas en la base. DOC-28 y DOC-29 llaman a esta misma función; si un día necesitan
  sumar en la base, cambia su plan, no se copia la fórmula.
- Salida exactamente a las 12:00 a. m. cuenta ese día (lectura literal de «contando el día de
  salida»). Ver **P-02**.

### D5. Un servicio para escribir: `App\Services\RegistroEstadias` (§7)

(No `Estadias`: así se llama la tabla Livewire `App\Livewire\IngresosEgresos\Estadias`.)

Métodos: `registrarIngreso`, `registrarEgreso`, `corregirIngreso`, `corregirEgreso`, `reabrir`,
`anular`. Los llaman solo las acciones de Filament (D7) y las pruebas. Cada uno, **en una
`DB::transaction`**, en este orden:

1. **Bloquear la persona:** `UsuarioHc::whereKey($id)->lockForUpdate()->firstOrFail()` (con scope:
   otra dependencia, 404). En los métodos sobre una estadía, el `usuario_hc_id` sale de una lectura
   previa con scope (`Estadia::findOrFail`, otra dependencia u otro componente: 404). Serializa
   todos los movimientos de una persona en MySQL; en SQLite la escritura ya es exclusiva.
2. **Releer la estadía bajo el bloqueo** (`Estadia::findOrFail`, con scopes) y **autorizar después**,
   sobre lo releído, con la Policy (D6; `create` al registrar ingreso), y comprobar
   `auth()->user()->activo` (CA-34; el middleware persistente ya corta, esto da el mensaje).
   Autorizar antes del bloqueo decidiría sobre un estado que puede cambiar entretanto (p. ej.
   Edición corrige una estadía que otro acaba de anular).
3. **Comprobar la revisión:**
   `Estadia::whereKey($id)->where('revision', $enviada)->increment('revision')`; si da 0, «Otra
   persona cambió esta estadía mientras la editabas. Recarga para ver sus cambios» (CA-13). Un
   segundo egreso del mismo formulario cae aquí (CA-08). Si una regla posterior falla, la
   transacción deshace el incremento.
4. **Bloquear la sede** cuando el movimiento suma ocupación: registrar ingreso, corregir ingreso
   cambiando de sede (la nueva) y reabrir: `Sede::whereKey($id)->lockForUpdate()->firstOrFail()`
   (con scopes) y comprobar que siga **activa**. Desactivar una sede (IYE-07) bloquea la misma fila
   antes de contar sus activas. Orden fijo, **persona y después sede**; desactivar solo bloquea la
   sede: no hay espera cruzada. Así un ingreso y una desactivación simultáneos no dejan a alguien
   dentro de una sede inactiva (CA-26, CA-34). Reabrir en una sede ya inactiva se rechaza («San
   Cristóbal está inactiva; actívala antes de reabrir», §7 regla 9).
5. **Validar** (una regla falla → excepción de validación, nada escrito, el formulario conserva lo
   escrito, CA-20):
   - persona **Viva** (CA-03, CA-34); sede del componente (CA-26); opciones vigentes o ya
     presentes en esa estadía (`UsuarioHc::opciones`, CA-33, CA-34);
   - **una activa:** `Estadia::entreComponentes()->where('usuario_hc_id', $id)->where('estado', 'activa')`
     → «Esta persona ya tiene un ingreso activo en Estabilización» (solo el nombre, CA-03);
   - **cruces** (regla 2, CA-04): existe otra válida (`estado <> anulada`, otro `id`) con
     `ingreso_en < :fin` y (`egreso_en IS NULL` o `egreso_en > :inicio`), donde `:fin` es el egreso
     (o «sin fin» si queda activa). Desigualdades estrictas: llegar **a la hora exacta** de la
     salida anterior pasa (CA-25); una estadía de duración cero dentro de otra choca;
   - **retroactiva** (regla 7, CA-29): ingreso nuevo `>=` `max(egreso_en)` de las válidas de la
     persona;
   - salida `>=` llegada (regla 3); **no futura** (regla 4); **un mes** (regla 5, D3);
   - comuna solo con procedencia «Intervención calle»; destino obligatorio solo con «Derivación a
     otro componente» y vaciado con cualquier otro motivo (CA-10); condiciones excluyentes con
     `UsuarioHc::chocanExcluyentes` (CA-09).
6. **Escribir** la estadía, sus respuestas y, al corregir, reabrir o anular, el historial
   (`estadia_cambios`).
7. **Efectos en la ficha** (§6, CA-17), **después** de escribir la estadía (ya egresada: así el
   aviso de UHC-21, que mira si hay estadía activa, no sale; CA-17 lo comprueba con
   `Notification::assertNothingSent()`). La ficha ya está bloqueada (paso 1):
   - egreso o corrección **a** «Fallecimiento» →
     `FichasUsuariosHc::cambiarEstadoVital($ficha, 'fallecida', $fechaFallecimiento, $motivo)`;
   - reabrir, anular o corregir **desde** «Fallecimiento» exige la respuesta `persona_viva`:
     `true` → `cambiarEstadoVital($ficha, 'viva', null, $motivo)`; `false` → se rechaza («No se
     reabrió: la persona sigue registrada como fallecida»; «No se anuló…»; «No se corrigió…»);
   - **corregir un egreso que sigue en «Fallecimiento»:** el formulario muestra la fecha de
     fallecimiento **de la ficha**; si se cambia o se borra, `cambiarEstadoVital($ficha, 'fallecida',
     $nueva, $motivo)`. Cambiar la fecha y hora del egreso **no** mueve la de fallecimiento (son
     independientes, §6);
   - **el motivo que recibe la ficha** (queda en su historial): registrar egreso →
     «Egreso por fallecimiento en {sede} (Ingresos y egresos)»; corregir → «Corrección del egreso:
     {motivo escrito}»; reabrir → «Reabrió la estadía: {motivo escrito}»; anular → «Anuló la
     estadía: {motivo escrito}».
8. **Auditar** (D8).
9. **Capacidad** (regla 6, CA-07): después de guardar, si la ocupación de la sede en el momento del
   ingreso (`estado <> anulada`, `ingreso_en <= t`, `egreso_en` nulo o `> t`) llega o pasa
   `capacidadEn(t)`, el servicio devuelve el aviso y la acción lo muestra («San Cristóbal quedó con
   31 personas; su capacidad es 30»). Se calcula al **registrar ingreso**, al **reabrir** y al
   **corregir el ingreso cambiando de sede** (sobre la sede nueva). Si `capacidadEn(t)` es `null`
   (día anterior a la primera capacidad, PA-11), no hay aviso. Nunca bloquea ni cambia la
   capacidad. Ver **P-05**.

**Duplicados (regla 8, CA-08):** el doble clic lo frena el botón de Filament (se deshabilita
mientras guarda); dos personas a la vez, el bloqueo de la fila de la persona; y si todo eso falla,
el índice único `activa_usuario_hc_id`: la `UniqueConstraintViolationException` (igual en los dos
motores, ya usada en `CasoDerivacionController`) da el mismo mensaje de «ya tiene un ingreso
activo».

**Cierres de periodo (regla 11, DOC-30):** el servicio es el único camino de escritura; DOC-30 pondrá
su comprobación aquí. Nada se construye ahora.

**Reabrir (CA-14):** con la persona bloqueada, comprueba que no tenga otra activa y que la estadía,
**sin fin**, no se cruce con otra válida (cualquier válida con `ingreso_en >= ingreso_en` de esta
choca; el mensaje dice cuál: «Choca con la estadía del 12/12/2026 en San Cristóbal (Básica)» —
de otro componente, solo el componente). Escribe el historial del egreso, vacía el egreso, `estado
= activa`, `activa_usuario_hc_id = usuario_hc_id`.

**Anular (CA-15, CA-27):** activa o egresada; `estado = anulada`, `activa_usuario_hc_id = null`,
`anulada_*`, una fila `anular` en el historial. Ningún dato se borra.

### D6. Permisos: `EstadiaPolicy` y `SedePolicy`

Las dos con `ExigeModulo` (`const MODULO = 'ingresos_egresos'`) y el mismo `before` que
`UsuarioHcPolicy` (DOC-26 D3): sin módulo, todo negado (también el superadministrador); un
registro de otra dependencia, negado en todos los métodos; **además, una estadía o sede de otro
componente** (`(int) $registro->componente_id !== (int) $contexto->componenteId()`, con
`$contexto = app(\App\Services\ContextoDependencia::class)`), negada. Las dos comparaciones
(dependencia y componente) con `(int)` a ambos lados: el atributo puede llegar como texto según el
driver, y `!==` entre `'3'` y `3` negaría al dueño; sin componente en la sesión, `0` no casa con
nada y se niega. Es la segunda defensa: la primera es el 404 de los scopes.

| Método `EstadiaPolicy` | Regla (rol en la dependencia, parado en el componente) | CA |
|---|---|---|
| `viewAny` | Cualquier rol | CA-18, CA-22 |
| `view` (Ver estadía: condiciones, observaciones, historial) | `puedeEditar()` | CA-18 |
| `create`, `registrarEgreso` (activa), `corregir` (no anulada) | `puedeEditar()` | CA-32 |
| `pasarDelMes` | `puedeGestionar()` (Coordinación en adelante) | CA-05, CA-28 |
| `reabrir` (egresada), `anular` (no anulada) | `puedeGestionar()` | CA-16, CA-27 |
| `verFotos` (después del 15/12) | `puedeEditar()` | CA-35 |

- El coordinador líder y el superadministrador tienen Administración en `rolEn`: pueden lo de
  Coordinación **en el componente donde están parados** (§8). Para otro, cambian de componente.
  Así «las fotos las ve el superadministrador» se cumple parado en ese componente (P-06).
- `SedePolicy`: todos los métodos, `$usuario->es_superadmin` (CA-01). El coordinador líder y
  Coordinación reciben rechazo.
- Cada acción de Filament lleva su `->authorize()`; el servicio vuelve a autorizar (D5.2).
- **Livewire restaura modelos sin scopes:** las acciones de tabla resuelven el registro con la
  consulta de la tabla (con scopes, porque `EstablecerDependencia` es persistente); el servicio
  igual lo relee con `findOrFail` bajo el bloqueo y autoriza sobre eso (D5.1 y D5.2), como DOC-26
  con `$fichaId`.

### D7. Pantallas

| Pieza | Cómo | Quién |
|---|---|---|
| Pantalla `ingresos-egresos/index.blade.php` | Layout de Documenta con `@livewire(Estadias)` y, `@can('viewAny', Sede::class)`, `@livewire(Sedes)` debajo | Todos / solo superadministrador |
| `App\Livewire\IngresosEgresos\Estadias` | Tabla Filament (patrón `PuntosCriticos`) | Lectura en adelante |
| `App\Livewire\IngresosEgresos\Sedes` | Tabla Filament: crear, renombrar, cambiar capacidad, activar/desactivar | Superadministrador |
| «Ver estadía» | Blade + `EstadiaController@show` (ruta propia: se prueba el 404 por dirección y se audita) | Edición en adelante |

**Tabla de estadías:**

- Consulta: `Estadia::query()->with('usuarioHc')` (los dos scopes: solo el componente de la
  sesión, CA-22). Sin juntas: nombre y documento vienen de la relación. Orden por defecto
  `ingreso_en desc`.
- Columnas de la spec §2; «días» con el accessor (D4); horas con `->fechaHora()`; sede con
  `sede_nombre`. **Nunca** carga respuestas ni historial (CA-18).
- **Búsqueda:** `->searchable(query: fn (Builder $query, string $search) => $query->whereIn('usuario_hc_id', UsuarioHc::buscar($search)->select('usuarios_hc.id')))`
  (nombres de closure de Filament). Reusa `scopeBuscar` de UHC-05 tal cual.
- **Filtros:** «Fechas» (desde y hasta, por defecto del 1 del mes a hoy, en Colombia):
  `ingreso_en <= hasta 11:59 p. m.` y (`egreso_en IS NULL` o `egreso_en >= desde 12:00 a. m.`)
  (CA-22: trae lo que empezó antes). «Estado»: Todas (= activa o egresada), Activa, Egresada,
  Anuladas. «Sede»: sedes del componente, **incluidas las inactivas**. Sin filtro de componente.
- `persistSearchInSession()`, `persistFiltersInSession()`, página en la dirección (§2).
- **Acciones por fila** según estado (tabla de §2), cada una con `->authorize()`: Ver usuario HC
  (enlace a `usuarios-hc.show`, que llega con UHC-11 el 11/12: la acción va con
  `->visible(fn () => Route::has('usuarios-hc.show'))`, así IYE-05 se fusiona el 4/12 sin una ruta
  que no existe y el enlace aparece solo cuando llega), Ver estadía (enlace), Registrar egreso, Corregir ingreso,
  Corregir egreso, Reabrir, Anular. Reabrir y Anular con `->requiresConfirmation()` y motivo; si el
  motivo del egreso es Fallecimiento, el formulario agrega la pregunta «¿La persona está viva?»
  (Sí/No, obligatoria).
- **Acción de encabezado «Registrar ingreso»:** persona con `Select::searchable()` +
  `getSearchResultsUsing` sobre `UsuarioHc::buscar($search)` (solo identificación, hasta 20), que
  **no ofrece** fallecidas ni personas con estadía activa
  (`whereNotIn('usuarios_hc.id', Estadia::entreComponentes()->where('estado', 'activa')->select('usuario_hc_id'))`,
  CA-03); `getOptionLabelUsing` arma la etiqueta de la persona ya elegida (nombre y documento,
  buscada con scope), porque `getSearchResultsUsing` solo etiqueta los resultados de la búsqueda;
  sede (activas del componente; si hay una sola, puesta); fecha y hora vacías;
  procedencia; comuna visible solo con «Intervención calle» (`$get`); proyección; condiciones
  (`CheckboxList`); orientación; observación.
- **Desde Usuarios HC (UHC-22):** «Registrar ingreso» es acción **de encabezado de la tabla**, así
  que se abre con los parámetros de tabla de Filament 5.10.3
  (`vendor/filament/actions/src/Concerns/InteractsWithActions.php`, propiedades `#[Url]`):
  `route('ingresos-egresos.index', ['tableAction' => 'registrarIngreso', 'tableActionArguments' => ['usuario_hc' => $id], 'actionComponent' => 'estadias'])`.
  **`actionComponent` es obligatorio:** `isDefaultActionUrlTarget()` solo monta la acción si
  `getDefaultActionUrlIdentifier()` devuelve algo y casa con ese parámetro, y por defecto devuelve
  `null`. `Estadias` lo sobrescribe: `public function getDefaultActionUrlIdentifier(): ?string { return 'estadias'; }`.
  La acción llena el formulario con `->fillForm(fn (array $arguments) => …)`: **vuelve a buscar** el
  ID con `UsuarioHc::query()` (filtro de dependencia) y solo lo pone si la persona es Viva y no
  tiene estadía activa (`entreComponentes`); si no, el campo queda vacío. El ID de la dirección
  nunca se usa sin esa comprobación, y el servicio igual la repite al guardar. Sin ruta nueva.
- **Errores del servicio dentro de la ventana (CA-20):** el servicio lanza `ValidationException` con
  sus propias claves (`ingreso_en`, `sede_id`…), que no casan con los campos de la ventana
  (`mountedActions.0.data.*`): Filament la relanza con la ventana abierta, pero el mensaje no se ve.
  Cada acción envuelve la llamada:
  `try { … } catch (ValidationException $e) { Notification::make()->danger()->title('No se guardó')->body(collect($e->errors())->flatten()->first())->send(); $action->halt(); }`
  (`Filament\Notifications\Notification`; `$action` por nombre). `halt()` deja la ventana abierta
  con lo escrito; la transacción del servicio ya se deshizo. La prueba de CA-20 comprueba el texto
  visible (`assertNotified`) y que el formulario conserva lo escrito.
- Al guardar: notificación de éxito y, si hay, el aviso de capacidad (D5.9).

**Sedes (solo superadministrador, P-01):** crear (nombre, capacidad, vigente desde), renombrar,
cambiar capacidad (nueva, desde cuándo, motivo; muestra las anteriores), activar/desactivar.
Desactivar con estadías activas se rechaza: «San Cristóbal tiene 4 personas dentro; regístrales
el egreso antes de desactivarla» (CA-26); en una transacción que bloquea la sede antes de contar
(D5.4). Nombre único por componente con su mensaje (D1). Es una pieza aparte: moverla a «Componentes y nodos» u
otra pantalla es cambiar dónde se incluye un `@livewire`.

**Ver estadía:** ingreso (con sus condiciones), egreso, quién registró y cuándo, estado, y el
historial (`estadia_cambios`, del más nuevo al más viejo). Audita `EstadiaConsultada` cada vez
(CA-19). Horas con `->fechaHora()`.

En claro y oscuro, en celular, con las variables de `app.css`.

### D8. Auditoría

Acciones nuevas en `AccionAuditoria`, todas con `Auditor::registrar` y el modelo como auditable
(así `auditorias.componente_id` sale del registro, ACC-11):

| Acción | Cuándo | `datos` (planos, sin arreglos anidados) |
|---|---|---|
| `EstadiaIngreso` («Registró un ingreso») | Registrar ingreso. La descripción dice si la ficha es de otro componente (CA-30) | `['sede' => nombre]` |
| `EstadiaEgreso` («Registró un egreso») | Registrar egreso | `['motivo' => texto del motivo]` |
| `EstadiaCorregida` («Corrigió una estadía») | Corregir ingreso o egreso | `['cambios' => 'Fecha de ingreso, Sede', 'motivo' => …]`: **solo nombres de campo y motivo, sin valores** (CA-24) |
| `EstadiaReabierta` · `EstadiaAnulada` | Reabrir · anular | `['motivo' => …]` |
| `EstadiaConsultada` («Abrió una estadía») | Cada apertura de «Ver estadía» | `[]` |
| `EstadiaFotoVista` | Cada foto abierta (IYE-13) | `['tipo' => 'ingreso'|'egreso']` |
| `SedeGuardada` («Guardó una sede») | Crear, renombrar, activar, desactivar, cambiar capacidad (CA-01) | `['cambios' => …, 'capacidad' => '30 desde 01/12/2026', 'motivo' => …]`: la sede no es dato personal |

- **Datos planos a propósito:** la pantalla de Auditoría hace `implode` de cada valor de `datos`
  (`auditorias/index.blade.php:77`); un arreglo anidado da error 500 (lo encontró DOC-26 D4). La
  prueba de CA-24 abre esa pantalla con un registro de estadía.
- Cambiar el estado vital desde una estadía audita además `UsuarioHcActualizado` sobre la ficha
  (lo hace `cambiarEstadoVital`, UHC-12).

### D9. Configuración: listas de la estadía

En `config/repositorio.php`, junto al bloque `usuarios_hc` de DOC-26 y con su mismo formato
(`opciones`, `retiradas`, `excluyentes`):

```php
'ingresos_egresos' => [
    // Procedencia, destino y proyección usan esta lista (PA-05). Código => texto.
    'componentes_hc' => ['opciones' => ['estabilizacion' => 'Estabilización', 'intervencion_calle' => 'Intervención calle', /* … 8 */], 'retiradas' => []],
    'condiciones' => ['opciones' => [/* … 10 */], 'retiradas' => [], 'excluyentes' => ['sin_manifestaciones', 'no_valoradas']],
    'orientacion' => ['opciones' => ['orientada' => 'Orientada', 'desorientada' => 'Desorientada', 'no_valorada' => 'No valorada'], 'retiradas' => []],
    'motivos_egreso' => ['opciones' => [/* … 10, con 'traslado_sede', 'derivacion', 'fallecimiento' */], 'retiradas' => []],
],
```

- Comunas: `repositorio.comunas`, sin copiarla (§3).
- **Códigos con efecto** (`intervencion_calle`, `derivacion`, `fallecimiento`): constantes en
  `Estadia` (`PROCEDENCIA_CALLE`, `MOTIVO_DERIVACION`, `MOTIVO_FALLECIMIENTO`). No dependen del
  componente de Documenta (Básica o Estabilización), así que no van a `Componente::REGLAS`. Un
  comentario en la configuración dice que esos tres códigos no se retiran ni se cambian (el texto
  sí).
- `Componente::REGLAS`: **nada**. Nada cambia entre Básica y Estabilización.

### D10. Lo que hace DOC-27 dentro de DOC-26 (tareas UHC de DOC-26)

| Qué | Dónde | Tarea | Quién |
|---|---|---|---|
| `conocidaPor`: registro **o** una estadía válida (activa o egresada) en ese componente; `editablePor`: la activa está en ese componente; sin estadías válidas, el de registro; con estadías y ninguna activa, nadie salvo el líder (CA-27, CA-30) | `UsuarioHc`, con `Estadia::entreComponentes()` (D1) | UHC-19 | Edwar |
| Columna «componente o estado» y filtro de estado en la lista de Usuarios HC | `Listado`, con `with(['estadiaActiva' => fn ($query) => $query->select([...]), 'estadiaActiva.componente'])` (sin N+1; solo el nombre del componente, D1) y el filtro como subconsulta `whereIn`/`whereNotIn` sobre `entreComponentes()` | UHC-20 | José |
| Marcar «Fallecida» con estadía activa avisa a la Coordinación de ese componente y al líder; tipo nuevo en la campana (`NotificacionController::TIPOS`) (CA-31) | `FichasUsuariosHc`, notificación nueva. Destinatarios **solo Coordinación del componente y Administración, activos**, como `DerivacionPendiente::enviar` pero sin Edición: `usuariosDelComponente($componenteId)->wherePivotIn('rol', [RolDependencia::Coordinacion->value, RolDependencia::Administracion->value])->where('activo', true)` | UHC-21 | Edwar |
| «Registrar ingreso» en la lista de Usuarios HC | `Listado`, enlace con `tableAction`, `tableActionArguments` y `actionComponent` (D7); visible solo si `Gate::allows('modulo', 'ingresos_egresos')`, `can('create', Estadia::class)` y la persona es Viva sin estadía activa | UHC-22 | José |

### D11. Después del 15/12

- **Fotos (CA-35, IYE-13):** migración `estadia_fotos` (`estadia_id`, `tipo` ingreso/egreso, `ruta`,
  `tamano`, `subida_por`, `anulada_en`, `created_at`); campo en los formularios con
  `x-campo-archivo` sin sello (UHC-10); recodificación GD compartida con UHC-09; ruta de D2 con
  `verFotos`, `Content-Type: image/jpeg`, `Cache-Control: no-store`, CSP de `previsualizar`,
  `EstadiaFotoVista` cada vez. Reabrir deja la foto de egreso en el historial (no se borra).
  Disco `repositorio.disco`, ruta `estadias/{dependencia}/{estadia}/{uuid}.jpg`; nada en Documentos.
- **Diagnósticos (CA-36, IYE-14):** `estadia_respuestas` con `campo = diagnosticos`,
  `fuente = cie10`; `Select::multiple()->searchable()` como UHC-14, con `getOptionLabelsUsing` desde
  el texto guardado. Oculto si el catálogo está vacío (como DOC-26 D8). Las pruebas crean sus
  códigos.

## Qué cambia en la configuración y en `Componente`

- `Componente::MODULOS`: `ingresos_egresos` después de `usuarios_hc`. Sin migración de encendido.
- `Componente::REGLAS`: nada.
- `config/repositorio.php`: bloque `ingresos_egresos` (D9). Se reutilizan `horario.zona`,
  `comunas` y, después del 15/12, `disco`, `formatos.imagenes`, `tamano_maximo_kb`, `imagen.*`.

## Pruebas

- Carpeta `tests/Feature/IngresosEgresos/`: `IngresosEgresosModuloTest`, `SedesTest`,
  `DiasDeEstadiaTest`, `EstadiasAccesoTest`, `EstadiasIngresoEgresoTest`, `EstadiasListaTest`,
  `EstadiasCorreccionTest`, `EstadiasReaperturaAnulacionTest`, `EstadiasVerTest`. Nombres
  `test_doc27_caNN_…`; la tabla de tasks.md dice cuál va dónde.
- Básica y Estabilización con `Componente::factory()->create(['modulos' => ['usuarios_hc', 'ingresos_egresos']])`;
  Calle con `MODULOS_CALLE`. Livewire: dependencia y componente fijados a mano (`docs/FILAMENT.md`).
- **El reloj:** toda prueba de fechas fija `Carbon::setTestNow()` en Colombia; CA-06 con el 31/3 y
  el 3/11; CA-23 con las dos zonas (D3).
- Las aserciones sobre la base usan `withoutGlobalScopes()` (el singleton del contexto queda
  establecido tras un `get()`, lección de DOC-25).

### Consultas que se verifican contra MySQL (constitución VII)

`DB_CONNECTION=mysql DB_DATABASE=documenta_pruebas_mysql DB_URL= php artisan test --filter=IngresosEgresos`
(base aparte, sin tocar el `.env`), en IYE-11. No hay `GROUP BY` ni JSON; sí hay fechas, `OR NULL`,
subconsultas y bloqueos:

1. Filtro de rango con permanencia (`ingreso_en <=` … y `egreso_en IS NULL OR >=` …) junto con la
   búsqueda (`whereIn` sobre `scopeBuscar`, que ya tiene su propia subconsulta) y el conteo de la
   paginación de Filament.
2. Cruces y retroactivas sobre `Estadia::entreComponentes()` (sin `ComponenteScope`, con
   `DependenciaScope`): columnas calificadas, `max(egreso_en)`; la misma consulta como subconsulta
   (`whereNotIn` al ofrecer personas) y como relación con `with()` (UHC-20).
3. Índice único `activa_usuario_hc_id` con varios `NULL`, y que su violación llegue como
   `UniqueConstraintViolationException`.
4. `lockForUpdate()` de la persona y de la sede dentro de la transacción (en SQLite no hace nada).
5. Ocupación de la sede (aviso de capacidad) y conteo de activas al desactivar.
6. `capacidadEn` (orden por `vigente_desde` y `id`, `limit 1`) con `whereDate` (D1).
7. Nombre de sede repetido con otra tilde o mayúscula: MySQL lo rechaza por el índice y debe salir
   el mensaje, no un error 500 (D1).
8. Cast `HoraColombia` ida y vuelta en MySQL (que no corra la hora).
9. La pantalla de Auditoría con registros de estadía y sede.

## Riesgos

| Riesgo | Qué pasa | Cómo se baja |
|---|---|---|
| Servidor sin `APP_TIMEZONE` | Horas corridas 5 h; días, cruces y límite del mes mal | Cast `HoraColombia`, `now($zona)` y `datetime-local` sin `DateTimePicker` (D3); prueba con `UTC`; Edwar revisa la zona en cada servidor |
| Dos fórmulas de días (lista, tablero, reporte) | Números distintos para la misma estadía | Una función estática en PHP (D4); DOC-28/29 la llaman |
| Consultas que SQLite deja pasar | Falla en pruebas o producción | Sin juntas en la lista, sin `GROUP BY`, sin funciones de fecha en SQL; lista de MySQL arriba |
| La consulta que cruza componentes se usa de más | Se ve la estadía de otro componente | Un solo punto (`Estadia::scopeEntreComponentes`, D1); al usuario solo le llega el nombre del componente; QA lo busca con `grep -rn "withoutGlobalScope(ComponenteScope" app` |
| Valores sensibles en la auditoría, o arreglos anidados | Fuga a quien ve Auditoría; error 500 | Solo campos y motivo, planos (D8); CA-24 abre la pantalla |
| Carreras (doble clic, dos personas, ingreso contra desactivar la sede) | Dos activas, dos egresos o alguien dentro de una sede inactiva | Bloqueo de la persona y de la sede en orden fijo, revisión atómica, índice único (D5) |
| Errores del servicio que no se ven en la ventana | El usuario no sabe por qué no se guardó | Notificación de error + `halt()` (D7); CA-20 comprueba el texto |
| Fallecimiento depende de `FichasUsuariosHc` (UHC-12, 3/12) | IYE-04 no puede cerrar CA-17 el 4/12 | Firma escrita en DOC-26 (D7) el 25/11; si UHC-12 se atrasa, el efecto se mueve a IYE-10 (contado arriba, «Lo que se toma de DOC-26») |
| Archivos compartidos | Conflictos en git | `MODULOS` y `bootstrap/app.php` en IYE-01 (Edwar); `sidebar` en IYE-01 después de UHC-06; `ModulosPorComponenteTest` en IYE-03 (Edwar) e IYE-08 (José), D2; `UsuarioHc.php` (relación `estadiaActiva`) en IYE-02, avisando a José. `Estadias.php` lo toca José en IYE-06 **antes** de que Edwar agregue las acciones de IYE-09 e IYE-10 (tasks.md) |
| Calendario sin holgura hasta el 4/12 | El punto de control falla | tasks.md, «Calendario»: UHC-11 sale de esa semana (P-03). Holgura de Edwar: 0,5 en la semana del 7/12 y 0,5 el 14/12; medio día de retraso el 4/12 se come toda la de la semana del 7/12 |
| Sedes reales sin llegar (PA-01) | No se puede usar en producción | Sedes de prueba; bloquea solo producción |

## Documentación (IYE-12, y el manual después del 15/12)

- `documentacion/docs/context/domain.md`: RN-57 a RN-61 de la spec; RN-49 (módulo Ingresos y
  egresos), RN-04 y RN-06 (lecturas de estadías y fotos), RN-13 (fotos sin sello), RN-38 (tipo de
  la campana, con UHC-21); estados de la estadía y de la sede; en «Parámetros», el bloque
  `ingresos_egresos`; en «Nada se borra», que el historial de la estadía (`estadia_cambios`) es el
  registro de verdad: guarda el egreso reabierto y, al corregir, las `estadia_respuestas` vigentes
  se reemplazan y las anteriores quedan completas en el historial (como `usuario_hc_respuestas`,
  P-17 de DOC-26).
- `glossary.md`: Sede, Capacidad, Estadía, Ingreso, Egreso, Reabrir, Días de estadía; Módulo.
- `docs/FILAMENT.md`: regla 2 (los saltos de D1) y la tabla de pantallas; nota de
  `datetime-local` en vez de `DateTimePicker` para fechas reales.
- `CHANGELOG.md` (sección de la versión con DOC-26), estado de la spec, `documentacion/README.md`.
- Manual (sección Ingresos y egresos), después del 15/12, antes de producción.

## Preguntas técnicas para Edwar

**Respondidas por Edwar (2026-11-24): todas con la respuesta por defecto.**

- **P-01. Dónde va «Sedes».** Por defecto, como propone la spec: una sección dentro de «Ingresos y
  egresos», visible solo para el superadministrador. Es una pieza aparte (`Livewire\IngresosEgresos\Sedes`):
  moverla a «Componentes y nodos» es cambiar una línea y la prueba de la ruta.
- **P-02. Salida exactamente a las 12:00 a. m.** Entra el 3 a las 10:00 p. m. y sale el 4 a las
  12:00 a. m. Por defecto, **2 días** (la regla dice «contando el día de salida»). La otra lectura
  (no estuvo dentro ningún minuto del 4) da 1. Es un caso raro, pero DOC-28 contará cupos con la
  misma regla.
- **P-03. UHC-11 (ver la ficha e historial, DOC-26, tuya el 3/12) pasa al 11/12.** Sin eso, la
  semana del 1 al 4/12 suma 4,5 días de trabajo en 4 y el punto de control no llega. No retrasa
  DOC-26: UHC-11 no bloquea a nadie antes de UHC-17 (15/12).
- **P-04. Qué significa «pasan a la semana siguiente» si falla el 4/12.** Corregir, reabrir y
  anular ya están planeados para la semana del 7/12. Por defecto: si el 4/12 ingresos y egresos
  no funcionan en pruebas **y el retraso pasa de un día**, IYE-09 e IYE-10 salen del corte del
  15/12 y van a la semana del 14 al 18/12; si es medio día, lo absorbe la holgura de la semana del
  7/12 (0,5, que se agota); entre medio y un día, también la del 14/12, y QA queda sin margen
  (ajuste de la cuenta, 2026-11-25; la respuesta no cambia).
- **P-05. El aviso de capacidad.** Por defecto se muestra **al guardar** («quedó con 31 personas;
  su capacidad es 30»), sin un paso de confirmación antes. La regla es «avisa, no bloquea», así
  que no cambia lo que se guarda.
- **P-06. Fotos y superadministrador.** El superadministrador ve las fotos **parado en el
  componente de la estadía** (como todo lo demás). Por defecto, así: no se abre una excepción al
  aislamiento para él.
- **P-07. Sedes en el servidor de pruebas el 4/12.** La pantalla «Sedes» llega el 9/12 (José, IYE-07).
  Para el punto de control, Edwar crea una o dos sedes de prueba en el servidor con `tinker`
  (con su primera capacidad). Por defecto, así; si prefieres esperar a la pantalla, el punto de
  control se mira el 9/12. **Detalle (2026-11-25):** en `tinker` no hay usuario en sesión: a
  `sede_capacidades.user_id` se le pasa el `id` de Edwar a mano, y esas sedes quedan **sin
  auditoría a propósito** (son de prueba; no pasan por `Auditor::registrar`). Las fichas de
  prueba salen de la pantalla si UHC-07 y UHC-08 están en `develop` el 4/12; si no, también por
  `tinker`.

## Candidatos

- Los de la spec (retroactivas, sedes por usuario, permisos sueltos, sede destino en el traslado,
  anular con atenciones).
- `DemoIngresosEgresosSeeder` con sedes y estadías ficticias (local), después del 15/12.
- Confirmación previa cuando la sede está llena (si P-05 cambia).
