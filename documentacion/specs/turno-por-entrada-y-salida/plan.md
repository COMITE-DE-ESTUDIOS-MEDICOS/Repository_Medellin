# Plan: Turno por entrada y salida, y cierre sin salida por cargo (DOC-21)

Spec: [spec.md](spec.md) v0.2, aprobada.

## Reconocimiento (TES-01)

- `Programacion::reconocer($componente, $entrada, $habitual = null, $salida = null)` conserva
  su forma de respuesta.
  - Con una salida real, cada candidato de la ventana se mide por
    `|entrada − inicio| + |salida − fin|` (el fin pasa al día siguiente si el turno cruza la
    medianoche) y gana el menor.
  - Sin salida, gana el inicio más cercano.
  - El habitual solo desempata entre candidatos con la misma distancia.
- `Jornadas::turno()` le pasa la salida, salvo que sea un `CierreAutomatico`. La tardanza del
  reporte sale del turno ya elegido.
- Las marcaciones no se reescriben, porque son inmutables: el turno de la entrada sigue siendo el
  provisional. Lo definitivo lo calcula `Jornadas`.
- Al registrar la salida (`Novedades::evaluar`), si el turno definitivo ya no da tardanza, la
  novedad de tardanza pendiente se cierra como corregida, con el motivo. Si la tardanza cambia,
  se actualizan su detalle y sus minutos.

## Cierre (TES-02)

- Nuevo en `Componente::REGLAS`: `horas_max_por_cargo => []`, un mapa de cargo a horas. Se edita
  en `Livewire\Turnos\Componentes`, en la sección Jornada, y solo admite cargos de la lista del
  componente.
- `Marcador::tope(Marcacion $entrada): Carbon` es el menor de:
  - la entrada más el máximo de horas (el del cargo, si lo hay; si no, `horas_max_turno`);
  - si la entrada se reconoce como un turno corto (de 4 h o menos de inicio a fin) y ningún
    turno largo tiene su inicio dentro de la tolerancia de esa entrada (PA-02): el fin del
    turno corto más la tolerancia.
- `cerrarAbiertas()` usa `tope()`.
- Al marcar (`marcar` y `estadoParaMarcar`): si la entrada abierta ya pasó su `tope()`, se
  cierra en la misma transacción, igual que en `cerrarAbiertas`, y lo nuevo es una entrada.
- En el reporte (MarcacionController, la vista, el Excel) y en la ficha, un turno con salida
  `CierreAutomatico` cuyas novedades siguen pendientes lleva «Cierre automático · por corregir».

Los dos frentes tocan archivos distintos (TES-01: Programacion, Jornadas, Novedades; TES-02:
Marcador, Componente, Livewire, MarcacionController, vistas) y van en paralelo.
