<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOC-33 Seguridad CCTV: los casos que la oficina pasa a una persona de campo
 * y la casilla «Responsable de Seguridad CCTV» (por dependencia, como «Aprueba novedades»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependencia_usuario', function (Blueprint $table) {
            $table->boolean('responsable_cctv')->default(false)->after('aprueba_novedades');
        });

        Schema::create('casos_cctv', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('componente_id')->constrained('componentes')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->uuid('clave_formulario')->unique();
            $table->string('estado', 10)->default('abierto');
            $table->string('entidad', 20);
            $table->string('entidad_otra')->nullable();
            $table->dateTime('requerido_at')->index();
            $table->foreignId('colaborador_asignado_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('nodo_id')->nullable()->constrained('nodos')->nullOnDelete();
            $table->text('descripcion');
            $table->string('ubicacion', 500);
            $table->unsignedTinyInteger('comuna')->nullable();
            $table->foreignId('soporte_documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('evidencia1_documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('evidencia2_documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('evidencia3_documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            // Quién lo atendió (copia, como en derivaciones).
            $table->foreignId('colaborador_id')->nullable()->constrained('colaboradores')->nullOnDelete();
            $table->string('cedula', 30)->nullable();
            $table->string('nombre')->nullable();
            $table->string('cargo')->nullable();
            $table->foreignId('enlace_turno_id')->nullable()->constrained('enlaces_turno')->nullOnDelete();
            $table->dateTime('atendido_at')->nullable();
            $table->unsignedInteger('tiempo_respuesta_min')->nullable();
            $table->text('justificacion')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cerrado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->unique(['dependencia_id', 'numero']);
            $table->index(['dependencia_id', 'componente_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos_cctv');
        Schema::table('dependencia_usuario', fn (Blueprint $table) => $table->dropColumn('responsable_cctv'));
    }
};
