<?php

namespace App\Services;

use App\Models\Carpeta;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Única puerta de entrada y salida de los archivos físicos. Todo pasa por el
 * sistema de archivos de Laravel, así que mover el piloto a S3 es cambiar
 * 'disco' en config/repositorio.php.
 */
class AlmacenamientoDocumentos
{
    public function disco(): string
    {
        return config('repositorio.disco');
    }

    /**
     * Guarda el archivo como una versión nueva del documento.
     * Nunca sobrescribe: cada carga es una versión con su propio archivo.
     */
    public function guardarVersion(
        Documento $documento,
        UploadedFile $archivo,
        ?string $comentario = null,
    ): DocumentoVersion {
        $numero = $documento->proximoNumeroVersion();
        $extension = strtolower($archivo->getClientOriginalExtension());

        // El mime se detecta del contenido, no se acepta el que declara el
        // navegador: de él dependen la previsualización y el Content-Type con
        // que se devuelve el archivo.
        $mime = $archivo->getMimeType() ?: $archivo->getClientMimeType();

        $directorio = sprintf('documentos/%d/%d', $documento->dependencia_id, $documento->id);
        $nombreEnDisco = sprintf('v%d-%s.%s', $numero, Str::uuid(), $extension ?: 'bin');

        $ruta = $archivo->storeAs($directorio, $nombreEnDisco, ['disk' => $this->disco()]);

        return $documento->versiones()->create([
            'numero' => $numero,
            'ruta' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'extension' => $extension,
            'mime' => $mime,
            'tamano' => $archivo->getSize(),
            'hash' => hash_file('sha256', $archivo->getRealPath()),
            'comentario' => $comentario,
            'subido_por' => auth()->id(),
        ]);
    }

    /**
     * Copia física de una versión como versión 1 de otro documento. Archivo
     * propio y no la misma ruta: inactivar o versionar uno no toca al otro.
     */
    public function copiarVersion(DocumentoVersion $origen, Documento $destino, string $comentario): DocumentoVersion
    {
        $ruta = sprintf(
            'documentos/%d/%d/v1-%s.%s',
            $destino->dependencia_id, $destino->id, Str::uuid(), $origen->extension ?: 'bin',
        );

        abort_unless(Storage::disk($this->disco())->copy($origen->ruta, $ruta), 500, 'No se pudo copiar el archivo.');

        return $destino->versiones()->create([
            'numero' => 1,
            'ruta' => $ruta,
            'comentario' => $comentario,
            'subido_por' => auth()->id(),
        ] + $origen->only(['nombre_original', 'extension', 'mime', 'tamano', 'hash']));
    }

    /**
     * Documento nuevo en otra carpeta con los mismos datos, etiquetas y un
     * archivo propio. Null si el original no tiene archivo en disco.
     */
    public function copiarDocumento(Documento $documento, ?int $carpetaId): ?Documento
    {
        $origen = $documento->versionActual;

        if ($origen === null || ! $this->existe($origen)) {
            return null;
        }

        $copia = Documento::create([
            'carpeta_id' => $carpetaId,
            'creado_por' => auth()->id(),
            'actualizado_por' => auth()->id(),
        ] + $documento->only(['dependencia_id', 'tipo_documento_id', 'nombre', 'descripcion', 'fecha_documento']));

        $copia->etiquetas()->sync($documento->etiquetas()->pluck('etiquetas.id'));

        $this->copiarVersion($origen, $copia, "Copia de «{$documento->nombre}»");

        return $copia;
    }

    /**
     * Copia una carpeta con sus subcarpetas y documentos activos dentro de
     * otra (o en la raíz). Lo inactivo no se copia: copiar no resucita lo que
     * alguien retiró. Devuelve la carpeta nueva y cuántos documentos copió.
     *
     * Quien llama abre la transacción y comprueba que el destino no sea la
     * carpeta misma ni una de sus subcarpetas.
     *
     * @return array{0: Carpeta, 1: int}
     */
    public function copiarCarpeta(Carpeta $carpeta, ?int $padreId): array
    {
        $documentos = 0;
        $raiz = null;
        $pendientes = [[$carpeta, $padreId]];

        while ($pendientes) {
            [$original, $padre] = array_shift($pendientes);

            $nueva = Carpeta::create([
                'dependencia_id' => $original->dependencia_id,
                'carpeta_id' => $padre,
                'nombre' => $original->nombre,
                'descripcion' => $original->descripcion,
                'creado_por' => auth()->id(),
            ]);
            $raiz ??= $nueva;

            foreach ($original->documentos()->activos()->with('versionActual', 'etiquetas')->get() as $documento) {
                $documentos += $this->copiarDocumento($documento, $nueva->id) ? 1 : 0;
            }

            foreach ($original->hijas()->activas()->get() as $hija) {
                $pendientes[] = [$hija, $nueva->id];
            }
        }

        return [$raiz, $documentos];
    }

    /** Ruta absoluta en disco, para armar el ZIP sin cargar el archivo en memoria. */
    public function rutaLocal(DocumentoVersion $version): string
    {
        return Storage::disk($this->disco())->path($version->ruta);
    }

    public function eliminar(string $ruta): void
    {
        Storage::disk($this->disco())->delete($ruta);
    }

    public function existe(DocumentoVersion $version): bool
    {
        return $this->existeRuta($version->ruta);
    }

    public function existeRuta(string $ruta): bool
    {
        return Storage::disk($this->disco())->exists($ruta);
    }

    public function contenidoDeRuta(string $ruta): ?string
    {
        return Storage::disk($this->disco())->get($ruta);
    }

    public function descargar(DocumentoVersion $version)
    {
        abort_unless($this->existe($version), 404, 'El archivo ya no está disponible en el servidor.');

        return Storage::disk($this->disco())->download($version->ruta, $version->nombre_original);
    }

    public function contenido(DocumentoVersion $version): ?string
    {
        return $this->contenidoDeRuta($version->ruta);
    }
}
