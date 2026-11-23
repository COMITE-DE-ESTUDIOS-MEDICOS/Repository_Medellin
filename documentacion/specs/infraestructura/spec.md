# Spec: Infraestructura · nginx, Redis, monitoreo y la decisión de Docker y PostgreSQL

| | |
|---|---|
| Historia | DOC-32 |
| Tipo | Mejora técnica · no cambia reglas de negocio `RN-NN`; **propone una enmienda a la constitución** (tabla «Stack aprobado» y principio V) y corrige el registro de cómo está producción |
| Estado | **Borrador** |
| Versión | 0.2 |
| Fuentes | Pedido y calendario aprobado por Edwar, 2026-11-23 (**aún no está en `fuentes/`**, ver PA-12). Lectura del código: `bin/deploy.sh`, `.github/workflows/deploy.yml`, `.env.example`, `routes/console.php`, `config/repositorio.php`, `config/logging.php`, `app/Services/CargaPorTandas.php`, `docs/INICIO.md` |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Pendiente (Edwar) |

## Problema

Documenta corre en dos servidores que no son iguales y eso no está escrito en ninguna parte
confiable:

- **Pruebas** usa nginx con php-fpm. **Producción usa Apache** con PHP dentro de Apache (lo dijo
  Edwar y lo confirma el comentario de `bin/deploy.sh`). Sin embargo, `CLAUDE.md` y la
  constitución dicen que los dos servidores usan nginx. Lo que se prueba en `develop` no corre
  sobre lo mismo que producción.
- La caché, las sesiones (quién tiene la sesión abierta) y la cola de trabajos viven en tablas de
  la misma base de datos MySQL que guarda los documentos, los turnos y los casos. Cada página
  abierta le pide a la base de datos también su sesión.
- No hay monitoreo: nadie se entera de que el servidor se está quedando sin disco, de que el
  cierre automático de turnos dejó de correr o de que el sitio responde lento, hasta que alguien
  llama a quejarse.

Edwar quiere nginx en producción, Redis, Docker, PostgreSQL y monitoreo. Se acordó hacerlo en tres
fases (calendario aprobado el 2026-11-23) y **no** partir Documenta en servicios: sigue siendo
**una sola aplicación Laravel**; lo que se separa es la infraestructura que la rodea (aplicación,
base de datos, Redis, monitoreo).

## Palabras que se usan en esta spec

Se explican una vez; después se usan sin más.

| Palabra | Qué es |
|---|---|
| **nginx** | El programa que recibe las visitas al sitio y se las pasa a PHP. Hace lo que hoy hace Apache en producción |
| **php-fpm** | PHP corriendo como un servicio aparte, al que nginx le pasa cada petición. Con Apache, en cambio, PHP va dentro de Apache |
| **Redis** | Una base de datos pequeña y muy rápida que guarda todo en memoria. Aquí se usa para la caché, las sesiones y la cola |
| **Caché** | Datos que se guardan un rato para no volver a calcularlos. En Documenta, por ejemplo, lo que ya entró de una carga por tandas y los contadores de intentos de la vía pública |
| **Sesión** | Lo que hace que el sistema recuerde que alguien ya inició sesión, y la protección de los formularios (también la de los enlaces públicos por cédula) |
| **Cola** | Una lista de trabajos que se hacen después, por fuera de la página que los pidió. Necesita un proceso que los atienda (el «trabajador») |
| **Docker** | Una forma de empaquetar un programa con todo lo que necesita para que corra igual en cualquier servidor. Cada programa empaquetado corriendo es un **contenedor** |
| **Prometheus** | Recoge cifras de los servidores cada pocos segundos (memoria, disco, peticiones, errores) |
| **Grafana** | Las pantallas con gráficas de esas cifras, y las **alertas** cuando algo pasa de un límite |
| **Loki** | Guarda los registros de texto (logs) de nginx y de Laravel para buscarlos desde Grafana |
| **Tempo / OpenTelemetry** | Trazas: el detalle de cuánto tardó cada parte de una petición. Solo si hace falta (fase 3) |
| **VPN** | Una red privada cifrada entre máquinas que van por internet |
| **Plan de reversa** | Los pasos para volver a como estaba si algo falla |

## Comportamiento

### 0. Cómo está hoy (leído del código y de lo dicho por Edwar)

