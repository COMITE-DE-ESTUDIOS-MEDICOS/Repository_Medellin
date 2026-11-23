# Spec: Infraestructura · Redis, Docker, monitoreo y migración a AWS

| | |
|---|---|
| Historia | DOC-32 |
| Tipo | Mejora técnica · no cambia reglas de negocio `RN-NN`; **propone una enmienda a la constitución** (tabla «Stack aprobado» y principios V y VIII) y corrige el registro de cómo está producción |
| Estado | **Borrador** |
| Versión | 0.6 |
| Fuentes | Pedido y calendario de Edwar, 2026-11-23, y su decisión del mismo día de **migrar a AWS (región Ohio, `us-east-2`), probablemente en enero de 2027** (**aún no están en `fuentes/`**, ver PA-12). Informe de producción srv1915154 (PA-04). Lectura del código: `bin/deploy.sh`, `.github/workflows/deploy.yml`, `.env.example`, `composer.json`, `routes/console.php`, `config/repositorio.php`, `config/filesystems.php`, `config/logging.php`, `app/Services/AlmacenamientoDocumentos.php`, `app/Services/CargaPorTandas.php`, `docs/INICIO.md` |
| Redactó | Claude |
| Aprobó (Dueño de Producto) | Pendiente (Edwar) |

## Problema

Documenta corre en dos servidores que no son iguales y eso no está escrito en ninguna parte
confiable:

- **Pruebas** usa nginx con php-fpm. **Producción usa Apache** (lo dijo Edwar y lo confirma el
  comentario de `bin/deploy.sh`), y ni siquiera está claro con cuál de sus dos formas de correr PHP
  (PA-04). Sin embargo, `CLAUDE.md` y la constitución dicen que los dos servidores usan nginx. Lo
  que se prueba en `develop` no corre sobre lo mismo que producción.
- La caché, las sesiones (quién tiene la sesión abierta) y la cola de trabajos viven en tablas de
  la misma base de datos MySQL que guarda los documentos, los turnos y los casos.
- No hay monitoreo: nadie se entera de que el servidor se está quedando sin disco, de que el
  cierre automático de turnos dejó de correr o de que el sitio responde lento, hasta que alguien
  llama a quejarse.

Edwar quiere nginx en producción, Redis, Docker y monitoreo, y el 2026-11-23 decidió además
**mudar Documenta a AWS, probablemente en enero de 2027, en la región de Ohio**. Con la mudanza
tan cerca, el trabajo hecho a mano sobre el servidor actual de producción (VPS srv1915154, en
Hostinger según Edwar) se perdería en enero. Por eso la spec se reorganiza: **casi nada en el
servidor actual; la configuración nueva se arma y se prueba en el servidor de pruebas, y esa misma
es la que se lleva a AWS.**

Documenta **no** se parte en servicios: sigue siendo **una sola aplicación Laravel**; lo que se
separa es la infraestructura que la rodea (aplicación, base de datos, Redis, monitoreo).

## Palabras que se usan en esta spec

Se explican una vez; después se usan sin más.

| Palabra | Qué es |
|---|---|
| **nginx** | El programa que recibe las visitas al sitio y se las pasa a PHP. Hace lo que hoy hace Apache en producción |
| **php-fpm** | PHP corriendo como un servicio aparte, al que nginx le pasa cada petición |
| **`php.ini`** | El archivo con los límites de PHP: cuántos archivos y cuántos MB se pueden subir, cuánto puede tardar una página, qué extensiones tiene |
| **Redis** | Una base de datos pequeña y muy rápida que guarda todo en memoria. Aquí se usa para la caché, las sesiones y la cola |
| **Caché** | Datos que se guardan un rato para no volver a calcularlos. En Documenta, por ejemplo, lo que ya entró de una carga por tandas y los contadores de intentos de la vía pública |
| **Sesión** | Lo que hace que el sistema recuerde que alguien ya inició sesión, y la protección de los formularios (también la de los enlaces públicos por cédula) |
| **Cola** | Una lista de trabajos que se hacen después, por fuera de la página que los pidió. Necesita un proceso que los atienda (el «trabajador») |
| **Docker** | Una forma de empaquetar un programa con todo lo que necesita para que corra igual en cualquier servidor. Cada programa empaquetado corriendo es un **contenedor** |
| **`docker compose`** | Un archivo que dice qué contenedores forman Documenta (nginx, PHP, Redis) y cómo se conectan, para encenderlos todos juntos y de la misma forma en cualquier servidor |
| **AWS** | Amazon Web Services: el proveedor de servidores en la nube al que se muda Documenta |
| **Región (Ohio, `us-east-2`)** | El lugar físico donde quedan los servidores de AWS. Ohio está en **Estados Unidos** |
| **EC2** | Un servidor alquilado en AWS. Aquí correría Docker con la aplicación, nginx y Redis |
| **RDS** | La base de datos MySQL que AWS administra: AWS se encarga de los respaldos, las actualizaciones y de mantenerla encendida |
| **S3** | El almacenamiento de archivos de AWS. Documenta podría guardar ahí los documentos y las fotos en vez de en el disco del servidor |
| **CloudWatch** | El monitoreo propio de AWS. Cobra según el volumen de registros que se le manden |
| **Prometheus** | Recoge cifras de los servidores cada pocos segundos (memoria, disco, peticiones, errores) |
| **Grafana** | Las pantallas con gráficas de esas cifras, y las **alertas** cuando algo pasa de un límite |
| **Loki** | Guarda los registros de texto (logs) de nginx y de Laravel para buscarlos desde Grafana |
| **Tempo / OpenTelemetry** | Trazas: el detalle de cuánto tardó cada parte de una petición. Solo si hace falta, después de la migración |
| **VPN** | Una red privada cifrada entre máquinas que van por internet |
| **DNS** | La «guía telefónica» de internet: dice a qué servidor lleva el dominio de Documenta. Cambiarla es lo que hace que las visitas lleguen a AWS en vez de al servidor actual |
| **Corte** | El rato en que el sitio queda en mantenimiento mientras se pasan los datos y los archivos al servidor nuevo |
| **Plan de reversa** | Los pasos para volver a como estaba si algo falla |

## Comportamiento

### 0. Cómo está hoy (leído del código y de lo dicho por Edwar)

| Tema | Pruebas | Producción |
|---|---|---|
| Proveedor | Por confirmar (PA-01) | VPS srv1915154, en Hostinger según Edwar |
| Servidor web | nginx + php-fpm | **Apache**, con `mod_php` y `proxy_fcgi` cargados a la vez: no se sabe cuál de los dos atiende el sitio ni, por tanto, cuál `php.ini` manda (PA-04) |
| Base de datos | MySQL | MySQL (versión por confirmar, PA-01) |
| Caché, sesiones y cola | En tablas de la base de datos | En tablas de la base de datos (`cache`, `sessions`, `jobs`). Redis está declarado en el `.env` pero nada lo usa; no se sabe si el servicio está instalado (PA-04) |
| Archivos (documentos, fotos de turnos, soportes de casos) | En el disco del servidor, `storage/app/private` | Igual |
| Registros (logs) | Un solo archivo `storage/logs/laravel.log` (`LOG_STACK=single` en `.env.example`), sin rotación | Por confirmar (PA-04) |
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

