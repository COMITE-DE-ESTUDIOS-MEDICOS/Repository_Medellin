<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién aprueba las novedades de turno: Coordinación y Administración
     * siempre; de Edición, solo a quien se le marque. Va por dependencia,
     * junto al rol.
     */
    public function up(): void
    {
        Schema::table('dependencia_usuario', function (Blueprint $table) {
            $table->boolean('aprueba_novedades')->default(false)->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('dependencia_usuario', function (Blueprint $table) {
            $table->dropColumn('aprueba_novedades');
        });
    }
};
