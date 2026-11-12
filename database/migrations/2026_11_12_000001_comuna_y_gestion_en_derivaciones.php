<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DOC-23: la comuna del caso (número de config('repositorio.comunas')) y
     * lo que hizo la persona de campo al atender. Los anteriores quedan en null («—»).
     */
    public function up(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->unsignedTinyInteger('comuna')->nullable()->index()->after('ubicacion');
            $table->text('gestion_realizada')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->dropIndex(['comuna']);
            $table->dropColumn(['comuna', 'gestion_realizada']);
        });
    }
};
