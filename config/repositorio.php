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

];
