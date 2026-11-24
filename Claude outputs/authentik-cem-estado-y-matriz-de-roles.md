# Authentik CEM — Estado y matriz de roles

22 de octubre de 2026 · José Amaya

## Resumen ejecutivo

Authentik es el sistema de autenticación única (SSO) del Comité de Estudios
Médicos. Documenta es la primera aplicación conectada; las demás aplicaciones
Laravel del CEM se conectarán después sobre la misma instancia, cada una con su
propia aplicación y credenciales.

Este documento somete a aprobación dos cosas: la matriz de roles y perfiles
—quién existe en cada sistema y qué puede hacer cada quien— y siete decisiones
de seguridad que hoy están tomadas de hecho, por necesidad operativa, pero sin
aprobación formal.

El principio del diseño es que **Documenta decide quién tiene acceso y con qué
rol; Authentik solo verifica identidad**. Authentik nunca crea accesos por su
cuenta. Las personas de campo no son usuarios de ninguno de los dos sistemas:
se identifican por cédula en los enlaces públicos y no inician sesión.

Estado de implementación: la integración está construida y probada en ambiente
local. El despliegue en producción está preparado (variables de entorno
cargadas en el servidor y verificada la conexión con Authentik), pendiente de
confirmación final.

## Estado actual

### Infraestructura

Authentik corre en el VPS del Comité sobre Docker Compose, detrás de Apache
como proxy inverso con certificado SSL de Let's Encrypt. Los contenedores
escuchan solo en `127.0.0.1:9000`, así que no quedan expuestos directamente a
internet.

| Elemento | Valor |
| --- | --- |
| Dominio | `auth.comitedeestudiosmedicos.com.co` |
| Versión instalada | 2024.8.3 |
| Despliegue | Docker Compose (servidor, worker, PostgreSQL, Redis) |
| Exposición | Apache como proxy inverso, SSL Let's Encrypt |
| Base de datos | PostgreSQL propia del contenedor, independiente de Documenta |

### Integración con Documenta

Documenta se conecta por OpenID Connect contra una aplicación propia dentro de
Authentik. En Laravel se usa `laravel/socialite` con el proveedor
`socialiteproviders/authentik`; no hay desarrollo propio del protocolo.

| Elemento | Valor |
| --- | --- |
| Aplicación en Authentik | CEM PROJECTS |
| Proveedor | CEM DOCUMENTA (OAuth2/OpenID) |
| Tipo de cliente | Confidencial |
| Dirección de retorno (producción) | `https://documenta.botenterprise.com.co/auth/authentik/callback` |
| Dirección de retorno (desarrollo) | `http://localhost:8000/auth/authentik/callback` |
| Credenciales | En el archivo `.env` de cada ambiente, fuera del repositorio |

### Qué cambió para el usuario

La pantalla de ingreso de Documenta ya no pide usuario y contraseña: el botón
«Ingresar» lleva a Authentik, y desde allí el usuario vuelve a Documenta ya
autenticado.

Cuando se crea un usuario en Documenta, el sistema lo crea también en Authentik
por su API y le fija la misma contraseña, en la misma operación. No hay que
crear a nadie dos veces, ni la persona recibe correos de activación.

La contraseña viaja solo en ese instante. Documenta la guarda cifrada en un
sentido único (no reversible), así que después de crear el usuario ya no existe
en ninguna parte en texto legible.

## Cómo se asignan los permisos en Authentik

Authentik no permite darle permisos directamente a una persona. La cadena es:
un **permiso** (por ejemplo, «crear usuario») se agrupa en un **rol**, el rol se
asigna a un **grupo**, y la persona recibe los permisos por pertenecer a ese
grupo.

Esto importa para la matriz porque explica por qué existe un grupo con un solo
miembro: no es redundancia, es el único camino que ofrece la herramienta.
También significa que quitarle permisos a alguien se hace sacándolo del grupo,
sin tocar sus datos ni su cuenta.

Aparte de eso existe la marca de **superusuario**, que se activa a nivel de
grupo y otorga control total sobre Authentik. Ningún grupo creado para Documenta
la tiene activada.

## Matriz de identidades en Authentik