| Tema | Pruebas | Producción |
|---|---|---|
| Servidor web | nginx + php-fpm | **Apache + PHP dentro de Apache** |
| Base de datos | MySQL | MySQL |
| Caché, sesiones y cola | En tablas de la base de datos (`CACHE_STORE`, `SESSION_DRIVER` y `QUEUE_CONNECTION` = `database` en `.env.example`; el valor real de cada servidor no se leyó, ver PA-04) | Igual |
| Registros (logs) | Un solo archivo `storage/logs/laravel.log` (`LOG_STACK=single` en `.env.example`), sin rotación | Igual (por confirmar, PA-04) |
| Despliegue | GitHub Actions entra por SSH, trae el código, sube los estilos compilados y corre `bin/deploy.sh`, que recarga php-fpm, apache2 o nginx, el que esté encendido | Igual |

**Qué usa la cola hoy: nada.** Ninguna parte de Documenta manda trabajos a la cola. Los avisos por
la campana se guardan en el momento; los ZIP de descarga y los Excel se arman dentro de la misma
petición; las tareas programadas las dispara el cron (`schedule:run` cada minuto), no la cola. No
hay trabajador de cola corriendo, y no hace falta. `bin/deploy.sh` ya corre `queue:restart`, que
hoy no reinicia nada.

**Lo programado que debe seguir funcionando** (en `routes/console.php`):

| Tarea | Cada cuánto | Qué hace |
|---|---|---|
| `turnos:cerrar-abiertas` | Cada hora | Cierra las entradas sin salida (RN-20) |
| `turnos:revisar-entradas` | Cada 15 minutos | Novedad y aviso «no registró entrada» (RN-25) |
| `derivaciones:recordar` | Cada minuto | Aviso único de caso vencido (RN-33) |

Las tres llevan «sin solaparse» (si la anterior no ha terminado, no arranca otra), y ese candado
**se guarda en la caché**. Si la caché pasa a Redis y Redis se cae, estas tareas dejan de correr.

### 1. Fase 1 — Ahora, en paralelo a DOC-25 (1 o 2 días)

Tres pasos, en este orden, cada uno con su propia ventana de cambio:

**1a. Producción pasa de Apache a nginx + php-fpm.**

- Producción queda servida igual que pruebas. Las direcciones no cambian: el dominio, los enlaces
  públicos por cédula (`/t/…`, `/d/…`), los enlaces de carga y **las URL de los QR impresos**
  siguen funcionando igual.
- Lo que nginx debe cumplir para Documenta:
  - **Solo sirve `public/`.** Ningún archivo de `storage/` (documentos, fotos de turnos, soportes
    de casos) se puede pedir por su ruta: toda descarga pasa por el controlador con permiso y
    auditoría (RN-06, constitución V). Tampoco se sirven archivos ocultos (`.env`, `.git`) ni
    ningún `.php` distinto de `index.php`.
  - **Tamaño de lo que se sube.** El límite de nginx (`client_max_body_size`) debe ser igual al
    `post_max_size` de PHP. Los dos deben quedar **por encima de una tanda completa**: hasta
    50 archivos o 150 MB por tanda (`mb_por_tanda` en `config/repositorio.php`), con 25 MB por
    archivo. Si nginx queda por debajo, una carga grande falla con un error que la persona no
    entiende (ver PA-05 sobre la cifra; `docs/INICIO.md` habla de 50 × 25 MB).
  - **Tiempo de espera.** Las descargas en ZIP (documentos y reporte de derivaciones con sus
    soportes), los Excel de turnos, derivaciones y supervisión y la carga masiva de casos se arman
    mientras la persona espera. nginx y php-fpm no pueden cortarlas antes de que PHP termine
    (ver PA-06 sobre la cifra).
  - **HTTPS** como hoy, con el mismo certificado o uno renovado igual.
- **El `php.ini` de php-fpm es otro archivo** que el de Apache. Los valores que hoy tiene
  producción en el de Apache pasan al de php-fpm: `max_file_uploads = 50`, `upload_max_filesize`,
  `post_max_size`, `max_execution_time`, `memory_limit`, y las extensiones `intl`, `xmlreader`,
  `zip`, `gd`, `pdo_mysql` (lista de `CLAUDE.md`). Sin `max_file_uploads = 50`, PHP descarta
  archivos de las tandas en silencio (lección ya aprendida).
- php-fpm corre como `www-data`, igual que hoy Apache, para que `bin/deploy.sh` (que devuelve los
  archivos a `www-data`) siga sirviendo.
