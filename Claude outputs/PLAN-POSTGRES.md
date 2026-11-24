# Migración a PostgreSQL — procedimiento

30 de octubre de 2026 · José Amaya

Este documento se escribe antes de ejecutar nada. Mientras no esté revisado y no
tenga resueltas sus decisiones abiertas, no se toca ninguna base de datos.

## Alcance y principios

Se cambia el motor de base de datos de MySQL a PostgreSQL, corriendo en Docker,
en los dos ambientes: pruebas (botenterprise) y producción
(comitedeestudiosmedicos). Nada más.

**Lo que este cambio NO incluye**, aunque la tentación aparezca en el camino:
modificar el esquema, limpiar datos viejos, renombrar columnas, actualizar
paquetes o aprovechar la ventana para otros despliegues. Si algo sale mal, la
única forma de saber qué lo causó es que solo haya cambiado una cosa.

Los archivos y documentos del repositorio no están en la base de datos: viven en
disco. Esta migración no los toca.

### Las reglas que no se negocian

**Se copia y se conmuta, nunca se mueve.** MySQL no se apaga ni se borra. Se
levanta PostgreSQL al lado, se copian los datos, y se cambia `DB_CONNECTION` en
el `.env`. Si algo falla, se devuelve esa línea y el sistema vuelve a estar como
estaba.

**Local primero, producción al final.** En local aparecen los problemas de
código; en pruebas, los de datos reales. Producción solo se toca cuando los dos
anteriores salieron limpios y este procedimiento ya se ejecutó completo al menos
una vez.

**Una base por ambiente, sin excepciones.** Contenedor propio, volumen propio,
contraseña propia. El riesgo real de estas migraciones no es la conversión: es
un `.env` de pruebas apuntando por descuido a la base de producción.

**Nada se borra hasta que lo nuevo lleve tiempo funcionando.** La base MySQL de
producción se conserva intacta al menos treinta días después de conmutar.

**El respaldo va fuera del servidor.** Un respaldo que vive en la misma máquina
que la base no es un respaldo.

## Cambios de código necesarios

El inventario se hizo sobre el repositorio el 30 de octubre de 2026, buscando SQL
crudo, agrupaciones y búsquedas por texto. El resultado: **el código es medio día
de trabajo**. El esfuerzo real de esta migración está en el procedimiento de
datos, no en programar.

### Lo único que rompe: las búsquedas por texto

En MySQL, `LIKE` ignora mayúsculas por configuración predeterminada. En
PostgreSQL no. Buscar «acta» dejaría de encontrar «Acta de comité», sin lanzar
ningún error: simplemente devuelve menos resultados. Es el tipo de falla que
nadie reporta el primer día y que erosiona la confianza en el sistema.

Afecta seis lugares:

| Archivo | Qué busca |
| --- | --- |
| `DocumentoController` | Nombre del documento |
| `Documento` (scope `buscar`) | Nombre, descripción, archivo y etiquetas |
| `UsuarioController` | Nombre y correo |
| `DerivacionController` | Nombre, entidad y descripción |
| `AuditoriaController` | Descripción |
| `ColaboradorController` | Nombre |

Los `LIKE` sobre `documento` y `cedula` no se tocan: son dígitos, no tienen
mayúsculas.

**La corrección no es reemplazar `like` por `ilike` en seis sitios.** Eso amarra
el código a PostgreSQL y rompe las pruebas de cualquiera que corra MySQL. Va un
único punto que elija el operador según el motor activo, y los seis lugares lo
usan.

### Un punto por verificar en vivo

En `ColaboradorController` hay un `orderByRaw('documento = ? desc')`. En MySQL esa
comparación devuelve 1 o 0; en PostgreSQL devuelve un booleano. Debería ordenar
igual, pero hay que verlo corriendo antes de darlo por bueno.

### Lo que revisamos y no es problema

Los dos `groupBy` del código (en `MarcacionController` y en `Jornadas`) son de
colección, no de consulta: agrupan en PHP sobre datos ya traídos. No generan
`GROUP BY` en SQL, así que el `GROUP BY` estricto de PostgreSQL no los alcanza.
Era el riesgo que más preocupaba, y no existe.

