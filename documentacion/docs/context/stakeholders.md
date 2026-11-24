# Actores y permisos

> **Estado:** borrador para aprobar por Edwar.

## Quién usa Documenta

| Actor | Quién es | Entra con | Qué hace en Documenta |
|---|---|---|---|
| Persona de campo | Educadores, operadores terapéuticos, conductores, monitores de ruta… de un componente (Calle, Básica, Estabilización) | **Su cédula**, por un enlace o QR. No tiene cuenta | Marca entrada y salida, envía evidencias, registra derivaciones |
| Líder (rol **Edición**) | Líder de un componente o nodo | Cuenta (Authentik) | Sube y organiza documentos, gestiona turnos y personas de campo, responde derivaciones |
| Seguimiento de derivaciones (rol **Edición**) | Por ejemplo, Deisy | Cuenta | Sigue qué derivaciones están sin respuesta y quién responde (D-20). Si tiene la casilla, aprueba novedades de turno (D-13) |
| **Coordinación** | Coordinación **de un componente** (desde DOC-25; antes, de la dependencia). El documento de origen la llama «Coordinador de componente» | Cuenta | Lo de Edición en su componente, más usuarios, su componente con sus nodos y la auditoría de lo que hicieron los usuarios de su componente. Si su componente tiene Documentos (Calle): tipos de documento, enlaces de carga de todos y custodia |
| **Administración** · coordinador líder | Coordinación general de la dependencia (DOC-25, RN-50). No pertenece a ningún componente | Cuenta | Control total en todos los componentes de la dependencia (elige en el menú en cuál trabaja): da los permisos (crea usuarios, les asigna componente y rol), retira y devuelve documentos, envía avisos a los componentes que elija |
| **Lectura** | Personal que consulta | Cuenta | Consulta y descarga lo autorizado |
| Contratista o entidad externa | Quien envía documentos al Comité | Un enlace de carga temporal, sin cuenta | Envía documentos o fotos declarando quién es |
| Talento Humano | Área del Comité | — (por ahora no usa Documenta) | Define jornadas, descansos y reglas laborales; responde por escrito (F-05) |
| Nómina | Área del Comité | — (recibe el Excel) | Liquida con el reporte de turnos; calcula los valores (D-11) |
| Superadministrador | Cuenta de plataforma | Cuenta | Administración en todas las dependencias; solo otro superadmin la gestiona. Desde DOC-25, el único que da el rol Administración, crea componentes y decide sus módulos (ACC-07 y ACC-09, pendientes) |

## Roles de la aplicación

El rol es **por dependencia**: la misma persona puede ser Lectura en una dependencia y
Administración en otra. Cada rol incluye lo del anterior. *(F-02; `app/Enums/RolDependencia.php`)*

| Puede… | Lectura | Edición | Coordinación | Administración |
|---|:-:|:-:|:-:|:-:|
| Ver y descargar documentos activos (salvo custodia) | ✓ | ✓ | ✓ | ✓ |
| Subir, versionar, copiar, mover, comentar | | ✓ | ✓ | ✓ |
| Inactivar documentos (con motivo) y carpetas | | ✓ | ✓ | ✓ |
| Turnos: enlaces, personas, en turno, marcaciones, reporte | | ✓ | ✓ | ✓ |
| Aprobar novedades de turno | | Con casilla | ✓ | ✓ |
| Derivaciones: ver, responder, corregir | | ✓ | ✓ | ✓ |
| Eliminar derivaciones | | | ✓ | ✓ |
| Usuarios, tipos de documento, componentes, custodia, auditoría | | | ✓ | ✓ |
| Reactivar carpetas | | | ✓ | ✓ |
| Ver y reactivar documentos retirados; enviar avisos | | | | ✓ |

Nadie gestiona a un usuario con un rol mayor que el suyo (`UsuarioController::verificarRango`).

**Primero el componente, después el rol (DOC-25).** Lectura, Edición y Coordinación
pertenecen a un componente y la tabla de arriba vale **dentro de ese componente**: abren solo
los módulos que su componente tiene y ven solo sus datos (RN-01, RN-49). Administración es el
coordinador líder: sin componente y con acceso a todos los de la dependencia, uno a la vez
(RN-50). En la Auditoría, Coordinación ve solo lo de su componente (también lo de la vía pública y lo
automático); Administración, todo. Lo que falta de la pantalla de Usuarios (Coordinación gestiona solo los
de su componente; Administración la da solo el superadministrador) se aplica con ACC-07 y
ACC-08.

## Componentes y nodos

Un **componente** es un programa de la dependencia (Calle, Básica, Estabilización); se
divide en **nodos** (equipos o zonas: Nodo 1 a Nodo 6 en Calle). Las reglas de turno,
cargos, tipos de turno y puntos críticos van por componente. *(F-03, F-06)*

Desde DOC-25 cada componente tiene además **sus módulos** (Documentos, Turnos, Derivaciones,
Supervisión). Calle tiene los cuatro; Básica y Estabilización se crearon sin módulos ni nodos,
a la espera de los módulos de Habitante de Calle (DOC-26 a DOC-31). Documentos es solo de
Calle (RN-51).