- El cron no cambia: corre PHP de consola, no depende de Apache ni de nginx.

**1b. Redis en el servidor de pruebas** para caché, sesiones y cola.
**1c. Redis en producción**, después de verlo funcionar en pruebas (constitución VIII).

- Redis queda instalado en el mismo servidor de Documenta (en la fase 2 pasa a Docker).
- **Seguridad de Redis:** escucha solo dentro del servidor, nunca en una dirección pública; exige
  contraseña; la contraseña vive solo en el `.env` del servidor (nunca en el repositorio ni en un
  mensaje a una IA externa).
- **Que un reinicio de Redis no cierre todas las sesiones:** Redis guarda en disco lo que tiene en
  memoria, de modo que reiniciar el servidor o el servicio no saque a todos.
- **Que llenarse la caché no borre sesiones:** si Redis se queda sin memoria, no puede descartar
  sesiones para hacerle espacio a la caché.
- PHP necesita la extensión de Redis (`.env.example` ya trae `REDIS_CLIENT=phpredis`); entra en la
  lista de extensiones de producción.
- **La cola** pasa a Redis porque así lo decidió Edwar, aunque hoy nada la usa: **no se instala
  trabajador de cola** en esta fase. La spec que algún día encole un trabajo debe pedir el
  trabajador y su vigilancia (PA-07).

**Lo que la gente nota al pasar a Redis (1b y 1c):**

| Qué pasa | A quién | Qué se hace |
|---|---|---|
| **Todos vuelven a iniciar sesión una vez.** Las sesiones guardadas en la base de datos no pasan a Redis | Todos los usuarios | Aviso por la campana el día anterior (Administración, «Enviar aviso») y cambio fuera de horario de oficina |
| Un formulario que estaba abierto en el momento del cambio responde «la página expiró» y hay que recargarlo | Usuarios con una pantalla abierta **y personas de campo con el enlace por cédula abierto** (los enlaces públicos usan la protección de formularios) | Elegir la hora con menos marcaciones (no en un cambio de turno; ver PA-08). Se recarga y se marca de nuevo |
| Una **carga por tandas** que iba a la mitad pierde lo que llevaba (se guarda en la caché) y hay que empezarla de nuevo | Quien estaba subiendo en ese momento | Fuera de horario; nadie debería estar subiendo |
| Los contadores de intentos de la vía pública y del inicio de sesión vuelven a cero | Nadie lo nota | — |
| Las tres tareas programadas siguen corriendo, ahora con su candado en Redis | Nadie lo nota | Se comprueba en la ventana del cambio (CA-09) |

**Despliegue en la fase 1:**

- `.github/workflows/deploy.yml` **no cambia**: sigue entrando por SSH y corriendo `bin/deploy.sh`.
  Su verificación final («el sitio responde») detecta también un Redis caído, porque el sitio
  respondería con error.
- `bin/deploy.sh`: su comentario inicial pasa a decir que los dos servidores usan nginx + php-fpm.
  La línea que recarga `apache2` **se deja** mientras dure el periodo de reversa (no hace nada si
  Apache está apagado, y permite volver sin tocar el script); se retira después (C-02).
- Se agrega un paso que comprueba que la aplicación llega a Redis antes de quitar el modo
  mantenimiento; si no llega, el despliegue falla con un mensaje claro en vez de dejar el sitio
  caído en silencio.

**Plan de reversa de la fase 1:**

| Si falla | Cómo se vuelve | Qué se pierde |
|---|---|---|
| nginx en producción (1a) | Apache **no se desinstala** durante el periodo de reversa (PA-09): se apaga nginx y php-fpm, se enciende Apache con su configuración de antes (guardada con fecha antes del cambio), y se recarga. El `php.ini` de Apache no se toca | Nada: los datos no cambian de lugar |
| Redis (1b o 1c) | En el `.env` del servidor, `CACHE_STORE`, `SESSION_DRIVER` y `QUEUE_CONNECTION` vuelven a `database`, y se regenera la configuración (`config:cache`). Las tablas `cache`, `sessions` y `jobs` **no se borran** durante el periodo de reversa | Todos vuelven a iniciar sesión otra vez; se pierden las cargas por tandas a medias |

Cada vuelta atrás queda anotada (fecha, motivo, quién) en el historial de esta spec.

### 2. Fase 2 — Después del 10/12/2026: Docker para Redis y monitoreo

