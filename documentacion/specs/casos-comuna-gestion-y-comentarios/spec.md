# Spec: Casos de derivación · comuna, gestión realizada, comentarios y paginación

| | |
|---|---|
| Historia | DOC-23 |
| Tipo | Historia de usuario · amplía DOC-20 (RN-29, RN-34, RN-36) |
| Estado | **Aprobada** |
| Versión | 1.0 |
| Fuentes | Pedido de Edwar, 2026-11-12 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-12 |

## Problema

Los casos DC van a crecer. Hoy:

- no se sabe en qué **comuna** pasó cada caso, y no se puede filtrar por ella;
- la persona de campo responde «¿Se cumplió?» Sí/No, pero no cuenta **qué hizo**;
- Coordinación no tiene dónde dejarle una indicación a quien creó el caso, como sí existe en los
  documentos;
- la lista ya está paginada (25 por página, con los enlaces al final), pero con muchos casos
  hace falta moverse más rápido.

## Comportamiento

### 1. Paginación de la lista de casos

La lista de casos ya trae **25 por página**. Se agrega:

- un selector **«Ver 25 · 50 · 100»** abajo, junto a los números de página (la misma
  paginación de Marcaciones), que se conserva al filtrar;
- los indicadores, el Excel y el ZIP siguen tomando **todo el filtro**, no solo la página.

### 2. Comuna al crear el caso

En «Nuevo caso», un campo **Comuna** obligatorio, con la lista oficial de Medellín:

| Comunas | Corregimientos |
|---|---|
| 1 Popular · 2 Santa Cruz · 3 Manrique · 4 Aranjuez · 5 Castilla · 6 Doce de Octubre · 7 Robledo · 8 Villa Hermosa · 9 Buenos Aires · 10 La Candelaria · 11 Laureles-Estadio · 12 La América · 13 San Javier · 14 El Poblado · 15 Guayabal · 16 Belén | 50 Palmitas · 60 San Cristóbal · 70 Altavista · 80 San Antonio de Prado · 90 Santa Elena |

- La lista es un valor de la plataforma (`config/repositorio.php` → `comunas`), no una tabla. Se
  guarda el número (1–16, 50–90).
- Se ve en la ficha del caso, en la lista, en el Excel (columna nueva **COMUNA**) y en lo que ve
  la persona de campo al atender.
- La edita Edición en adelante, como los demás textos del caso.
- Los casos y derivaciones anteriores quedan **sin comuna** («—»). El superadministrador o
  Edición pueden ponérsela al editar.

### 3. Filtro por comuna en Derivaciones

En la lista de casos, un filtro **Comuna** junto a los de nodo y estado, que incluye la opción
«Sin comuna». Los indicadores, el Excel y el ZIP respetan el filtro.

### 4. Comentarios en el caso

En la ficha del caso, una sección **Comentarios**, igual a la de los documentos:

- **Comentan Coordinación y Administración**, para dejarle indicaciones a quien lo creó.
  Edición responde en el mismo hilo.
- Se enteran por la campana quien creó el caso y quienes ya comentaron, nunca el propio autor.
- El comentario lo borra quien lo escribió, o Coordinación en adelante.
- Comentar no cambia el caso, ni sus horas, ni su estado. Se puede comentar abierto o atendido.
- **La conversación se pliega con un clic.** El encabezado «Comentarios (N)» la abre y la cierra.
  Aparece abierta si hay comentarios o si se llega desde la campana (`#comentarios` o
  `#comentario-N`).
- Queda en la auditoría.
- **La persona de campo no ve los comentarios.** La vía pública solo muestra lo necesario para
  atender (RN de la vía pública, DOC-20).

### 5. Gestión realizada en la atención

En el formulario de atención (enlace `/d/{token}`), antes de «¿Se cumplió con el requerimiento?»,
un campo **«Atención o gestión realizada»**:

- texto libre, **obligatorio**, de 10 a 2000 caracteres;
- ayuda: «Qué se hizo en el sitio: a quién se atendió, qué se entregó, a dónde se remitió…».

La observación sigue igual: obligatoria solo si la respuesta es «No». Se ve en la ficha, y en el
Excel en una columna nueva **GESTIÓN REALIZADA**. La corrige Edición en adelante, como la
observación. Los casos ya atendidos quedan sin ella («—»).

## Criterios de aceptación

| CA | Criterio |
|---|---|
| CA-01 | La lista se puede ver de a 25, 50 o 100, el selector se conserva con los filtros, y los indicadores y el Excel cuentan todo el filtro |
| CA-02 | Crear un caso exige una comuna de la lista; un valor fuera de la lista se rechaza |
| CA-03 | La comuna se ve en la ficha, en la lista, en el Excel y al atender |
| CA-04 | El filtro por comuna (incluido «Sin comuna») limita la lista, los indicadores y el Excel |
| CA-05 | Coordinación y Administración comentan un caso; Edición responde; Lectura no comenta |
| CA-06 | Al comentar, se avisa por la campana a quien creó el caso y a quienes ya comentaron, no al autor |
| CA-07 | Un comentario lo borra su autor o Coordinación en adelante; cada comentario se audita |
| CA-08 | La vía pública no muestra los comentarios |
| CA-09 | Atender exige la gestión realizada (10 a 2000 caracteres); se ve en la ficha y en el Excel |
| CA-10 | Los casos anteriores sin comuna ni gestión siguen en la lista y en el Excel con «—» |

## Reglas que cambian

- RN-29: el caso lleva comuna.
- RN-34: la trazabilidad suma la comuna, la gestión realizada y los comentarios.
- RN-36: el Excel suma las columnas COMUNA y GESTIÓN REALIZADA, al final, para no mover las de
  Nómina y las que ya existen.

## Decisiones (Edwar, 2026-11-12)

- **P-1.** Sí: selector 25/50/100.
- **P-2.** Solo Medellín: sin «Fuera de Medellín».
- **P-3.** Edición sí responde; la conversación se pliega con un clic.
- **P-4.** La gestión realizada es obligatoria siempre.

## Candidatos para la siguiente versión

- Barrio, además de la comuna (lista oficial de barrios por comuna).
- Un indicador por comuna (casos y tiempo promedio) en el panel.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-11-12 | Aprobada: P-1 a P-3 respondidas; conversación plegable |
| 0.1 | 2026-11-12 | Borrador con el pedido de Edwar |