| Cuenta | Tipo | Para qué sirve | Alcance | Quién la administra |
| --- | --- | --- | --- | --- |
| `akadmin` | Persona | Administración de Authentik: aplicaciones, proveedores, grupos, roles | Superusuario: control total | Infraestructura (Edward Muñoz) |
| `adminDocumenta` | Persona | Administración de Documenta; hoy además porta el token de la API | Limitado al grupo `documenta-api` | Infraestructura / José Amaya |
| `ak-outpost-…` | Cuenta de servicio | Interna de Authentik, creada por la propia herramienta | Interno | Authentik (no se toca) |
| Usuarios de Documenta | Personas | Iniciar sesión en Documenta | Sin permisos en Authentik | Documenta, por la API |
| Colaboradores de campo | **No existen aquí** | Se identifican por cédula en los enlaces públicos | Ninguno | Coordinación, dentro de Documenta |

### Grupo y rol creados para la integración

| Objeto | Nombre | Contenido |
| --- | --- | --- |
| Grupo | `documenta-api` | Superusuario desactivado. Un miembro: `adminDocumenta` |
| Rol | `documenta-api-role` | Los permisos de la tabla siguiente |

| Permiso | Para qué lo necesita Documenta | Riesgo que implica |
| --- | --- | --- |
| Can add User | Crear en Authentik al usuario que se crea en Documenta | Bajo |
| Can view User | Verificar si la persona ya existe antes de crearla | Bajo |
| Reset Password | Fijar la contraseña inicial que la persona definió en Documenta | **Alto**: alcanza a cualquier cuenta, `akadmin` incluida |
| Can change User | Inactivar en Authentik a quien se inactive en Documenta | Medio. Recomendado agregarlo; confirmar si ya está |

El token de API se llama `documenta-api-token`, está emitido sobre
`adminDocumenta` y hoy no tiene fecha de vencimiento. Vive únicamente en el
archivo `.env` del servidor, nunca en el repositorio.

## Matriz de perfiles en Documenta

Los perfiles viven en Documenta, no en Authentik. Se asignan **por
dependencia**: la misma persona puede ser Coordinación en Inclusión Social y
Solo lectura en otra dependencia, y nunca ve los documentos de una dependencia
en la que no tiene rol.

Son cuatro niveles acumulativos: cada uno hace todo lo del anterior y algo más.

| Perfil | Nivel | Documentos y carpetas | Personas y estructura | Retirar contenido | Turnos |
| --- | --- | --- | --- | --- | --- |
| Solo lectura | 1 | Consulta y descarga lo autorizado | No | No | No |
| Edición | 2 | Sube, crea, copia y mueve. Inactiva las carpetas que creó, si no tienen contenido ajeno. Genera sus propios enlaces de carga | No | No | Enlaces, personas de campo, novedades y reporte |
| Coordinación | 3 | Lo anterior. Inactiva y reactiva cualquier carpeta | Usuarios, tipos de documento, todos los enlaces de carga, auditoría, componentes de turnos | Carpetas sí; documentos no | Sí, incluida la configuración |
| Administración | 4 | Control total | Sí | Sí, carpetas y documentos | Sí |

### Dos fronteras deliberadas

**Entre Edición y Coordinación** está administrar personas y estructura. Quien
solo edita puede trabajar con los documentos, pero no decide quién entra ni con
qué rol.

**Entre Coordinación y Administración** está retirar documentos de la vista.
Esconder información de toda la dependencia es la única acción que no se delega.

Además, **nadie puede otorgar un rol superior al suyo**. Sin esa regla,
Coordinación podría crear un usuario con rol Administración —o ascenderse a sí
misma— y saltarse en dos clics justo lo que no debe poder hacer.

### Correspondencia entre los dos sistemas

| Perfil en Documenta | ¿Existe en Authentik? | Permisos en Authentik |
| --- | --- | --- |
| Solo lectura | Sí, como usuario | Ninguno |
| Edición | Sí, como usuario | Ninguno |
| Coordinación | Sí, como usuario | Ninguno |
| Administración | Sí, como usuario | Ninguno, salvo `adminDocumenta` por el token |
| Colaborador de campo | No | No aplica |

La columna de la derecha es el punto importante: ningún perfil de Documenta
otorga permisos dentro de Authentik. Los usuarios existen allá únicamente para
poder autenticarse. Lo que cada quien puede hacer se decide siempre del lado de
Documenta.

## Ciclo de vida de un acceso

| Etapa | Quién la ejecuta | Qué pasa en Documenta | Qué pasa en Authentik |
| --- | --- | --- | --- |
| Alta | Coordinación o Administración de la dependencia | Se crea el usuario con su rol y su dependencia | Se crea la cuenta y se le fija la contraseña, automáticamente |
| Cambio de rol | Coordinación o Administración, sin exceder el propio | Cambia el rol en la dependencia | Nada: Authentik no conoce los roles |
| Cambio de contraseña | La persona | Nada | Se cambia allá, que es donde vive |
| Inactivación | Coordinación o Administración | El usuario queda inactivo, se conserva su historial | Debe inactivarse la cuenta, para no dejar un acceso vivo |
| Eliminación | Nadie | No existe: el sistema inactiva, nunca borra, para preservar la auditoría | No aplica |