**Cómo se guardan los archivos.** Todo lo que se sube (documentos, fotos de turnos, soportes de
casos) pasa por un solo servicio de almacenamiento que usa el disco configurado en
`config/repositorio.php` (`disco`, hoy `local`). El comentario de ese archivo dice que cambiarlo a
S3 basta para migrar a la nube; **no es del todo cierto hoy**: el paquete que Laravel necesita para
hablar con S3 no está instalado (`composer.json`), y las descargas en ZIP (documentos y reporte de
derivaciones) agregan cada archivo por su ruta en el disco del servidor, cosa que con S3 no existe.

### 1. Fase 1 — Ahora, en el servidor actual: lo mínimo

**No se cambia Apache por nginx en el servidor actual.** Ese trabajo se perdería con la mudanza:
nginx llega a producción con AWS (fase 3). La fase 1 queda en dos pasos:

**1a. Aclarar qué `php.ini` usa el sitio en producción y dejarlo bien.**

- Se averigua cuál de las dos formas de PHP atiende el sitio (`mod_php` o php-fpm por
  `proxy_fcgi`) y, por tanto, cuál `php.ini` manda (PA-04). Queda escrito en esta spec.
- Ese `php.ini` debe tener `max_file_uploads = 50` (sin él, PHP descarta archivos de las tandas en
  silencio), `upload_max_filesize` y `post_max_size` por encima de una tanda (PA-05),
  `max_execution_time` y `memory_limit` que no corten los ZIP ni los Excel (PA-06), y las
  extensiones `intl`, `xmlreader`, `zip`, `gd`, `pdo_mysql`.
- Si la forma que **no** atiende el sitio sobra, apagarla es opcional y solo si es inmediato; si
  hay la menor duda, se deja como está hasta la mudanza.
- Lo que se encuentre se copia tal cual a la configuración de Docker de la fase 2: esos son los
  valores que producción ya necesita.

**1b. Redis para caché, sesiones y cola — solo si es barato.**

Se hace **solo si** el informe de PA-04 muestra que Redis ya está instalado y corriendo en
producción y la extensión de Redis ya está en el PHP que atiende el sitio. Si falta cualquiera de
las dos, **1b no se hace** y Redis llega con la fase 2 (pruebas) y la fase 3 (producción en AWS).
En ese caso **la fase 1 es solo 1a**.

Si se hace, primero en pruebas y después en producción (constitución VIII):

- **Seguridad de Redis:** escucha solo dentro del servidor, nunca en una dirección pública; exige
  contraseña; la contraseña vive solo en el `.env` del servidor (nunca en el repositorio ni en un
  mensaje a una IA externa).
- **Que un reinicio de Redis no cierre todas las sesiones:** Redis guarda en disco lo que tiene en
  memoria.
- **Que llenarse la caché no borre sesiones:** si Redis se queda sin memoria, no puede descartar
  sesiones para hacerle espacio a la caché.
- **La cola** pasa a Redis porque así lo decidió Edwar, aunque hoy nada la usa: **no se instala
  trabajador de cola** (PA-07).
- `bin/deploy.sh` agrega un paso que comprueba que la aplicación llega a Redis antes de quitar el
  modo mantenimiento; si no llega, el despliegue falla con un mensaje claro.

Hay que saber que **el cambio a Redis en el servidor actual le cuesta a la gente un «vuelvan a
iniciar sesión» ahora, y la mudanza de enero cuesta otro**. Si Edwar prefiere uno solo, 1b se salta
(PA-17).

**Lo que la gente nota al pasar a Redis** (en 1b, y otra vez en el corte de la fase 3):

| Qué pasa | A quién | Qué se hace |
|---|---|---|
| **Todos vuelven a iniciar sesión una vez** | Todos los usuarios | Aviso por la campana el día anterior (Administración, «Enviar aviso») y cambio de noche (PA-08) |
| Un formulario abierto en el momento del cambio responde «la página expiró» y hay que recargarlo | Usuarios con una pantalla abierta **y personas de campo con el enlace por cédula abierto** | Elegir la hora con menos marcaciones (PA-08). Se recarga y se marca de nuevo |
| Una **carga por tandas** que iba a la mitad pierde lo que llevaba y hay que empezarla de nuevo | Quien estaba subiendo | De noche; nadie debería estar subiendo |
| Los contadores de intentos de la vía pública y del inicio de sesión vuelven a cero | Nadie lo nota | — |
| Las tres tareas programadas siguen corriendo, con su candado en Redis | Nadie lo nota | Se comprueba en la ventana del cambio (CA-06) |

**Plan de reversa de la fase 1:**

| Si falla | Cómo se vuelve | Qué se pierde |
|---|---|---|
| Un cambio en el `php.ini` (1a) | Se guarda una copia con fecha antes de tocarlo; se restaura y se recarga | Nada |
| Redis (1b) | En el `.env` del servidor, `CACHE_STORE`, `SESSION_DRIVER` y `QUEUE_CONNECTION` vuelven a `database` y se regenera la configuración (`config:cache`). Las tablas `cache`, `sessions` y `jobs` **no se borran** | Todos vuelven a iniciar sesión otra vez; se pierden las cargas por tandas a medias |

Cada vuelta atrás queda anotada (fecha, motivo, quién) en el historial de esta spec.

### 2. Fase 2 — Después del 10/12/2026: Docker y monitoreo, en el servidor de pruebas

Se arma la configuración con la que Documenta va a correr en AWS y se prueba en el servidor de
pruebas. **La misma configuración (los mismos archivos) es la que se lleva a AWS en la fase 3**; en
AWS solo cambian las direcciones y las contraseñas.

**2a. Documenta en Docker, con `docker compose`:**

- Contenedores: **nginx**, **PHP (php-fpm) con la aplicación** y **Redis**. La base de datos **no**
  va en un contenedor: en pruebas se usa la MySQL que ya tiene el servidor, igual que en AWS se
  usará RDS, una base de datos aparte (fase 3).
- Lo que nginx debe cumplir para Documenta:
  - **Solo sirve `public/`.** Ningún archivo de `storage/` (documentos, fotos de turnos, soportes
    de casos) se puede pedir por su ruta: toda descarga pasa por el controlador con permiso y
    auditoría (RN-06, constitución V). Tampoco se sirven archivos ocultos (`.env`, `.git`) ni
    ningún `.php` distinto de `index.php`.
  - **Tamaño de lo que se sube.** El límite de nginx (`client_max_body_size`) debe ser igual al
    `post_max_size` de PHP y los dos deben quedar **por encima de una tanda completa**: hasta
    50 archivos o 150 MB por tanda (`mb_por_tanda` en `config/repositorio.php`), con 25 MB por
    archivo (PA-05).
  - **Tiempo de espera.** Las descargas en ZIP, los Excel de turnos, derivaciones y supervisión y
    la carga masiva de casos se arman mientras la persona espera. nginx y php-fpm no pueden
    cortarlas antes de que PHP termine (PA-06).
  - **HTTPS** como hoy.
