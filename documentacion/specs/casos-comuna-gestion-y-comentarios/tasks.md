# Tareas: Casos · comuna, gestión realizada, comentarios y paginación (DOC-23)

Prefijo `CAS`. Pruebas `test_doc23_caNN_…`.

| Tarea | Qué | CA | Quién | Estado |
|---|---|---|---|---|
| CAS-01 | Migración: `derivaciones.comuna` (tinyint, null) y `derivaciones.gestion_realizada` (text, null). `config/repositorio.php` → `comunas` (número => nombre). Comuna obligatoria en «Nuevo caso» y editable al editar; se ve en la lista, la ficha y al atender | CA-02, CA-03, CA-10 | Agente A | Hecha |
| CAS-02 | Filtro por comuna (con «Sin comuna») en `consulta()`: lista, indicadores, Excel, ZIP. Selector 25/50/100 (`por_pagina`, lista blanca) y «Casos X a Y de N» | CA-01, CA-04 | Agente A | Hecha |
| CAS-03 | «Atención o gestión realizada» obligatoria (10 a 2000) en el formulario público de atención; en la ficha; editable por Edición en adelante; columnas COMUNA y GESTIÓN REALIZADA al final del Excel | CA-09, CA-10 | Agente A | Hecha |
| CAS-04 | Comentarios del caso: tabla `comentarios_derivacion`, controlador (comentar: Edición en adelante; borrar: autor o Coordinación en adelante), notificación por campana (creador y quienes comentaron, no el autor), auditoría, sección plegable en la ficha. La vía pública no los ve | CA-05 a CA-08 | Agente B | Hecha |
| CAS-05 | Manual, CHANGELOG, domain.md (RN-29, 34, 36), suite completa | — | Claude | Hecha |
