# Spec: Cuentas de Authentik compartidas entre las apps

| | |
|---|---|
| Historia | DOC-22 |
| Tipo | Mejora técnica con efecto en la administración de usuarios · cambia el aprovisionamiento y el ingreso por Authentik |
| Estado | **Aprobada** |
| Versión | 1.3 |
| Fuentes | Propuesta de organización de Authentik de la sesión de MedTech y respuestas de Edwar transmitidas desde esa sesión (2026-11-11). Guía: `c:\laragon\www\MedTech\docs\Authentik_multiplataforma.docx` |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Edwar, 2026-11-11 |

## Problema

Hoy Documenta trata la cuenta de Authentik como suya. Al crear un usuario la crea allá con la
contraseña que escribió el administrador. Al editarlo, cambia allá el nombre, el username, el
correo y el estado. Al desactivarlo, desactiva la cuenta.

Ahora una persona tiene **una sola cuenta de Authentik para todas las apps** (MedTech,
Documenta…). Con eso:

- crear una persona que ya existe por otra app **falla**;
- desactivarla en Documenta la deja **sin ninguna app**;
- una contraseña puesta en Documenta **reemplaza la que usa en MedTech**;
- el ingreso acepta también el correo: es una segunda llave a la misma puerta.

## El modelo (igual en todas las apps)

- **Cuenta de persona**: una por persona. Su username es el documento (solo números y letras
  mayúsculas, sin puntos ni espacios). El correo es opcional y no identifica.
- **Grupo de acceso**: uno por app y por entorno: «Documenta Local», «Documenta Pruebas» y
  «Documenta». Estar en el grupo es poder entrar a Documenta en ese entorno. La aplicación de
  Authentik de cada entorno se liga solo a su grupo.
- **Cuenta de servicio** `repository-sync`, con un rol que solo tiene «Can view User» y «Can add
  User», y permisos sobre el grupo de Documenta: ver el grupo, agregar y sacar miembros. Un token
  por entorno.
- Documenta guarda el **UUID del grupo**, no su nombre.

## Comportamiento

### 0. El campo «Usuario» (aclaración de Edwar, 2026-11-11)

- En pantalla, el documento se llama **«Usuario»**. Por norma interna es la cédula de la
  persona: **solo números**, sin puntos ni espacios, de 3 a 20 (`^[0-9]{3,20}$`).
- **El superadministrador** es el único con letras, por ejemplo `adminDocumenta`
  (`^[A-Za-z0-9]{3,30}$`). Se crea a mano en Authentik, **sin** pertenecer a «authentik Admins»:
  administra Documenta, no Authentik.
- El usuario se compara **tal cual**, solo sin espacios a los lados y sin pasarlo a mayúsculas:
  «ADMINDOCUMENTA» no liga con «adminDocumenta». A la cédula se le quitan los puntos.
- Para corregir un usuario mal escrito, el administrador lo cambia en Authentik (Directorio →
  Usuarios → Editar) y en cada app. Quien ya entró alguna vez sigue entrando, porque lo reconoce el
  `sub`. Es obligatorio y único, garantizado por el índice único de
  `users.documento`, que ya existe.
- Es el username de Authentik y con él se liga a la persona la primera vez.
- Se guarda en `users.documento`, sin columna nueva. Los mensajes hablan de «usuario».
- El alias corto que ya existía (`users.usuario`) pasa a llamarse «Alias de emergencia
  (opcional)» y solo aparece para el superadministrador: es para el ingreso de emergencia.

### 1. Al dar acceso a una persona

1. Se busca en Authentik la cuenta con username = su documento, exacto.
2. Si **no existe**, se crea con el username (documento), el nombre y el correo solo si es real.
   **Sin contraseña.** Después se agrega al grupo de Documenta del entorno.
3. Si **ya existe** (por ejemplo, la creó MedTech), **solo se agrega al grupo**. No se cambia el
   nombre, el correo, la contraseña ni el estado.
4. Se guarda el identificador de la cuenta.

### 2. Al editar a una persona

- **Nada viaja a Authentik**: ni el nombre, ni el correo, ni el documento.
- Si se corrige el documento en Documenta, **el username de Authentik no cambia**. Si estaba mal
  escrito, se corrige a mano en Authentik.

### 3. Al desactivar, quitar el acceso o reactivar

- Desactivar a la persona en Documenta, o quitarle el acceso a la dependencia (si no le queda
  ninguna), **la saca del grupo de Documenta**. La cuenta de Authentik **nunca** se desactiva ni
  se borra: perdería las otras apps.
- Reactivarla la vuelve a agregar al grupo.
- Quien se va de la institución se desactiva a mano en Authentik.

### 4. La contraseña

- **La gestiona Authentik. Documenta no la toca nunca**, ni siquiera al crear la cuenta.
- El campo de contraseña del formulario de usuarios deja de viajar a Authentik (ver PA-01).
- Para que una persona nueva defina su contraseña y su segundo factor: en Authentik →
  Directorio → Usuarios → la persona → **«Crear enlace de recuperación»**. Es una URL de un solo
  uso que el administrador le entrega (impresa, en persona o por mensaje). Va en el manual.

### 5. Al entrar con Authentik

