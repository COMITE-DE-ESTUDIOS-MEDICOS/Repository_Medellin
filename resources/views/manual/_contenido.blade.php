@php
    $mb = round(config('repositorio.tamano_maximo_kb') / 1024);
    $tope = config('repositorio.carga.maximo_total');

    // Quién lo usa: Lectura, Edición, Coordinación, Administración.
    $roles = fn (string ...$si) => collect(['Lectura', 'Edición', 'Coordinación', 'Administración'])
        ->map(fn ($r) => '<span class="rol'.(in_array($r, $si, true) ? ' si' : '').'">'.$r.'</span>')->join('');
    $hay = '<span class="si-marca">●</span>';
    $no = '<span class="no-marca">—</span>';

    $capitulos = [
        'bienvenida' => 'Bienvenida',
        'ingreso' => 'Ingreso y primeros pasos',
        'roles' => 'Roles y permisos',
        'documentos' => 'Documentos y carpetas',
        'enlaces-carga' => 'Enlaces de carga',
        'turnos-enlaces' => 'Turnos: enlaces y marcación',
        'personas' => 'Personas de campo, turnos y ausencias',
        'seguimiento' => 'Seguimiento: en turno, novedades y reportes',
        'derivaciones' => 'Atención territorial: derivaciones',
        'administracion' => 'Administración',
        'notificaciones' => 'Notificaciones y avisos',
        'preguntas' => 'Preguntas frecuentes',
    ];
    $n = 0;
    $cap = function (string $id) use (&$n, $capitulos) {
        $n++;
        return '<div class="capitulo-encabezado"><span class="capitulo-numero">'.str_pad((string) $n, 2, '0', STR_PAD_LEFT).'</span><h2>'.e($capitulos[$id]).'</h2></div>';
    };
@endphp

