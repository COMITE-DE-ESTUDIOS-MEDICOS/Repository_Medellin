<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La derivación llega pendiente y el tiempo corre desde el requerimiento
     * hasta que un líder la responde en Documenta. Las que ya existían
     * traían su hora de respuesta: quedan solucionadas.
     */
    public function up(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->string('estado', 20)->default('pendiente')->after('ubicacion');
            $table->text('respuesta')->nullable()->after('estado');
            $table->time('hora_respuesta')->nullable()->change();
            $table->unsignedInteger('tiempo_respuesta_min')->nullable()->change();
            $table->timestamp('respondida_at')->nullable()->after('justificacion');
            $table->foreignId('respondida_por')->nullable()->after('respondida_at')->constrained('users')->nullOnDelete();
            $table->timestamp('ultimo_aviso_at')->nullable()->after('respondida_por');

            $table->index(['estado', 'ultimo_aviso_at']);
        });

        DB::table('derivaciones')->update(['estado' => 'solucionada']);
    }

    public function down(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->dropIndex(['estado', 'ultimo_aviso_at']);
            $table->dropConstrainedForeignId('respondida_por');
            $table->dropColumn(['estado', 'respuesta', 'respondida_at', 'ultimo_aviso_at']);
        });
    }
};