Los `whereDate` los traduce Laravel según el motor. Queda solo una nota de
rendimiento: en PostgreSQL, filtrar por fecha sobre una columna de fecha y hora
obliga a convertir y puede dejar de usar el índice. Con el volumen actual no se
nota; conviene revisarlo si la auditoría crece.

La subconsulta de `Marcacion` y el `LIKE ... ESCAPE` de `Documento` son SQL
estándar y funcionan igual en ambos motores.

### Con Filament dentro

Filament entró al proyecto en octubre y arma sus propias consultas. No requiere
cambios, pero sí entra en la prueba funcional de cada fase: sus tablas, filtros y
buscadores son código que ninguno de los dos escribió y que nadie ha visto correr
sobre PostgreSQL.

## Infraestructura objetivo

Ya existe un precedente en el mismo servidor: Authentik corre su PostgreSQL en
Docker. Se repite ese patrón, pero **en un contenedor aparte**. La base de
Documenta no se mete dentro del PostgreSQL de Authentik: son sistemas distintos,
con ciclos de actualización y respaldos distintos, y mezclarlos significa que
tumbar uno tumba el otro.

| Elemento | Pruebas (botenterprise) | Producción (comitedeestudiosmedicos) |
| --- | --- | --- |
| Contenedor | `documenta-pruebas-db` | `documenta-prod-db` |
| Volumen | `documenta_pruebas_data` | `documenta_prod_data` |
| Puerto publicado | `127.0.0.1:5433` | `127.0.0.1:5432` |
| Contraseña | Propia, distinta | Propia, distinta |

Los puertos distintos aplican si ambos ambientes comparten servidor. Si están en
máquinas separadas, cada uno usa el 5432.

### Decisiones de la imagen

**Versión: PostgreSQL 18**, fijada explícitamente en el `docker-compose.yml`
(`postgres:18`, nunca `postgres:latest`). La 19 está todavía en candidata a
versión final y no entra en producción. Fijar la versión evita que un
`docker compose pull` descuidado cambie el motor por debajo.

**Nunca expuesto a internet.** El puerto se publica solo en `127.0.0.1`. Laravel
sigue corriendo en PHP-FPM sobre el host y se conecta ahí.

**Credenciales en el `.env` del compose**, fuera del repositorio, igual que las de
Authentik.

**Idioma y zona horaria.** La base se crea con intercalación en español para que
el ordenamiento por nombre respete las tildes. El contenedor queda en UTC; la
aplicación ya maneja `America/Bogota` por su cuenta.

### Respaldos: la decisión que no se puede dejar para después

Un volumen de Docker borrado por error se lleva todo y no hay papelera. Antes de
conmutar producción tiene que estar funcionando un `pg_dump` automático, diario,
con copia fuera del servidor y una retención definida.

Y tiene que estar **probado restaurando**, no solo generando archivos. Un
respaldo que nadie ha restaurado nunca es una suposición, no un respaldo.

## Fase 1 — Local

Aquí no hay nada que perder y es donde aparece la mayoría de los problemas de
código. No se pasa a la fase 2 hasta que esta termine en verde.

1. Confirmar la línea base **en MySQL**: la suite completa en verde. (Hecho el 30
   de octubre: 478 pruebas, 0 fallos.) Sin esto, lo que falle después no se sabe
   si es PostgreSQL o código nuevo.
2. Habilitar `pdo_pgsql` y `pgsql` en el PHP local.
3. Levantar un PostgreSQL en Docker para desarrollo.
4. Crear la base vacía y correr `php artisan migrate` contra ella. **Las
   migraciones construyen el esquema; no se convierte el esquema de MySQL.** Así
   la base queda siendo exactamente lo que el código describe, y la próxima
   migración que escriba cualquiera seguirá funcionando.
5. Aplicar los cambios de código de la sección anterior.
6. Correr la suite completa contra PostgreSQL. Aquí aparece lo que falte.
7. Verificar a mano el `orderByRaw` de colaboradores y los seis buscadores,
   incluyendo búsquedas con mayúsculas y con tildes.
8. Recorrer Filament: tablas, filtros y buscadores.

El trabajo de código sale de aquí en su propia rama y su propio PR, sin mezclarse
con otros cambios.

## Fase 2 — Pruebas (botenterprise)

