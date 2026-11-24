# Tareas: Acceso por componente (DOC-25)

Prefijo `ACC`. Plan: [plan.md](plan.md). Pruebas en `tests/Feature/AccesoPorComponenteTest.php`
(y archivos hermanos si crece), con nombres `test_doc25_caNN_…`.

Edwar lleva DOC-25 (plan de trabajo). José está en DOC-26: las tareas marcadas **José** son
para cuando tenga hueco y no tocan archivos de las de Edwar. Una tarea a la vez, cada una con
la suite completa pasando.

Estimación en días-persona. Edwar: ~4,5. José: ~2,25. Meta: servidor de pruebas el 27/11 si
José toma las suyas; si no, el 30/11.

| Tarea | Qué | CA | Tipo | Quién | Est. | Depende | Estado |
|---|---|---|---|---|---|---|---|
| ACC-01 | Esquema y datos: migración de `componentes.modulos` y `dependencia_usuario.componente_id`; `AccesoPorComponenteSeeder` (Calle con sus 4 módulos, Básica y Estabilización vacíos, usuarios de Lectura/Edición/Coordinación a Calle, personas de campo sin componente a Calle) y su migración; `DatabaseSeeder` lo llama. `Componente::MODULOS`, `tieneModulo()`, cast de `modulos`; `componente_id` en los `withPivot`; `User::componenteEn()`. `ComponenteFactory` con todos los módulos y estado `sinModulos()` | CA-01, CA-02 | Backend | Edwar | 0,5 | — | Hecha |
| ACC-02 | Contexto y permiso de módulo: `ContextoDependencia` (`establecerComponente`, `componente`, `componenteId`, `filtraComponente`, `tieneModulo`); `EstablecerDependencia` fija el componente (pivote, o sesión para Administración y superadministrador) y comparte `componenteActual` / `componentesDisponibles`; Gate `modulo`. Helper `usuarioCon`/`darRol` con componente por defecto. Sin filtrar datos todavía | CA-15 (parte) | Backend | Edwar | 0,5 | ACC-01 | Hecha |
| ACC-03 | Aislamiento de datos: `ComponenteScope`, trait `DelComponente` (scope + rechazo al guardar en otro componente) en los 9 modelos, scope por `id` en Componente, scope `whereHas` en Ausencia; componente en el aviso de caso duplicado y en la huella de la carga masiva de casos. Arreglar las pruebas que rompan por orden de creación o personas sin componente | CA-06, CA-15, CA-03 | Backend | Edwar | 1 | ACC-02 | Hecha |
| ACC-04 | Módulos: grupos de rutas con `can:modulo,…` (documentos, turnos, derivaciones, supervisiones) y trait `ExigeModulo` en las 10 Policies. Prueba que recorre cada ruta de cada grupo con un usuario de Básica | CA-05, CA-07, CA-11 (rechazo) | Backend | Edwar | 0,5 | ACC-02 | Hecha |
| ACC-05 | Llegada y selector, servidor: ruta `inicio` y su controlador (primer módulo que puede abrir, o bienvenida); login, Authentik, cambio de dependencia → `inicio`; `PUT componente/{componente}` con validación (Administración o superadministrador, componente activo de la dependencia) | CA-04 (llegada), CA-09 (selector) | Backend | Edwar | 0,25 | ACC-02 | Hecha |
| ACC-06 | Menú y pantallas: `sidebar.blade.php` (cada enlace pide su módulo; Tipos de documento y Grupos de custodia piden Documentos; selector de componente debajo del de dependencia; nombre del componente para los demás; logotipo → `inicio`); vista `inicio.blade.php` con los dos mensajes. En claro y oscuro, en celular. El selector es un grupo plegable del menú, como los demás (pedido de José) | CA-04, CA-11 (menú) | Frontend | José | 0,5 | ACC-02 (contrato en plan D3, D7) | Hecha |
| ACC-07 | Usuarios, servidor: `User::rolesAsignablesEn()` (Administración solo la da el superadministrador), `GuardarUsuarioRequest` (componente obligatorio salvo Administración; Coordinación solo el suyo), `UsuarioController` (pertenencia por componente, rango para cuentas de Administración, rechazo de «Dar acceso» a quien ya está en la dependencia, auditoría del componente), tabla `Livewire\Usuarios` (columna, filtro, Coordinación solo su componente) | CA-08, CA-09, CA-10 (rol) | Backend | Edwar | 0,75 | ACC-03 | Hecha |
| ACC-09 | Componentes y nodos: `ComponentePolicy::create` solo superadministrador; campo «Módulos» visible y guardable solo por el superadministrador; Documentos en un solo componente; `nombreUnico` sin el scope de componente | CA-10, CA-11 | Backend | Edwar | 0,5 | ACC-03 | Hecha |
| ACC-08 | Usuarios, vistas: campo «Componente» en crear y editar (oculto o deshabilitado con rol Administración; fijo para Coordinación) y texto de ayuda de los roles | CA-08 | Frontend | José | 0,25 | ACC-07 (nombres de campos acordados: `componente_id`) | Hecha · selector para Administración, fijo para Coordinación, de solo lectura en la ficha propia; con el rol Administración marcado se esconde (solo CSS). Pruebas en `AccesoPorComponenteUsuariosFormularioTest` |
| ACC-10 | Campana: `Dependencia::usuariosDelComponente()` en `Novedades::avisar`, `DerivacionPendiente::enviar` y `SupervisionImportante::enviarSiHace` | CA-12 | Backend | Edwar | 0,25 | ACC-01 | Hecha |
| ACC-11 | Auditoría por componente para Coordinación (`whereIn` de usuarios de su componente, también en el filtro «Usuario») y «Enviar aviso» por componentes (`Avisos::enviar` con `$componentes`, casillas en la vista) | CA-13 | Backend + frontend | José | 0,5 | ACC-02 | Hecha |
| ACC-12 | Vía pública sin cambios: pruebas de `/t/`, `/d/` y `/s/` con usuarios de Calle y de Básica existiendo y una sesión abierta en Básica en el mismo navegador | CA-14 | Backend (pruebas) | José | 0,25 | ACC-03 | Hecha |
| ACC-13 | Datos locales: `DemoTurnosSeeder` y `turnos:simular-dia` / `simular-semana` dejan sus componentes con Turnos, Derivaciones y Supervisión | — | Backend | José | 0,25 | ACC-01 | Hecha |
| ACC-14 | Verificación: suite completa; pantallas de la lista de riesgos contra MySQL con Calle y Básica; las consultas previas al despliegue en producción y la lista de Administración (PA-03) | CA-03 | Backend | Edwar | 0,25 | ACC-03 a ACC-11 | Pendiente |
| ACC-15 | Documentación: `docs/context/` (RN y glosario del plan), `docs/FILAMENT.md` regla 2, manual (componente, selector, bienvenida), `CHANGELOG.md`, estado de la spec y `README` | — | Frontend (docs) | José | 0,5 | ACC-06, ACC-08 | En curso · manual de Usuarios hecho (ACC-08); falta, al cerrar ACC-09 y ACC-10, quitar las marcas «pendiente» de `docs/context/` y del `CHANGELOG.md` |

