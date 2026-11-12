# Manual de uso — Documenta

Guía funcional para las personas que usan la plataforma día a día. Para instalación
y decisiones de arquitectura, ver [INICIO.md](INICIO.md).

---

## 1. Qué es

Repositorio documental centralizado para la dependencia de **Inclusión Social**
(Comité de Estudios Médicos), pensado desde el inicio para replicarse a otras
dependencias (Salud Mental es la siguiente) sin que unas vean los archivos de
otras. Nace para reemplazar el envío de evidencias por enlaces de OneDrive, que
no dejaba rastro de quién subía qué ni permitía controlar el acceso.

## 2. Cómo entrar

No hay registro público. Un administrador crea la cuenta de cada persona con su
correo institucional. Se ingresa por `/ingresar` con correo y contraseña.

Si la cuenta tiene acceso a más de una dependencia, un selector en el encabezado
permite cambiar entre ellas — lo que se ve en el explorador, las carpetas y la
bandeja cambia según la dependencia activa.

## 3. Roles

El rol no es global: se asigna **por dependencia**. La misma persona puede ser
lectora en Inclusión Social y administradora en Salud Mental si el sistema
crece a fase 2.

| Rol | Puede |
|---|---|
| **Solo lectura** | Ver carpetas, buscar, previsualizar y descargar documentos activos. |
| **Edición** | Lo anterior + crear carpetas, subir documentos nuevos y nuevas versiones de los existentes. |
| **Administración** | Lo anterior + inactivar/reactivar documentos y carpetas, gestionar usuarios, tipos de documento y ver la auditoría. |

```
Login → ¿credenciales válidas? → identificar dependencia activa → cargar
interfaz según el rol asignado en esa dependencia
```

## 4. Explorador de documentos

- **Buscar**: por nombre o etiqueta, dentro de la dependencia activa.
- **Previsualizar**: el archivo se abre en el navegador sin descargarlo; el
  archivo físico nunca se sirve por URL directa, siempre pasa por un
  controlador que valida permisos y audita el acceso.
- **Descargar**: igual, queda registrado quién descargó qué y cuándo.
- **Subir un documento nuevo** (rol Edición o superior): se piden nombre,
  carpeta, tipo de documento y etiquetas.
- **Subir una nueva versión**: subir un archivo sobre un documento existente
  **nunca reemplaza** el anterior. Cada carga queda como una versión con su
  propio autor y comentario de qué cambió; el historial completo es
  consultable y descargable.

## 5. Carpetas

Se organizan en árbol (carpetas dentro de carpetas). Rol Edición en adelante
puede crear carpetas y renombrarlas; Administración puede inactivarlas y
reactivarlas.

## 6. Inactivar en vez de borrar

Nada se elimina de la base de datos. Administración puede **inactivar** un
documento o carpeta: desaparece de la vista de lectores y editores, pero el
registro permanece íntegro y puede **reactivarse**. Toda inactivación pide un
motivo y queda en la auditoría con autor, fecha e IP.

## 7. Recepción externa — enlaces de carga y bandeja

Reemplaza el flujo de OneDrive. Un funcionario externo (contratista, otra
entidad) no tiene ni necesita una cuenta:

1. Alguien con permiso genera un **enlace de carga** para esa persona, desde
   `/admin/enlaces`: queda identificada por nombre, correo y entidad, y el
   enlace apunta a la bandeja del funcionario que debe revisar lo que suba.
   Puede tener fecha de vencimiento y un número máximo de usos, y se puede
   revocar en cualquier momento. La URL completa solo se muestra **una vez**,
   justo al crearlo — cópiala ahí.
2. El remitente abre el enlace, ve un formulario mínimo (sin nada más del
   sistema), sube su archivo y listo.
3. Lo subido cae en la **bandeja** (`/bandeja`) del funcionario asignado como
   *recepción pendiente* — todavía **no es un documento del repositorio**.
4. El funcionario revisa la bandeja. **Archivar, descartar y reasignar** una
   recepción —convertirla en documento del repositorio— están especificadas
   en `prompts/PROMPT-BANDEJA.md` pero aún no tienen ruta ni vista.

### Quién puede crear enlaces

- **Administración** crea enlaces para cualquier carpeta de su dependencia, o
  sin carpeta fija (queda a criterio de quien archive después).
- **Un líder de carpeta** —alguien asignado en la tabla `carpeta_lider`, sin
  necesidad de ser Administración— también puede, pero **solo** para las
  carpetas que lidera, y solo revoca los enlaces que él mismo creó. Liderar
  una carpeta no da visibilidad sobre el resto de la dependencia: es una
  capacidad puntual, no un rol nuevo. Hoy asignar a alguien como líder de una
  carpeta se hace por consola (`php artisan tinker` o un seeder); no tiene
  pantalla propia todavía.
- La carpeta elegida en el enlace es solo una **sugerencia** para quien
  clasifique lo recibido — el remitente externo nunca la ve ni la elige.

**Seguridad ya implementada:** el token se genera con `random_bytes`
(`Str::random(48)`, 286 bits de entropía) y se guarda solo su hash SHA-256 más
una copia cifrada — nunca en texto plano. La ruta pública está limitada por
IP y por token con ventanas de tiempo (no un contador acumulado sin límite de
tiempo): 30 solicitudes de formulario por minuto por IP, y al cargar, 10 por
minuto por IP y 6 por minuto por token. Un enlace inválido, vencido o
revocado siempre responde lo mismo («enlace no disponible») para que no se
puedan enumerar tokens válidos por prueba y error.

**Estado actual:** la ruta pública de envío y la bandeja (listar, ver,
previsualizar) ya funcionan. **Archivar, descartar y reasignar** una
recepción, y la pantalla de administración para **crear y revocar enlaces**
desde la interfaz, están especificadas en `prompts/PROMPT-BANDEJA.md` pero aún no
tienen ruta ni vista — hoy un enlace solo puede crearse por consola
(`php artisan tinker` o un seeder).

## 8. Administración

Bajo `/admin`, visible solo para rol Administración:

- **Usuarios**: crear, editar y revocar el acceso de una persona a la
  dependencia activa.
- **Tipos de documento**: catálogo de tipos, generales o propios de la
  dependencia.
- **Auditoría** (`/auditoria`): cada acción sensible (inactivar, reactivar,
  crear usuario, revocar acceso, subir versión) queda registrada con autor,
  fecha e IP.

## 9. Confirmaciones de acciones

Crear, actualizar, inactivar o reactivar algo no muestra un cartel fijo
arriba del contenido: aparece como un **aviso flotante** (toast) en la
esquina superior derecha, con el color del tema activo (verde para éxito,
rojo para error). Se cierra solo a los pocos segundos, o se puede cerrar a
mano. Los errores de validación de un formulario son la excepción: siguen
mostrándose junto al formulario, porque hay que verlos mientras se corrige,
no como un aviso que desaparece solo.

## 10. Fase 2 — nueva dependencia

El seeder deja creada Salud Mental, inactiva. Para habilitarla: marcar
`activa = true` en la tabla `dependencias`, crear su primer administrador y
asignarle el rol ahí. Ese administrador da acceso a su propio equipo desde
`/admin/usuarios`. No se toca código para esto.

## 11. Fuera de alcance (a propósito)

Para mantener el sistema ligero, deliberadamente no incluye: edición en línea
de documentos, chats internos, firmas digitales ni flujos de aprobación con
varios pasos.

---

## Turnos de las personas de campo

Marcar entrada y salida con QR, ver quién está en turno, validar novedades y
descargar el reporte: está explicado paso a paso en
[`turnos/README.md`](turnos/README.md).
