# Documenta — instrucciones para Claude

Documenta es el repositorio documental y operativo del **Comité de Estudios Médicos**
(Inclusión Social, Medellín): documentos con versiones y custodia, enlaces de carga,
turnos de las personas de campo, derivaciones y respuesta territorial. Lo desarrollan
Edwar (Jorge Eduardo Muñoz Quintero) y José Amaya, y lo usa personal no técnico.

Antes de un trabajo que no sea trivial, lee `documentacion/README.md`.

## Orden de autoridad

`documentacion/constitution.md` > `documentacion/docs/context/` > `documentacion/docs/architecture/`
> `spec.md` > `plan.md` > `tasks.md` > código.

Una capa nunca contradice a la de arriba. Si encuentras una contradicción que cambia lo que
se construye, **detente y repórtala**; las demás, al final del resumen.

`documentacion/fuentes/` es evidencia (actas, respuestas de Talento Humano y Nómina,
decisiones de Edwar): se lee y se cita, **nunca se modifica**.

## Cómo se trabaja

Documenta ya existe: lo construido está documentado en `documentacion/` y en `docs/`.
Desde ahora:

1. **Funcionalidad nueva o cambio de comportamiento** (una regla, un permiso, un cálculo,
   un flujo): primero `documentacion/specs/<funcionalidad>/spec.md` aprobado por Edwar,
   después `plan.md` y `tasks.md`, después código. Una tarea a la vez.
2. **Ajustes triviales** (textos, estilos, un error evidente que no cambia una regla):
   directo, sin spec.
3. Cada criterio de aceptación tiene su prueba (`test_caNN_…` en lo nuevo).
4. Al terminar una fase o tarea: resume, lista lo pendiente y **detente**.
5. Lo que contradiga una regla de negocio (`RN-NN` en `documentacion/docs/context/domain.md`)
   se pregunta; no se resuelve por cuenta propia.
6. Dudas que no bloquean: van a «Candidatos para la siguiente versión» del documento.

## Git y seguridad (no negociable)

- **No hagas commit, push, merge, tags ni PR sin que Edwar lo pida.** Ramas: `eduDev` y
  `josedev` → PR a `develop` (servidor de pruebas) → PR a `main` (producción).
- Firma de commits: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Nunca** muestres ni copies el `.env` (base de datos, Authentik). Nunca subas secretos.
- Los archivos de Nómina y Talento Humano traen salarios, EPS, cuentas y datos personales:
  **no se versionan**; si haces copias para leerlos, bórralas al terminar.
- A una IA externa (Hostinger u otra) nunca se le pasan contraseñas ni el `.env`.
- Antes de borrar o sobrescribir algo, míralo. Los datos de prueba (DEMO, SIM) son locales.

## Stack

- Laravel 12, PHP 8.3 (local: Laragon en Windows; servidores: Ubuntu + nginx + php8.3-fpm).
- Blade + Tailwind 4 (Vite). Livewire 4 solo donde hay piezas de Filament.
- **Filament 5 por piezas** (tablas, formularios, acciones), **dentro del layout de
  Documenta**: no hay panel aparte. Ver `docs/FILAMENT.md`.
- MySQL en local y en los servidores; **las pruebas corren en SQLite**.
- PHPUnit (`tests/Feature`). Autenticación con Authentik (OIDC) + formulario propio.
- Almacenamiento local abstraído (`config/repositorio.php` → `disco`).

## Comandos

- Pruebas: `php artisan test` (todas deben pasar antes de dar algo por terminado).
- Una prueba: `php artisan test --filter=NombreDeLaPrueba`.
- Compilar: `npm run build` (después de cambiar CSS/JS, y después de cada `git pull`).
- Migrar: `php artisan migrate`.
- Datos de demostración: `php artisan db:seed --class=DemoTurnosSeeder`;
  estrés de turnos: `php artisan turnos:simular-dia 2026-10-20 --personas=400`.
- Desarrollo: `composer run dev` (o `php artisan serve`).

## Reglas técnicas del proyecto

- **Aislamiento por dependencia:** `DependenciaScope` (global scope) + `ContextoDependencia`.
  Nada de filtrar a mano en el controlador como única defensa. En consola el scope no actúa.
- **Permisos en Policies**, nunca solo en la vista. Roles por dependencia en la pivote
  `dependencia_usuario`: Lectura < Edición < Coordinación < Administración
  (`RolDependencia::puedeEditar/puedeGestionar/puedeAdministrar`).
