# Actores y permisos

> **Estado:** borrador para aprobar por Edwar.

## Quién usa Documenta

| Actor | Quién es | Entra con | Qué hace en Documenta |
|---|---|---|---|
| Persona de campo | Educadores, operadores terapéuticos, conductores, monitores de ruta… de un componente (Calle, Básica, Estabilización) | **Su cédula**, por un enlace o QR. No tiene cuenta | Marca entrada y salida, envía evidencias, registra derivaciones |
| Líder (rol **Edición**) | Líder de un componente o nodo | Cuenta (Authentik) | Sube y organiza documentos, gestiona turnos y personas de campo, responde derivaciones |
| Seguimiento de derivaciones (rol **Edición**) | Por ejemplo, Deisy | Cuenta | Sigue qué derivaciones están sin respuesta y quién responde (D-20). Si tiene la casilla, aprueba novedades de turno (D-13) |
| **Coordinación** | Coordinación de la dependencia | Cuenta | Lo de Edición, más usuarios, componentes, tipos de documento, enlaces de carga de todos, custodia, auditoría |
| **Administración** | Administración de la plataforma en la dependencia | Cuenta | Control total: retirar y devolver documentos, enviar avisos |
| **Lectura** | Personal que consulta | Cuenta | Consulta y descarga lo autorizado |
| Contratista o entidad externa | Quien envía documentos al Comité | Un enlace de carga temporal, sin cuenta | Envía documentos o fotos declarando quién es |
| Talento Humano | Área del Comité | — (por ahora no usa Documenta) | Define jornadas, descansos y reglas laborales; responde por escrito (F-05) |
| Nómina | Área del Comité | — (recibe el Excel) | Liquida con el reporte de turnos; calcula los valores (D-11) |
| Superadministrador | Cuenta de plataforma | Cuenta | Administración en todas las dependencias; solo otro superadmin la gestiona |

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

## Componentes y nodos

Un **componente** es un programa de la dependencia (Calle, Básica, Estabilización); se
divide en **nodos** (equipos o zonas: Nodo 1 a Nodo 6 en Calle). Las reglas de turno,
cargos, tipos de turno y puntos críticos van por componente. *(F-03, F-06)*