- **El `php.ini` va dentro de la configuración**, con los valores que se aclararon en 1a, la
  extensión de Redis y `max_file_uploads = 50`. Así deja de depender de lo que alguien haya puesto
  a mano en cada servidor.
- **Nada importante vive dentro de un contenedor.** Los archivos (`storage/app/private`), los logs
  y los datos de Redis quedan en el disco del servidor, fuera de los contenedores: borrar y volver
  a crear los contenedores no pierde documentos, fotos ni sesiones. El dueño de los archivos sigue
  siendo el mismo usuario con que PHP los escribe.
- **Redis** con las mismas condiciones de 1b: contraseña, memoria que no descarta sesiones, datos
  guardados en disco. Atención: los puertos que publica Docker se saltan el cortafuegos del
  servidor; el de Redis **no se publica** (solo lo ve el contenedor de PHP).
- **El cron** de las tres tareas sigue corriendo cada minuto, **una sola vez** (si corre en el
  servidor y también dentro de un contenedor, los avisos «no registró entrada» saldrían dobles).
  Dónde corre lo decide el plan.
- **Despliegue:** `bin/deploy.sh` y `.github/workflows/deploy.yml` cambian para pruebas (traer el
  código, reconstruir o reiniciar los contenedores, migrar, comprobar Redis, verificar que el sitio
  responde). Producción sigue con el despliegue de hoy hasta la fase 3.
- **Reversa en pruebas:** la instalación de hoy (nginx + php-fpm del servidor) se deja apagada pero
  instalada hasta que la de Docker funcione dos semanas sin incidentes; volver es apagar los
  contenedores y encender lo de antes.

**Mientras dure la fase 2, pruebas (Docker) y producción (Apache) son aún más distintos que hoy.**
Lo que más cambia entre los dos son los límites de PHP; por eso 1a deja escritos los de producción
y la configuración de Docker los copia. Ver «Contradicciones y riesgos».

**2b. Monitoreo con Prometheus, Grafana y Loki**, en contenedores. Dónde viven (en el servidor de
pruebas, en uno aparte pequeño, o directamente en AWS) y si en AWS se usan estos o CloudWatch:
PA-02 y PA-21.

- **Qué se vigila** (como mínimo):

  | Qué | Por qué |
  |---|---|
  | El sitio responde (pruebas y producción) | Saber antes que los usuarios |
  | Errores y tiempo de respuesta de las páginas | Ver si algo se pone lento o falla |
  | Memoria, procesador y **disco** del servidor de Documenta | Los documentos y las fotos crecen todos los días |
  | MySQL y Redis encendidos, y la memoria de Redis | Sin ellos no hay sitio ni sesiones |
  | **Las tres tareas programadas corrieron** a su hora | Si el cron se detiene, no se cierran turnos ni salen avisos, y nadie se da cuenta |
  | Vencimiento del certificado HTTPS | Evitar que el sitio aparezca como inseguro |

  Mientras producción siga en el servidor actual, de ella se vigila al menos **que el sitio
  responde y el certificado** (se mira desde fuera, sin instalar nada allá).
- **Alertas**: Grafana avisa cuando el sitio no responde, el disco pasa de un límite, Redis o
  MySQL se caen, o una tarea programada no corre a su hora. A quién y por dónde: PA-03. Los límites
  exactos se fijan en el plan con lo que se observe; no se inventan aquí.
- **Seguridad y logs:** ver §4.

### 3. Fase 3 — Enero de 2027: migración a AWS, región Ohio

**Bloqueada** hasta que se responda la pregunta jurídica de la Ley 1581 (PA-16, ver §4) y se
confirmen cuenta, presupuesto y fecha (PA-18, PA-19).

**Lo decidido por Edwar:** AWS, región Ohio (`us-east-2`), probablemente enero de 2027.

**Lo propuesto (pendiente de Edwar, PA-10):**

| Pieza | Propuesta | Por qué |
|---|---|---|
| Aplicación, nginx y Redis | Un servidor **EC2** con la configuración de Docker de la fase 2 | Es la misma que ya se probó; no se inventa nada en enero |
| Base de datos | **RDS para MySQL**, con respaldos administrados por AWS | AWS se encarga de los respaldos y las actualizaciones |
| Motor de base de datos | **Se mantiene MySQL** | Cambiar de motor (PostgreSQL) a la vez que de proveedor suma riesgo: si algo falla no se sabe si fue la mudanza o el motor. PostgreSQL se evalúa **después** de la migración (§5, PA-11) |
| Archivos | **En el disco del EC2** para la mudanza; S3 después (PA-22) | Con el disco del servidor el código funciona igual que hoy. S3 exige instalar el paquete y cambiar cómo se arman los ZIP (§0) |
| Tamaños de servidor y de base de datos | Los fija el plan con lo que use hoy producción (PA-01) | No se inventan aquí |

**Hay otra propuesta sobre la mesa.** Edwar trajo una comparación de AWS frente al servidor
actual (KVM 8), transcrita en [`comparacion-aws-kvm8.md`](comparacion-aws-kvm8.md). Esa propuesta
**no es la de esta spec**: usa **Fargate** (contenedores que administra AWS, sin servidor propio)
en lugar de EC2, y **PostgreSQL** en RDS (Multi-AZ) en lugar de MySQL, además de balanceador, WAF,
GuardDuty y otras herramientas de seguridad, con un costo declarado de 630.97 USD al mes. Si se
adopta, cambia esta fase: el cron pasa a tareas programadas de AWS, los archivos tienen que ir a S3
o a un disco compartido (lo que exige C-06 antes de la mudanza), y la migración a PostgreSQL entra
en enero con su ensayo y la revisión de consultas del §5. **Cuál de las dos arquitecturas se adopta
lo decide Edwar (PA-24).** Las cifras de esa comparación son las que llegaron; esta spec no las
valida.

**Lo que la migración debe cumplir:**

- **Base de datos.** Se pasa con una copia completa de producción. En la base nueva, el conteo de
  filas tabla por tabla es igual al de antes, y los números de identificación siguen desde donde
  iban. La versión de MySQL en RDS es la misma que la de hoy o una compatible (PA-01). La base de
  datos **no tiene puerta a internet**: solo la alcanza el servidor de Documenta, y la conexión va
  cifrada.
- **Archivos.** Se copia todo `storage/app/private`. Después de copiar, el número de archivos y el
  tamaño total son iguales en los dos lados, y abrir al azar documentos, fotos de turnos y soportes
  de casos funciona. Siguen sin servirse por su ruta (constitución V).
