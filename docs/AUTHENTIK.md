# Authentik en Documenta: puesta en marcha por entorno

Cómo se configura Authentik para Documenta con cuentas compartidas entre las apps (DOC-22).
La regla de negocio está en `documentacion/specs/authentik-cuentas-compartidas/spec.md`. La guía
general del Comité está en `c:\laragon\www\MedTech\docs\Authentik_multiplataforma.docx`.

**Authentik decide quién es la persona** (usuario = cédula, contraseña, segundo factor) y si
puede llegar a Documenta, porque está en su grupo. **Documenta decide qué puede hacer adentro**
(dependencia y rol). Los grupos de Authentik no son roles.

## Lo que ya existe (una sola vez para Documenta)

| Qué | Nombre | Cómo quedó |
|---|---|---|
| Cuenta de servicio | `repository-sync` | Tipo «service account». Su contraseña de aplicación no se usa |
| Rol | `Documenta aprovisionamiento` | Solo **Can view User** y **Can add User** (authentik Core). Nada más |
| Grupo de la cuenta | `repository-sync` | Sin padre, sin superusuario, con el rol, y su único miembro es `repository-sync` |
| Superadministrador | `adminDocumenta` | Usuario normal, **no** en «authentik Admins». En Documenta, su «Usuario» es `adminDocumenta` |

El permiso llega así: **rol → grupo `repository-sync` → cuenta `repository-sync`**. Si falta un
eslabón, la API responde 403. Para revisarlo, `GET /api/v3/core/users/me/` con el token dice de
quién es y en qué grupos está.

## Por entorno (local ✔, pruebas, producción)

| Entorno | Grupo | Token |
|---|---|---|
| Local | `Documenta Local` (`27f178a9-85d4-4068-ab8b-fc77bc4c546c`) | `repository-sync-local` |
| Pruebas | `Documenta Pruebas` | `repository-sync-pruebas` |
| Producción | `Documenta` | `repository-sync-prod` |

1. **Grupo:** Directorio → Grupos → Crear.
   - Sin padre (que no quede «authentik Admins»), sin superusuario y sin roles.
   - **Comprobar en el listado que Padre dice «-».**
   - Copiar el UUID de la URL: lo que va después de `/groups/` y antes del `;`.
2. **Permisos en ese grupo:** Permisos → Role Object Permissions → Assign to new role →
   `Documenta aprovisionamiento` → solo **Can view Group**, **Add user to group** y **Remove
   user from group**.
3. **Superadministrador:** agregar `adminDocumenta` al grupo del entorno.
4. **Token:** Directorio → Tokens → Crear.
   - Usuario `repository-sync`, intención **API**, **sin vencimiento**.
   - Va directo al `.env` del servidor, **nunca** al chat ni al correo.
5. **`.env` del servidor:**
   ```
   AUTHENTIK_API_TOKEN=<token del paso 4>
   AUTHENTIK_GRUPO_ID=<UUID del paso 1>
   ```
   Después, `php artisan config:clear`.
6. **Revisar y meter a la gente actual:**
   ```
   php artisan authentik:revisar                     # solo lee: quién no tiene usuario o cuenta
   php artisan authentik:sincronizar-grupo --simular
   php artisan authentik:sincronizar-grupo
   ```
7. **Cerrar la puerta:** Aplicaciones → la de Documenta de ese entorno → Policy / Group / User
   Bindings → ligar solo el grupo del entorno. Se hace **después** del paso 6, para no dejar a
   nadie afuera.
8. **Comprobar:**
   - entra `adminDocumenta` por «Entrar con la cuenta institucional»;
   - una persona que no está en el grupo no pasa de Authentik.

El código de DOC-22 tiene que estar desplegado **antes** del paso 5. Con el código viejo, el token
nuevo no alcanza para lo que ese código intentaba hacer (cambiar cuentas y contraseñas).

## Operación diaria

- **Persona nueva:** se le da acceso en Documenta, con su cédula y su rol. Documenta crea la
  cuenta en Authentik sin contraseña, o la agrega al grupo si ya existía por otra app. Después,
  en Authentik → Directorio → Usuarios → la persona → **Crear enlace de recuperación** → se le
  entrega la URL, de un solo uso.
- **Desactivar:** en Documenta, al guardar la ficha. La saca del grupo; su cuenta sigue viva para las otras
  apps. Reactivarla la devuelve al grupo. **No** se pone «Activo: No» dentro de Authentik: eso la
  bloquea en todas las aplicaciones, y es solo para quien sale de la empresa.
- **Usuario mal escrito:** se corrige a mano en Authentik (Directorio → Usuarios → Editar) y en
  Documenta. Quien ya entró sigue entrando, porque lo reconoce el `sub`.
- **Si Authentik falla al dar acceso:** queda en la auditoría y la fila dice «Falta en
  Authentik». Se reintenta con **Sincronizar con Authentik** en la fila (o guardando la ficha).
- **El superadministrador** tiene además el acceso de emergencia (formulario plegado de la
  pantalla de ingreso), con una contraseña que solo vive en Documenta.

## Pendiente: una aplicación por entorno

Hoy hay una sola aplicación, «Documenta», **sin bindings**: cualquier cuenta de Authentik llega a
la pantalla de Documenta, y Documenta la rechaza si no tiene cuenta local activa. Antes de ligar
un grupo hay que revisar las Redirect URIs de su proveedor:

- si la aplicación sirve a varios entornos, se crea una por entorno («Documenta Local»,
  «Documenta Pruebas», «Documenta»), cada una con su proveedor, y se cambian `AUTHENTIK_CLIENT_ID`
  y `AUTHENTIK_CLIENT_SECRET` en el `.env` de cada entorno;
- después, cada una se liga solo a su grupo (paso 7).

## Al final, cuando los tres entornos usen `repository-sync`

Retirar lo viejo:

1. sacar a `adminDocumenta` del grupo `documenta-api`;
2. borrar el token `documenta-api-token`;
3. retirar el grupo `documenta-api` y el rol `documenta-api-role`;
4. vaciar o retirar el grupo general que se usaba antes.