Aquí aparecen los problemas de datos reales: caracteres que MySQL aceptó y
PostgreSQL rechaza, fechas inválidas, campos que quedaron con basura hace meses.
Esta fase es también el **ensayo cronometrado** de lo que se hará en producción.

1. Respaldo completo de la base MySQL de pruebas, guardado fuera del servidor.
2. Levantar el contenedor de PostgreSQL de pruebas.
3. Crear la base vacía y correr las migraciones contra ella.
4. Copiar los datos desde MySQL. **Hay que medir cuánto tarda**: ese número es el
   que define la ventana de mantenimiento de producción.
5. Reiniciar las secuencias (ver la sección de verificación; es el paso que más se
   olvida).
6. Recorrer la lista de verificación completa.
7. Cambiar el `.env` de pruebas a PostgreSQL y recargar.
8. Usar el sistema varios días con normalidad antes de tocar producción. No basta
   con que abra: hay que crear documentos, marcar turnos, generar reportes.

MySQL de pruebas queda levantado y sin tocar durante todo esto. Si algo falla, se
devuelve el `.env` y se sigue trabajando mientras se corrige.

## Fase 3 — Producción (comitedeestudiosmedicos)

No se entra a esta fase improvisando. Se entra con la fase 2 funcionando desde
hace días, con el tiempo de copia ya medido y con la ventana acordada con las
personas que usan el sistema.

### Antes de la ventana

- Respaldo completo de MySQL, **verificado restaurándolo** en otra parte. Un
  archivo que nadie abrió no cuenta.
- Contenedor de PostgreSQL de producción levantado, con su base vacía y sus
  migraciones ya corridas.
- Respaldo automático configurado y probado.
- Acordar día y hora con las dependencias. Fuera de horario de atención, y no un
  viernes: si algo aparece al día siguiente, conviene tener gente disponible.
- Avisar a las personas de campo. Durante la ventana, los enlaces de turno no
  marcan.

### Durante la ventana

1. `php artisan down` — la aplicación queda en mantenimiento. **Desde este momento
   nadie escribe**: todo lo que alguien alcanzara a registrar durante la copia se
   perdería.
2. Respaldo final de MySQL, ya con el sistema detenido. Este es el bueno.
3. Copiar los datos a PostgreSQL.
4. Reiniciar las secuencias.
5. Recorrer la lista de verificación. **Si algo no cuadra, se detiene aquí**, antes
   de conmutar, y se levanta el sistema contra MySQL como estaba.
6. Cambiar `DB_CONNECTION` y las demás variables de base en el `.env`.
7. `php artisan config:clear` y volver a cachear.
8. `php artisan up`.

### Inmediatamente después

Recorrer a mano, con el sistema ya arriba: entrar, ver carpetas, subir un
documento, abrir uno existente, buscar por texto con mayúsculas y con tildes,
marcar una entrada y una salida de turno, revisar las pantallas de Filament y
generar un reporte.

Revisar el registro de errores durante las primeras horas. Los fallos de un cambio
de motor rara vez aparecen al abrir: aparecen cuando alguien ejecuta la consulta
rara que nadie probó.

### En los días siguientes

MySQL queda levantado e intacto treinta días. No se borra ni se apaga aunque todo
funcione: es la única forma de recuperar un dato que se descubra mal copiado dos
semanas después.

## Verificación antes de conmutar

Esta lista se recorre completa en cada fase, **antes** de cambiar el `.env`. Si un
punto no cuadra, no se conmuta.

### Las secuencias: el error número uno

En PostgreSQL el autoincremento vive en un objeto aparte, la secuencia. Al copiar
datos con sus identificadores, la secuencia **no se entera** y sigue apuntando al
1.

El síntoma es cruel: la migración parece perfecta, todos los datos están, todo se
ve bien… y el primer usuario que intente crear algo recibe un error de clave
duplicada. Si eso pasa en producción, pasa con gente usando el sistema.

Después de copiar, hay que reiniciar la secuencia de cada tabla a su identificador
máximo. Y hay que **comprobarlo**, no asumirlo: crear un registro de prueba en una
tabla cualquiera y confirmar que recibe el identificador siguiente, no el 1.

### Conteo tabla por tabla