- **Dominio y QR.** El dominio de Documenta no cambia, así que **las URL de los QR impresos, los
  enlaces públicos por cédula (`/t/…`, `/d/…`) y los enlaces de carga siguen funcionando igual**.
  Solo cambia a qué servidor apunta el dominio (DNS). Unos días antes se acorta el tiempo que
  internet recuerda la dirección vieja, para que el cambio llegue rápido a todos. El certificado
  HTTPS queda listo en AWS antes del cambio.
- **Cron.** Las tres tareas corren en AWS **y se apagan en el servidor viejo en el mismo corte**:
  nunca los dos a la vez (avisos dobles, cierres dobles) ni ninguno (turnos sin cerrar).
- **Authentik.** El ingreso con Authentik sigue funcionando después del corte. Si Authentik se queda
  donde está, solo se comprueba que acepte el regreso al mismo dominio. Si se muda, es otra mudanza
  con su propio corte, y no debe coincidir con la de Documenta (PA-20).
- **Respaldos.** Base de datos (RDS) y archivos con respaldo automático; cuánto tiempo se guardan
  y dónde, PA-23. **Un respaldo solo cuenta si se probó restaurarlo**: antes de cerrar la fase 3 se
  restaura uno en un lugar aparte y se comprueba.
- **Seguridad en AWS.** Desde internet solo se abren las puertas de la web (HTTP y HTTPS). La
  entrada por SSH, solo desde las direcciones que se acuerden (la de GitHub Actions para desplegar
  incluida). Redis y la base de datos, sin ninguna puerta a internet. Las llaves y contraseñas de
  AWS viven en el servidor y en los secretos de GitHub, **nunca en el repositorio ni en un mensaje
  a una IA externa**.
- **Despliegue.** GitHub Actions despliega a AWS igual que hoy despliega a los servidores: por SSH,
  con la configuración de la fase 2. Lo que cambia son el servidor de destino y los secretos.
- **Ensayo antes del corte.** Se hace la migración completa al menos una vez con una copia de
  producción, en AWS, sin cambiar el dominio. En el ensayo se **mide** cuánto dura el corte (no se
  supone), se cuentan filas y archivos y se recorren las pantallas principales.
- **El corte.** De noche, en la franja de PA-08, entre semana; aviso por la campana el día anterior
  («Enviar aviso»: el sitio estará en mantenimiento de tal a tal hora y todos tendrán que volver a
  iniciar sesión). Coordinación sabe que en ese rato nadie marca turnos. Orden: modo mantenimiento
  en el servidor viejo y su cron apagado → copia final de base de datos y archivos → comprobación
  de conteos → cambio de DNS → comprobación de las pantallas principales en AWS → se abre.
- **Lo que la gente nota:** el rato de mantenimiento, volver a iniciar sesión una vez, y lo mismo
  de la tabla de 1b (formularios abiertos, cargas a medias).

**Plan de reversa de la fase 3:**

| Si falla | Cómo se vuelve | Qué se pierde |
|---|---|---|
| Durante el corte, antes de abrir en AWS | El servidor viejo **no se ha tocado**: se le quita el modo mantenimiento, se enciende su cron y el DNS vuelve a apuntarle | Nada: en AWS nadie alcanzó a escribir |
| Después de abrir en AWS | Ya hay datos nuevos en AWS (marcaciones, casos, documentos). Volver exige otro corte con la copia de vuelta de esos datos al servidor viejo, con el mismo procedimiento al revés. Queda escrito antes del corte | Lo que diga el ensayo; nunca se vuelve **sin** traer los datos nuevos |

El servidor viejo se conserva encendido y sin cambios durante el periodo de reversa (PA-09) y solo
después se da de baja (C-02).

### 4. Seguridad y datos personales

**Ley 1581 de 2012: los datos se van a Estados Unidos.** Ohio está en Estados Unidos. Con la
migración, quedan allá la base de datos completa (cédulas y nombres de personas de campo, sus
marcaciones con ubicación, los casos de derivaciones), las **fotos de personas** (que la
constitución V ya nombra como datos personales) y, con los Usuarios HC de Habitante de Calle
(DOC-26), **datos de salud**, que la ley trata como sensibles. También quedarían allá los
respaldos, los logs y el monitoreo. Guardar esos datos en otro país es una **transferencia
internacional de datos personales**, que la ley limita. Esta spec **no** dice si se puede o en qué
condiciones (autorización de los titulares, contrato con el proveedor, lo que diga la
Superintendencia de Industria y Comercio, lo que pida la Alcaldía como responsable de los datos):
eso lo revisa quien lleve lo jurídico en el Comité. **La fase 3 no empieza sin ese concepto por
escrito en `fuentes/`** (PA-16). Si el concepto dice que no, la región de Ohio se cambia, o AWS se
replantea; lo decide Edwar.

- **Grafana, Prometheus y Loki no quedan abiertos a internet.** Propuesta (PA-13):
  - **Grafana** se abre solo **con la cuenta de Authentik** y, además, desde una lista de
    direcciones permitidas o por VPN. Solo pueden entrar Edwar y José (y quien Edwar diga).
  - **Prometheus y Loki** no tienen ninguna puerta hacia internet: solo Grafana los consulta.
  - Lo que el monitoreo lee de los servidores de Documenta solo se acepta desde el monitoreo y
    viaja **cifrado** si cruza internet.
  - Las páginas de estado de nginx y php-fpm no se publican en el sitio.
- **Redis y la base de datos:** sin puerta a internet y con contraseña.
- **Contraseñas** de Redis, Grafana, la base de datos y las llaves de AWS: solo en los servidores y
  en los secretos de GitHub; nunca en el repositorio, en un mensaje, ni a una IA externa.
- **Logs y datos personales.** Los logs de Laravel guardan los errores con el detalle de la
  consulta que falló, y eso incluye cédulas, nombres y lo escrito en los casos. Los logs de nginx
  guardan la dirección IP y la ruta de cada visita, incluidos los códigos de los enlaces públicos.
  Propuesta (PA-14):

  | Qué | ¿Va a Loki? | Cuánto se guarda |
  |---|---|---|
  | Errores y advertencias de Laravel (nivel `warning` en adelante) | Sí, **con las cédulas y los correos enmascarados** antes de salir del servidor | 30 días en Loki |
  | Mensajes de depuración e información de Laravel | No | — |
  | Errores de nginx | Sí | 30 días |
  | Visitas de nginx (access log) | Solo ruta sin parámetros, código de respuesta y tiempo; los códigos de los enlaces públicos recortados | 30 días |
  | El archivo de log en el propio servidor | Pasa a un archivo por día | 14 días (`LOG_DAILY_DAYS`, que hoy Laravel ya trae por defecto) |

  Si el monitoreo va a CloudWatch (PA-21), aplica lo mismo: nada sin enmascarar sale del servidor.

### 5. Después de la migración (no es parte de esta spec)

