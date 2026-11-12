# Spec: Ubicación imprecisa (iPhone con «Ubicación precisa» apagada)

| | |
|---|---|
| Historia | DOC-15 |
| Tipo | Corrección / historia de usuario |
| Estado | **Aprobada · Construida** |
| Versión | 1.0 |
| Fuentes | Reporte de problemas de ubicación en iPhone; simulación en WebKit del 26-10-2026 |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 26-10-2026 («sí, aplícalo así») |

## Problema

Con «Ubicación precisa» apagada (frecuente en iPhone), el teléfono entrega una ubicación
aproximada con kilómetros de error. Documenta la guardaba como buena: salía «fuera de zona»
falso y nadie le decía a la persona qué ajustar. Además, si el componente exige ubicación y
el teléfono no la daba, el servidor rechazaba y la foto se perdía.

## Reglas de negocio

- **RN-46. Ubicación imprecisa.** Una ubicación con error mayor a «Precisión mínima» del
  componente (500 m de fábrica) es imprecisa: se guarda, cuenta como ubicación, **no** se
  juzga «fuera de zona» y crea la novedad «Ubicación imprecisa».

## Criterios de aceptación

**CA-01. Aviso antes de enviar.** Si al registrar la ubicación es imprecisa, el enlace no
envía al primer toque: muestra «Tu teléfono está dando una ubicación aproximada (±N km).
Activa la ubicación precisa: <instrucciones del teléfono>.» y el botón cambia a «Registrar
de todos modos».

**CA-02. Se guarda y avisa.** Si registra de todos modos, la marcación queda con su
ubicación, marcada como imprecisa, **sin** «fuera de zona», y nace la novedad
«Ubicación imprecisa».

**CA-03. Precisa como siempre.** Con error menor o igual a la precisión mínima, todo sigue
igual (dentro o fuera de zona según el nodo).

**CA-04. Sin ubicación y obligatoria.** Si el componente exige ubicación y el teléfono no la
entrega, el enlace no envía: deja el aviso con las instrucciones y conserva la foto.

**CA-05. Se configura.** «Precisión mínima (m)» es una regla del componente, en Componentes
y nodos.

## Fuera de alcance

- Supervisión y derivaciones: guardan la ubicación tal cual (no juzgan zona).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-10-26 | Aprobada |
| 1.0 | 2026-10-26 | Construida. Pruebas: `UbicacionImprecisaTest`; verificada en un iPhone 14 emulado con WebKit (aproximada ±3 km, permiso negado). De paso: detección real de `DataTransfer` en `campo-archivo.js` y nombres largos de archivo que se partían fuera de la tarjeta |
