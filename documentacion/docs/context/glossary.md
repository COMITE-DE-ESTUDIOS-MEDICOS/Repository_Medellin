# Glosario

> Un término, un significado. Si el código usa otro nombre, va entre paréntesis.

| Término | Significado |
|---|---|
| **Dependencia** | Unidad del Comité que usa Documenta con sus propios datos (Inclusión Social; Salud Mental, inactiva). Nada se ve entre dependencias |
| **Componente** | Programa dentro de una dependencia: Calle, Básica, Estabilización. Tiene sus reglas de turno, cargos y catálogo (`componentes`) |
| **Nodo** | Equipo o zona de un componente (Nodo 1…). Puede tener centro y radio para saber si una marca quedó lejos (`nodos`) |
| **Punto crítico** | Lugar de la calle que se cubre en un turno (Parque Bolívar, Terminal del Norte…), con enlace al mapa (`puntos_criticos`) |
| **Persona de campo** | Quien trabaja en la calle y se identifica solo con su cédula; no tiene cuenta (`colaboradores`) |
| **Usuario** | Quien entra a Documenta con cuenta (`users`) |
| **Líder** | Usuario con rol Edición que gestiona turnos y derivaciones de su componente o nodo |
| **Documento** | Ficha de un archivo del repositorio; el archivo físico está en sus versiones |
| **Versión** | Cada carga física de un documento. Nunca se sobrescribe (`documento_versiones`) |
| **Inactivar** | Retirar de la vista con motivo, sin borrar. Lo inactivo se puede reactivar |
| **Custodia** | Restricción de una carpeta a un grupo: solo sus miembros la ven y la trabajan (`grupos_custodia`) |
| **Enlace de carga** | Enlace temporal para que alguien de fuera envíe documentos sin cuenta (`enlaces_carga`) |
| **Recepción** | Lo que llega por un enlace de carga, con su cadena de custodia (`recepciones`) |
| **Enlace por cédula** | Enlace o QR de un componente donde las personas de campo se identifican con la cédula. Sirve para **turnos** (`/t/…`) o **derivaciones** (`/d/…`) (`enlaces_turno`, columna `tipo`) |
| **Marcación** | Una entrada o una salida. Inmutable: corregir la anula y crea otra (`marcaciones`) |
| **Turno** (de una persona) | Una entrada con su salida |
| **Tipo de turno** | Código de la malla con horario y horas de nómina: N1, N1., T3, N7, T4, C13, AP… (`tipos_turno`, `es_turno = true`) |
| **Ausencia** | Día en que no se espera a la persona: D, VAC, I, P, PNR, F, EVENT (`ausencias`, con un tipo de turno `es_turno = false`) |
| **Malla** | La programación mensual de turnos por persona (hoy en Excel de Calle, F-08) |
| **Turno habitual** | El tipo de turno, punto crítico y días de descanso de siempre de una persona; con eso se la espera |
| **Permanencia** | Tiempo entre la entrada y la salida |
| **Descanso no remunerado** | Lo que se descuenta de la permanencia por tramos (desayuno, almuerzo) |
| **Trabajo real** (horas trabajadas) | Permanencia menos descanso. Es lo que se compara con la jornada |
| **Jornada esperada** | Trabajo neto que debería tener un turno (regla del componente `jornada_horas`) |
| **Tolerancia** | Margen en minutos antes de contar tardanza, turno corto u horas extra |
| **Novedad** (de turno) | Algo que se salió de lo esperado y se valida: tardanza, turno corto, horas extra, sin salida, sin ubicación, fuera de zona, no registró entrada (`novedades_turno`) |
| **Derivación** | Caso que una persona de campo escala (lo pide una entidad: Policía, Línea 123…) y que se debe responder en máximo 3 h. En el Excel heredado se llama «novedad» («quien escala la novedad») (`derivaciones`) |
| **Solucionada** | Derivación que un líder ya respondió; su tiempo quedó detenido |
| **Vencida** | Derivación pendiente que ya pasó la meta de 3 h |
| **Supervisión** | Visita del Apoyo al control territorial a una dupla de dinamizadores en un punto crítico, calificada de 1 a 5 (`supervisiones`) |
| **Dinamizador** | Persona de campo que atiende un punto crítico, normalmente en dupla; es a quien se supervisa |
| **EPP** | Elementos de protección personal. Al marcar entrada se responde si se recibieron |
| **Evidencia** | Foto con sello de hora y lugar, de un envío, una marcación o una derivación |
| **Sello** | Franja estampada en la foto: hora grande, fecha, dirección y coordenadas (D-01) |
| **Aviso** | Mensaje por la campana a usuarios (de versión o enviado por Administración) |
| **RN / RFD / RFN** | Recargo nocturno / festivo-dominical diurno / festivo-dominical nocturno (Nómina, F-09) |