Se evalúan, cada una en su propia spec si se aprueba:

**¿Se migra de MySQL a PostgreSQL?** (PA-11) Evaluar:

- **Migración de los datos:** herramienta, ensayo completo con copia de producción, conteo de filas
  tabla por tabla y números de identificación que sigan desde donde iban.
- **Tiempo de corte:** medido en el ensayo; durante ese tiempo nadie marca turnos.
- **Consultas escritas a mano** (`selectRaw`, `whereRaw`, `orderByRaw`), que hoy se prueban en
  SQLite y se verifican contra MySQL (constitución VII). Lo encontrado en el código:
  - Analítica de supervisión: compara `casos_escalados = 1`; en PostgreSQL un sí/no no se compara
    con un número y la consulta falla.
  - Búsquedas con LIKE (documentos, y los filtros de las listas): en MySQL no distinguen
    mayúsculas ni, según la intercalación, tildes; en PostgreSQL sí. «jose» dejaría de encontrar
    «José». Es un cambio de comportamiento visible.
  - Indicadores de derivaciones, analítica de supervisión, conteo de la campana, última marca de
    cada persona y orden de los resultados por cédula: revisar una por una.
  - Migraciones escritas pensando en MySQL (por ejemplo, la que quita la bandeja de enlaces y
    recepciones) y el comentario del ingreso con Authentik sobre comparar sin mayúsculas «como
    MySQL».
- **Pruebas:** la constitución VII pasaría a decir «probar contra PostgreSQL».

**Archivos en S3** (C-06), **Tempo** (C-05) y **el servidor de pruebas en AWS**, si no se muda en
enero (PA-15b).

## Enmienda propuesta a la constitución

Para aprobar por Edwar. **No se ha modificado `constitution.md`.**

**Tabla «Stack aprobado»** (texto propuesto; lo de AWS se escribe cuando la fase 3 esté hecha):

| Capa | Tecnología | Diferencia con la plantilla SDD |
|---|---|---|
| Base de datos | MySQL (pruebas en SQLite); en producción, **RDS para MySQL en AWS**. PostgreSQL se evalúa después de la migración | La plantilla dice PostgreSQL |
| Caché, sesiones y cola | **Redis**, sin puerta a internet y con contraseña | — |
| Ambiente | Laragon (local). Servidores: **AWS, región Ohio (`us-east-2`): EC2 con Docker (`docker compose`: nginx + php-fpm + Redis)** y RDS para la base de datos. Hasta la migración, pruebas en Docker (desde la fase 2) y producción en el servidor actual con Apache | La plantilla dice Docker: ahora coincide |
| Monitoreo | **Prometheus, Grafana y Loki en Docker** (o CloudWatch, según PA-21), sin puerta a internet; acceso con Authentik | — |

Y, si después de la migración se aprueba PostgreSQL: la fila «Base de datos» pasa a «PostgreSQL
(pruebas en SQLite)» y el principio VII cambia «probar también contra MySQL» por «contra
PostgreSQL».

**Principio V**, dos viñetas nuevas propuestas:

> - Alojar datos personales fuera de Colombia (servidores, base de datos, archivos, respaldos,
>   logs o monitoreo) **DEBE** tener antes un concepto jurídico escrito, guardado en `fuentes/`,
>   sobre la transferencia internacional (Ley 1581 de 2012), en especial para datos de salud.
> - Los registros técnicos (logs) que salen del servidor de Documenta **NO DEBEN** llevar cédulas
>   ni datos de salud sin enmascarar; se guardan por un plazo definido por el Comité. Las
>   herramientas de monitoreo **NO DEBEN** quedar abiertas a internet.

**Principio VIII**, una viñeta nueva propuesta:

> - Todo cambio de infraestructura en producción **DEBE** tener un plan de reversa escrito y
>   probarse antes en el servidor de pruebas (o, si es una mudanza, en un ensayo completo con copia
>   de producción).

## Preguntas abiertas

