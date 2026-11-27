<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\CasoCctv;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * DOC-34: completar, desde la ficha, una evidencia vacía de un caso de
 * Derivaciones (Solucionado) o de Seguridad CCTV (Atendido o Cerrado).
 * Nunca reemplaza una evidencia y no cambia nada más del caso.
 */
class EvidenciaFichaController extends Controller
{
    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {}

    public function derivacion(Request $request, Derivacion $derivacion, int $n): RedirectResponse
    {
        Gate::authorize('cargarEvidencia', $derivacion);

        return $this->cargar($request, $derivacion, $n, 'admin.turnos.derivaciones.show');
    }

    public function cctv(Request $request, CasoCctv $caso, int $n): RedirectResponse
    {
        Gate::authorize('cargarEvidencia', $caso);

        return $this->cargar($request, $caso, $n, 'admin.turnos.cctv.show');
    }

    private function cargar(Request $request, Derivacion|CasoCctv $caso, int $n, string $ruta): RedirectResponse
    {
        $campo = "evidencia{$n}";
        $columna = "{$campo}_documento_id";

        $archivo = $request->validate(
            [$campo => ['required', 'image', 'mimetypes:'.implode(',', config('repositorio.formatos.imagenes.mimetypes')), 'max:'.config('repositorio.tamano_maximo_kb')]],
            [
                "{$campo}.required" => 'Elige la imagen de la evidencia.',
                "{$campo}.image" => 'La evidencia debe ser una imagen (JPG, PNG…)',
                "{$campo}.mimetypes" => 'La evidencia debe ser una imagen (JPG, PNG…)',
                "{$campo}.max" => 'La evidencia no puede pasar de 25 MB',
            ],
        )[$campo];

        $rutaArchivo = null;

        try {
            $documento = DB::transaction(function () use ($caso, $n, $campo, $columna, $archivo, &$rutaArchivo): Documento {
                // Dos cargas a la vez: la segunda espera aquí y encuentra la tarjeta llena (el modelo de la ruta puede estar viejo).
                $actual = $caso::whereKey($caso->getKey())->lockForUpdate()->firstOrFail();

                if ($actual->{$columna} !== null) {
                    throw ValidationException::withMessages([$campo => 'Esta evidencia ya tiene un archivo; no se reemplaza']);
                }

                $usuario = (int) auth()->id();
                $documento = Documento::create([
                    'dependencia_id' => $actual->dependencia_id,
                    'carpeta_id' => $caso::carpetaPara($actual->componente)->id,
                    'nombre' => "Caso {$actual->codigo()} · Evidencia {$n} · {$actual->nombre}",
                    'fecha_documento' => today()->toDateString(),
                    'creado_por' => $usuario,
                    'actualizado_por' => $usuario,
                ]);

                $rutaArchivo = $this->almacenamiento
                    ->guardarVersion($documento, $archivo, "Evidencia {$n} del caso {$actual->codigo()} (cargada desde la ficha)")->ruta;

                $actual->forceFill([$columna => $documento->id])->save();

                return $documento;
            });
        } catch (Throwable $e) {
            // El documento se deshace con la transacción; el archivo físico, aquí.
            if ($rutaArchivo !== null) {
                $this->almacenamiento->eliminar($rutaArchivo);
            }

            throw $e;
        }

        $this->auditor->registrar(
            AccionAuditoria::EvidenciaCargadaDesdeFicha,
            $caso,
            "Cargó la evidencia {$n} de {$caso->codigo()} desde la ficha",
            ['evidencia' => $n, 'documento' => $documento->id],
        );

        return redirect()->route($ruta, $caso)->with('exito', "Evidencia {$n} cargada.");
    }
}
