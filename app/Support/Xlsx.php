<?php

namespace App\Support;

use ZipArchive;

/**
 * Un .xlsx de una o varias hojas: encabezado negro con letra blanca, fila
 * fija y filtros. Una celda es texto/número, ['url' => ..., 'texto' => ...]
 * para un enlace, ['imagen' => bytes JPEG, 'ancho' => px, 'alto' => px] para
 * una foto incrustada (ver miniatura()), o ['alerta' => valor] para
 * resaltarla en rojo.
 *
 * ponytail: escrito a mano sobre ZipArchive (ya en uso para los ZIP). Si un
 * reporte necesita fórmulas, varias hojas o gráficos, pasar a
 * phpoffice/phpspreadsheet.
 */
class Xlsx
{
    /** Pixeles a EMU, la unidad de los dibujos de Office. */
    protected const EMU = 9525;

    /** @param iterable<array<int, mixed>> $filas */
    public static function guardar(string $ruta, string $hoja, array $encabezados, iterable $filas): string
    {
        return self::libro($ruta, [$hoja => [$encabezados, $filas]]);
    }

    /**
     * Varias hojas, en orden: ['Turnos' => [$encabezados, $filas], 'Resumen' => [...]].
     *
     * @param array<string, array{0: array, 1: iterable}> $hojas
     */
    public static function libro(string $ruta, array $hojas): string
    {
        $xml = fn (string $texto) => htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $tipos = '';
        $relacionesLibro = '';
        $hojasLibro = '';
        $filtros = '';
        $n = 0;

        foreach ($hojas as $nombre => [$encabezados, $filas]) {
            $n++;
            $nombre = $xml(mb_substr((string) $nombre, 0, 31));
            [$ultimaFila, $conDibujo] = self::hoja($zip, $n, $encabezados, $filas);
            $ultima = self::columna(count($encabezados) - 1);

            $tipos .= "<Override PartName=\"/xl/worksheets/sheet{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>"
                .($conDibujo ? "<Override PartName=\"/xl/drawings/drawing{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.drawing+xml\"/>" : '');
            $relacionesLibro .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
            $hojasLibro .= "<sheet name=\"{$nombre}\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
            $filtros .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.($n - 1).'" hidden="1">\''.$nombre.'\'!$A$1:$'.$ultima.'$'.$ultimaFila.'</definedName>';
        }

        $estilos = $n + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="jpeg" ContentType="image/jpeg"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$tipos
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$hojasLibro.'</sheets>'
            .'<definedNames>'.$filtros.'</definedNames>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relacionesLibro
            .'<Relationship Id="rId'.$estilos.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // Estilos: 0 normal, 1 encabezado (negro, letra blanca en negrita), 2 enlace, 3 alerta (rojo).
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><u/><sz val="11"/><color rgb="FF0563C1"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF9C0006"/><name val="Calibri"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF000000"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFC7CE"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="top"/></xf></cellXfs>'
            .'</styleSheet>');

        $zip->close();