<section class="capitulo" aria-label="Índice">
    <h2 style="margin-bottom:14px">Contenido</h2>
    <ol class="indice">
        @foreach($capitulos as $id => $titulo)
            <li><span class="n">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><a href="#{{ $id }}">{{ $titulo }}</a></li>
        @endforeach
    </ol>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="bienvenida">
    {!! $cap('bienvenida') !!}
    <p class="entrada">Documenta es la plataforma del Comité de Estudios Médicos para el programa de Inclusión Social: guarda los documentos de forma ordenada y segura, recibe evidencias de campo y lleva el registro de los turnos del personal.</p>

    <h3>Qué puedes hacer en Documenta</h3>
    <ul>
        <li><strong>Documentos:</strong> organizar archivos en carpetas, buscarlos, verlos, descargarlos, copiarlos y moverlos.</li>
        <li><strong>Enlaces de carga:</strong> pedir a personas externas que envíen archivos y fotos a una carpeta, sin que necesiten cuenta.</li>
        <li><strong>Turnos:</strong> el personal de campo marca entrada y salida con un QR y su cédula; el sistema reconoce el turno, avisa las ausencias y genera el reporte para nómina.</li>
        <li><strong>Atención territorial:</strong> crear casos (DC-001), atenderlos desde el enlace con evidencias y medir el tiempo de atención.</li>
        <li><strong>Administración:</strong> usuarios, estructura, custodia de carpetas y auditoría de todo lo que pasa.</li>
    </ul>

    <div class="nota">
        <span class="nota-titulo">Cómo leer este manual</span>
        Cada capítulo indica qué roles usan esa parte. Lo que ves en el menú depende de tu rol: si algo de este manual no te aparece, es porque tu rol no lo necesita.
    </div>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="ingreso">
    {!! $cap('ingreso') !!}
    <div class="roles">{!! $roles('Lectura', 'Edición', 'Coordinación', 'Administración') !!}</div>

    <h3>Ingresar</h3>
    <ol class="pasos">
        <li>Abre Documenta en el navegador y toca <strong>Ingresar</strong>.</li>
        <li>Te lleva a la página de acceso del Comité (Authentik). Escribe tu <strong>número de documento</strong> como usuario y tu contraseña.</li>
        <li>Al entrar llegas a <strong>Documentos</strong>.</li>
    </ol>
    <div class="nota">
        <span class="nota-titulo">¿No puedes entrar?</span>
        Las cuentas las crea Coordinación o Administración. Si dice que tu usuario no está registrado o que tu cuenta está desactivada, comunícate con ellos.
    </div>

    <h3>Mi perfil</h3>
    <p>En el menú de tu cuenta → <strong>Mi perfil</strong>: tu nombre y cargo, la apariencia (claro, oscuro o la del sistema, que te acompaña en cualquier equipo) y el cambio de contraseña. El documento y el rol los cambia quien administra los usuarios.</p>

    <h3>La pantalla</h3>
    <ul>
        <li><strong>Menú lateral:</strong> los módulos agrupados (Turnos, Atención territorial, Administración). Cada grupo se pliega y el menú recuerda cómo lo dejaste. En el celular se abre con el botón <span class="tecla">☰</span>.</li>
        <li><strong>Dependencia:</strong> arriba del menú. Si trabajas en varias, cámbiala ahí; todo lo que ves es de la dependencia elegida.</li>
        <li><strong>Barra superior:</strong> la campana de notificaciones, el cambio de tema claro u oscuro y tu cuenta (perfil y salir).</li>
        <li><strong>Versión:</strong> al pie del menú; las novedades de cada versión llegan por la campana.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="roles">
    {!! $cap('roles') !!}
    <p class="entrada">Cada persona tiene un rol en cada dependencia. Cada rol incluye lo del anterior.</p>

    <table>
        <thead><tr><th>Puede…</th><th class="centro">Lectura</th><th class="centro">Edición</th><th class="centro">Coordinación</th><th class="centro">Administración</th></tr></thead>
        <tbody>
            <tr><td>Ver, buscar y descargar documentos; comentar archivos</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td></tr>
            <tr><td>Subir y editar documentos, crear carpetas, copiar y mover</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td></tr>
            <tr><td>Inactivar documentos y carpetas (con motivo)</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td></tr>
            <tr><td>Enlaces de carga; turnos: enlaces, personas, novedades, reportes, tipos de turno y puntos críticos; derivaciones</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td></tr>
            <tr><td>Ver versiones anteriores de un archivo; reactivar carpetas</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td></tr>
            <tr><td>Usuarios, tipos de documento, grupos de custodia, componentes y nodos, auditoría</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td></tr>
            <tr><td>Eliminar derivaciones</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $hay !!}</td><td class="centro">{!! $hay !!}</td></tr>
            <tr><td>Ver lo inactivo y reactivar documentos; enviar avisos a todos</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $no !!}</td><td class="centro">{!! $hay !!}</td></tr>
        </tbody>
    </table>
    <div class="nota">
        <span class="nota-titulo">Carpetas bajo custodia</span>
        Una carpeta puede quedar a cargo de un grupo (por ejemplo, los líderes de un nodo). Entonces solo ese grupo la ve y la trabaja, aunque otros tengan el mismo rol. Ver el capítulo de Administración.
    </div>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="documentos">
    {!! $cap('documentos') !!}
    <div class="roles">{!! $roles('Lectura', 'Edición', 'Coordinación', 'Administración') !!}</div>
    <p class="entrada">El explorador funciona como las carpetas de tu computador: entras a una carpeta, ves sus subcarpetas y sus archivos, y la ruta arriba te dice dónde estás.</p>

    <h3>Buscar</h3>
    <p>Escribe en el buscador del explorador: encuentra por nombre, descripción y etiquetas en todas las carpetas que puedes ver. Puedes filtrar por tipo de documento y por fechas.</p>

    <h3>Subir documentos <span class="rol si">Edición</span></h3>
    <ol class="pasos">
        <li>Entra a la carpeta y toca <strong>Subir documento</strong>.</li>
        <li>Arrastra los archivos o búscalos en tu equipo. Se aceptan PDF, imágenes y hojas de cálculo, de hasta <strong>{{ $mb }} MB</strong> cada uno.</li>
        <li>Cada archivo queda con una tarjeta donde puedes escribir su <strong>nombre</strong> (si no, toma el del archivo).</li>
        <li>Toca <strong>Guardar</strong>. Puedes subir hasta <strong>{{ $tope }} archivos</strong> de una vez: se envían solos por partes, con una barra de progreso.</li>
    </ol>
    <div class="nota importante">
        <span class="nota-titulo">Si se corta la conexión</span>
        Vuelve a intentar la misma carga: lo que ya había llegado no se duplica. Si el servidor recibe menos archivos de los enviados, no guarda nada y te lo dice.
    </div>

    <h3>La ficha de un archivo</h3>
    <ul>
        <li><strong>Vista previa</strong> de PDF e imágenes, sin descargar.</li>
        <li><strong>Pasar al siguiente:</strong> arriba a la derecha aparece <span class="tecla">‹ 3 de 25 ›</span>. También con las teclas <span class="tecla">←</span> y <span class="tecla">→</span>.</li>
        <li><strong>Comentarios:</strong> escribe, por ejemplo, «la foto está desenfocada». A quien lo subió le llega por la campana, con un enlace directo.</li>
        <li><strong>Reemplazar archivo</strong> <span class="rol si">Edición</span>: sube la versión corregida. La anterior no se borra; Coordinación la puede consultar.</li>
        <li><strong>Copiar a</strong> otra carpeta <span class="rol si">Edición</span>: crea una copia independiente.</li>
    </ul>

    <h3>Copiar o mover varios a la vez <span class="rol si">Edición</span></h3>
    <ol class="pasos">
        <li>Marca los documentos con su casilla. Para una carpeta, usa su menú <span class="tecla">⋮</span> → <strong>Seleccionar</strong>.</li>
        <li>Abajo aparece una barra con la cantidad marcada. Elige la carpeta de destino: se ve la <strong>ruta completa</strong> y hay un buscador para encontrarla rápido.</li>
        <li>Toca <strong>Copiar aquí</strong> o <strong>Mover aquí</strong>.</li>
    </ol>

    <h3>Carpetas</h3>
    <ul>
        <li><strong>Nueva carpeta</strong> <span class="rol si">Edición</span>: dentro de la carpeta actual o en la raíz.</li>
        <li><strong>Editar</strong> (menú <span class="tecla">⋮</span> de la carpeta): cambiar el nombre, moverla dentro de otra o copiarla con todo su contenido.</li>
        <li><strong>Descargar carpeta:</strong> la carpeta entera en un ZIP, con sus subcarpetas. Los documentos y las carpetas inactivados no se incluyen.</li>
    </ul>

    <h3>Inactivar <span class="rol si">Edición</span></h3>
    <p>Inactivar <strong>no borra</strong>: oculta el documento o la carpeta y queda en la auditoría con el motivo. Para un documento, el motivo es obligatorio. Reactivar documentos es de Administración; reactivar carpetas, de Coordinación.</p>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="enlaces-carga">
    {!! $cap('enlaces-carga') !!}
    <div class="roles">{!! $roles('Edición', 'Coordinación', 'Administración') !!}</div>
    <p class="entrada">Un enlace de carga permite que alguien sin cuenta envíe archivos o fotos directo a una carpeta. Sirve para evidencias de campo, actas o soportes de terceros.</p>

    <ol class="pasos">
        <li>Ve a <span class="ruta">Enlaces de carga</span> → <strong>Nuevo enlace</strong> (o desde la carpeta, con el botón de enlace de carga).</li>
        <li>Elige la <strong>carpeta de destino</strong> y, si es para el personal de un componente, el <strong>componente</strong>: así basta con la cédula de la persona.</li>
        <li>Opcional: fecha de vencimiento y número máximo de envíos.</li>
        <li>Comparte el <strong>link</strong> por WhatsApp o el <strong>QR</strong>.</li>
    </ol>

    <h3>Lo que ve quien envía</h3>
    <ul>
        <li>Un formulario sencillo en el celular: su identificación o sus datos, y los archivos.</li>
        <li>Si el enlace es de un componente (por cédula), la persona tiene que estar <strong>en turno</strong>: con su entrada marcada. Si no, ve «Para enviar evidencias tienes que estar en turno: marca tu entrada primero.». Las personas nuevas se registran marcando su primera entrada.</li>
        <li><strong>Tomar foto</strong> abre la cámara: la foto lleva un <strong>sello</strong> con la hora en grande, la fecha, la dirección, las coordenadas y el tipo (Evidencia, Turno, Derivación, Apoyo Territorial). <strong>Elegir archivo</strong> sube desde la galería, sin sello (en los enlaces de turnos y de supervisión no existe: solo cámara).</li>
        <li>Al terminar recibe una confirmación con lo que envió. A quien creó el enlace le llega un aviso por la campana.</li>
    </ul>
    <div class="nota">
        <span class="nota-titulo">El sello necesita ubicación</span>
        Para que la foto salga con la dirección, el celular debe permitir la ubicación y tener internet. Sin ubicación, la foto sale igual, con «Ubicación no disponible».
    </div>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="turnos-enlaces">
    {!! $cap('turnos-enlaces') !!}
    <div class="roles">{!! $roles('Edición', 'Coordinación', 'Administración') !!}</div>
    <p class="entrada">El personal de campo no tiene cuenta en Documenta: marca su turno con un QR o un link y su número de cédula.</p>

    <h3>Crear el enlace o QR</h3>
    <ol class="pasos">
        <li>Ve a <span class="ruta">Turnos → Enlaces por cédula</span> → <strong>Nuevo enlace</strong>.</li>
        <li>Elige para qué es: <strong>Turnos</strong> (entrada y salida) o <strong>Derivaciones</strong>.</li>
        <li>Elige el <strong>componente</strong>. Opcional: un <strong>nodo</strong> (para pegar el QR en ese lugar o vehículo) o un <strong>punto crítico</strong> (quien marque con ese QR queda en ese punto ese día).</li>
        <li>Copia el link o toca <strong>Imprimir QR</strong>.</li>
    </ol>

    <h3>Cómo marca la persona</h3>
    <ol class="pasos">
        <li>Escanea el QR con la cámara del celular.</li>
        <li>Escribe su cédula. Si el componente lo permite, la primera vez llena sus datos; después, solo la cédula.</li>
        <li>Al entrar, responde si <strong>recibió los elementos de protección personal (EPP)</strong>: Sí o No. Un «No» llega a Novedades como <strong>Sin EPP</strong>.</li>
        <li>Toma la foto con <strong>Tomar foto</strong> (solo con la cámara, no desde la galería) y toca <strong>Registrar marca</strong>. El sistema decide si es <strong>entrada</strong> o <strong>salida</strong>.</li>
    </ol>
    <p><strong>Si el teléfono da una ubicación aproximada</strong> (frecuente en iPhone con «Ubicación exacta» apagada), el enlace lo avisa y dice cómo activarla; se puede registrar de todos modos y queda la novedad <strong>Ubicación imprecisa</strong> en lugar de un «fuera de zona» falso. En iPhone: <span class="ruta">Ajustes → Privacidad y seguridad → Localización → Sitios web de Safari → «Al usar la app» y «Ubicación exacta»</span>.</p>
    <ul>
        <li>Al marcar la entrada, el sistema <strong>reconoce el turno por la hora</strong> (por ejemplo, 4:55 → N1; 20:50 → N7; domingo o festivo 6:05 → C13) y anota si llegó tarde. <strong>Con la salida lo confirma</strong>: el viernes, entrar a las 6:00 y salir a las 10:05 es T4; salir a las 12:35 es N1 con tardanza. Si la salida cambia el turno, la tardanza se corrige sola.</li>
        <li>Cuando completa su turno del día, el enlace ya no le deja volver a marcar.</li>
        <li>Si vuelve a marcar antes de 30 minutos de su última marca (porque creyó que no se registró), no cuenta: ve «Tu marca ya quedó registrada». Si de verdad se fue antes, Coordinación agrega la salida. Los 30 minutos se cambian en Componentes y nodos («Salida mínima»).</li>
        <li>Si olvida la salida, el turno se cierra solo al <strong>terminar su turno más la tolerancia de horas extra</strong> (60 min: un N1 a la 1:30 p. m., un C13 a las 7:00 p. m.). Los cargos con un máximo propio (los conductores, 15 h) se cierran a esas horas de la entrada. Queda la novedad «Sin salida» y en el reporte dice <strong>Cierre automático · por corregir</strong> hasta que Coordinación lo revise. Si vuelve a marcar al día siguiente, esa marca es su <strong>entrada</strong>, nunca la salida de ayer.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="personas">
    {!! $cap('personas') !!}
    <div class="roles">{!! $roles('Edición', 'Coordinación', 'Administración') !!}</div>

    <h3>Personas de campo</h3>
    <p>En <span class="ruta">Turnos → Personas de campo</span> está todo el personal, con buscador por nombre o cédula. Quienes se registraron solas por el enlace aparecen como <strong>pendientes de verificar</strong>: revisa sus datos y verifícalas.</p>
    <p><strong>Muchas a la vez:</strong> <strong>Descargar Excel de carga</strong> trae la plantilla (con una hoja de valores válidos y otra de instrucciones). Se llena la hoja «Personas» desde la fila 2 y se sube con <strong>Carga masiva</strong>. Es todo o nada: si una fila tiene un error, no se carga ninguna y se dice qué fila corregir. Solo se lee la primera hoja.</p>

    <h3>Lo habitual de cada persona</h3>
    <p>Al <strong>editar</strong> a una persona, además de componente, nodo y cargo:</p>
    <ul>
        <li><strong>Turno habitual</strong> (N1, N1., T3, N7…): con esto el sistema sabe a qué hora esperarla cada día. Sin turno habitual, no se la espera.</li>
        <li><strong>Punto crítico habitual:</strong> la zona donde suele estar.</li>
        <li><strong>Días de descanso:</strong> esos días no se la espera.</li>
    </ul>

    <h3>Ausencias: descanso, vacaciones, incapacidad, permiso</h3>
    <p>En la ficha de la persona, tarjeta <strong>Turno y ausencias</strong> → <strong>Registrar ausencia</strong>: elige el código (D, VAC, I, P, PNR…) y las fechas desde y hasta. Esos días no se la espera y no sale como falta.</p>

    <h3>Los turnos en la ficha</h3>
    <p>La ficha de la persona muestra sus turnos <strong>por quincena</strong> (1–15 y 16–fin de mes), con flechas ‹ › para ir a otras. Arriba, el resumen: turnos, horas trabajadas, novedades pendientes y correcciones. Cada fila es un turno: <em>entrada → salida</em>, las horas trabajadas (sin el descanso), el nodo, el EPP y sus novedades (⚠ pendiente, ✓ aprobada, ✕ rechazada). Una salida del día siguiente dice «(+1)»; de más días, su fecha. «en turno» es que aún no ha salido; «sin salida», que le faltó. Una marca <strong>sin pareja</strong> aparece en amarillo arriba: casi siempre sobra o es del otro tipo. Para corregir, toca la hora. Lo corregido o anulado no ocupa filas: está en <strong>Historial de correcciones de la quincena</strong>, con quién y el motivo.</p>

    <h3>Marcas que faltaron o con error</h3>
    <ul>
        <li><strong>Agregar una marca que faltó:</strong> en la ficha, con la hora real y el motivo.</li>
        <li><strong>Corregir hora:</strong> toca la hora de la marca. La original no se borra: queda anulada en el historial y la nueva la reemplaza.</li>
        <li><strong>Cambiar a entrada / salida</strong> y <strong>Anular marca</strong> (Coordinación y Administración, dentro de «Corregir hora», con motivo): para un turno invertido o una marca de más. Nada se borra: la marca queda tachada como «Anulada» con tu nombre y el motivo, y deja de contar en el turno y el reporte.</li>
    </ul>

    <h3>Tipos de turno y puntos críticos</h3>
    <ul>
        <li><strong>Tipos de turno</strong> (botón en Personas de campo): los códigos de la malla con su horario, sus días y las horas que cuenta nómina. Los de descanso y ausencia (D, I, VAC…) también están aquí.</li>
        <li><strong>Puntos críticos</strong> (botón en En turno ahora): las zonas de trabajo. Pega sus <strong>coordenadas</strong> como las copia Google Maps (por ejemplo <code>6.2518, -75.5636</code>) y aparece <strong>Ver en el mapa</strong>.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="seguimiento">
    {!! $cap('seguimiento') !!}
    <div class="roles">{!! $roles('Edición', 'Coordinación', 'Administración') !!}</div>

    <h3>En turno ahora</h3>
    <p>Quién marcó entrada y aún no su salida: el turno reconocido, si llegó tarde, su punto crítico (con enlace al mapa) y cuánto lleva. Se puede filtrar por componente y nodo.</p>

    <h3>Novedades</h3>
    <p>Lo que se sale de lo esperado y debe revisarse. Llegan por la campana.</p>
    <p><strong>Quién aprueba:</strong> Coordinación y Administración, y los usuarios de Edición que tengan marcado <strong>Aprueba novedades de turno</strong> (se marca persona por persona en <span class="ruta">Administración → Usuarios → Editar</span>). Los demás líderes las ven, pero no las aprueban. Con <strong>Descargar Excel</strong> salen las del filtro de la pantalla.</p>
    <table>
        <thead><tr><th>Novedad</th><th>Cuándo sale</th><th>Qué hacer</th></tr></thead>
        <tbody>
            <tr><td><strong>No registró entrada</strong></td><td>Tenía turno y 30 minutos después del inicio no ha marcado.</td><td>Llamar a la persona. Si estaba en D, VAC, I…, <strong>Justificar</strong>. Si sí vino, agregar la entrada. Si marca después, el aviso se cierra solo.</td></tr>
            <tr><td><strong>Tardanza</strong></td><td>Entró después de la hora de su turno, pasada la tolerancia.</td><td>Aprobar o rechazar con comentario.</td></tr>
            <tr><td><strong>Horas extra / Turno corto</strong></td><td>Trabajó más o menos que la jornada de <strong>su turno</strong> (T4: 4 h; N1: 7 h, descontado el descanso). Salir hasta 60 min después (la tolerancia de horas extra) no es hora extra.</td><td>Aprobar o rechazar; o corregir la hora si fue un error.</td></tr>
            <tr><td><strong>Sin salida</strong></td><td>No marcó salida y el turno se cerró solo.</td><td>Corregir la hora de salida real.</td></tr>
            <tr><td><strong>Sin ubicación / Fuera de zona</strong></td><td>Marcó sin GPS o lejos de su nodo.</td><td>Revisar y decidir.</td></tr>
            <tr><td><strong>Ubicación imprecisa</strong></td><td>El teléfono dio una ubicación aproximada (iPhone con «Ubicación exacta» apagada).</td><td>Pedirle que la active; aprobar si estaba en su punto.</td></tr>
            <tr><td><strong>Sin EPP</strong></td><td>Al entrar respondió que no recibió los elementos de protección personal.</td><td>Entregarlos y aprobar o rechazar con comentario.</td></tr>
        </tbody>
    </table>

    <h3>Marcaciones y reporte</h3>
    <ol class="pasos">
        <li>Ve a <span class="ruta">Turnos → Marcaciones y reporte</span> y filtra por fechas, componente, nodo o cédula.</li>
        <li>Toca <strong>Descargar reporte (Excel)</strong>. Se lleva los filtros tal como están en pantalla.</li>
    </ol>
    <ul>
        <li><strong>Pestaña Turnos:</strong> un turno por fila: entrada, salida, tiempo en el sitio, descanso, horas trabajadas, ordinarias, extra y si se aprobaron. La columna <strong>Turno</strong> dice qué turno de la malla es (N1, N7…) según la entrada y la salida, y la <strong>Jornada</strong> es la de ese turno. Si ninguno encaja con la hora, dice <strong>Sin reconocer</strong> en rojo: revisa esa entrada.</li>
        <li><strong>Pestaña Resumen mensual:</strong> una fila por persona: días trabajados, horas, extras, y las horas de <strong>cada semana</strong>, en rojo si pasan de 42 h. También aparece quien no marcó en todo el período. Si filtras por nodo, cada persona sale con todos sus turnos del período, aunque haya cubierto en otro nodo.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="derivaciones">
    {!! $cap('derivaciones') !!}
    <div class="roles">{!! $roles('Edición', 'Coordinación', 'Administración') !!}</div>
    <p class="entrada">La oficina crea el caso con su código (DC-001) y la persona de campo lo atiende desde su enlace. El tiempo corre solo: desde que se crea hasta que se envía la atención. Meta: 3 horas.</p>

    <h3>1. Crear el caso (oficina)</h3>
    <ol class="pasos">
        <li><span class="ruta">Atención territorial → Derivaciones → Nuevo caso</span> (funciona bien desde el celular).</li>
        <li>Escribe el <strong>mensaje para la atención</strong>, elige <strong>quién lo envía</strong> (Alcaldía, Autoridades locales del sector u Otra, y cuál), la <strong>ubicación</strong> y sube el <strong>soporte</strong>: una foto con la cámara, o una imagen o un PDF.</li>
        <li><strong>Crear caso.</strong> Aparece el código en grande: díctaselo a la persona de campo. Desde ese momento corre el tiempo.</li>
    </ol>
    <p>Si ya hay un caso abierto con la misma ubicación y el mismo «quién lo envía» de los últimos 30 minutos, Documenta avisa para no crearlo dos veces. Si es otro caso, marca <strong>Crear de todas formas</strong>. Tocar dos veces el botón no crea dos casos.</p>

    <h3>2. Atender el caso (persona de campo)</h3>
    <p>Con el enlace por cédula de tipo <strong>Derivaciones</strong>, en <strong>Atender caso</strong>:</p>
    <ol class="pasos">
        <li>La cédula: tiene que estar registrada y <strong>en turno</strong>, con su entrada marcada.</li>
        <li>El <strong>número del caso</strong>: solo los dígitos, porque el «DC-» ya está puesto. Aparece lo que subió la oficina: mensaje, quién lo envía, ubicación y soporte.</li>
        <li>El <strong>nodo que atiende</strong> (sale su nodo habitual), <strong>¿Se cumplió con el requerimiento?</strong> Sí o No (con No, la observación es obligatoria) y, si pasaron más de 3 horas, la justificación.</li>
        <li>Las <strong>tres evidencias</strong>: una foto con la cámara, que lleva el sello de hora y lugar, o una imagen del celular.</li>
        <li><strong>Enviar atención.</strong> Ahí se detiene el tiempo.</li>
    </ol>
    <p>Un caso se atiende <strong>una sola vez</strong>; si hay que volver, la oficina crea uno nuevo. Si el número no existe o ya se atendió, sale «No hay un caso abierto con ese número». Por seguridad se permiten pocas consultas seguidas y cada una queda registrada.</p>

    <h3>3. Seguimiento (oficina)</h3>
    <ul>
        <li><span class="ruta">Atención territorial → Derivaciones</span>: lista con filtros por estado, fechas, nodo, cumplimiento y búsqueda (también por código: «DC-12» o «12»). Los abiertos van primero, con el tiempo que llevan corriendo.</li>
        <li>En la ficha de cada caso se ve la <strong>trazabilidad</strong>: quién lo creó y cuándo, quién lo atendió (persona y nodo) y cuándo, el tiempo, si cumplió la meta, el «¿Se cumplió?», las observaciones, el soporte y las evidencias.</li>
        <li>Arriba de la lista: abiertos y vencidos, cumplimiento, nodos con casos sin atender y los <strong>casos atendidos por cada persona de campo</strong>, con su tiempo promedio.</li>
        <li><strong>Avisos:</strong> si un caso pasa 3 horas sin atención, Edición y Coordinación reciben un solo aviso de vencido. Cuando lo atienden, quien lo creó recibe el suyo y el de vencido desaparece.</li>
        <li><strong>Corregir:</strong> Edición y Coordinación corrigen solo los textos. Las horas, el «¿Se cumplió?» y el <strong>cierre a mano</strong> de un caso que se atendió y no se pudo enviar (plan de contingencia) los hace solo el superadministrador, con un motivo que queda en la auditoría.</li>
        <li><strong>Eliminar:</strong> solo Coordinación; las fotos se conservan.</li>
        <li><strong>Descargar Excel:</strong> encabezado negro, las fotos dentro de sus celdas y el soporte en PDF como enlace que abre sin cuenta durante 30 días. Al final van el código, quién lo creó, las horas de creación y de atención, el «¿Se cumplió?» y la observación.</li>
        <li><strong>Excel + fotos (ZIP):</strong> el Excel y una carpeta por caso con las fotos en tamaño original.</li>
        <li>Las derivaciones anteriores a este cambio siguen en la lista y en el Excel, marcadas «registro anterior».</li>
    </ul>

    <h3>Supervisión territorial</h3>
    <p>Reemplaza el Forms «Registros de supervisión». Lo llena el <strong>Apoyo al control territorial</strong> con su cédula, por un enlace de tipo <strong>Supervisión territorial</strong> (se crea en <span class="ruta">Enlaces por cédula</span>).</p>
    <ol class="pasos">
        <li><strong>Quién supervisa:</strong> su cédula. Si su cargo no supervisa, el enlace no lo deja seguir.</li>
        <li><strong>La visita:</strong> punto crítico (o «Otro»), nodo, turno, fecha y hora, la cédula de los dinamizadores (el segundo es opcional) y la descripción del punto.</li>
        <li><strong>Calificación:</strong> nueve criterios y la calificación global de la dupla, de 1 a 5.</li>
        <li><strong>Hallazgos:</strong> incidencias, casos escalados (y cuál), casos prioritarios, recomendaciones y observaciones.</li>
        <li><strong>Evidencias:</strong> una o dos fotos, tomadas con la cámara.</li>
    </ol>
    <ul>
        <li><span class="ruta">Atención territorial → Supervisión</span>: lista con filtros (cédula del dinamizador, quién supervisó, nodo, punto, fechas), detalle con las calificaciones y <strong>Descargar Excel</strong> con las fotos dentro.</li>
        <li><strong>Analítica</strong> (arriba de la lista, con los filtros aplicados): total, promedios, incidencias, el promedio de cada criterio, los dinamizadores con promedio más bajo (tocando uno se ven solo sus supervisiones) y tablas por dinamizador, supervisor y nodo. El Excel trae esas mismas hojas.</li>
        <li>Quién puede supervisar se define en <span class="ruta">Componentes y nodos → Cargos que supervisan</span>.</li>
        <li><strong>Hay que estar en turno:</strong> quien supervisa y los dinamizadores tienen que haber marcado entrada. Lo mismo para atender un caso de derivación o enviar evidencias con la cédula.</li>
        <li>Las supervisiones con incidencia relevante o crítica, casos escalados o calificación global de 1 o 2 llegan por la campana (pestaña <strong>Supervisión</strong>) a líderes y Coordinación.</li>
    </ul>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="administracion">
    {!! $cap('administracion') !!}
    <div class="roles">{!! $roles('Coordinación', 'Administración') !!}</div>

    <h3>Componentes y nodos</h3>
    <p>Crear componentes (Calle, Básica…), sus nodos y la lista de cargos, y ajustar sus reglas: jornada, tolerancia, tolerancia de horas extra (40 a 60 min), duración máxima por cargo, descanso, foto u ubicación obligatoria, autorregistro, turnos por día y cuándo reconocer el turno o avisar «no registró entrada». Todo en un panel lateral: <strong>Nuevo componente</strong> o <strong>Editar</strong> en su fila. Los nodos se agregan, se ordenan arrastrando y se desactivan ahí mismo (no se borran).</p>
    <ul>
        <li><strong>Cargos que supervisan:</strong> los cargos que pueden registrar supervisiones (en Calle, «Apoyo al control territorial»). Tiene que estar escrito igual que en «Cargos».</li>
        <li><strong>Salida mínima (minutos):</strong> volver a marcar antes de esto, después de la última marca, es un reintento: no cambia de entrada a salida ni al revés (30 de fábrica).</li>
        <li><strong>Precisión mínima (m):</strong> una ubicación con más error que esto (500 m de fábrica) se toma como imprecisa: no cuenta como «fuera de zona» y queda la novedad «Ubicación imprecisa».</li>
        <li><strong>Tolerancia (minutos):</strong> el margen para la tardanza y el turno corto (en Calle, 20).</li>
        <li><strong>Tolerancia de horas extra (minutos):</strong> salir hasta tanto después del fin del turno no es hora extra; también es el margen con que se cierra solo un turno sin salida. Va de 40 a 60 (en Calle, 60).</li>
        <li><strong>Duración máxima del turno (horas):</strong> solo se usa cuando la hora de entrada no encaja con ningún turno; si encaja, el turno sin salida se cierra al fin de su turno más la tolerancia de horas extra (en Calle, 8).</li>
        <li><strong>Duración máxima por cargo:</strong> los cargos que trabajan jornadas largas y su máximo, contado desde la entrada (en Calle, Conductor camioneta y Conductor microbús, 15 h). Se eligen de la lista de «Cargos».</li>
        <li>La <strong>jornada de cada turno</strong> sale de su horario en <span class="ruta">Tipos de turno</span> (T4 → 4 h; N1 → 7 h, descontado el descanso): por eso cada turno necesita su hora de inicio y de fin.</li>
    </ul>

    <h3>Usuarios</h3>
    <ol class="pasos">
        <li>Ve a <span class="ruta">Administración → Usuarios</span> → <strong>Dar acceso</strong>.</li>
        <li>Nombre, número de documento (es su usuario) y correo.</li>
        <li>Asigna el <strong>rol</strong> en cada dependencia. Puedes desactivar a alguien sin borrarlo.</li>
        <li>Para un usuario de Edición, marca <strong>Aprueba novedades de turno</strong> si debe aprobarlas (Coordinación y Administración siempre pueden).</li>
    </ol>
    <p>En la lista se busca por nombre, correo o documento (con o sin puntos) y se filtra por rol, estado o «Aprueba novedades». <strong>Revocar</strong> quita el acceso a esta dependencia sin borrar la cuenta; pide confirmación. Nadie gestiona a quien tiene un rol mayor que el suyo.</p>

    <h3>Tipos de documento</h3>
    <p>Las categorías para clasificar documentos (Acta, Informe, Contrato…) y filtrar en el explorador.</p>

    <h3>Grupos de custodia</h3>
    <ol class="pasos">
        <li>Crea el grupo (por ejemplo, «Líderes Nodo 6») y agrega a sus miembros.</li>
        <li>En la carpeta, <strong>Editar</strong> → asigna el grupo de custodia.</li>
        <li>Desde ese momento solo el grupo ve y trabaja esa carpeta y lo que tiene dentro (aparece con un candado). Los miembros con rol Lectura solo consultan. <strong>Abrir</strong> un documento custodiado queda en la auditoría.</li>
    </ol>

    <h3>Auditoría</h3>
    <p>Quién hizo qué y cuándo: cargas, cambios, inactivaciones, ingresos, comentarios… Filtra por usuario, acción y fechas, o busca por <strong>nombre de archivo</strong>. Cada carga muestra su <strong>origen</strong> (aplicación o enlace de carga) y la <strong>carpeta</strong> a la que fue.</p>

    <h3>Enviar aviso <span class="rol si">Administración</span></h3>
    <p>Un mensaje por la campana a toda la dependencia o solo a ciertos roles.</p>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="notificaciones">
    {!! $cap('notificaciones') !!}
    <div class="roles">{!! $roles('Lectura', 'Edición', 'Coordinación', 'Administración') !!}</div>
    <p>La <strong>campana</strong> de la barra superior muestra cuántas notificaciones tienes sin leer. Toca una para ir directo a lo que la generó.</p>
    <ul>
        <li>Alguien comentó un archivo tuyo, o respondió donde comentaste.</li>
        <li>Llegaron archivos por un enlace de carga que creaste.</li>
        <li>Novedades de turno, incluida «No registró entrada» (líderes y Coordinación; las demás, a quien las aprueba).</li>
        <li>Derivaciones: un solo aviso cuando un caso pasa 3 horas sin atención (Edición y Coordinación) y otro a quien lo creó cuando lo atienden, que retira el de vencido.</li>
        <li>Supervisiones con incidencia relevante o crítica, casos escalados o calificación global de 1 o 2 (líderes y Coordinación).</li>
        <li>Avisos de Administración y las novedades de cada nueva versión.</li>
    </ul>
    <p>En <span class="ruta">Notificaciones</span> se separan por pestañas: <strong>Derivaciones · Supervisión · Turnos · Documentos · Avisos</strong>, cada una con su número de sin leer.</p>
