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
2. **La consulta de la tabla filtra por dependencia.** Si el modelo no tiene
   `dependencia_id`, por su padre: `Modelo::query()->whereHas('componente')`.
3. **No quitar los middlewares persistentes** de `AppServiceProvider`
   (`Livewire::addPersistentMiddleware`): cada clic de la tabla es una petición nueva y,
   sin `EstablecerDependencia`, `DependenciaScope` no filtra y se verían datos de todas
   las dependencias. Lo vigila `CatalogosMallaTest`.
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
   no pasa por los middlewares: establecer la dependencia a mano.

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
