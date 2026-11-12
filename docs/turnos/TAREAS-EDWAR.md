# Turnos — tareas de Edwar: base de datos y administración

Lee primero [`PLAN.md`](PLAN.md): el modelo de datos, el reparto de archivos (sección 4)
y el contrato con José (sección 5) están ahí. Rama: `eduDev` → PR a `develop`.

**Tu parte:** los datos y todo lo que ve la gente con sesión en Documenta (Coordinación
y Administración). **No tocas** nada de `resources/views/publico`, `resources/js`,
`EnvioPublicoController` ni `app/Services/Turnos/Marcador.php`: es de José.

---

## Fase 0 — la base (va primero: José arranca encima) ✅

Un solo PR, pequeño, que se mezcle rápido.

- [x] Migraciones: `componentes`, `nodos`, `colaboradores`, `enlaces_turno`,
      `marcaciones`, y las columnas `recepciones.colaborador_id` y
      `enlaces_carga.componente_id`. Columnas exactas en `PLAN.md` §3.
- [x] Índices:
  - único `(dependencia_id, documento)` en `colaboradores`;
  - `(colaborador_id, marcada_at)` en `marcaciones`;
  - `(componente_id, marcada_at)` en `marcaciones`.
- [x] Modelos con `#[ScopedBy([DependenciaScope::class])]` donde aplique, sus relaciones
      y factories.
- [x] `Colaborador::porDocumento()`. Normaliza con `User::normalizarDocumento()`.
- [x] `Componente::regla($clave, $defecto)`, leyendo el JSON.
- [x] Trait `app/Models/Concerns/TieneTokenSecreto.php`: saca de `EnlaceCarga`
      `generarToken`, `hashDe`, `porToken` y `token`, y aplícalo a `EnlaceCarga` y
      `EnlaceTurno`. Los tests de enlaces de carga actuales deben seguir verdes **sin
      tocarlos**.
- [x] `Marcacion`: impide `update` y `delete` en el modelo (lanza una excepción).
      Inmutable de verdad, no por convención.
- [x] Seeder: componente **Calle** y **Nodo 1 a Nodo 6**.
- [x] Rutas: crea `routes/turnos-admin.php` y `routes/turnos-publico.php` (este vacío,
      es de José) y regístralos en `bootstrap/app.php`.
- [x] `AccionAuditoria`: agrega **ahora** todas las acciones del módulo, para que José
      no tenga que tocar ese archivo:
      `colaborador.registrado`, `colaborador.actualizado`, `colaborador.verificado`,
      `turno.entrada`, `turno.salida`, `turno.correccion`, `turno.cierre_automatico`,
      `enlace_turno.creado`, `enlace_turno.revocado`.

**Listo cuando:** `php artisan migrate:fresh --seed` funciona, la suite completa pasa y
José puede crear un `Colaborador` y un `EnlaceTurno` con factories.

## Fase 1b — colaboradores, componentes y nodos ✅

- [x] **Colaboradores** (`admin/turnos/colaboradores`):
  - listado con **búsqueda por cédula** (coincidencia exacta y por prefijo) y por nombre;
  - filtros por componente, nodo, verificado y activo;
  - crear, editar y desactivar (nunca borrar);
  - botón **«Verificar»** para los autorregistrados;
  - ficha del colaborador con sus marcaciones y evidencias recientes.
- [x] **Componentes y nodos** (solo Administración): crear, editar, activar y desactivar,
      con la configuración del JSON en campos de formulario, no como JSON crudo.
- [x] `ColaboradorPolicy` y `ComponentePolicy`: Coordinación gestiona colaboradores y
      Administración gestiona componentes.
- [x] Menú lateral: sección **«Turnos»**, visible desde Coordinación.

## Fase 2b — enlaces de turno y «En turno ahora» ✅

- [x] **Enlaces de turno**: crear (componente, y nodo opcional), copiar, mostrar como
      **QR** (imprimible) y revocar. El QR se genera en el navegador; no hace falta
      ninguna librería PHP.
- [x] **En turno ahora**: quién tiene una entrada abierta, en qué nodo y desde qué hora.
      Usa `Marcador::estadoDe()` de José. Mientras no exista, usa un *stub*.

## Fase 3b — marcaciones, novedades, corrección y reporte ✅

- [x] **Marcaciones**: listado con filtros por **cédula**, componente, nodo y rango de
      fechas, con miniatura de la foto y enlace al mapa (lat y lng).
- [x] **Novedades**: marcaciones con `fuera_de_zona`, `fuera_de_turno`,
      `sin_ubicacion` u `origen = cierre_automatico`.
- [x] **Corrección manual**: agregar la marca que falta con motivo obligatorio. Llama a
      `Marcador::registrarManual()`. **Nunca edita** una marca existente.
- [x] **Tarea programada** (`routes/console.php`): `Marcador::cerrarAbiertas()` cada hora.
- [x] **Reporte CSV**: horas por colaborador y nodo en un rango. Lleva BOM UTF-8 para
      que Excel muestre bien las tildes. No hace falta ninguna librería de Excel por
      ahora.

## Tests (`tests/Feature/Turnos/Admin*`) ✅

- [x] La búsqueda por cédula encuentra con y sin puntos («1.234.567» = «1234567»).
- [x] Edición y Lectura no ven el módulo; Coordinación sí.
- [x] Una dependencia no ve los colaboradores ni las marcaciones de otra.
- [x] La corrección manual crea una fila nueva y deja la original intacta.
- [x] `Marcacion::update()` lanza una excepción.
- [x] El CSV suma bien un turno que pasa de medianoche.

## Pendiente de coordinar con José (ver `PLAN.md` §8) ✅

- [x] **8.1** Cargo, componente y nodo obligatorios; selects dependientes del componente,
      sin `<optgroup>`; toast con lo que falte. Aplica también a los filtros y al
      formulario de enlaces de turno.
- [x] **8.2** Versión automática en el menú (deploy cuenta las fusiones desde `v2.0`).
- [x] **8.3** Pantalla «Novedades» con aprobar o rechazar y aviso por la campana.
      Decidir si Edición valida.
- [x] **8.4** Avisos de versión automáticos (`CHANGELOG.md`) y módulo «Enviar aviso».