| PA | Pregunta | Propuesta | Bloquea |
|---|---|---|---|
| PA-01 | ¿Qué servidor es pruebas (proveedor), cuánta memoria, procesador y disco usan hoy los dos, cuánto ocupan los archivos y la base de datos, y qué versión de MySQL tiene producción? | Edwar lo dice; con eso el plan fija la memoria de Redis y propone los tamaños de EC2 y RDS. No se inventan | Sí (fase 1, solo si se hace 1b); **Sí (fase 3)** |
| PA-02 | ¿Dónde vive el monitoreo en la fase 2: en el servidor de pruebas, en uno aparte pequeño, o ya en AWS? | **Ya en AWS** si la cuenta existe (PA-18), para no montarlo dos veces; si no, en el servidor de pruebas solo si PA-01 muestra que la memoria alcanza | Sí (fase 2) |
| PA-03 | ¿Quién recibe las alertas y por dónde (correo, Telegram, WhatsApp…)? | Edwar y José, por correo y por un canal de mensajería que usen a diario. No a la campana de Documenta: si Documenta se cae, la campana también | Sí (fase 2) |
| PA-04 | ¿Qué valores tienen hoy en producción la caché, las sesiones, la cola y PHP? | **Respondida en parte (informe de producción, srv1915154, 2026-11-23):** `CACHE_STORE`, `SESSION_DRIVER` y `QUEUE_CONNECTION` = `database` (tablas `cache`, `sessions` y `jobs`). Redis está declarado en el `.env` (127.0.0.1:6379) pero ningún driver lo usa; no se sabe si el servicio está instalado y corriendo. En disco solo hay cachés de compilación (`bootstrap/cache`, vistas). Existen dos `php.ini`, el de php-fpm y el de Apache; opcache con los valores por defecto de Ubuntu. **Apache tiene cargados a la vez `mod_php` y `proxy_fcgi`**, así que no está claro cuál `php.ini` usa el sitio. **Falta:** el resultado de `grep -rE "SetHandler\|php-fpm\|proxy:unix" /etc/apache2/sites-enabled/`, `systemctl status redis-server`, si la extensión de Redis está cargada, `LOG_STACK`, y en el `php.ini` que mande: `upload_max_filesize`, `post_max_size`, `max_file_uploads`, `max_execution_time` y `memory_limit` | **Sí (fase 1)** |
| PA-05 | ¿Qué tamaño máximo por petición? `docs/INICIO.md` dice «50 × 25 MB» (1 250 MB), pero el navegador ya parte las tandas a 150 MB | Igualar `client_max_body_size` y `post_max_size` a la tanda (150 MB) con un margen, y corregir `docs/INICIO.md`. En la fase 1 solo se comprueba que producción esté por encima | Sí (fase 1, comprobación); Sí (fase 2) |
| PA-06 | ¿Cuánto puede esperar una descarga de ZIP o un Excel antes de que la corten? No hay mediciones | Medir en pruebas el ZIP y el Excel más grandes y poner el doble de lo medido; mientras tanto, el tiempo máximo que tiene hoy producción (PA-04) | Sí (fase 2) |
| PA-07 | La cola pasa a Redis pero hoy nada la usa. ¿Se instala ya un trabajador de cola? | **No**: se instala con la primera spec que encole un trabajo, con su vigilancia | No |
| PA-08 | ¿A qué hora se hace cada cambio? Hay turnos de noche que marcan por el enlace público | **Edwar (2026-11-23): en la noche.** Falta la franja exacta: en producción el turno N7 entra a las 9:00 p. m. y sale a las 4:30 a. m., y los turnos de la mañana entran desde las 5:00 a. m. Propuesta: entre 11:00 p. m. y 3:00 a. m., entre semana, lejos de esas marcaciones. Vale también para **el corte de la migración** (fase 3): si el ensayo mide un corte más largo que la franja, se replantea con Coordinación | Sí (fase 1 si se hace 1b); **Sí (fase 3)** |
| PA-09 | ¿Cuánto dura cada periodo de reversa (tablas `cache`, `sessions` y `jobs` sin borrar; instalación vieja de pruebas apagada; **servidor actual encendido y sin cambios después de la migración**)? | Dos semanas sin incidentes en cada caso. Para el servidor viejo, también lo que cueste mantenerlo ese tiempo (Edwar) | No |
| PA-10 | ¿Se aprueba la forma de la migración? | **Resuelta (Edwar, 2026-11-23): EC2 con Docker para la aplicación, nginx y Redis, y RDS para MySQL** (§3). PostgreSQL queda para evaluar después de la migración (§5) | — |
| PA-11 | ¿Se migra a PostgreSQL? | **No en la mudanza.** Se evalúa después (§5), con ensayo y la lista de consultas | No |
| PA-12 | El calendario y las decisiones de Edwar del 2026-11-23 (incluida la de AWS) no están en `fuentes/` | Registrarlas como decisión (D-NN) antes de aprobar | No |
| PA-13 | ¿Cómo se entra a Grafana: Authentik, lista de direcciones, VPN o varias juntas? | Authentik **y** lista de direcciones (o VPN si las direcciones de Edwar y José cambian mucho) | Sí (fase 2) |
| PA-14 | ¿Qué logs van a Loki (o a CloudWatch) y por cuánto tiempo? ¿Quién fija el plazo? (constitución V) | La tabla del §4: enmascarado, 30 días en Loki, 14 en el servidor. El plazo lo confirma el Comité, como el de las fotos | Sí (fase 2) |
| PA-15 | El calendario de Habitante de Calle da 14 días hábiles entre dos personas hasta el 10/12 (`habitante-de-calle/plan-de-trabajo.md`). ¿Quién hace la fase 1 y de qué días sale? | **Sin respuesta (Edwar, 2026-11-23: «no sé de dónde salen»).** Con la fase 1 reducida a 1a (y 1b solo si es barato) son horas, no días. Se hace de noche y la lleva quien termine primero su tarea de DOC-25 | No |
| PA-15b | ¿El servidor de pruebas también se muda a AWS en enero? | **Sí, antes que producción**: así el ensayo de la fase 3 ocurre en el mismo lugar donde va a quedar producción y pruebas y producción vuelven a ser iguales (constitución VIII). Si no se muda, el ensayo con copia de producción se hace en AWS de todas formas | **Sí (fase 3)** |
| PA-16 | **Ley 1581 de 2012.** ¿Se pueden guardar en Estados Unidos (Ohio) los datos personales de Documenta (cédulas, nombres, turnos con ubicación, fotos de personas, casos) y los datos de salud de Habitante de Calle (DOC-26)? ¿Con qué condiciones (autorización, contrato con AWS, aviso a la Superintendencia, política de la Alcaldía)? | Edwar se lo pasa a quien lleve lo jurídico en el Comité. Su concepto, por escrito, va a `fuentes/`. Si dice que no, se cambia de región o de proveedor | **Sí (fase 3)** |
| PA-17 | ¿Redis en el servidor actual (fase 1b) o en AWS? | **Resuelta (Edwar, 2026-11-23): Redis en enero, con AWS.** La fase 1b no se hace; la fase 1 queda solo en 1a (aclarar qué `php.ini` usa el sitio) | — |
| PA-18 | ¿Ya hay cuenta de AWS a nombre de quién (Comité, Alcaldía, alguien en persona) y con qué presupuesto mensual? | Cuenta institucional, no personal, con alertas de gasto. El presupuesto lo dice Edwar; el plan compara con él lo que cuesten EC2, RDS, disco, respaldos y monitoreo, sin inventar cifras aquí | **Sí (fase 3)**; Sí (fase 2 si el monitoreo va a AWS) |
| PA-19 | ¿Fecha exacta de la migración? | Una noche entre semana de la segunda quincena de enero, después del ensayo y fuera de cierres de Nómina. La confirma Edwar con Coordinación | **Sí (fase 3)** |
| PA-20 | ¿Authentik se muda a AWS también o se queda donde está? ¿Dónde vive hoy? | **Se queda donde está** durante la migración de Documenta; si se muda, después y con su propio corte. Si Authentik corre hoy en el mismo servidor que producción, la propuesta cambia: Edwar lo dice | **Sí (fase 3)** |
| PA-21 | En AWS, ¿monitoreo con Prometheus, Grafana y Loki en un EC2 o con CloudWatch? | **Prometheus, Grafana y Loki en Docker**, la misma configuración de la fase 2, en un EC2 pequeño aparte. CloudWatch cobra por volumen de logs y obliga a cambiar lo ya armado; se usa solo para lo que AWS ya da sin costo extra si conviene | Sí (fase 3) |
| PA-22 | ¿Los archivos van al disco del EC2 o a S3? | **Disco del EC2** en la mudanza (el código funciona igual); S3 después, con su spec (C-06) | Sí (fase 3) |
| PA-23 | ¿Cuánto tiempo se guardan los respaldos de la base de datos y de los archivos, y dónde (¿también fuera de AWS?)? | Lo fija Edwar; propuesta de partida: respaldos diarios con 30 días, más uno mensual. También caen en PA-16 (dónde quedan) | Sí (fase 3) |
| PA-24 | ¿Qué arquitectura se adopta en AWS: EC2 + MySQL (esta spec) o Fargate + PostgreSQL ([`comparacion-aws-kvm8.md`](comparacion-aws-kvm8.md))? | **Resuelta (Edwar, 2026-11-23): EC2 con MySQL.** La comparación queda como referencia de costos y servicios de seguridad (WAF, GuardDuty y demás se pueden sumar a EC2 si se aprueban) | — |
## Criterios de aceptación

Los de infraestructura se prueban con una verificación en el servidor (escrita en `tasks.md` como
pasos que cualquiera puede repetir); los que dependen de la aplicación, con pruebas automáticas.

### Fase 1

