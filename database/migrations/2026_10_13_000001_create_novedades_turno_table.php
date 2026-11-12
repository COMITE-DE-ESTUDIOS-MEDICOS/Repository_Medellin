<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que se sale de lo esperado en un turno (corto, horas extra, sin salida,
 * sin ubicación, fuera de zona) y su validación por Coordinación.
 *
 * Va aparte de las marcaciones a propósito: esas son inmutables, y aprobar o
 * rechazar es un juicio posterior con su propio autor y su propia fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('novedades_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('componente_id')->constrained('componentes')->restrictOnDelete();

            // La marca que la originó (la salida, o la entrada sin ubicación)
            // y, si aplica, la entrada con la que se midió la duración.
            $table->foreignId('marcacion_id')->constrained('marcaciones')->restrictOnDelete();
            $table->foreignId('entrada_id')->nullable()->constrained('marcaciones')->nullOnDelete();

            $table->string('tipo', 20);
            $table->string('detalle');
            $table->unsignedInteger('minutos')->nullable();

            $table->string('estado', 15)->default('pendiente');
            $table->text('comentario')->nullable();
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_at')->nullable();
            $table->timestamps();

            // Una misma marca no genera dos veces la misma novedad.
            $table->unique(['marcacion_id', 'tipo']);
            $table->index(['dependencia_id', 'estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novedades_turno');
    }
};
