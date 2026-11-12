Título: Explorador: menú ⋮ con Editar y Seleccionar en las tarjetas de carpeta
Base: develop  ←  menu-tarjetas-carpeta

## Qué cambia

En el explorador, la tarjeta de carpeta ya no enseña el checkbox arriba a la izquierda. Ahora tiene un botón **⋮** arriba a la derecha que abre un menú con:

- **Editar**: lleva a `carpetas.edit`, la pantalla que ya existía. Antes ese enlace solo aparecía al pasar el ratón y no se podía alcanzar con el teclado.
- **Seleccionar** / **Quitar selección**: marca o desmarca la carpeta para copiar o mover.

Lo que hace el sistema no cambia, solo el camino para llegar. Este PR va aparte del trabajo de turnos.

## Cómo está hecho

- **El check del lote sigue ahí, escondido** (`hidden`, con el mismo `name="carpetas[]" form="form-lote" data-lote-item`). «Seleccionar» lo marca y lanza `change`, así que `lote-explorador.js` lo trata como siempre: la barra, el contador, «Quitar marcas», copiar, mover y el `confirm()` de mover no se tocan.
- **La tabla de documentos no cambia.** Usa el mismo mecanismo (`data-lote-item` + `form-lote`), pero su marcado es otro y aquí no se modifica. «Marcar todos los documentos» sigue marcando solo documentos, igual que antes.
- **El menú es un `<details>`**, como el de la cuenta (no hay Alpine y no se agrega ninguna librería). El nuevo `menu-opciones.js` añade:
  - ↑/↓ para recorrer las opciones, más Inicio/Fin;
  - Escape cierra y devuelve el foco al ⋮; Tab cierra el menú;
  - se cierra al hacer clic afuera, y abrir uno cierra el anterior;
  - se reubica para no salirse de la pantalla: hacia arriba si no cabe debajo, y nunca por los lados.
- **El ⋮ va al lado del `<a>` de la tarjeta, no dentro**, así que pulsarlo no puede abrir la carpeta. No uso `stopPropagation`: no hay ningún enlace ancestro que frenar, y frenarlo rompería el cierre por clic afuera del menú de la cuenta.
- **La tarjeta seleccionada se reconoce sin checkbox**: borde y anillo de color, tinte de fondo y una marca ✓ sobre el icono, todo con `has-[:checked]` y las variables del tema (claro y oscuro).
- **Accesibilidad**:
  - el ⋮ tiene `aria-label="Opciones de la carpeta X"`, `aria-haspopup="menu"` y `aria-expanded`;
  - las opciones usan `role="menuitem"` y miden 44 px de alto;
  - el estado «Seleccionada» se anuncia con `aria-describedby` en el ⋮ y también va dentro del enlace de la tarjeta.
- En las carpetas inactivas el menú solo ofrece «Editar», porque ya antes no se podían marcar. El ⋮ queda a opacidad completa y solo se atenúa el contenido.

## Pruebas

- `php artisan test`: **423 passed** (antes 416).
- Nuevo `MenuTarjetaCarpetaTest`: marcado del menú, check escondido que sigue enviándose al lote, el ⋮ fuera del enlace, Lectura sin menú, carpeta inactiva solo con Editar y la tabla con sus checks intactos.
- Nuevo test en `CopiarMoverPorCheckTest`: recorre el marcado (opción → check escondido → `#form-lote`) y envía el lote con una carpeta y un documento.
- Verificado en Chrome headless (43 comprobaciones): teclado completo, ratón, un solo menú abierto, barra y contador, «Marcar todos», «Quitar marcas», móvil 360 px (sin salirse por los lados y abriéndose hacia arriba) y mover de punta a punta con el `confirm()`. Sin errores de consola.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
