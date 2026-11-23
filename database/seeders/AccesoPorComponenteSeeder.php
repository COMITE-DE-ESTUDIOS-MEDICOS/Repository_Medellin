<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DOC-25: Calle con sus cuatro módulos, Básica y Estabilización sin ninguno,
 * y lo que ya existía en Inclusión Social pasa a Calle (usuarios de Lectura,
 * Edición y Coordinación; personas de campo sin componente). Administración
 * queda sin componente.
 *
 * Idempotente y solo sobre Inclusión Social: lo corren el DatabaseSeeder y la
 * migración 2026_11_24_000002 en producción. Con DB::table y no con modelos,
 * para que un cambio futuro de un modelo no rompa la migración.
 */
class AccesoPorComponenteSeeder extends Seeder
{
    public const MODULOS_CALLE = ['documentos', 'turnos', 'derivaciones', 'supervisiones'];

    public function run(): void
    {
        $inclusion = DB::table('dependencias')->where('slug', 'inclusion-social')->value('id');
        $calle = $inclusion === null ? null : DB::table('componentes')
            ->where('dependencia_id', $inclusion)->where('slug', 'calle')->value('id');

        if ($calle === null) {
            return;
        }

        DB::table('componentes')->where('id', $calle)->whereNull('modulos')
            ->update(['modulos' => json_encode(self::MODULOS_CALLE)]);

        foreach (['basica' => 'Básica', 'estabilizacion' => 'Estabilización'] as $slug => $nombre) {
            $existe = DB::table('componentes')->where('dependencia_id', $inclusion)->where('slug', $slug)->exists();

            if (! $existe) {
                DB::table('componentes')->insert([
                    'dependencia_id' => $inclusion,
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'activo' => true,
                    'config' => null,
                    'modulos' => '[]',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('dependencia_usuario')
            ->where('dependencia_id', $inclusion)
            ->whereIn('rol', ['lectura', 'edicion', 'coordinacion'])
            ->whereNull('componente_id')
            ->update(['componente_id' => $calle]);

        DB::table('colaboradores')
            ->where('dependencia_id', $inclusion)
            ->whereNull('componente_id')
            ->update(['componente_id' => $calle]);
    }
}