- **Nada se borra del negocio:** documentos y carpetas se inactivan con motivo; las
  marcaciones son inmutables (se anulan al corregir); las personas de campo se desactivan.
  Excepciones explícitas: las derivaciones usan `SoftDeletes` (Coordinación) y las
  notificaciones se reemplazan o retiran.
- **Toda acción que cambia datos deja auditoría** (`Auditor::registrar` + `AccionAuditoria`).
- **Lo que cambia entre componentes es dato**, no código: `Componente::REGLAS` y
  `componentes.config`. Nada de `if ($componente->slug === 'calle')`.
- Valores de la plataforma en `config/repositorio.php` (formatos, tamaños, horario hábil,
  festivos, cargas por tandas). No hay tabla `parametros`.
- Archivos fuera de la web: toda descarga pasa por un controlador con permiso y auditoría.
- La vía pública (enlaces por cédula `/t/{token}`, `/d/{token}`) nunca devuelve datos
  personales al navegador; solo el saludo recortado. Las URL de los QR impresos no cambian.
- Cambios de base de datos solo con migraciones. Notificaciones: canal `database`, con
  `mensaje` y `url` relativa.
- Horas en pantalla y en mensajes en 12 h: `->hora()` («5:09 p. m.») y `->fechaHora()`
  (macros en `AppServiceProvider`). Los Excel para Nómina y los `datetime-local` siguen en 24 h.
- Textos de la interfaz en español claro, para quien no programa. Colores con las variables
  de `resources/css/app.css` (`--primary`, `--card`, `--border`…), en claro y oscuro.

## Lecciones aprendidas (errores que ya pasaron)

- **SQLite deja pasar consultas que MySQL rechaza** (p. ej. `GROUP BY` con un `ORDER BY`
  heredado de la relación `notifications()` → usar `reorder()`). Si tocas consultas con
  agrupación, júntas o `JSON`, verifícalas contra MySQL además de las pruebas.
- **Filament inyecta los parámetros de los closures por nombre:** `$query`, `$data`,
  `$record`, `$state`, `$get`, `$rule`, `$search`. Con otro nombre llegan `null`.
- Fuera de un panel, **cada acción de Filament necesita su `->authorize()`**. Los
  middlewares de dependencia van como persistentes de Livewire (`AppServiceProvider`).
  En pruebas, `Livewire::test` no pasa por middlewares: establece la dependencia a mano.
- `Rule::exists(...)->where('col', false)` se convierte en `''`: usa `0`.
- El CSS de Filament importa de `vendor/filament`: en CI hay que correr `composer install`
  antes de `npm run build` (ya está en `.github/workflows/deploy.yml`).
- PHP descarta en silencio los archivos que pasan de `max_file_uploads`: las cargas se
  hacen por tandas y el servidor verifica que llegaron todos.
- Los scripts de edición en Python: usa cadenas crudas (`r"""…"""`) con rutas o `\` de PHP,
  y respeta los finales de línea del archivo (`newline=''`).

## Producción (checklist antes de fusionar a `main`)

- Extensiones de PHP: `intl`, `xmlreader`, `zip`, `gd`, `pdo_mysql`.
- Cron: `* * * * * cd <ruta> && php artisan schedule:run` (cierre de turnos, «no registró
  entrada», recordatorios de derivaciones).
- `php.ini`: `max_file_uploads = 50`, límites de subida acordes, parche de seguridad al día.
- `CHANGELOG.md` con la sección de la versión que se publica (se anuncia por la campana).
- Migraciones nuevas revisadas: `bin/deploy.sh` las corre solo.

## Dónde está cada cosa

| Tema | Dónde |
|---|---|
| Índice de la documentación SDD | `documentacion/README.md` |
| Reglas de negocio, estados, glosario | `documentacion/docs/context/` |
| Puesta en marcha y decisiones iniciales | `docs/INICIO.md` |
| Turnos (uso, despliegue, técnica) | `docs/turnos/README.md` |
| Filament dentro de Documenta | `docs/FILAMENT.md` |
| Fotos con sello y geolocalización | `docs/GEOLOCALIZACION.md`, `docs/RESOLUCION-FOTOS.md` |
| Interfaz | `docs/INTERFAZ.md` |
| Cambios publicados | `CHANGELOG.md` |
| Manual de usuario (en la app, exportable a PDF) | `resources/views/manual/` |
