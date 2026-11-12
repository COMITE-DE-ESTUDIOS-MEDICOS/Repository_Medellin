<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manual de usuario · Documenta · Comité de Estudios Médicos</title>
    <link rel="icon" href="{{ asset('img/favicon-cem.png') }}">
    @include('manual._estilos')
    <style>
        /*
         * La versión para guardar en PDF (Imprimir → Guardar como PDF): A4,
         * portada a sangre, pie con el número de página y cada capítulo en
         * página nueva.
         */
        @page { size: A4; margin: 18mm 16mm 20mm; }
        @page {
            @bottom-left { content: 'Documenta · Manual de usuario · Comité de Estudios Médicos'; font: 8.5pt 'Inter', sans-serif; color: #8a909a; }
            @bottom-right { content: counter(page); font: 600 9pt 'Quicksand', sans-serif; color: #3b3e48; }
        }
        @page portada { margin: 0; @bottom-left { content: none; } @bottom-right { content: none; } }

        html, body { margin: 0; background: #e9edf0; }
        .manual { font-size: 10.5pt; }
        .hoja { max-width: 210mm; margin: 0 auto; background: #fff; }
        .cuerpo { padding: 14mm 16mm; }

        .portada {
            page: portada; position: relative; height: 297mm; box-sizing: border-box; overflow: hidden;
            background: var(--carbon); color: #fff; display: flex; flex-direction: column; justify-content: space-between; padding: 26mm 22mm;
        }
        .portada .logo { width: 72mm; }
        .portada .franja { position: absolute; left: 0; right: 0; bottom: 0; height: 14mm; background: var(--bruma); }
        .portada .puntos { position: absolute; right: 22mm; top: 26mm; display: grid; grid-template-columns: repeat(4, 6px); gap: 9px; opacity: .55; }
        .portada .puntos span { width: 6px; height: 6px; border-radius: 99px; background: var(--bruma); }
        .manual .portada h1 { color: #fff; font-size: 40pt; font-weight: 500; letter-spacing: .03em; line-height: 1.05; margin: 0; }
        .portada .sobre { font-family: 'Quicksand', sans-serif; color: var(--bruma); letter-spacing: .3em; font-size: 10pt; font-weight: 600; margin: 0 0 6mm; }
        .portada .pie { font-size: 10pt; color: var(--bruma); margin-bottom: 14mm; line-height: 1.7; }
        .portada .pie strong { color: #fff; }

        .barra-pdf { position: sticky; top: 0; z-index: 5; display: flex; gap: 10px; align-items: center; justify-content: center; padding: 10px; background: #3b3e48; color: #fff; font: 13px 'Inter', sans-serif; }
        .barra-pdf button { font: 700 13px 'Quicksand', sans-serif; background: #c0ced4; color: #3b3e48; border: 0; border-radius: 999px; padding: 8px 18px; cursor: pointer; }

        @media print {
            html, body { background: #fff; }
            .barra-pdf { display: none; }
            .hoja { max-width: none; }
            .cuerpo { padding: 0; }
            .manual .capitulo { break-before: page; border-top: 0 !important; padding-top: 0 !important; }
            .manual .capitulo:first-child { break-before: auto; }
            .manual h3 { break-after: avoid; }
            .manual a { color: inherit; }
        }
    </style>
</head>
<body>

<div class="barra-pdf">
    En la ventana de impresión elige <strong>«Guardar como PDF»</strong>.
    <button type="button" onclick="window.print()">Guardar como PDF</button>
</div>

<div class="manual hoja">
    <section class="portada">
        <div class="puntos" aria-hidden="true">@for($i = 0; $i < 16; $i++)<span></span>@endfor</div>
        <img class="logo" src="{{ asset('img/logo-cem-oscuro.png') }}" alt="Comité de Estudios Médicos">

        <div>
            <p class="sobre">DOCUMENTA · {{ mb_strtoupper($dependenciaActual?->nombre ?? 'Inclusión Social') }}</p>
            <h1 class="marca">Manual<br>de usuario</h1>
        </div>

        <div class="pie">
            <strong>Versión {{ config('app.version') }}</strong> · {{ now()->translatedFormat('j \\d\\e F \\d\\e Y') }}<br>
            Gestión documental, evidencias de campo, turnos y atención territorial.
        </div>
        <div class="franja"></div>
    </section>

    <div class="cuerpo">
        @include('manual._contenido')
    </div>
</div>

<script>
    // Abre la ventana de impresión sola, cuando las fuentes ya cargaron.
    window.addEventListener('load', () => document.fonts.ready.then(() => setTimeout(() => window.print(), 300)));
</script>
</body>
</html>