| CA | Criterio | Prueba |
|---|---|---|
| CA-01 | Queda escrito en esta spec cuál forma de PHP atiende el sitio en producción y cuál `php.ini` manda; ese `php.ini` tiene `max_file_uploads = 50`, `upload_max_filesize` y `post_max_size` por encima de una tanda, y las extensiones `intl`, `xmlreader`, `zip`, `gd`, `pdo_mysql` | Por construir (verificación en el servidor) |
| CA-02 | Una carga de 300 archivos por tandas, con archivos de hasta 25 MB, entra completa en producción sin error de tamaño | Por construir (verificación en el servidor) |
| CA-03 | Si se hace 1b: en pruebas y después en producción, la caché, las sesiones y la cola usan Redis; las tablas `cache` y `sessions` dejan de crecer. Si no se hace, la spec dice por qué | Por construir (verificación en el servidor) |
| CA-04 | Si se hace 1b: Redis no responde desde fuera del servidor y, desde dentro, no responde sin contraseña | Por construir (verificación en el servidor) |
| CA-05 | Si se hace 1b: reiniciar Redis no cierra la sesión de quien ya había entrado | Por construir (verificación en el servidor) |
| CA-06 | Si se hace 1b: en producción, `turnos:cerrar-abiertas` corre a la hora, `turnos:revisar-entradas` cada 15 minutos y `derivaciones:recordar` cada minuto, y cada una deja su resultado | Por construir (verificación en el servidor) |
| CA-07 | Ninguna funcionalidad de Documenta necesita un trabajador de cola: ninguna clase de la aplicación se encola | Por construir (prueba automática `test_ca07_…`) |
| CA-08 | Si se hace 1b: reintentar una tanda no duplica archivos y un enlace de un uso no se cierra en la primera tanda con caché en Redis (las pruebas que ya existen pasan con caché en Redis en el servidor de pruebas) | Por construir (verificación en pruebas + las pruebas que ya existen) |
| CA-09 | Si se hace 1b: los usuarios recibieron por la campana, al menos el día anterior, el aviso de que tendrán que volver a iniciar sesión; y si la aplicación no llega a Redis, el despliegue falla con un mensaje claro | Por construir (verificación) |

### Fase 2

| CA | Criterio | Prueba |
|---|---|---|
| CA-10 | En pruebas, Documenta corre con `docker compose` (nginx, php-fpm con la aplicación, Redis) y la MySQL fuera de los contenedores. Iniciar sesión (formulario y Authentik), abrir documentos, marcar un turno por `/t/…`, registrar un caso por `/d/…`, abrir un enlace de carga y leer un QR funcionan igual que antes, con las mismas direcciones | Por construir (verificación en pruebas) |
| CA-11 | Pedir por su ruta un archivo de `storage/`, el `.env`, una carpeta `.git` o un `.php` distinto de `index.php` responde «No encontrado» o «Prohibido», nunca el contenido | Por construir (verificación en pruebas) |
| CA-12 | El `php.ini` va en la configuración con los valores de CA-01 y la extensión de Redis; una carga de 300 archivos por tandas entra completa; el ZIP y el Excel más grandes terminan sin que nginx ni php-fpm los corten | Por construir (verificación en pruebas) |
| CA-13 | Borrar y volver a crear todos los contenedores no pierde documentos, fotos, logs ni sesiones; el puerto de Redis no responde desde fuera del servidor | Por construir (verificación en pruebas) |
| CA-14 | Las tres tareas programadas corren a su hora **una sola vez** (cada aviso «no registró entrada» sale una vez) | Por construir (verificación en pruebas) |
| CA-15 | Un despliegue por GitHub Actions a pruebas termina bien con Docker; si la aplicación no llega a Redis, falla con un mensaje claro | Por construir (verificación en pruebas) |
| CA-16 | Grafana muestra para pruebas: sitio arriba, errores y tiempo de respuesta, memoria, procesador y disco, MySQL, Redis y la última ejecución de cada tarea programada; para producción, al menos sitio arriba y certificado | Por construir (verificación) |
| CA-17 | Apagar el sitio de pruebas, detener su cron o llenar su disco por encima del límite dispara una alerta que llega a quien diga PA-03 | Por construir (verificación en pruebas) |
| CA-18 | Grafana, Prometheus y Loki no responden desde internet sin la forma de acceso de PA-13; Prometheus y Loki no tienen ninguna puerta hacia internet | Por construir (verificación) |
| CA-19 | Lo que llega a Loki no contiene cédulas ni correos completos ni contraseñas (probado provocando un error con una cédula conocida) y se borra solo al cumplir el plazo de PA-14 | Por construir (verificación en pruebas) |
| CA-20 | El log de Laravel en el servidor queda en un archivo por día y guarda solo el plazo de PA-14 | Por construir (verificación en pruebas) |

### Fase 3

| CA | Criterio | Prueba |
|---|---|---|
| CA-21 | Antes de empezar, está en `fuentes/` el concepto jurídico sobre la transferencia internacional de datos (PA-16) y dice que se puede, con las condiciones que ponga cumplidas | Por construir (verificación documental) |
| CA-22 | Hubo al menos un ensayo completo con copia de producción en AWS: duración del corte medida, conteo de filas tabla por tabla igual, números de identificación que siguen, y número de archivos y tamaño total iguales | Por construir (informe del ensayo) |
| CA-23 | Después del corte, en AWS y con el mismo dominio: iniciar sesión (formulario y Authentik), abrir y descargar documentos (también en ZIP), marcar un turno por `/t/…`, registrar un caso por `/d/…`, abrir un enlace de carga y leer **un QR impreso de los que ya están en la calle** funcionan igual que antes | Por construir (verificación en producción) |
| CA-24 | Desde internet solo responden HTTP y HTTPS; la base de datos y Redis no responden desde fuera; SSH solo desde las direcciones acordadas | Por construir (verificación) |
| CA-25 | Después del corte, las tres tareas corren en AWS y el cron del servidor viejo está apagado: ningún aviso sale doble ni falta ningún cierre de turnos esa noche | Por construir (verificación en producción) |
| CA-26 | Se restauró un respaldo de la base de datos y de los archivos en un lugar aparte y Documenta abrió con él | Por construir (verificación) |
| CA-27 | El plan de reversa está escrito paso a paso y su primera fila (volver antes de abrir) se ensayó | Por construir (verificación en el ensayo) |
| CA-28 | Un despliegue por GitHub Actions a producción en AWS termina bien | Por construir (verificación) |
| CA-29 | El monitoreo (PA-21) vigila producción en AWS con todo lo de CA-16 y sus alertas llegan (CA-17) | Por construir (verificación) |
| CA-30 | Los usuarios recibieron por la campana, al menos el día anterior, el aviso del mantenimiento y de que tendrán que volver a iniciar sesión | Por construir (verificación) |

## Reglas que cambian

- **Reglas de negocio `RN-NN`:** ninguna. La lectura del código confirma que nada del negocio
  depende de que la caché, la sesión o la cola estén en la base de datos, ni del proveedor.
