# Filament dentro de Documenta

Documenta usa **piezas de Filament 5** (tablas, formularios, ventanas y avisos, con
Livewire 4) **dentro de sus propias páginas**: mismo menú, misma barra, mismos permisos.
No hay panel aparte. Se adopta módulo a módulo, para las pantallas de mucha tabla.

Documentación oficial: <https://filamentphp.com/docs> (secciones *Tables*, *Forms*,
*Actions*; uso fuera del panel: «components»).

## Cómo está armado

| Pieza | Dónde |
|---|---|
| Componentes (uno por tabla) | `app/Livewire/<Módulo>/` (p. ej. `Turnos/TiposTurno.php`) |
| Vista común de una tabla | `resources/views/livewire/tabla-filament.blade.php` |
| Página que la contiene | Una vista normal que extiende `layouts.app` (ver `admin/turnos/catalogo.blade.php`) |
| Estilos de Filament | `resources/css/filament.css`, cargados **solo** en esas páginas con `@push('estilos')` |
| Colores (el azul de Documenta) | `FilamentColor::register` en `AppServiceProvider` |

## Reglas que no se pueden saltar

1. **Cada acción con su `authorize()`.** Fuera del panel Filament no revisa permisos
   solo: `CreateAction::make()->authorize('create', Modelo::class)`,
   `EditAction::make()->authorize('update')`, `DeleteAction::make()->authorize('delete')`.
2. **La consulta de la tabla filtra por dependencia y por componente** (DOC-25). Lo hacen
   los global scopes: `DependenciaScope` y `ComponenteScope` (trait `DelComponente`, que
   además rechaza guardar en otro componente). Si el modelo no tiene `dependencia_id` o
   `componente_id`, por su padre: `Modelo::query()->whereHas('componente')` (así hereda los
   dos). Quien se salta el de componente a propósito usa
   `withoutGlobalScope(ComponenteScope::class)`, **nunca** `withoutGlobalScopes()`, que quita
   también el de dependencia. Hoy solo lo hacen el selector de componente
   (`EstablecerDependencia`, `InicioController`) y los componentes de «Enviar aviso»
   (`AvisoController`); con ACC-07 y ACC-09 se suman el campo «Componente» de Usuarios y
   `Componentes::nombreUnico()` (plan D4). DOC-26 (plan D1) suma la relación
   `UsuarioHc::componenteRegistro()` y las opciones del filtro «Componente» de la lista de
   Usuarios HC (`UsuariosHc\Listado::componentesConElModulo()`): la lista cruza componentes
   dentro de la dependencia, nunca dependencias (spec DOC-26 §6.1).
3. **No quitar los middlewares persistentes** de `AppServiceProvider`
   (`Livewire::addPersistentMiddleware`): cada clic de la tabla es una petición nueva y,
   sin `EstablecerDependencia`, `DependenciaScope` no filtra y se verían datos de todas
   las dependencias (y, desde DOC-25, de todos los componentes: el mismo middleware fija el
   componente). Lo vigila `CatalogosMallaTest`.
4. **La ruta de la página lleva su permiso**: `->middleware('can:viewAny,App\Models\Modelo')`.
5. **Parámetros por nombre.** En los closures de Filament se inyecta por nombre:
   `$data`, `$record`, `$state`, `$get`, `$rule`… Con otro nombre (`$datos`, `$regla`)
   falla.

## Agregar una tabla

1. Copiar `app/Livewire/Turnos/PuntosCriticos.php` como punto de partida.
2. Política del modelo en `app/Policies`.
3. Ruta con `Route::view(..., 'admin.turnos.catalogo', [...])` o una vista propia con los
   mismos `@push('estilos')` / `@push('scripts')`.
4. Prueba con `Livewire::test(...)`: acciones con `TestAction::make(...)->table()`,
   visibilidad con `assertCanSeeTableRecords` / `assertCanNotSeeTableRecords`. Livewire::test
   no pasa por los middlewares: establecer la dependencia y el componente a mano
   (`$contexto->establecer($dependencia)` y `$contexto->establecerComponente($componente)`,
   con `$contexto = app(ContextoDependencia::class)`). Sin `establecerComponente`,
   `ComponenteScope` no filtra.

## Despliegue

`composer install` corre `php artisan filament:upgrade` (publica sus assets en
`public/css|js|fonts/filament`, ignorados por git) y el despliegue compila
`resources/css/filament.css` con `npm run build`. El servidor necesita las extensiones
de PHP **intl**, **xmlreader** y **zip**.

## Tablas con Filament

| Página | Se abre desde | Quién |
|---|---|---|
| Usuarios (crear y editar siguen en UsuarioController) | Administración → «Usuarios» | Coordinación en adelante |
| Componentes y nodos (panel lateral con reglas y nodos) | Administración → «Componentes y nodos» | Coordinación en adelante |
| Tipos de turno | Personas de campo → «Tipos de turno» | Edición en adelante |
| Puntos críticos (con «Ver en el mapa») | En turno ahora → «Puntos críticos» | Edición en adelante |
| En turno ahora (solo consulta: páginas, búsqueda y filtros) | Turnos → «En turno ahora» | Edición en adelante |
| Usuarios HC (búsqueda con `UsuarioHc::buscar()`, búsqueda y filtros guardados en la sesión, página en la dirección) | «Usuarios HC», en el menú principal | Todos los roles de un componente con el módulo |