- **Redis pasa a un contenedor de Docker**, con las mismas condiciones de la fase 1: solo dentro
  del servidor, con contraseña y con sus datos guardados en disco fuera del contenedor (si el
  contenedor se borra, las sesiones no se pierden). Atención: los puertos que publica Docker se
  saltan el cortafuegos del servidor; el de Redis se publica solo hacia dentro del servidor. Si el
  cambio no puede traer las sesiones de antes, es otro «todos vuelven a iniciar sesión», con el
  mismo aviso.
- **Monitoreo con Prometheus, Grafana y Loki**, en contenedores, de preferencia en **un servidor
  aparte pequeño** para no quitarle memoria a Documenta (PA-02).
- **Qué se vigila** (como mínimo):

  | Qué | Por qué |
  |---|---|
  | El sitio responde (pruebas y producción) | Saber antes que los usuarios |
  | Errores y tiempo de respuesta de las páginas | Ver si algo se pone lento o falla |
  | Memoria, procesador y **disco** del servidor de Documenta | Los documentos y las fotos crecen todos los días |
  | MySQL y Redis encendidos, y la memoria de Redis | Sin ellos no hay sitio ni sesiones |
  | **Las tres tareas programadas corrieron** a su hora | Si el cron se detiene, no se cierran turnos ni salen avisos, y nadie se da cuenta |
  | Vencimiento del certificado HTTPS | Evitar que el sitio aparezca como inseguro |

- **Alertas**: Grafana avisa cuando el sitio no responde, el disco pasa de un límite, Redis o
  MySQL se caen, o una tarea programada no corre a su hora. A quién y por dónde: PA-03. Los
  límites exactos (qué porcentaje de disco, cuántos minutos sin responder) se fijan en el plan con
  lo que se observe en las primeras semanas; no se inventan aquí.
- **Seguridad del monitoreo:** ver §4.
- **Logs a Loki:** ver §4.

### 3. Fase 3 — Enero de 2027: decisión, no construcción

En enero Edwar decide dos cosas (PA-10 y PA-11). Esta spec no las construye: dice qué se evalúa
antes de decidir. Si alguna se aprueba, va en su propia spec.

**¿La aplicación misma pasa a Docker?** Evaluar:

- Qué se gana frente a lo que ya hay (nginx + php-fpm iguales en los dos servidores desde la fase 1).
- Los archivos (`storage/app/private`: documentos, fotos, soportes) viven fuera del contenedor, en
  un disco que sobrevive a cualquier reinicio, y los respaldos siguen funcionando.
- El cron de las tres tareas, el `php.ini` (límites de carga, extensiones) y el dueño de los
  archivos (`www-data`) dentro del contenedor.
- Cómo cambian `bin/deploy.sh` y `.github/workflows/deploy.yml`, y el plan de reversa.
- La memoria que consume en cada servidor (PA-01).

**¿Se migra de MySQL a PostgreSQL?** Evaluar:

- **Migración de los datos de producción:** con qué herramienta, ensayo completo con una copia de
  producción en el servidor de pruebas, conteo de filas tabla por tabla igual antes y después, y
  que los números de identificación sigan desde donde iban.
- **Tiempo de corte:** cuánto tiempo queda el sitio en mantenimiento (medido en el ensayo, no
  supuesto). Durante ese tiempo nadie marca turnos: hay que acordarlo con Coordinación.
- **Consultas escritas a mano** (`selectRaw`, `whereRaw`, `orderByRaw`), que hoy se prueban en
  SQLite y se verifican contra MySQL (constitución VII). Lo encontrado en el código:
  - Analítica de supervisión: compara `casos_escalados = 1`; en PostgreSQL un sí/no no se compara
    con un número y la consulta falla.
  - Búsquedas con LIKE (documentos, y los filtros de las listas): en MySQL no distinguen
    mayúsculas ni, según la intercalación, tildes; en PostgreSQL sí. «jose» dejaría de encontrar
    «José». Es un cambio de comportamiento visible.
  - Indicadores de derivaciones, analítica de supervisión, conteo de la campana, última marca de
    cada persona y orden de los resultados por cédula: revisar una por una.
  - Migraciones que ya se escribieron pensando en MySQL (por ejemplo, la que quita la bandeja de
    enlaces y recepciones) y el comentario del ingreso con Authentik sobre comparar sin
    mayúsculas «como MySQL».