## Orden y paralelo

```
ACC-01 ─ ACC-02 ─┬─ ACC-03 ─┬─ ACC-07 ─ (José) ACC-08
                 │          ├─ ACC-09
                 │          └─ (José) ACC-12
                 ├─ ACC-04
                 ├─ ACC-05
                 ├─ (José) ACC-06
                 └─ (José) ACC-11
ACC-01 ─ ACC-10, (José) ACC-13
todo ─ ACC-14 ─ (José) ACC-15
```

- Edwar: 01 → 02 → 03 → 04 → 05 → 07 → 09 → 10 → 14.
- José, sin pisar a Edwar: **ACC-06** (`sidebar.blade.php`, `inicio.blade.php`), **ACC-11**
  (`AuditoriaController`, `AvisoController`, `Services/Avisos.php`, sus vistas), **ACC-08**
  (vistas de usuarios), **ACC-12** (solo pruebas), **ACC-13**, **ACC-15**. Ninguno toca los
  archivos de las tareas de Edwar. `tests/TestCase.php` y `routes/` son solo de Edwar.
- Archivos que comparten ACC-05 (Edwar) y ACC-06 (José): ninguno; ACC-05 crea la ruta y el
  controlador, ACC-06 la vista. Se acuerda el nombre: ruta `inicio`, vista `inicio`, variables
  `componenteActual`, `componentesDisponibles`, `sinComponente`.

## Pendiente para salir a producción

- Respuestas de Edwar a P-01 a P-05 del plan (tienen respuesta por defecto).
- Consultas previas en producción (plan, «Antes de desplegar») y lista de Administración (PA-03).
- `CHANGELOG.md`: sección de la versión (cambio visible: Administración recibe ahora derivación
  vencida y supervisión importante).
- Revisión en el servidor de pruebas con un usuario real de cada rol en Calle.

## Candidatos

- C-01: mover una persona de campo de un componente a otro (hoy imposible con el aislamiento).
- C-02: vista de «Componentes y nodos» con todos a la vez para Administración (P-01).