        return $ruta;
    }

    /**
     * Escribe la hoja $n (y su dibujo si lleva fotos).
     *
     * @return array{0: int, 1: bool} última fila escrita y si lleva dibujo
     */
    protected static function hoja(ZipArchive $zip, int $n, array $encabezados, iterable $filas): array
    {
        $xml = fn (string $texto) => htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $imagenes = [];
        $anchoColumna = [];

        $celda = function ($valor, int $columna, int $fila, int $estilo) use ($xml, &$imagenes, &$anchoColumna): string {
            $ref = self::columna($columna).$fila;

            if (is_array($valor) && array_key_exists('alerta', $valor)) {
                $valor = $valor['alerta'];
                $estilo = $estilo === 1 ? 1 : 3;
            }

            if (is_array($valor) && isset($valor['imagen'])) {
                $imagenes[] = $valor + ['columna' => $columna, 'fila' => $fila];
                $anchoColumna[$columna] = max($anchoColumna[$columna] ?? 0, $valor['ancho']);

                return '';
            }

            if (is_array($valor)) {
                $url = str_replace('"', '""', $valor['url']);
                $texto = str_replace('"', '""', $valor['texto']);

                return "<c r=\"{$ref}\" s=\"2\" t=\"str\"><f>{$xml("HYPERLINK(\"{$url}\",\"{$texto}\")")}</f><v>{$xml($valor['texto'])}</v></c>";
            }

            if ($valor === null || $valor === '') {
                return '';
            }

            if (is_int($valor) || is_float($valor)) {
                return "<c r=\"{$ref}\" s=\"{$estilo}\"><v>{$valor}</v></c>";
            }

            return "<c r=\"{$ref}\" s=\"{$estilo}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$xml((string) $valor)}</t></is></c>";
        };

        $filasXml = '';
        $numero = 0;

        foreach ([$encabezados, ...$filas] as $fila) {
            $numero++;
            $antes = count($imagenes);
            $celdas = '';

            foreach (array_values($fila) as $i => $valor) {
                $celdas .= $celda($valor, $i, $numero, $numero === 1 ? 1 : 0);
            }

            // Una fila con fotos crece a lo alto de la más alta (puntos = px × 0,75).
            $alto = max([0, ...array_column(array_slice($imagenes, $antes), 'alto')]);
            $filasXml .= $alto
                ? "<row r=\"{$numero}\" ht=\"".round(($alto + 8) * 0.75, 1).'" customHeight="1">'.$celdas.'</row>'
                : "<row r=\"{$numero}\">{$celdas}</row>";
        }

        $ultima = self::columna(count($encabezados) - 1);

        // Ancho en caracteres: unos 7 px por carácter.
        $columnas = implode('', array_map(
            fn ($i) => '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'
                .(isset($anchoColumna[$i]) ? round(($anchoColumna[$i] + 10) / 7, 1) : 22).'" customWidth="1"/>',
            array_keys($encabezados),
        ));

        $zip->addFromString("xl/worksheets/sheet{$n}.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"'.($n === 1 ? ' tabSelected="1"' : '').'><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$columnas.'</cols>'
            .'<sheetData>'.$filasXml.'</sheetData>'
            .'<autoFilter ref="A1:'.$ultima.$numero.'"/>'
            .($imagenes ? '<drawing r:id="rId1"/>' : '')
            .'</worksheet>');

        if ($imagenes) {
            self::dibujos($zip, $n, $imagenes);
        }

        return [$numero, (bool) $imagenes];
    }

    /**
     * Una foto lista para una celda: JPEG de lado máximo $lado. Null si no se
     * puede leer o el servidor no tiene GD (entonces la celda queda sin foto).
     *
     * @return array{imagen: string, ancho: int, alto: int}|null
     */
    public static function miniatura(?string $bytes, int $lado = 160): ?array
    {
        if ($bytes === null || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $original = @imagecreatefromstring($bytes);

        if ($original === false) {
            return null;
        }

        $escala = min(1, $lado / max(imagesx($original), imagesy($original)));
        $reducida = imagescale($original, max(1, (int) round(imagesx($original) * $escala)));

        ob_start();
        imagejpeg($reducida, null, 72);
        $jpeg = ob_get_clean();

        return ['imagen' => $jpeg, 'ancho' => imagesx($reducida), 'alto' => imagesy($reducida)];
    }

    /** Las fotos: cada una anclada a su celda, para que se mueva y filtre con la fila. */
    protected static function dibujos(ZipArchive $zip, int $n, array $imagenes): void
    {
        $anclas = '';
        $relaciones = '';

        foreach ($imagenes as $i => $imagen) {
            $id = $i + 1;
            $zip->addFromString("xl/media/hoja{$n}-image{$id}.jpeg", $imagen['imagen']);
            $relaciones .= "<Relationship Id=\"rId{$id}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/image\" Target=\"../media/hoja{$n}-image{$id}.jpeg\"/>";

            $anclas .= '<xdr:oneCellAnchor>'
                .'<xdr:from><xdr:col>'.$imagen['columna'].'</xdr:col><xdr:colOff>'.(4 * self::EMU).'</xdr:colOff>'
                .'<xdr:row>'.($imagen['fila'] - 1).'</xdr:row><xdr:rowOff>'.(4 * self::EMU).'</xdr:rowOff></xdr:from>'
                .'<xdr:ext cx="'.($imagen['ancho'] * self::EMU).'" cy="'.($imagen['alto'] * self::EMU).'"/>'
                .'<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="'.($id + 1).'" name="Foto '.$id.'"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                .'<xdr:blipFill><a:blip r:embed="rId'.$id.'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.($imagen['ancho'] * self::EMU).'" cy="'.($imagen['alto'] * self::EMU).'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
                .'</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        }

        $zip->addFromString("xl/drawings/drawing{$n}.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .$anclas.'</xdr:wsDr>');

        $zip->addFromString("xl/drawings/_rels/drawing{$n}.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relaciones.'</Relationships>');

        $zip->addFromString("xl/worksheets/_rels/sheet{$n}.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing'.$n.'.xml"/>'
            .'</Relationships>');
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    public static function columna(int $indice): string
    {
        $letras = '';

        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26).$letras;
        }

        return $letras;
    }
}