- **Pruebas:** la constitución VII pasaría a decir «probar contra PostgreSQL».
- **Tempo** (trazas con OpenTelemetry) se decide en la misma revisión, solo si para entonces
  Grafana y Loki no alcanzan para encontrar dónde está la lentitud.

### 4. Seguridad

- **Grafana, Prometheus y Loki no quedan abiertos a internet.** Propuesta (PA-13):
  - **Grafana** se abre solo **con la cuenta de Authentik** (Grafana entra con Authentik de forma
    nativa) y, además, desde una lista de direcciones permitidas o por VPN. Solo pueden entrar
    Edwar y José (y quien Edwar diga).
  - **Prometheus y Loki** no tienen ninguna puerta hacia internet: solo Grafana los consulta, dentro
    del servidor de monitoreo.
  - **Lo que el servidor de monitoreo lee del de Documenta** (cifras del servidor, de MySQL, de
    Redis, de nginx y php-fpm, y los logs) solo se acepta desde la dirección del servidor de
    monitoreo y viaja **cifrado** (VPN entre los dos servidores o conexión cifrada), porque cruza
    internet.
  - Las páginas de estado de nginx y php-fpm no se publican en el sitio.
- **Redis:** sin puerta a internet y con contraseña (fases 1 y 2).
- **Contraseñas** de Redis, Grafana y las de los exportadores: solo en los servidores; nunca en el
  repositorio, en un mensaje, ni a una IA externa (Hostinger u otra).
- **Logs y datos personales.** Los logs de Laravel guardan los errores con el detalle de la
  consulta que falló, y eso incluye cédulas, nombres y lo escrito en los casos. Los logs de nginx
  guardan la dirección IP y la ruta de cada visita, incluidos los códigos de los enlaces públicos.
  Además, los Usuarios HC (DOC-26) van a traer datos de salud. Propuesta (PA-14):

  | Qué | ¿Va a Loki? | Cuánto se guarda |
  |---|---|---|
  | Errores y advertencias de Laravel (nivel `warning` en adelante) | Sí, **con las cédulas y los correos enmascarados** antes de salir del servidor | 30 días en Loki |
  | Mensajes de depuración e información de Laravel | No | — |
  | Errores de nginx | Sí | 30 días |
  | Visitas de nginx (access log) | Solo ruta sin parámetros, código de respuesta y tiempo; los códigos de los enlaces públicos recortados | 30 días |
  | El archivo de log en el propio servidor | Pasa a un archivo por día | 14 días (`LOG_DAILY_DAYS`, que hoy Laravel ya trae por defecto) |

  **Esto toca la constitución V:** hoy solo habla de la retención de las fotos de personas, que
  define el Comité (y su plazo sigue abierto, PA-10 de `domain.md`). Los logs con datos personales
  son un lugar nuevo donde esos datos quedan, y en otro servidor. No la contradice, pero sí queda
  un vacío: la retención de los logs la debería definir el Comité igual que la de las fotos, y si
  el servidor de monitoreo queda en otro país, el envío de datos personales hacia allá también
  entra en la Ley 1581 de 2012. Se propone completar el principio V (ver «Enmienda propuesta a la
  constitución»). **Edwar decide.**

## Enmienda propuesta a la constitución

Para aprobar por Edwar. **No se ha modificado `constitution.md`.**

**Tabla «Stack aprobado»** (texto propuesto; lo de la fase 3 se escribe solo si se aprueba):

| Capa | Tecnología | Diferencia con la plantilla SDD |
|---|---|---|
| Base de datos | MySQL (pruebas en SQLite). **Se evalúa PostgreSQL en enero de 2027 (DOC-32, fase 3)** | La plantilla dice PostgreSQL |
| Caché, sesiones y cola | **Redis**, sin puerta a internet y con contraseña | — |
| Ambiente | Laragon (local); **Ubuntu + nginx + php-fpm en los dos servidores** (pruebas y producción). **Redis y el monitoreo en Docker** (desde la fase 2); la aplicación, sin Docker mientras no se apruebe lo contrario (fase 3) | La plantilla dice Docker |
| Monitoreo | **Prometheus, Grafana y Loki**, en un servidor aparte, sin puerta a internet; acceso con Authentik | — |

Y, si en enero se aprueba PostgreSQL: la fila «Base de datos» pasa a «PostgreSQL (pruebas en
SQLite)» y el principio VII cambia «probar también contra MySQL» por «contra PostgreSQL».

