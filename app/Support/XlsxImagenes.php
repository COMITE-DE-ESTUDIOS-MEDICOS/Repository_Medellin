<?php

namespace App\Support;

use DOMDocument;
use ZipArchive;

/**
 * Dónde están las imágenes de la primera hoja de un .xlsx: celda («P5») =>
 * archivo dentro del ZIP («xl/media/image3.png»). Excel las guarda de dos
 * formas y se leen las dos:
 *
 * - «Colocar en la celda»: la celda lleva vm="N", que por metadata.xml y
 *   richData/ llega a la imagen.
 * - «Colocar sobre las celdas» (flotante): un dibujo anclado; cuenta la celda
 *   donde está su esquina superior izquierda.
 *
 * ponytail: una flotante mal puesta (la esquina en la celda vecina) queda en
 * la celda vecina; no se adivina. Las imágenes de WPS (=DISPIMG) no se leen.
 */
class XlsxImagenes
{
    /** @return array<string, string> */
    public static function dePrimeraHoja(ZipArchive $zip): array
    {
        $libro = self::xml($zip, 'xl/workbook.xml');
        $primera = $libro?->getElementsByTagNameNS('*', 'sheet')->item(0);
        $hoja = self::relaciones($zip, 'xl/workbook.xml')[self::rid($primera)]['destino'] ?? null;

        if ($hoja === null || ($xml = self::xml($zip, $hoja)) === null) {
            return [];
        }

        // La imagen en la celda manda sobre una flotante en la misma celda.
        return self::enCelda($zip, $xml) + self::flotantes($zip, $hoja);
    }

    protected static function flotantes(ZipArchive $zip, string $hoja): array
    {
        $imagenes = [];

        foreach (self::relaciones($zip, $hoja) as $relacion) {
            if (! str_ends_with($relacion['tipo'], '/drawing') || ($dibujo = self::xml($zip, $relacion['destino'])) === null) {
                continue;
            }

            $medios = self::relaciones($zip, $relacion['destino']);

            foreach ($dibujo->getElementsByTagNameNS('*', 'from') as $desde) {
                $blip = $desde->parentNode->getElementsByTagNameNS('*', 'blip')->item(0);
                $destino = $medios[$blip?->getAttributeNS(self::R, 'embed')]['destino'] ?? null;
                $columna = $desde->getElementsByTagNameNS('*', 'col')->item(0)?->textContent;
                $fila = $desde->getElementsByTagNameNS('*', 'row')->item(0)?->textContent;

                if ($destino !== null && is_numeric($columna) && is_numeric($fila)) {
                    $imagenes[Xlsx::columna((int) $columna).((int) $fila + 1)] ??= $destino;
                }
            }
        }

        return $imagenes;
    }

    protected static function enCelda(ZipArchive $zip, DOMDocument $hoja): array
    {
        $celdas = [];
        foreach ($hoja->getElementsByTagNameNS('*', 'c') as $c) {
            if ($c->hasAttribute('vm')) {
                $celdas[$c->getAttribute('r')] = (int) $c->getAttribute('vm');
            }
        }

        $metadatos = self::xml($zip, 'xl/metadata.xml');
        $valores = self::xml($zip, 'xl/richData/rdrichvalue.xml');
        $relaciones = self::xml($zip, 'xl/richData/richValueRel.xml');

        if ($celdas === [] || $metadatos === null || $valores === null || $relaciones === null) {
            return [];
        }

        // vm (desde 1) → valueMetadata/bk → futureMetadata/bk → rvb@i → rv → rel → archivo.
        $bloques = fn (string $padre) => iterator_to_array($metadatos->getElementsByTagNameNS('*', $padre)->item(0)?->getElementsByTagNameNS('*', 'bk') ?? []);
        $deValor = $bloques('valueMetadata');
        $futuros = $bloques('futureMetadata');
        $rv = $valores->getElementsByTagNameNS('*', 'rv');
        $rel = $relaciones->getElementsByTagNameNS('*', 'rel');
        $medios = self::relaciones($zip, 'xl/richData/richValueRel.xml');
        $posiciones = self::posicionDeLaImagen($zip);

        $imagenes = [];

        foreach ($celdas as $celda => $vm) {
            $rc = ($deValor[$vm - 1] ?? null)?->getElementsByTagNameNS('*', 'rc')->item(0);
            $rvb = $rc === null ? null : ($futuros[(int) $rc->getAttribute('v')] ?? null)?->getElementsByTagNameNS('*', 'rvb')->item(0);
            $valor = $rvb === null ? null : $rv->item((int) $rvb->getAttribute('i'));

            if ($valor === null) {
                continue;
            }

            $indice = $valor->getElementsByTagNameNS('*', 'v')->item($posiciones[(int) $valor->getAttribute('s')] ?? 0)?->textContent;
            $destino = is_numeric($indice) ? ($medios[self::rid($rel->item((int) $indice))]['destino'] ?? null) : null;

            if ($destino !== null) {
                $imagenes[$celda] = $destino;
            }
        }

        return $imagenes;
    }

    /** En cada estructura de valor, en qué posición va la referencia a la imagen (casi siempre la 0). */
    protected static function posicionDeLaImagen(ZipArchive $zip): array
    {
        $posiciones = [];

        foreach (self::xml($zip, 'xl/richData/rdrichvaluestructure.xml')?->getElementsByTagNameNS('*', 's') ?? [] as $n => $estructura) {
            foreach ($estructura->getElementsByTagNameNS('*', 'k') as $i => $clave) {
                if (str_starts_with($clave->getAttribute('n'), '_rvRel')) {
                    $posiciones[$n] = $i;
                    break;
                }
            }
        }

        return $posiciones;
    }

    protected const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    protected static function rid(?\DOMElement $nodo): string
    {
        return (string) $nodo?->getAttributeNS(self::R, 'id');
    }

    protected static function xml(ZipArchive $zip, string $nombre): ?DOMDocument
    {
        $texto = $zip->getFromName($nombre);
        $xml = new DOMDocument;

        return $texto !== false && @$xml->loadXML($texto, LIBXML_NONET) ? $xml : null;
    }

    /** @return array<string, array{tipo: string, destino: string}> rId => relación, con la ruta ya resuelta dentro del ZIP */
    protected static function relaciones(ZipArchive $zip, string $parte): array
    {
        $carpeta = dirname($parte);
        $relaciones = [];

        foreach (self::xml($zip, $carpeta.'/_rels/'.basename($parte).'.rels')?->getElementsByTagName('Relationship') ?? [] as $r) {
            $destino = $r->getAttribute('Target');
            $partes = [];

            foreach (explode('/', str_starts_with($destino, '/') ? ltrim($destino, '/') : $carpeta.'/'.$destino) as $tramo) {
                $tramo === '..' ? array_pop($partes) : $partes[] = $tramo;
            }

            $relaciones[$r->getAttribute('Id')] = ['tipo' => $r->getAttribute('Type'), 'destino' => implode('/', $partes)];
        }

        return $relaciones;
    }
}
