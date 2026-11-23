<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** DOC-24: marca los casos que entraron por la carga única de casos ya atendidos. */
    public function up(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->boolean('carga_masiva')->default(false)->after('gestion_realizada');
        });
    }

    public function down(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->dropColumn('carga_masiva');
        });
    }
};