**Principio V**, una viñeta nueva propuesta:

> - Los registros técnicos (logs) que salen del servidor de Documenta **NO DEBEN** llevar cédulas
>   ni datos de salud sin enmascarar; se guardan por un plazo definido por el Comité. Las
>   herramientas de monitoreo **NO DEBEN** quedar abiertas a internet.

**Principio VIII**, una viñeta nueva propuesta:

> - Todo cambio de infraestructura en producción **DEBE** tener un plan de reversa escrito y
>   probarse antes en el servidor de pruebas.

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | ¿Qué servidor es producción y cuál pruebas (proveedor, ¿el VPS de Hostinger?), cuánta memoria y cuánto disco tiene cada uno? | Edwar lo dice antes de empezar; con eso el plan fija cuánta memoria se le da a Redis y a php-fpm | **Sí (fase 1)** |
| PA-02 | ¿El monitoreo va en un servidor aparte o en el mismo de Documenta? | **Aparte y pequeño**, como recomendó el calendario. Si en el mismo, solo después de ver en PA-01 que la memoria alcanza | Sí (fase 2) |
| PA-03 | ¿Quién recibe las alertas de Grafana y por dónde (correo, Telegram, WhatsApp…)? | Edwar y José, por correo y por un canal de mensajería que usen a diario. No a la campana de Documenta: si Documenta se cae, la campana también | Sí (fase 2) |
| PA-04 | ¿Qué valores tienen hoy en cada servidor `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` y `LOG_STACK`, y qué hay en el `php.ini` de Apache de producción? (no se leyó ningún `.env`) | **En curso (Edwar, 2026-11-23): los está pidiendo para revisarlos.** Se anotan en el plan, sin copiar contraseñas | **Sí (fase 1)** |
| PA-05 | ¿Qué tamaño máximo por petición se configura en nginx y PHP? `docs/INICIO.md` dice «50 × 25 MB» (1 250 MB), pero el navegador ya parte las tandas a 150 MB | Igualar `client_max_body_size` y `post_max_size` a la tanda (150 MB) con un margen, y corregir `docs/INICIO.md`. Si se prefiere no tocar lo que hoy tiene Apache, copiar su valor | **Sí (fase 1)** |
| PA-06 | ¿Cuánto puede esperar una descarga de ZIP o un Excel antes de que nginx o php-fpm la corten? No hay mediciones | Medir en pruebas el ZIP y el Excel más grandes que existan y poner el doble de lo medido; mientras tanto, el mismo tiempo máximo que tiene hoy Apache (PA-04) | **Sí (fase 1)** |
| PA-07 | La cola pasa a Redis pero hoy nada la usa. ¿Se instala ya un trabajador de cola? | **No**: se instala con la primera spec que encole un trabajo, con su vigilancia en Grafana | No |
| PA-08 | ¿A qué hora se hace cada cambio? Hay turnos de noche que marcan por el enlace público | **Edwar (2026-11-23): en la noche.** Falta la franja exacta: en producción el turno N7 entra a las 9:00 p. m. y sale a las 4:30 a. m., y los turnos de la mañana entran desde las 5:00 a. m. Propuesta: entre 11:00 p. m. y 3:00 a. m., entre semana, lejos de esas marcaciones. Aviso por la campana el día anterior | **Sí (fase 1)** |
| PA-09 | ¿Cuánto dura el periodo de reversa (Apache instalado, tablas `cache`, `sessions` y `jobs` sin borrar, línea de apache2 en `bin/deploy.sh`)? | Dos semanas sin incidentes en producción | No |
| PA-10 | Enero de 2027: ¿la aplicación pasa a Docker? | Decisión de Edwar con la evaluación del §3 | No (fase 3) |
| PA-11 | Enero de 2027: ¿se migra a PostgreSQL? ¿Qué se gana que justifique el corte y el cambio en las búsquedas? | Decisión de Edwar con la evaluación del §3 y un ensayo con copia de producción | No (fase 3) |
| PA-12 | El calendario y las decisiones de Edwar del 2026-11-23 no están en `fuentes/` | Registrarlas como decisión (D-NN) antes de aprobar | No |
| PA-13 | ¿Cómo se entra a Grafana: Authentik, lista de direcciones, VPN o varias juntas? | Authentik **y** lista de direcciones (o VPN si las direcciones de Edwar y José cambian mucho) | Sí (fase 2) |
| PA-14 | ¿Qué logs van a Loki y por cuánto tiempo? ¿Quién fija el plazo? (constitución V) | La tabla del §4: enmascarado, 30 días en Loki, 14 en el servidor. El plazo lo confirma el Comité, como el de las fotos | Sí (fase 2) |
| PA-15 | El calendario de Habitante de Calle da 14 días hábiles entre dos personas hasta el 10/12 (`habitante-de-calle/plan-de-trabajo.md`). ¿Quién hace la fase 1 y de qué días sale? | **Sin respuesta (Edwar, 2026-11-23: «no sé de dónde salen»).** Queda como riesgo en el plan de trabajo. Propuesta: el cambio se hace de noche (PA-08), fuera del horario de desarrollo, y la preparación en pruebas la lleva quien termine primero su tarea de DOC-25 | No |

