<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corregir una marcación sin perder la historia: la original queda anulada
 * (quién, cuándo, por qué) y apunta a la que la reemplaza. Para quien revisa
 * es «editar la hora»; para una auditoría, sigue todo ahí.
 *
 * Y una novedad deja de ser única por (marca, tipo): si se corrige la
 * entrada de un turno, la salida se vuelve a juzgar y puede generar otra
 * novedad del mismo tipo, mientras la anterior queda como «corregida».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->timestamp('anulada_at')->nullable()->after('agente');
            $table->foreignId('anulada_por')->nullable()->after('anulada_at')->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable()->after('anulada_por');
            $table->foreignId('reemplazada_por_id')->nullable()->after('motivo_anulacion')->constrained('marcaciones')->nullOnDelete();
        });

        Schema::table('novedades_turno', function (Blueprint $table) {
            // Primero el índice simple: MySQL usa el único para la llave foránea
            // de marcacion_id y no deja quitarlo si no hay otro que lo sustituya.
            $table->index('marcacion_id');
            $table->dropUnique(['marcacion_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('novedades_turno', function (Blueprint $table) {
            $table->unique(['marcacion_id', 'tipo']);
            $table->dropIndex(['marcacion_id']);
        });

        Schema::table('marcaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reemplazada_por_id');
            $table->dropColumn('motivo_anulacion');
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn('anulada_at');
        });
    }
};
