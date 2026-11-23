<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Models\Carpeta;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Los archivos nunca se exponen por URL directa del disco: cada descarga
 * pasa por aquí, valida permisos y queda registrada.
 */
class DescargaController extends Controller
{
    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {
    }

    public function descargar(Documento $documento): StreamedResponse
    {
        $this->authorize('download', $documento);

        $version = $documento->versionActual;
        abort_if($version === null, 404, 'El documento no tiene archivo asociado.');

        $this->auditor->registrar(
            AccionAuditoria::DocumentoDescargado,
            $documento,
            "Descargó «{$documento->nombre}»",
            ['version' => $version->numero],
        );

        return $this->almacenamiento->descargar($version);
    }

    public function descargarVersion(Documento $documento, DocumentoVersion $version): StreamedResponse
    {
        $this->authorize('verVersiones', $documento);
        abort_unless($version->documento_id === $documento->id, 404);

        $this->auditor->registrar(
            AccionAuditoria::DocumentoDescargado,
            $documento,
            "Descargó la versión {$version->numero} de «{$documento->nombre}»",
            ['version' => $version->numero],
        );

        return $this->almacenamiento->descargar($version);
    }

    /**
     * La carpeta entera en un ZIP, con sus subcarpetas. Entra solo lo que
     * quien descarga puede ver, y nunca lo inactivo: ni documentos ni
     * carpetas inactivadas (con todo lo que cuelga de ellas), aunque quien
     * descarga sí los vea en pantalla.
     */
    public function descargarCarpeta(Request $request, Carpeta $carpeta): BinaryFileResponse
    {
        $this->authorize('view', $carpeta);
        $usuario = $request->user();

        $zip = new ZipArchive();
        $temporal = tempnam(sys_get_temp_dir(), 'carpeta');
        $zip->open($temporal, ZipArchive::OVERWRITE);

        $total = 0;
        $pendientes = [[$carpeta, $this->nombreSeguro($carpeta->nombre)]];

        // ponytail: una consulta por carpeta y addFile sobre el disco local;
        // con árboles de cientos de carpetas, cargar el árbol de una vez, y
        // si el disco pasa a S3, bajar cada archivo a un temporal primero.
        while ($pendientes) {
            [$actual, $prefijo] = array_shift($pendientes);
            $zip->addEmptyDir($prefijo);
            $usados = [];

            $documentos = Documento::visiblesPara($usuario)
                ->activos()
                ->where('carpeta_id', $actual->id)
                ->with('versionActual')
                ->get();

            foreach ($documentos as $documento) {
                $version = $documento->versionActual;

                if ($version === null || ! $this->almacenamiento->existe($version)) {
                    continue;
                }

                $nombre = $this->nombreUnico(
                    $this->nombreSeguro($documento->nombre),
                    $version->extension,
                    $usados,
                );
                $zip->addFile($this->almacenamiento->rutaLocal($version), "{$prefijo}/{$nombre}");
                $total++;
            }

            $hijas = Carpeta::visiblesPara($usuario)->activas()->where('carpeta_id', $actual->id)->get();

            foreach ($hijas as $hija) {
                $pendientes[] = [$hija, $prefijo.'/'.$this->nombreSeguro($hija->nombre)];
            }
        }

        $zip->close();

        $this->auditor->registrar(
            AccionAuditoria::CarpetaDescargada,
            $carpeta,
            "Descargó la carpeta «{$carpeta->nombre}» ({$total} documentos)",
            ['documentos' => $total],
        );

        return response()
            ->download($temporal, $this->nombreSeguro($carpeta->nombre).'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Sin caracteres que Windows rechaza y con tope de largo: el nombre del
     * documento ya no tiene límite, pero una ruta de Windows sí (260).
     */
    protected function nombreSeguro(string $nombre): string
    {
        $limpio = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $nombre), ' .');

        return Str::limit($limpio, 100, '') ?: 'sin nombre';
    }

    /** Dos documentos con el mismo nombre no se pisan dentro del ZIP. */
    protected function nombreUnico(string $base, ?string $extension, array &$usados): string
    {
        $sufijo = $extension ? ".{$extension}" : '';
        $nombre = $base.$sufijo;

        for ($i = 2; isset($usados[mb_strtolower($nombre)]); $i++) {
            $nombre = "{$base} ({$i}){$sufijo}";
        }

        $usados[mb_strtolower($nombre)] = true;

        return $nombre;
    }

    /** Previsualización en el navegador para PDF, imágenes y texto plano. */
    public function previsualizar(Documento $documento): Response
    {
        $this->authorize('view', $documento);

        $version = $documento->versionActual;
        abort_if($version === null, 404);
        abort_unless($documento->esPrevisualizable(), 404, 'Este tipo de archivo no se puede previsualizar.');
        abort_unless($this->almacenamiento->existe($version), 404);

        return response($this->almacenamiento->contenido($version), 200, [
            'Content-Type' => $version->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($version->nombre_original).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; object-src 'self'; img-src 'self'",
        ]);
    }
}