Contar las filas de cada tabla en MySQL y en PostgreSQL, y compararlas. Tienen que
coincidir todas, no «casi todas». Una diferencia de una sola fila significa que
algo se rechazó en silencio y hay que averiguar qué antes de seguir.

Las tablas que más importan, por ser las que no se pueden reconstruir:
`documentos`, `documento_versiones`, `marcaciones`, `recepciones`, `auditorias`,
`colaboradores` y `users`.

### Revisiones puntuales

| Qué revisar | Por qué |
| --- | --- |
| Booleanos (`activo`, `verificado`, `fuera_de_zona`) | Pasan de 0/1 a verdadero/falso; una conversión mal hecha deja todo en falso y «desaparecen» registros de las listas |
| El JSON de `componentes.config` | Que se lea y conserve sus valores |
| Fechas mínima y máxima de `marcaciones` y `auditorias` | Que no se hayan corrido por zona horaria |
| Tildes y eñes en nombres y descripciones | Que no lleguen como caracteres rotos |
| Relaciones | Que un documento siga mostrando su carpeta, su tipo y su creador |

### Prueba funcional

Con la aplicación apuntando ya a PostgreSQL, pero antes de dar la migración por
buena: entrar, navegar carpetas, subir un documento, buscar por texto con
mayúsculas y con tildes, marcar entrada y salida de turno, abrir las pantallas de
Filament y generar un reporte.

Y correr la suite completa contra esa base. Las 478 pruebas son el control más
barato que existe aquí.

## Reversión

La reversión es simple **mientras MySQL siga intacto**. Por eso no se apaga ni se
borra.

### Si algo falla durante la ventana, antes de conmutar

No se conmuta. Se deja el `.env` como está, se corre `php artisan up`, y el sistema
vuelve a trabajar sobre MySQL como si nada hubiera pasado. PostgreSQL queda ahí,
con sus datos a medias, para revisarlo con calma. **Este es el escenario normal de
un intento fallido**, y no cuesta nada.

### Si algo falla después de conmutar, el mismo día

Se devuelven las variables de base del `.env` a MySQL, se limpia la caché de
configuración y se levanta. Se pierde únicamente lo que se haya escrito en
PostgreSQL desde la conmutación, que si se actúa rápido son minutos.

Por eso conviene conmutar al final de la jornada y no al principio: hay menos
escritura en juego.

### Si algo aparece días después

Aquí ya no se revierte: para entonces hay trabajo real en PostgreSQL que no está
en MySQL, y volver atrás significaría perderlo. Se corrige hacia adelante, usando
el MySQL conservado como fuente para recuperar lo que haya quedado mal.

Ese es exactamente el motivo de la regla de los treinta días.

## Decisiones abiertas

Estas hay que resolverlas antes de empezar la fase 2. No son técnicas: son de
operación y de responsabilidad.

1. **¿Por qué se migra?** Nadie lo ha escrito. Si es por costo, por rendimiento,
   por estándar institucional o por requisito de alguien, la razón cambia qué se
   prioriza y qué se puede negociar. Conviene anotarla aquí.
2. **¿Quién ejecuta la fase 3 y quién acompaña?** La migración de producción no la
   hace una sola persona sola a las diez de la noche.
3. **¿Qué día y a qué hora?** Depende del tiempo de copia que se mida en la fase 2.
4. **¿Quién avisa a las dependencias y a las personas de campo?** Durante la
   ventana no se puede marcar turno.
5. **¿Dónde se guardan los respaldos fuera del servidor y quién tiene acceso?**
6. **¿Se migra también la base de Authentik?** El plan dice que no: Authentik ya
   corre sobre su propio PostgreSQL y es un sistema aparte. Conviene dejarlo dicho
   para que nadie lo mezcle.
7. **¿Qué pasa con el XAMPP de los equipos de desarrollo?** Si todo el equipo pasa
   a PostgreSQL en local, hay que acordarlo; si cada quien usa lo que quiera, el
   código tiene que seguir funcionando en los dos motores, que es justo lo que
   exige la corrección de los buscadores.

### Estimación

El código es medio día. La fase 1 completa, con pruebas, un día. La fase 2, un día
más varios de uso real antes de seguir. La fase 3 es una ventana de un par de
horas, pero solo después de todo lo anterior.

No es un trabajo grande. Es un trabajo que se daña cuando se hace con prisa.