- **La primera vez**, la persona se reconoce por su documento (`preferred_username`), se guarda
  su `sub` y ya.
- **Después**, solo por el `sub`.
- **Se quita el respaldo por correo.**
- Un ingreso sin `preferred_username`, o con uno que queda vacío al normalizarlo, **no entra**.
  Nunca puede caer en un usuario con documento vacío o nulo.
- Sigue igual: si no tiene cuenta en Documenta o está desactivada, no entra.

### 6. Si Authentik no responde

Como hoy, la gestión de usuarios no se cae: la operación local se completa y el fallo queda en el
log y en la auditoría («no se pudo agregar al grupo… hay que reintentarlo»). Guardar la ficha de
nuevo es el reintento.

## Configuración

- `.env`: `AUTHENTIK_API_TOKEN` (el token de `repository-sync` del entorno) y una variable
  nueva, `AUTHENTIK_GRUPO_ID`, con el UUID del grupo de ese entorno. Sin grupo configurado, no se
  intenta nada contra Authentik, como hoy sin token.
- La parte de Authentik (grupos sin padre, cuenta de servicio, rol, tokens, bindings, apagar
  «permitir que los usuarios cambien su nombre de usuario») la hace Edwar a mano con la guía.

## Migración sin cortar a nadie

1. Edwar crea los grupos y mete en ellos a los usuarios actuales de Documenta. Puede haber un
   comando `authentik:sincronizar-grupo` que agregue a todos los activos (con `--simular`
   primero).
2. Se despliega este cambio y se ponen el token y el grupo en el `.env` de cada entorno.
3. Se cambia la binding de la aplicación de cada entorno a su grupo.
4. Al final, se vacía o se retira el grupo general.

## Criterios de aceptación

| CA | Criterio |
|---|---|
| CA-01 | Dar acceso a alguien que no existe en Authentik crea la cuenta sin contraseña y la agrega al grupo |
| CA-02 | Dar acceso a alguien que ya existe en Authentik solo lo agrega al grupo: no se envía ningún cambio a su cuenta |
| CA-03 | Editar nombre, correo o documento no envía nada a Authentik |
| CA-04 | Desactivar saca del grupo, sin desactivar la cuenta; reactivar la vuelve a agregar |
| CA-05 | Ninguna contraseña viaja a Authentik |
| CA-06 | El primer ingreso liga por documento y guarda el `sub`; los siguientes, solo por `sub`; el correo ya no sirve para entrar |
| CA-07 | Un ingreso sin `preferred_username` (o vacío) no entra, aunque haya usuarios con documento vacío |
| CA-08 | Si Authentik falla, la operación local se completa y el fallo queda en la auditoría |

## Decisiones (antes preguntas abiertas)

- **PA-01 → (b), Edwar 2026-11-11: «la contraseña puede ser solo en Authentik».** Las personas entran solo por Authentik. El formulario propio queda para el superadministrador, como entrada de emergencia. Antes: Documenta tiene además su **formulario de ingreso propio**, con la
  contraseña que pone el administrador y que se guarda solo en Documenta. Si la de Authentik la
  define la persona, habrá dos contraseñas distintas. Opciones:
  - (a) **quitar el ingreso por formulario** y que todos entren por Authentik;
  - (b) dejarlo **solo para el superadministrador**, como entrada de emergencia si Authentik se
    cae (recomendado);
  - (c) dejarlo como está, con su contraseña aparte.
- **PA-02.** Se hace con el comando `authentik:revisar`, que solo lista. ¿Hay usuarios en Documenta sin documento, o con un documento que no coincide con su
  username de Authentik (cuentas creadas a mano con correo)? Antes de quitar el respaldo por
  correo hay que listarlos. Propuesta: un comando que lo diga, sin cambiar nada.

## Candidatos para la siguiente versión

- **Botón «Sincronizar con Authentik»** en Administración → Usuarios, solo para el
  superadministrador. Hace lo mismo que `authentik:sincronizar-grupo`: primero muestra la
  simulación, después pide confirmar y lo deja en la auditoría. Hoy no hace falta, porque se usa
  una vez por entorno y lo demás ya es automático (dar acceso, desactivar, guardar la ficha como
  reintento). Edwar, 2026-11-11.
- Retirar lo viejo cuando los tres entornos usen `repository-sync`: sacar a `adminDocumenta` del
  grupo `documenta-api`, borrar `documenta-api-token` y retirar el grupo `documenta-api` y el rol
  `documenta-api-role`.

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.1 | 2026-11-11 | Borrador con la propuesta de MedTech y las respuestas de Edwar transmitidas desde esa sesión |
| 1.3 | 2026-11-11 | Candidatos: el botón de sincronizar y retirar lo viejo. Guía en `docs/AUTHENTIK.md` |
| 1.2 | 2026-11-11 | Norma de usuarios: personas solo números; el superadministrador con letras, tal cual |
| 1.1 | 2026-11-11 | El documento se llama «Usuario» en pantalla; el alias pasa a ser «Alias de emergencia» |
| 1.0 | 2026-11-11 | Aprobada por Edwar. PA-01: opción (b). Despliegue: local y pruebas primero, producción en la noche |
