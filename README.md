<p align="center">
  <img src="public/img/logo-cem-claro.png" alt="Comité de Estudios Médicos" height="64">
</p>

<h1 align="center">Documenta</h1>

<p align="center">
  Repositorio documental institucional del <strong>Comité de Estudios Médicos</strong><br>
  Inclusión Social · Medellín
</p>

---

## El problema

La información institucional vivía repartida entre carpetas compartidas, correos y
memorias USB. De ahí salen tres problemas que cuestan tiempo y, a veces, algo peor:

- **Se pierden versiones.** Alguien guarda encima del archivo de otro y lo anterior
  desaparece. Nadie sabe cuál era la buena, ni quién la cambió.
- **No hay rastro.** No se puede responder quién descargó un documento, quién lo
  retiró de la vista ni cuándo entró.
- **Recibir de fuera es un desorden.** Los contratistas y las entidades mandan
  documentos por correo, sin formato, sin constancia y a la bandeja de quien sea.

## Qué hace Documenta

Un sitio único donde cada documento tiene historia, dueño y trazabilidad, y donde lo
que llega de fuera entra por una puerta controlada.

| | |
|---|---|
| 📁 **Explorador con carpetas** | Árbol de carpetas por dependencia, con búsqueda, etiquetas y tipos documentales |
| 🕘 **Versiones, nunca sobrescritura** | Cada carga es una versión nueva, con su autor y qué cambió. El historial completo queda descargable |
| 🔒 **Archivos fuera de la web** | Ninguna descarga sale por URL directa: cada una pasa por un control de permisos y queda registrada |
| 👥 **Roles por dependencia** | Lectura, Edición, Coordinación y Administración. La misma persona puede ser lectora en una dependencia y administradora en otra |
| 🧾 **Auditoría completa** | Quién, qué, cuándo, desde qué IP y con qué navegador. Inactivar exige motivo |
| 📮 **Enlaces de carga** | Un enlace temporal para que alguien de fuera envíe documentos sin tener cuenta. Con vencimiento, tope de usos y revocable |
| 📸 **Fotos con hora y lugar** | Lo que se fotografía desde el enlace llega con fecha, coordenadas y municipio estampados |
| 🗂️ **Sin borrar nada** | Retirar un documento lo oculta, no lo destruye. Administración puede devolverlo |
| 🔑 **Entrada con Authentik** | Inicio de sesión institucional (OpenID Connect), además del formulario propio |
| 🌗 **Claro y oscuro** | La preferencia vive en la cuenta, no en el navegador |

## Cómo entra un documento

```
Desde dentro                          Desde fuera
─────────────                         ────────────
Usuario con cuenta                    Coordinación genera un enlace temporal
  ↓                                     ↓
Sube a una carpeta                    Lo envía a la entidad o contratista
  ↓                                     ↓
Queda como versión 1                  Quien recibe declara quién es y su nodo,
  ↓                                   y adjunta PDF o fotografía
Cada carga posterior                    ↓
es una versión más                    Entra ya como documento, en la carpeta
                                      del enlace, con su cadena de custodia
```

De todo lo que llega de fuera se guarda quién lo declaró, su correo, entidad y nodo,
la IP, el navegador, la huella SHA-256 del archivo, si llegó en horario hábil y
cuánto tardó en responder desde que se le entregó el enlace.

## Multi-dependencia desde el primer día

Nació para **Inclusión Social**, pero está construido para que Salud Mental —o
cualquier otra dependencia del Comité— entre después sin tocar código y sin ver nada
de las demás. El aislamiento no depende de que un programador se acuerde de filtrar:
va en la propia consulta a la base de datos.

## Lo que deliberadamente no hace

Un sistema pequeño que funciona vale más que uno grande a medias. Quedan fuera:
edición de documentos en línea, chat interno, firmas digitales y flujos de
aprobación. Documenta guarda, versiona, controla quién ve qué y deja constancia.

## Estado

Funcionando, con **242 pruebas automatizadas** sobre permisos, aislamiento entre
dependencias, versionado, la vía pública y los horarios.

**Stack:** Laravel 12 · Blade · Tailwind 4 (Vite) · MySQL · almacenamiento local
abstraído (migrable a S3 o MinIO cambiando una línea de configuración).

## Documentación

| Documento | Para qué |
|---|---|
| [`INICIO.md`](docs/INICIO.md) | Puesta en marcha, decisiones de arquitectura y mapa del código |
| [`MANUAL-USUARIO.md`](docs/MANUAL-USUARIO.md) | Cómo se usa, para quien no programa |
| [`GEOLOCALIZACION.md`](docs/GEOLOCALIZACION.md) | Las fotos con hora y lugar: cómo funcionan y hasta dónde sirven como prueba |
| [`RESOLUCION-FOTOS.md`](docs/RESOLUCION-FOTOS.md) | Tamaño y calidad de las fotos de evidencia, en lenguaje no técnico |
| [`INTERFAZ.md`](docs/INTERFAZ.md) | Convenciones de la capa visual: responsivo, avatar por rol, cabecera |
| [`turnos/`](docs/turnos/README.md) | Módulo de turnos: uso para Coordinación, despliegue (cron, versión, avisos) y arquitectura |
| [`PROXIMOS-CAMBIOS.md`](docs/PROXIMOS-CAMBIOS.md) | Cola de trabajo acordada: copiar por check, Edición inactiva, tope semanal de 42 h, carga por tandas |

Los prompts de desarrollo están en [`docs/prompts/`](docs/prompts/) y las notas de reunión en [`docs/reuniones/`](docs/reuniones/).


---

## Contacto

**Jorge Eduardo Muñoz Quintero**  
*Desarrollador*

* 📧 Eduard.munoz@comitedeestudiosmedicos.com
* 📱 3003766780  

**José Amaya**  
*Desarrollador*

* 📧 Sistemas@comitedeestudiosmedicos.com
* 📱 3024296365

<p align="center">
  <sub>Comité de Estudios Médicos · Inclusión Social · Medellín</sub>
</p>
