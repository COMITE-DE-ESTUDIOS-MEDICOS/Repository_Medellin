# Spec: Analítica de supervisión territorial

| | |
|---|---|
| Historia | DOC-14 |
| Tipo | Historia de usuario |
| Estado | **Aprobada · Construida** |
| Versión | 1.0 |
| Fuentes | Pedido de Edwar del 26-10-2026; C-01 de DOC-11 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 26-10-2026 («analítica como en derivaciones y exportar en Excel») |

## Objetivo

Que Coordinación y los líderes vean, sobre las supervisiones del filtro, cómo va cada
dinamizador, cada criterio, cada supervisor y cada nodo, y lo descarguen en Excel.

## Criterios de aceptación

**CA-01. Resumen.** Arriba de la lista: cuántas supervisiones, promedio de los nueve
criterios, promedio de la calificación global, cuántas con incidencia Relevante o Crítica
(y cuántas Críticas), cuántas con casos escalados y cuántas sin ubicación.

**CA-02. Por criterio.** El promedio de cada uno de los nueve criterios, del más bajo al
más alto, con su barra en el color de la calificación (verde ≥ 4, ámbar ≥ 3, rojo < 3).

**CA-03. Por dinamizador.** Cada dinamizador (sea el 1 o el 2 de la visita): cédula,
nombre, visitas, promedio de criterios, promedio global y última visita; del promedio más
bajo al más alto.

**CA-04. Por supervisor y por nodo.** Quién supervisa: visitas y promedio que da. Por nodo:
visitas, promedio y cuántas con incidencia Relevante o Crítica.

**CA-05. Respeta los filtros.** Todo lo anterior se calcula sobre las supervisiones del
filtro (fechas, nodo, punto, supervisor, dinamizador).

**CA-06. Excel.** «Descargar Excel» trae, además de la hoja de detalle, las hojas
«Por dinamizador», «Por criterio», «Por supervisor» y «Por nodo», con el mismo filtro.

**CA-07. Permisos.** Los mismos de la lista: Edición en adelante de la dependencia.

## Fuera de alcance

- Gráficas por mes (tendencia). Ver C-01.

## Candidatos

| # | Candidato |
|---|---|
| C-01 | Tendencia mensual por dinamizador y por criterio |
| C-02 | Promedio de supervisión en la ficha de la persona de campo |

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-10-26 | Aprobada |
| 1.0 | 2026-10-26 | Construida. Prueba: `test_doc14_la_analitica_resume_por_criterio_dinamizador_supervisor_y_nodo`. Verificada contra MySQL con 297 supervisiones (31 ms, 7 consultas) |
