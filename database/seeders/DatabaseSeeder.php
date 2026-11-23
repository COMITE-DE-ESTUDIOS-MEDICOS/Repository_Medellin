<?php

namespace Database\Seeders;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tiposGlobales = [
            'Acta', 'Informe', 'Contrato', 'Resolución', 'Circular',
            'Presentación', 'Formato', 'Otro',
        ];

        foreach ($tiposGlobales as $nombre) {
            TipoDocumento::firstOrCreate(
                ['dependencia_id' => null, 'slug' => str($nombre)->slug()->toString()],
                ['nombre' => $nombre],
            );
        }

        // Fase 1 del proyecto: piloto en Inclusión Social.
        $inclusion = Dependencia::firstOrCreate(
            ['slug' => 'inclusion-social'],
            [
                'nombre' => 'Inclusión Social',
                'descripcion' => 'Piloto del repositorio documental centralizado.',
            ],
        );

        // Fase 2 prevista: misma plataforma, accesos independientes.
        Dependencia::firstOrCreate(
            ['slug' => 'salud-mental'],
            [
                'nombre' => 'Salud Mental',
                'descripcion' => 'Dependencia prevista para la fase de extensión.',
                'activa' => false,
            ],
        );

        // Se entra con el documento, no con el correo.
        $admin = User::firstOrCreate(
            ['documento' => '1234567890'],
            [
                'name' => 'Administrador del repositorio',
                'email' => 'admin@menteplena.com.co',
                'cargo' => 'Administración de la plataforma',
                'password' => Hash::make('Cambiar2026'),
                'activo' => true,
                'es_superadmin' => true,
            ],
        );

        $admin->dependencias()->syncWithoutDetaching([
            $inclusion->id => ['rol' => RolDependencia::Administracion->value],
        ]);

        foreach (['Actas de comité', 'Informes', 'Normativa', 'Formatos'] as $nombre) {
            Carpeta::firstOrCreate([
                'dependencia_id' => $inclusion->id,
                'carpeta_id' => null,
                'nombre' => $nombre,
            ], [
                'creado_por' => $admin->id,
            ]);
        }

        $this->call(CalleSeeder::class);
        $this->call(AccesoPorComponenteSeeder::class);

        $this->command?->info('Usuario inicial — documento: 1234567890 / contraseña: Cambiar2026');
        $this->command?->warn('Cambia ese documento y esa contraseña antes de exponer la plataforma.');
    }
}
