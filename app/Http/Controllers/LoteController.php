<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Models\Carpeta;
use App\Models\Documento;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Copiar o mover varios a la vez: lo que se marcó con el check en el
 * explorador, a otra carpeta, sin descargar ni volver a subir.
 *
 * Todo o nada: si una pieza falla, no queda la mitad copiada. Copiar duplica
 * el archivo en el servidor (cada copia es independiente); mover solo cambia
 * la carpeta, ningún archivo se toca en disco.
 */
class LoteController extends Controller
{
    /** Lo más que se procesa de una vez: más que esto, mejor en dos pasos. */
    public const MAXIMO = 200;

    public function __construct(
        protected ContextoDependencia $contexto,
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {
    }

    public function aplicar(Request $request): RedirectResponse
    {
        abort_unless($this->contexto->puedeEditar(), 403, 'No tienes permisos de edición en esta dependencia.');

        $datos = $request->validate([
            'accion' => ['required', Rule::in(['copiar', 'mover'])],
            'documentos' => ['nullable', 'array', 'max:'.self::MAXIMO],
            'documentos.*' => ['uuid'],
            'carpetas' => ['nullable', 'array', 'max:'.self::MAXIMO],
            'carpetas.*' => ['uuid'],
            'destino' => [
                'nullable',
                Rule::exists('carpetas', 'id')->where('dependencia_id', $this->contexto->id())->where('activa', true),
                new \App\Rules\CarpetaAccesible,
            ],
        ], [
            'documentos.max' => 'Se pueden procesar hasta '.self::MAXIMO.' documentos de una vez.',
        ], ['destino' => 'carpeta de destino']);

        $usuario = $request->user();

        // Solo lo que esa persona puede ver y editar: un uuid ajeno o retirado
        // no se cuela por el formulario.
        $documentos = Documento::visiblesPara($usuario)->whereIn('uuid', $datos['documentos'] ?? [])->get();
        $carpetas = Carpeta::visiblesPara($usuario)->whereIn('uuid', $datos['carpetas'] ?? [])->get();

        if ($documentos->isEmpty() && $carpetas->isEmpty()) {
            throw ValidationException::withMessages(['documentos' => 'Marca al menos un documento o una carpeta.']);
        }

        $documentos->each(fn (Documento $d) => $this->authorize('update', $d));
        $carpetas->each(fn (Carpeta $c) => $this->authorize('update', $c));

        $destino = isset($datos['destino']) ? Carpeta::find($datos['destino']) : null;
        abort_if($destino?->estaRetirada(), 422, 'La carpeta de destino está retirada.');

        // Una carpeta no puede ir dentro de sí misma ni de una de sus subcarpetas.
        foreach ($carpetas as $carpeta) {
            if ($destino && ($destino->id === $carpeta->id || $carpeta->esAncestroDe($destino))) {
                throw ValidationException::withMessages([
                    'destino' => "No puedes llevar «{$carpeta->nombre}» dentro de sí misma o de una de sus subcarpetas.",
                ]);
            }
        }

        $nombreDestino = $destino?->nombre ?? 'Raíz';

        [$nDocumentos, $nCarpetas] = $datos['accion'] === 'copiar'
            ? $this->copiar($documentos, $carpetas, $destino, $nombreDestino)
            : $this->mover($documentos, $carpetas, $destino, $nombreDestino, $usuario->id);

        $verbo = $datos['accion'] === 'copiar' ? 'Se copiaron' : 'Se movieron';
        $partes = array_filter([
            $nDocumentos ? $nDocumentos.' '.($nDocumentos === 1 ? 'documento' : 'documentos') : null,
            $nCarpetas ? $nCarpetas.' '.($nCarpetas === 1 ? 'carpeta' : 'carpetas') : null,
        ]);

        return back()->with('exito', $partes
            ? "{$verbo} ".implode(' y ', $partes)." a «{$nombreDestino}»."
            : "Nada que mover: ya estaba en «{$nombreDestino}».");
    }

    /** @return array{0: int, 1: int} documentos y carpetas copiados */
    protected function copiar($documentos, $carpetas, ?Carpeta $destino, string $nombreDestino): array
    {
        [$copiasDocs, $copiasCarpetas] = DB::transaction(function () use ($documentos, $carpetas, $destino) {
            $copiasDocs = [];
            $copiasCarpetas = [];

            foreach ($documentos as $documento) {
                if ($copia = $this->almacenamiento->copiarDocumento($documento->load('versionActual'), $destino?->id)) {
                    $copiasDocs[] = [$documento, $copia];
                }
            }

            foreach ($carpetas as $carpeta) {
                $copiasCarpetas[] = [$carpeta, ...$this->almacenamiento->copiarCarpeta($carpeta, $destino?->id)];
            }

            return [$copiasDocs, $copiasCarpetas];
        });

        // Una entrada de auditoría por cada pieza: la auditoría sigue
        // documentos y carpetas, no lotes.
        foreach ($copiasDocs as [$original, $copia]) {
            $this->auditor->registrar(AccionAuditoria::DocumentoCopiado, $copia,
                "Copió «{$original->nombre}» a la carpeta «{$nombreDestino}»", ['origen' => $original->uuid, 'lote' => true]);
        }

        foreach ($copiasCarpetas as [$original, $copia, $n]) {
            $this->auditor->registrar(AccionAuditoria::CarpetaCopiada, $copia,
                "Copió la carpeta «{$original->nombre}» a «{$nombreDestino}» ({$n} documentos)", ['origen' => $original->uuid, 'lote' => true]);
        }

        return [count($copiasDocs), count($copiasCarpetas)];
    }

    /** @return array{0: int, 1: int} documentos y carpetas movidos (lo que ya estaba ahí no cuenta) */
    protected function mover($documentos, $carpetas, ?Carpeta $destino, string $nombreDestino, int $usuarioId): array
    {
        $documentos = $documentos->filter(fn (Documento $d) => $d->carpeta_id !== $destino?->id);
        $carpetas = $carpetas->filter(fn (Carpeta $c) => $c->carpeta_id !== $destino?->id);

        DB::transaction(function () use ($documentos, $carpetas, $destino, $usuarioId) {
            $documentos->each(fn (Documento $d) => $d->update(['carpeta_id' => $destino?->id, 'actualizado_por' => $usuarioId]));
            $carpetas->each(fn (Carpeta $c) => $c->update(['carpeta_id' => $destino?->id]));
        });

        foreach ($documentos as $documento) {
            $this->auditor->registrar(AccionAuditoria::DocumentoActualizado, $documento,
                "Movió «{$documento->nombre}» a la carpeta «{$nombreDestino}»", ['cambios' => ['carpeta_id'], 'lote' => true]);
        }

        foreach ($carpetas as $carpeta) {
            $this->auditor->registrar(AccionAuditoria::CarpetaActualizada, $carpeta,
                "Movió la carpeta «{$carpeta->nombre}» a «{$nombreDestino}»", ['cambios' => ['carpeta_id'], 'lote' => true]);
        }

        return [$documentos->count(), $carpetas->count()];
    }
}
