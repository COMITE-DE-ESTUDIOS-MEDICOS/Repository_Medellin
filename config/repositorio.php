<?php

/*
| Lo que el repositorio recibe de fuera, por un enlace de carga: PDF y
| fotografía, y nada más. Es la única puerta abierta a internet, así que es
| la lista corta, y se declara arriba para que el conjunto de la aplicación
| se lea abajo como lo que es: este mismo, más lo que se añada.
*/
$formatosPublicos = [
    // 'jfif' es un JPEG con otro nombre, el que a veces pone Windows o Chrome
    // al guardar una imagen. El servidor ya lo aceptaba, porque mira el
    // contenido; faltaba aquí para que el selector de archivos lo enseñe.
    'extensiones' => ['pdf', 'jpg', 'jpeg', 'jfif', 'png', 'webp'],

    'mimetypes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de almacenamiento
    |--------------------------------------------------------------------------
    | Los archivos nunca se sirven directamente desde el disco público: pasan
    | siempre por el controlador de descargas, que valida permisos y audita.
    | Cambiar a 's3' aquí basta para migrar a la nube sin tocar el código.
    */

    'disco' => env('REPOSITORIO_DISCO', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Límites de carga
    |--------------------------------------------------------------------------
    | 'tamano_maximo_kb' debe ir de la mano de upload_max_filesize y
    | post_max_size en php.ini, o PHP rechazará el archivo antes que Laravel.
    */

    'tamano_maximo_kb' => env('REPOSITORIO_TAMANO_MAXIMO_KB', 25600),

    /*
    |--------------------------------------------------------------------------
    | Cargas grandes, por tandas
    |--------------------------------------------------------------------------
    | Se pueden elegir hasta 'maximo_total' archivos de una vez; el navegador
    | los manda en tandas de 'por_tanda' (o menos, si una tanda pasaría de
    | 'mb_por_tanda'). El servidor sigue recibiendo como mucho 'por_tanda' por
    | petición: sus límites no cambian. 'mb_por_tanda' tiene que quedar por
    | debajo de post_max_size en php.ini.
    */
    'carga' => [
        'maximo_total' => (int) env('REPOSITORIO_CARGA_MAXIMO_TOTAL', 300),
        'por_tanda' => 50,
        'mb_por_tanda' => (int) env('REPOSITORIO_CARGA_MB_POR_TANDA', 150),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolución de las fotografías
    |--------------------------------------------------------------------------
    | El peso no basta como tope: una foto de 48 MP cabe en 25 MB y aun así se
    | come el disco, la memoria del navegador y el ancho de banda de quien
    | revisa. Son dos límites a propósito, no uno:
    |
    | - 'lado_maximo': a cuánto se reduce el lado largo EN EL NAVEGADOR, antes
    |   de enviar. 2560 px son unos 220 puntos por pulgada sobre una hoja
    |   carta: se lee la letra pequeña de un acta y pesa una fracción.
    | - 'lado_maximo_servidor': lo que el servidor deja pasar. Es la frontera
    |   de verdad —el navegador es de quien envía y puede saltárselo—, y va por
    |   encima del anterior a propósito: si el script no corrió, una foto
    |   normal de 12 MP (4000 px) entra igual; la de 48 MP no.
    */

    'imagen' => [
        'lado_maximo' => (int) env('REPOSITORIO_IMAGEN_LADO_MAXIMO', 2560),
        'lado_maximo_servidor' => (int) env('REPOSITORIO_IMAGEN_LADO_MAXIMO_SERVIDOR', 4096),
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatos aceptados
    |--------------------------------------------------------------------------
    | Los dos caminos de carga no reciben lo mismo, y por eso son dos listas:
    |
    | - 'publico': lo que envía alguien de fuera por un enlace de carga, sin
    |   cuenta y sin que nadie responda por él. Solo PDF y fotografía.
    |
    | - 'aplicacion': lo que sube alguien que ya entró al repositorio. Lo
    |   anterior más hojas de cálculo, que es trabajo interno y va firmado
    |   por una cuenta con nombre y apellido.
    |
    | Dentro de cada conjunto, las dos claves describen el mismo grupo de
    | formatos por dos caminos distintos: 'extensiones' alimenta la regla
    | mimes: y el atributo accept de los formularios; 'mimetypes' alimenta la
    | regla mimetypes:, que mira el contenido real del archivo y no se deja
    | engañar por un renombrado. Cambiar un conjunto se hace aquí y solo aquí.
    */

    'formatos' => [

        'publico' => $formatosPublicos,

        // Evidencias fotográficas: lo mismo de la puerta pública, sin el PDF.
        'imagenes' => [
            'extensiones' => array_values(array_diff($formatosPublicos['extensiones'], ['pdf'])),
            'mimetypes' => array_values(array_diff($formatosPublicos['mimetypes'], ['application/pdf'])),
        ],

        'aplicacion' => [
            'extensiones' => [
                ...$formatosPublicos['extensiones'],

                // Hojas de cálculo. Se quedan fuera .xlsm y demás formatos
                // con macros: traen VBA ejecutable, y el repositorio los
                // repartiría a todo el equipo tal como llegaron.
                'xlsx',
                'xls',
            ],

            'mimetypes' => [
                ...$formatosPublicos['mimetypes'],

                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Horario hábil
    |--------------------------------------------------------------------------
    | Con qué vara se mide si algo llegó en horario de trabajo. Lo consulta
    | App\Services\CalendarioHabil, y su respuesta se congela en cada
    | recepción: cambiar esto no reescribe lo ya recibido.
    |
    | La zona manda. La aplicación atiende en Medellín pero la base de datos
    | puede estar en UTC, y comparar horas en la zona del servidor correría
    | todo cinco horas sin que nadie lo note hasta que salga un informe torcido.
    */

    'horario' => [

        'zona' => 'America/Bogota',

        'dias' => [
            // 1 = lunes … 7 = domingo. Día ausente = no hábil.
            1 => ['07:00', '17:00'],
            2 => ['07:00', '17:00'],
            3 => ['07:00', '17:00'],
            4 => ['07:00', '17:00'],
            5 => ['07:00', '16:00'],
        ],

        /*
        | Festivos colombianos, en formato Y-m-d. Va vacía a propósito: los
        | corre la Ley Emiliani al lunes siguiente y no todos, así que una
        | lista calculada por fórmula sale mal y una lista mal calculada es
        | peor que ninguna. Los carga la administración, año por año.
        */
        'no_habiles' => [],

    ],

    /*
    |--------------------------------------------------------------------------
    | Festivos de Colombia
    |--------------------------------------------------------------------------
    | Para los turnos: en festivo aplican los turnos de «domingos y festivos»
    | (C13) y no los de lunes a sábado. Ley Emiliani y Semana Santa según la
    | Pascua de cada año.
    |
    | ponytail: lista fija; antes de 2028 agregar el año siguiente (o
    | calcularlos con easter_days(), que necesita la extensión calendar).
    */

    'festivos' => [
        '2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02', '2026-04-03', '2026-05-01',
        '2026-05-18', '2026-06-08', '2026-06-15', '2026-06-29', '2026-07-20', '2026-08-07',
        '2026-08-17', '2026-10-12', '2026-11-02', '2026-11-16', '2026-12-08', '2026-12-25',
        '2027-01-01', '2027-01-11', '2027-03-22', '2027-03-25', '2027-03-26', '2027-05-01',
        '2027-05-10', '2027-05-31', '2027-06-07', '2027-07-05', '2027-07-20', '2027-08-07',
        '2027-08-16', '2027-10-18', '2027-11-01', '2027-11-15', '2027-12-08', '2027-12-25',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    */

    'por_pagina' => 25,

    /*
    |--------------------------------------------------------------------------
    | Comunas y corregimientos de Medellín (DOC-23)
    |--------------------------------------------------------------------------
    | Se guarda el número en derivaciones.comuna. Solo Medellín.
    */

    'comunas' => [
        1 => 'Popular', 2 => 'Santa Cruz', 3 => 'Manrique', 4 => 'Aranjuez', 5 => 'Castilla',
        6 => 'Doce de Octubre', 7 => 'Robledo', 8 => 'Villa Hermosa', 9 => 'Buenos Aires',
        10 => 'La Candelaria', 11 => 'Laureles-Estadio', 12 => 'La América', 13 => 'San Javier',
        14 => 'El Poblado', 15 => 'Guayabal', 16 => 'Belén',
        50 => 'Palmitas', 60 => 'San Cristóbal', 70 => 'Altavista', 80 => 'San Antonio de Prado', 90 => 'Santa Elena',
    ],

    /*
    |--------------------------------------------------------------------------
    | Listas de Usuarios HC (DOC-26, spec §2, §3 y §7)
    |--------------------------------------------------------------------------
    | Código => texto. El código se guarda con cada respuesta y no cambia
    | nunca: con él se cuenta. El texto sí se puede cambiar, y cada ficha
    | conserva el que vio quien la registró.
    |
    | - Renombrar: cambiar el texto.
    | - Retirar: poner el código en 'retiradas'. No se quita de 'opciones': deja
    |   de ofrecerse, pero las fichas que la tienen la siguen mostrando.
    | - 'excluyentes': una opción así no se guarda junto con ninguna otra.
    |
    | La comuna usa 'comunas', la de arriba. Qué campo usa qué lista está en
    | App\Models\UsuarioHc::CAMPOS.
    */

    'usuarios_hc' => [

        'tipos_documento' => [
            'opciones' => [
                'CC' => 'Cédula de ciudadanía',
                'TI' => 'Tarjeta de identidad',
                'CE' => 'Cédula de extranjería',
                'PPT' => 'Permiso por Protección Temporal',
                'PEP' => 'Permiso Especial de Permanencia',
                'PAS' => 'Pasaporte',
                'RC' => 'Registro civil',
                // UsuarioHc::SIN_DOCUMENTO: con él no se pide el número. No se retira ni cambia de código.
                'SD' => 'Sin documento',
                'OTRO' => 'Otro',
            ],
            'retiradas' => [],
        ],

        'si_no' => [
            'opciones' => ['si' => 'Sí', 'no' => 'No'],
            'retiradas' => [],
        ],

        'escolaridad' => [
            'opciones' => [
                'primaria' => 'Primaria',
                'secundaria' => 'Secundaria',
                'bachiller' => 'Bachiller',
                'tecnico' => 'Técnico',
                'tecnologo' => 'Tecnólogo',
                'profesional' => 'Profesional',
                'ninguno' => 'Ninguno',
            ],
            'retiradas' => [],
        ],

        'sustancias' => [
            'opciones' => [
                'alcohol' => 'Alcohol',
                'basuco' => 'Basuco',
                'cocaina' => 'Cocaína',
                'heroina' => 'Heroína',
                'marihuana' => 'Marihuana',
                'sacol' => 'Sacol',
                'tabaco' => 'Tabaco',
                'tusi' => 'Tusi',
                'perico' => 'Perico',
                'no_consume' => 'No consume',
            ],
            'retiradas' => [],
            'excluyentes' => ['no_consume'],
        ],

        'nivel_apoyo' => [
            'opciones' => [
                'sin_red' => 'Sin red identificada',
                'red_sin_apoyo' => 'Red identificada, sin apoyo disponible',
                'apoyo_emocional' => 'Contacto y apoyo emocional',
                'apoyo_practico_limitado' => 'Apoyo práctico limitado',
                'apoyo_practico_sostenido' => 'Apoyo práctico sostenido',
                'vinculacion_no_recomendable' => 'Vinculación no recomendable actualmente',
                'pendiente_valoracion' => 'Pendiente de valoración',
            ],
            'retiradas' => [],
        ],

        'clasificacion' => [
            'opciones' => [
                'habitante_en_calle' => 'Habitante en calle',
                'habitante_de_calle' => 'Habitante de calle',
                'riesgo_calle' => 'Persona en riesgo de calle',
                'sin_clasificacion' => 'Sin clasificación',
                'matricula_resocializacion' => 'Matrícula en resocialización',
                'pendiente_valoracion' => 'Pendiente de valoración',
            ],
            'retiradas' => [],
        ],

        // La spec escribe «Femenino (F)…»: la letra es el código, no parte del texto.
        'sexo' => [
            'opciones' => [
                'F' => 'Femenino',
                'M' => 'Masculino',
                'NB' => 'No binario',
                'T' => 'Transgénero',
            ],
            'retiradas' => [],
        ],

        'orientacion' => [
            'opciones' => [
                'heterosexual' => 'Heterosexual',
                'homosexual' => 'Homosexual',
                'bisexual' => 'Bisexual',
                'pansexual' => 'Pansexual',
                'asexual' => 'Asexual',
                'otra' => 'Otra',
                'no_responde' => 'Prefiere no responder',
            ],
            'retiradas' => [],
        ],

        // Autorizada solo para el Usuario HC al aprobar la spec (PA-07). La EPS
        // de cada persona vive en la base de datos, no aquí.
        'eps' => [
            'opciones' => [
                'asmet' => 'Asmet',
                'aic' => 'Asociación Indígena del Cauca',
                'emsanar' => 'Emsanar',
                'capital_salud' => 'Capital Salud',
                'coosalud' => 'Coosalud',
                'nueva_eps' => 'Nueva EPS',
                'sanitas' => 'Sanitas',
                'salud_total' => 'Salud Total',
                'savia_salud' => 'Savia Salud',
                'sura' => 'Sura',
                'sin_afiliacion' => 'Sin afiliación',
            ],
            'retiradas' => [],
        ],

        // No hay «Múltiple»: se marcan varias. «Ninguna» y «Sin información»
        // no van con otras ni entre sí (CA-10).
        'discapacidad' => [
            'opciones' => [
                'fisica' => 'Física',
                'auditiva' => 'Auditiva',
                'visual' => 'Visual',
                'sordoceguera' => 'Sordoceguera',
                'intelectual' => 'Intelectual / cognitiva',
                'psicosocial' => 'Psicosocial (mental)',
                'ninguna' => 'Ninguna',
                'sin_informacion' => 'Sin información',
            ],
            'retiradas' => [],
            'excluyentes' => ['ninguna', 'sin_informacion'],
        ],

        'autonomia' => [
            'opciones' => [
                'autonomo' => 'Autónomo',
                'heteronomo' => 'Heterónomo',
            ],
            'retiradas' => [],
        ],

        'independencia' => [
            'opciones' => [
                'independiente' => 'Independiente',
                'supervision' => 'Requiere supervisión',
                'ayuda_parcial' => 'Requiere ayuda parcial',
                'ayuda_total' => 'Requiere ayuda total',
                'pendiente_valoracion' => 'Pendiente de valoración',
            ],
            'retiradas' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Carga única de casos ya atendidos (DOC-24)
    |--------------------------------------------------------------------------
    | Los casos represados de noviembre de 2026 entran por un Excel, con sus
    | horas reales (cualquier fecha pasada). 'abierta_hasta': último día en
    | que Coordinación ve la carga; después desaparece sola (null = sin
    | fecha de cierre).
    */

    'casos_carga_masiva' => [
        'abierta_hasta' => '2026-11-30',
        'maximo_filas' => 50,
    ],

];