- **Constitución:** la enmienda propuesta arriba (tabla «Stack aprobado», principios V y VIII; y
  VII si después se aprueba PostgreSQL). Lo decide Edwar.

**Documentación a corregir** (al aprobarse; no se editó nada):

- **`CLAUDE.md`:** dice «servidores: Ubuntu + nginx + php8.3-fpm»; hoy producción usa Apache. Queda
  cierto después de la fase 3 (en Docker). Agregar Redis, Docker y AWS al stack; en el checklist de
  producción, el `php.ini` pasa a ir en la configuración de Docker, el cron cambia de lugar y entra
  la regla de que el monitoreo no se abre a internet.
- **`constitution.md`:** fila «Ambiente» (mismo error) y las de la enmienda.
- **`bin/deploy.sh`:** el comentario «producción (Apache + mod_php)» según lo que diga PA-04; y su
  versión para Docker (fase 2).
- **`config/repositorio.php`:** el comentario «cambiar a 's3' aquí basta» no es cierto hoy (§0).
- **`docs/INICIO.md`:** «`client_max_body_size` acorde a 50 × 25 MB» según PA-05.
- **`documentacion/specs/habitante-de-calle/plan-de-trabajo.md`:** el riesgo «Infraestructura,
  fase 1 (DOC-32)» habla de pasar producción a nginx; ya no aplica.
- **`docs/architecture/`** (fase 2 de la documentación): una ADR para cada fase aprobada.

## Contradicciones y riesgos

- **Constitución V (no la contradice, deja un vacío):** solo habla del plazo de retención de las
  fotos. No dice nada de en qué país pueden quedar los datos personales. La migración a Ohio no la
  viola en su letra, pero sí entra en la Ley 1581 que la propia constitución cita. Se propone la
  viñeta del principio V y PA-16 bloquea la fase 3. **Edwar decide.**
- **Constitución VIII («nada a `main` sin verlo en pruebas»):** entre la fase 2 y la fase 3, pruebas
  corre en Docker y producción en Apache; lo que se prueba en `develop` corre sobre algo distinto
  de producción durante unas semanas. Se mitiga copiando los límites de PHP de producción (1a) y,
  si pruebas se muda antes a AWS (PA-15b), cerrando la diferencia antes del corte.
- **Constitución, tabla «Stack aprobado», fila «Ambiente»:** dice nginx en los servidores; con este
  orden sigue siendo falsa para producción hasta enero.

## Decisiones

**Edwar, 2026-11-23 (primeras):**

- Redis para caché, sesiones y colas; primero en pruebas.
- Docker para Redis y para el monitoreo después del 10/12/2026; monitoreo con Prometheus, Grafana
  y Loki.
- Los cambios se hacen de noche (PA-08).
- Tempo solo si hace falta.
- **Rechazado:** partir Documenta en «casi microservicios». Sigue siendo una sola aplicación
  Laravel; lo que se separa es la infraestructura.
- nginx en lugar de Apache en producción. *(Con la decisión siguiente, la propuesta es que nginx
  llegue a producción con AWS y no en el servidor actual.)*

**Edwar, 2026-11-23 (después):**

- **Documenta se migra a AWS, región Ohio (`us-east-2`), probablemente en enero de 2027.**
- **Redis entra en enero, con AWS**, no en el servidor actual (PA-17). La fase 1 es solo 1a.
- **En AWS: EC2 con Docker y RDS para MySQL** (PA-10, PA-24). PostgreSQL se evalúa después de la migración.

**Propuesto y pendiente de Edwar** (no son decisiones todavía):

- Fase 1 mínima: no cambiar Apache por nginx en el servidor actual; solo aclarar el `php.ini` y,
  si es barato, Redis (PA-17).
- La configuración de Docker de la fase 2 es la que se lleva a AWS.
- EC2 con Docker para la aplicación, nginx y Redis; RDS para MySQL (PA-10).
- Mantener MySQL en la mudanza; PostgreSQL se evalúa después (PA-11).
- Archivos en el disco del EC2 en la mudanza; S3 después (PA-22).

## Candidatos para la siguiente versión

- **C-01.** Trabajador de cola vigilado (y su alerta) cuando una spec encole trabajos: por ejemplo,
  armar ZIP y Excel grandes por fuera de la petición y avisar por la campana cuando estén listos.
- **C-02.** Al cerrar los periodos de reversa (PA-09): dar de baja el servidor actual, retirar de
  `bin/deploy.sh` la línea de `apache2` y borrar las tablas `cache`, `sessions` y `jobs` con una
  migración.
- **C-03.** Monitorear también Authentik, si Documenta depende de él para entrar.
- **C-04.** Respaldos de la base de datos y de `storage/app/private` vigilados desde Grafana.
- **C-05.** Tempo (trazas) si después de la migración Grafana y Loki no alcanzan para encontrar la
  lentitud.
- **C-06.** Archivos en S3: instalar el paquete de S3, armar los ZIP sin depender de la ruta en el
  disco y mover los archivos existentes. Bucket privado: los archivos siguen sin servirse por URL
  directa (constitución V).
- **C-07.** Evaluación de PostgreSQL (§5).

## Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 0.6 | 2026-11-23 | Decisiones de Edwar: Redis en enero con AWS (PA-17, sin fase 1b); EC2 con MySQL en AWS (PA-10 y PA-24) |
| 0.5 | 2026-11-23 | Fase 3: se enlaza la comparación AWS frente a KVM 8 que trajo Edwar (`comparacion-aws-kvm8.md`), que propone Fargate y PostgreSQL, distinta de EC2 + MySQL; nueva PA-24 (cuál arquitectura se adopta) |
| 0.4 | 2026-11-23 | Decisión de Edwar: migrar a AWS (Ohio, `us-east-2`), probablemente en enero de 2027. Fases reorganizadas (propuesta): fase 1 mínima en el servidor actual (sin nginx; `php.ini` y Redis solo si es barato); fase 2, Docker y monitoreo en pruebas con la configuración que va a AWS; fase 3, migración a EC2 + RDS manteniendo MySQL. PostgreSQL pasa a después de la migración. Nuevas PA-15b y PA-16 a PA-23 (Ley 1581, cuenta y presupuesto, fecha, Authentik, monitoreo, archivos, respaldos). Enmienda a la constitución ajustada a AWS + Docker. Criterios de aceptación renumerados (aún no hay plan que los cite) |
| 0.3 | 2026-11-23 | PA-04 con el informe de producción: todo en base de datos, Redis declarado y sin usar, Apache con mod_php y proxy_fcgi a la vez; faltan cinco datos |
| 0.2 | 2026-11-23 | Respuestas de Edwar: el cambio se hace de noche (PA-08, falta la franja); los valores de los servidores los está pidiendo (PA-04); de dónde salen los días sigue sin respuesta (PA-15) |
| 0.1 | 2026-11-23 | Borrador con el calendario aprobado por Edwar y lo leído en el código |
