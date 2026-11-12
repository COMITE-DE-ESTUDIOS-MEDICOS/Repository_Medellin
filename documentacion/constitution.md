# Constitución de Documenta

> **Estado:** borrador para aprobar por Edwar. Adaptada de la plantilla SDD a lo que
> Documenta ya es: donde la plantilla y el sistema construido difieren, manda lo
> construido y queda explicado aquí.

Este documento fija los principios que cumple todo Documenta. Prevalece sobre cualquier
spec, plan o tarea. Personas y agentes de IA lo leen antes de especificar o programar.

**Cómo leerlo:** **DEBE** y **NO DEBE** son obligatorias. **DEBERÍA** es la regla por
defecto; apartarse exige dejar la razón escrita en el plan.

---

## I. El proceso antes que el código

- Una funcionalidad **DEBE** responder a un problema real del Comité, contado por quien lo
  vive (líderes, Coordinación, Talento Humano, Nómina). Lo que se pide es una pista; el
  problema se confirma con ellos.
- Las cifras del negocio (porcentajes, horarios, tramos de descanso) **DEBEN** venir de
  Talento Humano o Nómina, por escrito, y quedar en `fuentes/`. Lo que no esté confirmado
  va como pregunta abierta, no como regla.

## II. Primero la especificación

- **NO DEBE** escribirse código de una funcionalidad nueva sin `spec.md` aprobado por Edwar,
  con criterios de aceptación.
- Si cambia el comportamiento, **DEBE** cambiarse primero la spec.
- Exentos: textos, estilos, mejoras visuales y errores evidentes que no cambian una regla.
- Lo construido antes de octubre de 2026 se documenta con specs en estado «Construida».

## III. Cada dependencia ve solo lo suyo

- Toda entidad del negocio **DEBE** pertenecer a una dependencia (o colgar de algo que
  pertenece) y filtrarse en la consulta (`DependenciaScope`), no solo en la vista.
- Agregar una dependencia (Salud Mental…) **NO DEBE** requerir código.
- Lo que cambia entre componentes de una dependencia (Calle, Básica, Estabilización)
  **DEBE** ser dato configurable, no condiciones en el código.

## IV. Nada se pierde

- Los datos del negocio **NO DEBEN** borrarse: se inactivan con motivo, se anulan al
  corregir o cambian de estado. Las excepciones se nombran en `docs/context/domain.md`.
- Subir un archivo **NUNCA** sobrescribe: crea una versión nueva.
- Toda acción que cambia datos **DEBE** dejar auditoría: quién, qué, cuándo, IP y navegador.

## V. Seguridad y datos personales

- Los permisos **DEBEN** vivir en Policies (o en la autorización de cada acción de
  Filament), nunca solo en la interfaz.
- Ningún archivo **DEBE** servirse por URL directa: toda descarga pasa por permisos y
  auditoría (salvo los enlaces firmados con vencimiento del reporte de derivaciones).
- La vía pública (enlaces por cédula) **NO DEBE** devolver datos personales; tiene límite
  de intentos.
- Datos de salarios, EPS, cuentas bancarias **NO DEBEN** entrar al repositorio de código
  ni a Documenta mientras no exista una funcionalidad aprobada para ellos.
- Las fotos de personas son datos personales (Ley 1581 de 2012): se guardan mientras sirvan
  como prueba y con un plazo de retención definido por el Comité.

## VI. Hecho para quien no programa

- Textos en español claro, sin jerga técnica. Los mensajes de error dicen qué hacer.
- **DEBE** funcionar en celular (la vía pública se usa en la calle) y en modo claro y oscuro.
- Los cambios visibles para los usuarios **DEBEN** anunciarse en `CHANGELOG.md`, que se
  publica por la campana al desplegar.

## VII. Calidad

- Cada regla de negocio y cada permiso **DEBE** tener prueba automatizada. Todas las pruebas
  pasan antes de fusionar.
- Lo nuevo **DEBERÍA** nombrar sus pruebas con el criterio (`test_caNN_…`).
- Las consultas con agrupación, uniones o JSON **DEBEN** probarse también contra MySQL
  (las pruebas corren en SQLite).

## VIII. Despliegue

- `develop` despliega al servidor de pruebas y `main` a producción, por GitHub Actions.
- **NO DEBE** fusionarse a `main` sin haber visto el cambio funcionando en pruebas.
- Migraciones reversibles cuando sea posible; nunca se edita una migración ya desplegada.

---

## Stack aprobado

| Capa | Tecnología | Diferencia con la plantilla SDD |
|---|---|---|
| Lenguaje y marco | PHP 8.3, Laravel 12 | — |
| Interfaz | Blade, Tailwind 4 (Vite); Filament 5 por piezas con Livewire 4 | La plantilla supone un panel de Filament: aquí va dentro del layout de Documenta |
| Base de datos | MySQL (pruebas en SQLite) | La plantilla dice PostgreSQL |
| Pruebas | PHPUnit | La plantilla dice Pest |
| Identidad | Authentik (OIDC) + formulario propio | — |
| Ambiente | Laragon (local), Ubuntu + nginx + php-fpm (servidores) | La plantilla dice Docker |
| Parámetros | `config/repositorio.php` y reglas por componente (`Componente::REGLAS`) | La plantilla usa una tabla `parametros` |
| API | No hay API pública: pantallas y enlaces públicos | La plantilla exige `openapi.yaml`; se usará si algún día hay API |
| Seguimiento | `tasks.md` en el repositorio | La plantilla usa Notion |
