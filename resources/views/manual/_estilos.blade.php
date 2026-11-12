{{--
    Estilos del manual: los mismos en pantalla y en el PDF. Es una «hoja» clara
    con la imagen del Comité (gris carbón, gris azulado, tipografía geométrica
    redondeada), independiente del tema claro u oscuro de Documenta.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    .manual {
        --carbon: #3b3e48;
        --carbon-suave: #5a5e6b;
        --bruma: #c0ced4;
        --bruma-suave: #e8eef1;
        --papel: #ffffff;
        --tinta: #23252c;
        --gris: #6b7280;
        --linea: #dde4e8;
        --azul: #1f4e79;
        --verde: #198754;
        --ambar: #b7791f;

        background: var(--papel);
        color: var(--tinta);
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        line-height: 1.65;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .manual h1, .manual h2, .manual h3, .manual .marca {
        font-family: 'Quicksand', 'Inter', system-ui, sans-serif;
        color: var(--carbon);
    }

    .manual .capitulo { padding: 8px 0 28px; }
    .manual .capitulo + .capitulo { border-top: 1px solid var(--linea); padding-top: 32px; }

    .manual .capitulo-encabezado { display: flex; align-items: baseline; gap: 14px; margin-bottom: 6px; }
    .manual .capitulo-numero {
        font-family: 'Quicksand', sans-serif; font-weight: 600; font-size: 13px; letter-spacing: .18em;
        color: var(--carbon); background: var(--bruma); border-radius: 999px; padding: 2px 12px;
    }
    .manual h2 { font-size: 24px; font-weight: 600; letter-spacing: .02em; margin: 0; }
    .manual .entrada { color: var(--carbon-suave); margin: 4px 0 18px; font-size: 15px; }
    .manual h3 { font-size: 16px; font-weight: 700; margin: 22px 0 6px; }
    .manual p { margin: 6px 0 10px; }
    .manual ul, .manual ol { margin: 6px 0 12px; padding-left: 22px; }
    /* La base de Tailwind quita las viñetas: aquí se devuelven. */
    .manual ul { list-style: disc; }
    .manual ol { list-style: decimal; }
    .manual li { margin: 3px 0; }
    .manual ul li::marker { color: var(--bruma); }
    .manual strong { color: var(--carbon); font-weight: 600; }
    .manual a { color: var(--azul); text-decoration: none; }
    .manual code, .manual .tecla {
        font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; font-size: 12.5px;
        background: var(--bruma-suave); border: 1px solid var(--linea); border-radius: 5px; padding: 1px 6px;
    }
    .manual .ruta { font-weight: 600; color: var(--carbon); }
    .manual .ruta::before { content: '▸ '; color: var(--bruma); }

    /* Pasos numerados, en círculos. */
    .manual ol.pasos { list-style: none; padding: 0; counter-reset: paso; }
    .manual ol.pasos > li { counter-increment: paso; position: relative; padding-left: 38px; margin: 8px 0; }
    .manual ol.pasos > li::before {
        content: counter(paso); position: absolute; left: 0; top: 1px; width: 24px; height: 24px; border-radius: 999px;
        background: var(--carbon); color: #fff; font: 600 12px/24px 'Quicksand', sans-serif; text-align: center;
    }

    /* Notas: consejo, importante. */
    .manual .nota { border-left: 3px solid var(--bruma); background: var(--bruma-suave); border-radius: 0 8px 8px 0; padding: 10px 14px; margin: 12px 0; break-inside: avoid; }
    .manual .nota.importante { border-color: var(--ambar); background: #fbf4e6; }
    .manual .nota-titulo { display: block; font-family: 'Quicksand', sans-serif; font-weight: 700; color: var(--carbon); font-size: 13px; letter-spacing: .04em; margin-bottom: 2px; }

    /* Quién lo usa. */
    .manual .roles { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 14px; }
    .manual .rol { font-size: 11.5px; font-weight: 600; border-radius: 999px; padding: 2px 10px; border: 1px solid var(--linea); color: var(--carbon-suave); background: #fff; }
    .manual .rol.si { border-color: var(--carbon); color: var(--carbon); background: var(--bruma-suave); }

    .manual table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; font-size: 13px; break-inside: avoid; }
    .manual th { text-align: left; background: var(--carbon); color: #fff; font-weight: 600; padding: 8px 10px; }
    .manual td { border-bottom: 1px solid var(--linea); padding: 7px 10px; vertical-align: top; }
    .manual tr:nth-child(even) td { background: #f7f9fa; }
    .manual td.centro, .manual th.centro { text-align: center; }
    .manual .si-marca { color: var(--verde); font-weight: 700; }
    .manual .no-marca { color: #c3c8ce; }

    /* Índice. */
    .manual .indice { columns: 2; column-gap: 32px; padding: 0; list-style: none; margin: 0; }
    .manual .indice li { break-inside: avoid; display: flex; gap: 10px; padding: 6px 0; border-bottom: 1px dotted var(--linea); }
    .manual .indice .n { font-family: 'Quicksand', sans-serif; font-weight: 700; color: var(--bruma); min-width: 24px; }
    .manual .indice a { color: var(--tinta); }

    .manual .preguntas dt { font-weight: 600; color: var(--carbon); margin-top: 12px; }
    .manual .preguntas dd { margin: 2px 0 0; color: var(--tinta); }

    @media (max-width: 700px) {
        .manual .indice { columns: 1; }
        .manual h2 { font-size: 20px; }
    }
</style>
