<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DOC-12: al marcar entrada la persona dice si recibió los EPP. Null es
     * «sin dato»: las salidas, las correcciones y lo marcado antes de esto.
     */
    public function up(): void
    {
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->boolean('epp_recibidos')->nullable()->after('sin_ubicacion');
        });
    }

    public function down(): void
    {
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->dropColumn('epp_recibidos');
        });
    }
};
