<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un mismo enlace por cédula sirve para marcar turno o para reportar
        // una derivación: el token, el QR y la identificación son iguales.
        Schema::table('enlaces_turno', function (Blueprint $table) {
            $table->string('tipo', 20)->default('turno')->after('dependencia_id');
        });

        /*
         * Derivaciones y respuesta territorial: un caso que alguien de campo
         * escala y atiende. Las columnas siguen el orden del reporte en Excel.
         * Quién escala se guarda también como texto: el reporte dice lo que
         * era ese día aunque la persona cambie de cargo después.
         */
        Schema::create('derivaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('componente_id')->constrained('componentes')->restrictOnDelete();
            $table->foreignId('enlace_turno_id')->nullable()->constrained('enlaces_turno')->nullOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();

            $table->string('cedula', 20);
            $table->string('nombre');
            $table->string('cargo')->nullable();

            $table->foreignId('nodo_id')->nullable()->constrained('nodos')->nullOnDelete();
            $table->date('fecha_requerimiento');
            $table->time('hora_requerimiento');
            $table->string('entidad');
            $table->text('descripcion');
            $table->string('ubicacion', 500);
            $table->time('hora_respuesta');
            $table->unsignedInteger('tiempo_respuesta_min');
            $table->text('justificacion')->nullable();

            // Los archivos van al repositorio como documentos.
            $table->foreignId('soporte_documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('evidencia1_documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('evidencia2_documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('evidencia3_documento_id')->nullable()->constrained('documentos')->nullOnDelete();

            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dependencia_id', 'fecha_requerimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derivaciones');

        Schema::table('enlaces_turno', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
