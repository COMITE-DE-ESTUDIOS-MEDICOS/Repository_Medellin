# Tareas: Cuentas de Authentik compartidas (DOC-22)

Prefijo `AUT`. Las pruebas, `test_doc22_caNN_…`, usan `Http::fake()` y nunca llaman a un
Authentik real.

| Tarea | Qué | CA | Estado |
|---|---|---|---|
| AUT-01 | Aprovisionamiento: buscar por documento, crear sin contraseña, agregar al grupo o sacarlo de él, sin tocar cuentas existentes; quitar la contraseña del formulario de usuarios; `AUTHENTIK_GRUPO_ID`; comandos `authentik:sincronizar-grupo` y `authentik:revisar` | CA-01 a CA-05, CA-08 | Hecha |
| AUT-02 | Ingreso: columna `authentik_sub`, primer ingreso por documento y después por `sub`, sin correo, sin `preferred_username` no entra; formulario propio solo para el superadministrador; Mi perfil sin cambio de contraseña para los demás | CA-06, CA-07 | Hecha |
| AUT-03 | Manual (enlace de recuperación, ingreso), CHANGELOG, `.env.example`, suite completa | — | Hecha (533 pruebas) |

## Plan

- `config/services.php`: `authentik.grupo_id` = `env('AUTHENTIK_GRUPO_ID')`. Sin URL, sin token o
  sin grupo, el aprovisionamiento no se intenta.
- API de Authentik:
  - buscar: `GET /api/v3/core/users/?username=<documento>` (exacto);
  - crear: `POST /api/v3/core/users/` con `{username, name, email?, is_active: true}`;
  - agregar al grupo: `POST /api/v3/core/groups/<uuid>/add_user/` con `{pk}`;
  - sacar del grupo: `POST …/remove_user/` con `{pk}`.
- `users.authentik_id` sigue guardando el `pk` de la cuenta; `users.authentik_sub` (nueva) guarda el
  `sub` del OIDC.
