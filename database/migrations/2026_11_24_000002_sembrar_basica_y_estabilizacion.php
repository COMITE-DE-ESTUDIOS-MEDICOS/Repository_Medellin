<?php

use Database\Seeders\AccesoPorComponenteSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * DOC-25. El despliegue solo corre 'migrate': esta es la vía para que Calle
 * tenga sus módulos y Básica y Estabilización existan en producción. El
 * down() no borra nada: para entonces puede haber usuarios en ellos.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new AccesoPorComponenteSeeder)->run();
    }

    public function down(): void
    {
        //
    }
};
