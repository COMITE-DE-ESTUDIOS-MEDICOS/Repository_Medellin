<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOC-25: los módulos que abre cada componente (Componente::MODULOS) y el
 * componente de cada usuario en cada dependencia (null = Administración o
 * sin componente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('componentes', function (Blueprint $table) {
            $table->json('modulos')->nullable()->after('config');
        });

        Schema::table('dependencia_usuario', function (Blueprint $table) {
            $table->foreignId('componente_id')->nullable()->after('aprueba_novedades')
                ->constrained('componentes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dependencia_usuario', fn (Blueprint $table) => $table->dropConstrainedForeignId('componente_id'));
        Schema::table('componentes', fn (Blueprint $table) => $table->dropColumn('modulos'));
    }
};