## Criterios de aceptación

Los de infraestructura se prueban con una verificación en el servidor (escrita en `tasks.md` como
pasos que cualquiera puede repetir); los que dependen de la aplicación, con pruebas automáticas.

### Fase 1

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Producción responde por nginx + php-fpm; Apache está apagado y no recibe visitas | Por construir (verificación en el servidor) |
| CA-02 | Iniciar sesión (formulario y Authentik), abrir documentos, marcar un turno por `/t/…`, registrar un caso por `/d/…`, abrir un enlace de carga y leer un QR impreso funcionan en producción igual que antes, con las mismas direcciones | Por construir (verificación en el servidor) |
| CA-03 | Pedir por su ruta un archivo de `storage/` (un documento, una foto de turno), el `.env`, una carpeta `.git` o un `.php` distinto de `index.php` responde «No encontrado» o «Prohibido», nunca el contenido | Por construir (verificación en el servidor) |
| CA-04 | Una carga de 300 archivos por tandas, con archivos de hasta 25 MB, entra completa en producción sin error de tamaño; el `php.ini` de php-fpm tiene `max_file_uploads = 50` y las extensiones `intl`, `xmlreader`, `zip`, `gd`, `pdo_mysql` y la de Redis | Por construir (verificación en el servidor) |
| CA-05 | La descarga en ZIP más grande que exista en producción y el Excel más grande de turnos, derivaciones y supervisión terminan sin que nginx ni php-fpm los corten | Por construir (verificación en el servidor) |
| CA-06 | En pruebas y después en producción, la caché, las sesiones y la cola usan Redis; las tablas `cache` y `sessions` dejan de crecer | Por construir (verificación en el servidor) |
| CA-07 | Redis no responde desde fuera del servidor y, desde dentro, no responde sin contraseña | Por construir (verificación en el servidor) |
| CA-08 | Reiniciar Redis no cierra la sesión de quien ya había entrado | Por construir (verificación en el servidor) |
| CA-09 | Después del cambio a Redis, en producción: `turnos:cerrar-abiertas` corre a la hora, `turnos:revisar-entradas` cada 15 minutos y `derivaciones:recordar` cada minuto, y cada una deja su resultado | Por construir (verificación en el servidor) |
| CA-10 | Ninguna funcionalidad de Documenta necesita un trabajador de cola: ninguna clase de la aplicación se encola | Por construir (prueba automática `test_ca10_…`) |
| CA-11 | La caché por tandas sigue funcionando con Redis: reintentar una tanda no duplica archivos y un enlace de un uso no se cierra en la primera tanda (las pruebas que ya existen pasan con caché en Redis en el servidor de pruebas) | Por construir (verificación en pruebas + las pruebas que ya existen) |
| CA-12 | Los usuarios recibieron por la campana, al menos el día anterior, el aviso de que tendrán que volver a iniciar sesión | Por construir (verificación) |
| CA-13 | Un despliegue por GitHub Actions a cada servidor termina bien con nginx + php-fpm + Redis; si la aplicación no llega a Redis, el despliegue falla con un mensaje claro | Por construir (verificación en pruebas) |
| CA-14 | El plan de reversa se ensayó en el servidor de pruebas (volver a la base de datos para caché y sesiones) y está escrito paso a paso | Por construir (verificación en pruebas) |

### Fase 2

