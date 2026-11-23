# Comparación: AWS propuesto frente al servidor KVM 8 actual

| | |
|---|---|
| Para | DOC-32 · Infraestructura, fase 3 (migración a AWS, posible enero de 2027, región Ohio) |
| Fuente | Comparación entregada por Edwar el 2026-11-23, transcrita sin cambiar las cifras |
| Estado | **Referencia.** Edwar decidió el 2026-11-23 EC2 con MySQL (DOC-32, PA-24), no Fargate con PostgreSQL. Sirve para comparar costos y los servicios de seguridad que se podrían sumar |

## La comparación, tal como llegó

> «Comparación de capacidad, arquitectura y coste. No son servicios equivalentes uno a uno.»

| | AWS propuesto (arquitectura distribuida) | KVM 8 actual (servidor único) |
|---|---|---|
| Coste mensual | 630.97 USD | 16,890,000 COP aprox. |
| Coste por periodo | 7,571.64 USD por 12 meses | 405,360,000 COP por 24 meses |
| Cómputo de aplicaciones | 2 tareas Fargate ARM | 8 vCPU en una VM |
| Memoria declarada | 8 GB en total (Fargate) | 32 GB RAM |
| Base de datos | PostgreSQL en RDS, db.m6g.large, Multi-AZ | No administrada como RDS; depende de la configuración propia |
| Almacenamiento principal | 400 GB gp3 + 150 GB de respaldos | 400 GB de disco; uso actual aprox. 151 GB |
| Alta disponibilidad | Multi-AZ para RDS, balanceador (ALB) y 2 NAT gateways | Una sola VM, sin redundancia equivalente |
| Seguridad administrada | WAF, GuardDuty, Security Hub, Inspector, KMS, Secrets Manager | Cortafuegos del VPS y administración propia |

## Notas para leerla (Claude, 2026-11-23)

### 1. Hay que confirmar las cifras del KVM 8 antes de comparar costos

- **El costo mensual del KVM 8 no cuadra.** 16,890,000 COP al mes son unos 4.000 USD, más de seis veces lo propuesto en AWS y muy por encima de lo que cuesta un VPS de 8 vCPU y 32 GB. 405,360,000 COP es exactamente 16,890,000 × 24, así que el error, si lo hay, viene de la cifra mensual. Puede ser un problema de separadores (por ejemplo, 168.900 COP al mes daría 4.053.600 COP en 24 meses). Hay que tomarlo de la factura real.
- **Los periodos no son iguales:** AWS está a 12 meses y el KVM 8 a 24. Para comparar, hay que llevar los dos al mismo periodo y a la misma moneda, con la tasa de cambio del día.

### 2. No es el mismo servicio

- **AWS da redundancia que hoy no hay:** base de datos en dos zonas, balanceador, respaldos administrados y herramientas de seguridad. Si la caída de un servidor es un riesgo que el Comité no acepta, ahí está buena parte del costo.
- **El KVM 8 da más capacidad bruta:** 32 GB de RAM frente a 8 GB en Fargate. Hoy usa unos 151 GB de 400 de disco. No hay mediciones de cuánta memoria y CPU consume Documenta realmente, así que no se sabe si 8 GB alcanzan. Eso se mide antes de comprar (es parte de la fase 2 de DOC-32: el monitoreo).

### 3. La propuesta de AWS no coincide con lo planeado en DOC-32

| Tema | Propuesta AWS | DOC-32 (propuesta, por aprobar) |
|---|---|---|
| Base de datos | **PostgreSQL** en RDS | **MySQL** en RDS para la mudanza; PostgreSQL se evalúa después. Cambiar de motor y de proveedor la misma noche suma riesgo. Hay consultas que fallarían en PostgreSQL y búsquedas que dejarían de ignorar tildes («jose» no encontraría «José») |
| Aplicación | **Fargate** (contenedores administrados, sin servidor propio) | EC2 con Docker. Fargate también sirve, pero el cron y el disco de archivos se resuelven distinto: el cron pasa a tareas programadas de AWS y los archivos a S3 o a EFS |
| Monitoreo | No aparece | Prometheus + Grafana + Loki, o CloudWatch, que **no está en el costo** y cobra por volumen de registros |
| Authentik | No aparece | Hay que decidir si se muda y dónde corre |

Si se compra la propuesta tal cual, DOC-32 se ajusta a Fargate y PostgreSQL, y la migración de motor entra en el plan de enero con su ensayo.

### 4. Lo que falta para decidir

1. La cifra real del KVM 8 (factura).
2. Qué incluye exactamente el costo de AWS: transferencia de datos, CloudWatch, el servidor de pruebas y Authentik.
3. Medición del uso real de memoria y CPU de Documenta.
4. **La revisión de la Ley 1581.** Ohio está en Estados Unidos: guardar ahí cédulas y, con Habitante de Calle, datos de salud es una transferencia internacional de datos personales. La debe revisar quien lleve lo jurídico en el Comité **antes de comprar**.
5. Si AWS se compra, qué se hace con el KVM 8 (cancelarlo, dejarlo como servidor de pruebas o como respaldo).
