<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DOC-20: el caso lo crea la oficina (DC-001) y la persona de campo lo
     * atiende. Las derivaciones anteriores quedan con numero = null
     * («registro anterior»). Quién atiende se sabe hasta que atiende: esas
     * columnas dejan de ser obligatorias.
     */
    public function up(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->unsignedInteger('numero')->nullable()->after('id');
            $table->string('remitente', 20)->nullable()->after('entidad');
            $table->foreignId('creado_por')->nullable()->after('enlace_turno_id')->constrained('users')->nullOnDelete();
            $table->string('clave_formulario', 64)->nullable()->unique()->after('creado_por');
            $table->timestamp('atendida_at')->nullable()->after('respondida_por');
            $table->boolean('cumplio_requerimiento')->nullable()->after('atendida_at');
            $table->text('observacion')->nullable()->after('cumplio_requerimiento');

            $table->foreignId('colaborador_id')->nullable()->change();
            $table->string('cedula', 20)->nullable()->change();
            $table->string('nombre')->nullable()->change();

            // El código no se repite en la dependencia (los NULL anteriores no chocan).
            $table->unique(['dependencia_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->dropUnique(['dependencia_id', 'numero']);
            $table->dropUnique(['clave_formulario']);
            $table->dropConstrainedForeignId('creado_por');
            $table->dropColumn(['numero', 'remitente', 'clave_formulario', 'atendida_at', 'cumplio_requerimiento', 'observacion']);
        });
    }
};
