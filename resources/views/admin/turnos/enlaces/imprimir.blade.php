<!DOCTYPE html>
{{--
    Página suelta, sin el menú ni la barra: lo único que sale en el papel es
    el QR, dónde va pegado y cómo se usa. Se imprime sola al abrirse.
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR · {{ $donde }}</title>
    @vite(['resources/js/app.js'])
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; color: #111; background: #fff; }
        .hoja { max-width: 16cm; margin: 1.5cm auto; text-align: center; }
        h1 { font-size: 28pt; margin: 0 0 4pt; }
        .sub { font-size: 14pt; color: #444; margin: 0; }
        .qr { width: 12cm; margin: 1cm auto; }
        .qr svg { width: 100%; height: auto; }
        ol { text-align: left; font-size: 13pt; line-height: 1.6; max-width: 12cm; margin: 0 auto; }
        .pie { margin-top: 1cm; font-size: 9pt; color: #666; word-break: break-all; }
        @media print { .no-imprimir { display: none; } .hoja { margin: 0 auto; } }
    </style>
</head>
<body>
    <div class="hoja">
        <h1>{{ $enlace->esDerivacion() ? 'Reporta aquí la derivación' : ($enlace->esSupervision() ? 'Registra aquí la supervisión' : 'Marca tu turno aquí') }}</h1>
        <p class="sub">{{ $donde }}{{ $enlace->nombre ? ' · '.$enlace->nombre : '' }}</p>

        <div class="qr" data-qr="{{ $enlace->url() }}"></div>

        <ol>
            <li>Escanea el código con la cámara de tu celular.</li>
            @if($enlace->esDerivacion())
                <li>Escribe tu cédula: tiene que estar registrada.</li>
                <li>Llena el caso, adjunta el soporte y toma las evidencias.</li>
            @elseif($enlace->esSupervision())
                <li>Escribe tu cédula: es solo para el Apoyo al control territorial.</li>
                <li>Escribe la cédula de los dinamizadores, califica de 1 a 5 y toma las evidencias.</li>
            @else
                <li>Escribe tu cédula. La primera vez te pide tus datos; después, solo la cédula.</li>
                <li>Toca <strong>Registrar entrada</strong> al llegar y <strong>Registrar salida</strong> al irte.</li>
            @endif
        </ol>

        <p class="pie">{{ $enlace->url() }}</p>

        <button class="no-imprimir" onclick="window.print()" style="margin-top:1cm;padding:8pt 16pt;font-size:12pt">
            Imprimir
        </button>
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