La inactivación es la etapa crítica. Si alguien pierde el acceso en Documenta
pero su cuenta sigue activa en Authentik, queda con credenciales válidas en el
sistema de autenticación del Comité, utilizables en las demás aplicaciones que
se conecten después.

Las personas de campo siguen otro camino: no se les da de alta como usuarios.
Se autorregistran por cédula desde el enlace público y Coordinación las verifica
después dentro de Documenta.

## Riesgos identificados

| # | Riesgo | Por qué importa | Recomendación |
| --- | --- | --- | --- |
| 1 | El token de la API está emitido sobre `adminDocumenta`, una cuenta de persona | En los registros no se distingue qué hizo la persona y qué hizo el sistema. Si se le activa verificación en dos pasos o cambia de responsable, la integración se ve afectada | Mover el token a una cuenta de servicio dedicada |
| 2 | El permiso Reset Password es global | Ese token puede cambiar la contraseña de **cualquier** cuenta de Authentik, `akadmin` incluida. Authentik no ofrece hoy una versión más acotada de este permiso | Activar verificación en dos pasos en `akadmin`, para que una contraseña cambiada no baste para entrar |
| 3 | El token no tiene vencimiento | Si se filtra, sirve indefinidamente | Ponerle vencimiento y definir una rotación periódica |
| 4 | Credenciales expuestas durante la configuración | El identificador de cliente, el secreto y el token quedaron visibles en capturas de pantalla compartidas durante el montaje | Regenerar las tres credenciales |
| 5 | Authentik está en la versión 2024.8.3 | Dos años sin actualizar. La propia herramienta lo señala en su panel. Acumula correcciones de seguridad no aplicadas | Planear la actualización en ventana de bajo uso; el servidor aloja otras aplicaciones |
| 6 | La contraseña inicial no obliga a cambiarse | Quien la define al crear el usuario la conoce. No hay cambio forzado en el primer ingreso | Activar el cambio obligatorio en el primer ingreso, que Authentik ya soporta |
| 7 | Usuarios previos de producción | Como el ingreso ya no pide usuario y contraseña, quien existía antes en Documenta y no está en Authentik no puede entrar | Migrarlos antes de activar el nuevo ingreso en producción |

Ninguno de estos siete es un error de implementación: son decisiones que se
tomaron por necesidad operativa y que conviene ratificar o corregir de manera
formal.

## Decisiones que requieren aprobación

| # | Decisión | Recomendación técnica | Esfuerzo | Estado |
| --- | --- | --- | --- | --- |
| 1 | ¿Se mantiene el token sobre `adminDocumenta` o se crea una cuenta de servicio dedicada? | Cuenta dedicada | Bajo | Pendiente |
| 2 | ¿Se activa verificación en dos pasos para `akadmin`? | Sí | Bajo | Pendiente |
| 3 | ¿Se le pone vencimiento y rotación al token de la API? | Sí, un año | Bajo | Pendiente |
| 4 | ¿Se regeneran las credenciales expuestas durante el montaje? | Sí | Bajo | Pendiente |
| 5 | ¿Se programa la actualización de Authentik? | Sí, con ventana acordada | Medio | Pendiente |
| 6 | ¿Se exige cambio de contraseña en el primer ingreso? | Sí | Bajo | Pendiente |
| 7 | ¿Cómo se migran los usuarios que ya existían en producción? | Carga masiva antes de activar el nuevo ingreso | Medio | Pendiente |

### Nombres y responsabilidades a confirmar

La matriz asume este reparto; conviene ratificarlo en la misma aprobación:

- **Administración de Authentik** (cuenta `akadmin`, aplicaciones, grupos,
  actualizaciones): Edward Muñoz.
- **Administración de Documenta** (usuarios, roles, dependencias): José Amaya.
- **Aprobación de nuevos perfiles de Administración**: por definir. Hoy
  cualquier Administración de una dependencia puede crear otra Administración
  en esa misma dependencia.

### Para aprobar

Marcar el estado de cada decisión en la tabla de arriba y devolver el documento
comentado. Las decisiones de esfuerzo bajo pueden aplicarse el mismo día; las de
esfuerzo medio requieren acordar fecha.