| CA | Criterio | Prueba |
|---|---|---|
| CA-15 | Redis corre en Docker con sus datos fuera del contenedor; borrar y volver a crear el contenedor no pierde las sesiones; su puerto no responde desde fuera del servidor | Por construir (verificación en el servidor) |
| CA-16 | Grafana muestra para pruebas y producción: sitio arriba, errores y tiempo de respuesta, memoria, procesador y disco, MySQL, Redis y la última ejecución de cada tarea programada | Por construir (verificación) |
| CA-17 | Apagar el sitio de pruebas, detener el cron de pruebas o llenar su disco por encima del límite dispara una alerta que llega a quien diga PA-03 | Por construir (verificación en pruebas) |
| CA-18 | Grafana, Prometheus y Loki no responden desde internet sin la forma de acceso de PA-13; Prometheus y Loki no tienen ninguna puerta hacia internet | Por construir (verificación) |
| CA-19 | Lo que llega a Loki no contiene cédulas ni correos completos ni contraseñas (probado provocando un error con una cédula conocida) y se borra solo al cumplir el plazo de PA-14 | Por construir (verificación en pruebas) |
| CA-20 | El log de Laravel en el servidor queda en un archivo por día y guarda solo el plazo de PA-14 | Por construir (verificación en el servidor) |

### Fase 3

| CA | Criterio | Prueba |
|---|---|---|
| CA-21 | Antes de que Edwar decida PA-10 y PA-11, existe la evaluación del §3: memoria por servidor, ensayo de migración con copia de producción (tiempo de corte medido y conteo de filas), y la lista de consultas escritas a mano revisadas con su resultado | Por construir (documento de evaluación) |

## Reglas que cambian

- **Reglas de negocio `RN-NN`:** ninguna. La lectura del código confirma que nada del negocio
  depende de que la caché, la sesión o la cola estén en la base de datos.
- **Constitución:** la enmienda propuesta arriba (tabla «Stack aprobado», principios V y VIII; y
  VII si se aprueba PostgreSQL). Lo decide Edwar.

**Documentación a corregir** (al aprobarse; no se editó nada):

- **`CLAUDE.md`:** dice «servidores: Ubuntu + nginx + php8.3-fpm»; hoy producción usa Apache. Queda
  cierto después de la fase 1. Agregar Redis al stack, la extensión de Redis al checklist de
  producción, el `php.ini` de **php-fpm** (no el de Apache) y la regla de que el monitoreo no se
  abre a internet.
- **`constitution.md`:** fila «Ambiente» (mismo error) y las de la enmienda.
- **`bin/deploy.sh`:** el comentario «producción (Apache + mod_php)» después de la fase 1.
- **`docs/INICIO.md`:** «`client_max_body_size` acorde a 50 × 25 MB» según PA-05.
- **`docs/architecture/`** (fase 2 de la documentación): una ADR para cada fase aprobada.

## Decisiones (Edwar, 2026-11-23)

- nginx en lugar de Apache en producción; Redis para caché, sesiones y colas; primero en pruebas.
- Docker para Redis y para el monitoreo después del 10/12/2026; monitoreo con Prometheus, Grafana
  y Loki, de preferencia en un servidor aparte pequeño.
- En enero de 2027 se decide si la aplicación pasa a Docker y si se migra a PostgreSQL. Tempo solo
  si hace falta.
- **Rechazado:** partir Documenta en «casi microservicios». Sigue siendo una sola aplicación
  Laravel; lo que se separa es la infraestructura.

## Candidatos para la siguiente versión

- **C-01.** Trabajador de cola vigilado (y su alerta) cuando una spec encole trabajos: por ejemplo,
  armar ZIP y Excel grandes por fuera de la petición y avisar por la campana cuando estén listos.
- **C-02.** Retirar la línea de `apache2` de `bin/deploy.sh`, desinstalar Apache y borrar las tablas
  `cache`, `sessions` y `jobs` con una migración, al cerrar el periodo de reversa (PA-09).
- **C-03.** Monitorear también Authentik, si Documenta depende de él para entrar.
- **C-04.** Respaldos de la base de datos y de `storage/app/private` vigilados desde Grafana
  (`docs/INICIO.md` los tiene como pendiente).
- **C-05.** Tempo (trazas) si en enero hace falta (fase 3).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.2 | 2026-11-23 | Respuestas de Edwar: el cambio se hace de noche (PA-08, falta la franja); los valores de los servidores los está pidiendo (PA-04); de dónde salen los días sigue sin respuesta (PA-15) |
| 0.1 | 2026-11-23 | Borrador con el calendario aprobado por Edwar y lo leído en el código |
