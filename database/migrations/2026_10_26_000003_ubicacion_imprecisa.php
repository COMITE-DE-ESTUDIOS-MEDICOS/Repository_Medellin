<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DOC-15: la ubicación que llegó con un error mayor a la precisión mínima
     * del componente (típico de un iPhone con «Ubicación precisa» apagada). Se
     * guarda el juicio al marcar, como fuera_de_zona y sin_ubicacion.
     */
    public function up(): void
    {
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->boolean('ubicacion_imprecisa')->default(false)->after('sin_ubicacion');
        });
    }

    public function down(): void
    {
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->dropColumn('ubicacion_imprecisa');
        });
    }
};