</section>

{{-- ============================================================ --}}
<section class="capitulo" id="preguntas">
    {!! $cap('preguntas') !!}
    <dl class="preguntas">
        <dt>No veo una carpeta que antes veía.</dt>
        <dd>Puede estar inactiva o bajo custodia de un grupo al que no perteneces. Pregunta a Coordinación.</dd>

        <dt>Subí muchos archivos y llegaron menos.</dt>
        <dd>Documenta ahora te avisa si el servidor recibió menos de los enviados y no guarda nada a medias. Vuelve a intentar; si se repite, avisa a soporte.</dd>

        <dt>La foto no tiene el sello de hora y lugar.</dt>
        <dd>Solo llevan sello las fotos tomadas con <strong>Tomar foto</strong>. Las de la galería se envían tal cual.</dd>

        <dt>Una persona dice que no la deja marcar.</dt>
        <dd>Si ya completó su turno del día, el enlace no deja marcar otra vez. Si la hora quedó mal, corrígela desde su ficha.</dd>

        <dt>Me llegó «No registró entrada» de alguien que estaba de vacaciones.</dt>
        <dd>Desde la novedad, <strong>Justificar</strong> con el código VAC. Para que no vuelva a pasar, registra sus vacaciones completas en su ficha (Registrar ausencia).</dd>

        <dt>¿Cómo guardo este manual en PDF?</dt>
        <dd>Toca <strong>Descargar PDF</strong> arriba del manual y, en la ventana de impresión, elige <strong>Guardar como PDF</strong>.</dd>
    </dl>
</section>
