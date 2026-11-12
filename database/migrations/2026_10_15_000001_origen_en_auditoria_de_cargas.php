<?php

use App\Enums\AccionAuditoria;
use App\Models\Documento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las cargas registradas antes de que la auditoría guardara el origen y la
 * carpeta. Lo que llegó por enlace se sabe con certeza (la recepción guarda
 * la carpeta del enlace); lo cargado en la aplicación solo se conoce por
 * dónde está hoy el documento, y si se movió después se dice así.
 */
return new class extends Migration
{
    public function up(): void
    {
        $carpetas = DB::table('carpetas')->get(['id', 'carpeta_id', 'nombre'])->keyBy('id');

        $ruta = function (?int $id) use ($carpetas): string {
            $nombres = [];

            for ($saltos = 0; $id !== null && isset($carpetas[$id]) && $saltos < 50; $saltos++) {
                array_unshift($nombres, $carpetas[$id]->nombre);
                $id = $carpetas[$id]->carpeta_id;
            }

            return $nombres ? implode(' / ', $nombres) : 'Raíz';
        };

        DB::table('auditorias')
            ->whereIn('accion', [AccionAuditoria::DocumentoCreado->value, AccionAuditoria::RecepcionRecibida->value])
            ->where('auditable_type', (new Documento)->getMorphClass())
            ->orderBy('id')
            ->chunkById(500, function ($registros) use ($ruta) {
                foreach ($registros as $registro) {
                    $datos = json_decode($registro->datos ?? 'null', true) ?: [];

                    if (isset($datos['carpeta'])) {
                        continue;
                    }

                    if ($registro->accion === AccionAuditoria::RecepcionRecibida->value) {
                        $carpetaId = isset($datos['recepcion'])
                            ? DB::table('recepciones')->where('id', $datos['recepcion'])->value('carpeta_sugerida_id')
                            : null;

                        if ($carpetaId === null && ! isset($datos['recepcion'])) {
                            continue; // un intento rechazado, no una carga
                        }

                        $datos = ['origen' => 'Enlace de carga', 'carpeta' => $ruta($carpetaId)] + $datos;
                    } else {
                        $actual = DB::table('documentos')->where('id', $registro->auditable_id)->value('carpeta_id');

                        $movido = DB::table('auditorias')
                            ->where('auditable_type', $registro->auditable_type)
                            ->where('auditable_id', $registro->auditable_id)
                            ->where('id', '>', $registro->id)
                            ->where('datos', 'like', '%carpeta_id%')
                            ->exists();

                        $datos = [
                            'origen' => 'Aplicación',
                            'carpeta' => $ruta($actual).($movido ? ' (carpeta actual; se movió después)' : ''),
                        ] + $datos;
                    }

                    DB::table('auditorias')->where('id', $registro->id)
                        ->update(['datos' => json_encode($datos, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        // Solo añade datos que faltaban: no hay nada que deshacer.
    }
};
