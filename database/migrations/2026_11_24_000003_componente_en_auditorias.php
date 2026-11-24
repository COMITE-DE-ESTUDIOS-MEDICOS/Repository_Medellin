<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOC-25 (PA-06, decisión de Edwar 2026-11-24): cada registro guarda el
 * componente al que pertenece, para que Coordinación vea lo de su componente
 * (también lo que llega por la vía pública y lo automático) y nada de otro.
 * Los anteriores quedan sin componente: todo era de Calle, y lo sin componente
 * lo ve el componente que tiene Documentos (AuditoriaController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->foreignId('componente_id')->nullable()->after('dependencia_id')->constrained('componentes');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('componente_id');
        });
    }
};
