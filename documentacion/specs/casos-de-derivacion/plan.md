# Plan: Casos de derivación creados por la oficina (DOC-20)

Spec: [spec.md](spec.md) v0.3, aprobada por Edwar el 2026-11-10.

## Decisión principal: la misma tabla `derivaciones`

Un caso nuevo es una fila de `derivaciones` con `numero` (DC-NNN). Las anteriores tienen
`numero = null` («registro anterior»). Así la lista, el Excel, el ZIP, los permisos y el
borrado lógico siguen siendo uno solo, y las viejas no se migran.

| Lo que pide la spec | Columna |
|---|---|
| Código DC-NNN | `numero` (entero, único con `dependencia_id`) |
| Mensaje para la atención | `descripcion` (la de siempre) |
| Quién lo envía | `remitente` (`alcaldia`, `autoridades`, `otra`) + `entidad` con el texto que se lee («Alcaldía», o lo que se escribió en Otra) |
| Ubicación | `ubicacion` |
| Soporte | `soporte_documento_id` |
| Inicio del tiempo | `created_at`, copiado también en `fecha_requerimiento` y `hora_requerimiento` para que `requeridaAt()` y los filtros sigan iguales |
| Quién lo creó | `creado_por` (usuario) |
| Clave de un solo uso | `clave_formulario` (única) |
| Persona de campo, nodo | `colaborador_id`, `cedula`, `nombre`, `cargo`, `nodo_id`; ahora vacíos hasta que se atiende |
| Cierre | `atendida_at` (hora del servidor), `hora_respuesta`, `tiempo_respuesta_min` |
| ¿Se cumplió? | `cumplio_requerimiento` (booleano), `observacion` |
| Justificación | `justificacion` |
| Aviso de vencido enviado | `ultimo_aviso_at` (la de siempre: si tiene valor, ya se avisó) |

En los estados, `pendiente` se muestra como Abierto y `solucionada` como Atendido cuando el
caso tiene número. Las anteriores conservan sus nombres. El tiempo de un caso nuevo se mide
entre `created_at` y `atendida_at`, sin `minutosEntre`, que no sirve para más de 24 h.

## Piezas

1. **Base** (DCA-01). Migración, modelo, `AccionAuditoria`, la política
   `corregirHoras` (solo `es_superadmin`), las rutas y el limitador `caso-consulta`.
2. **La oficina crea** (DCA-02). `Admin\Turnos\CasoDerivacionController@create/store`, una
   vista pensada para el celular, la clave de un solo uso, el aviso de posible duplicado y el
   código asignado con bloqueo.
3. **El campo atiende** (DCA-03). `Publico\DerivacionController`: `mostrar` («Atender
   caso»), `cedula` (sin cambios), `caso` (POST: consulta con mensaje uniforme, límite y
   auditoría), `soporte` (ruta firmada de 10 min, `no-store`) y `atender` (transacción con
   `lockForUpdate`, revalidación, borrado de archivos si se rechaza).
4. **Panel y avisos** (DCA-04). La lista, la ficha (trazabilidad), «Corregir» (textos para
   Edición; horas, Sí/No y cierre a mano solo para el superadministrador), Excel y ZIP con
   las columnas nuevas, `derivaciones:recordar`, que solo avisa una vez al vencer, y la
   notificación `CasoAtendido`.
5. **Documentación** (DCA-05). RN en `domain.md`, el manual, el CHANGELOG y el estado en la
   spec y el README.

Las piezas 2, 3 y 4 tocan archivos distintos: van en paralelo después de la base.

## Seguridad (las 8 condiciones de la spec)

- Toda consulta y todo envío pasan por `enlaceVigente()` (tipo derivación, vigente, con el
  componente activo) y por la cédula activa y en turno, revalidada en cada petición.
- Buscar el caso: `Derivacion::withoutGlobalScopes()` filtrado por `dependencia_id` y
  `componente_id` del enlace, `numero` y estado `pendiente`. Si no existe, se responde
  siempre el mismo 422.
- `caso-consulta`: 10 cada 10 minutos por IP y por cédula.
- La respuesta del caso lleva solo el mensaje, quién lo envía, la ubicación, la URL firmada
  del soporte y su tipo.

## Riesgos

- `colaborador_id` deja de ser obligatorio: la migración lo vuelve `nullable`, y los
  `->colaborador` del código y de las vistas se revisan con `?->`.
- MySQL frente a SQLite: el índice único y `lockForUpdate` se prueban también contra MySQL
  local.
