<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOC-27 (plan D1): sedes con capacidad por vigencia, estadías (ingreso y
 * egreso en una fila), sus respuestas de varias opciones y su historial.
 * Ninguna lleva SoftDeletes: se anula, no se borra (constitución IV).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('componente_id')->constrained('componentes')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['componente_id', 'nombre']);
        });

        // Nunca se edita ni se borra una fila: cada cambio de capacidad es una fila nueva.
        Schema::create('sede_capacidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->unsignedInteger('capacidad');
            $table->date('vigente_desde');
            $table->text('motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['sede_id', 'vigente_desde']);
        });

        Schema::create('estadias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('componente_id')->constrained('componentes')->restrictOnDelete();
            $table->foreignId('usuario_hc_id')->constrained('usuarios_hc')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // El nombre de la sede al registrar: renombrarla no lo cambia (CA-26).
            $table->string('sede_nombre', 120);
            $table->string('estado', 10)->default('activa');
            // = usuario_hc_id mientras está activa, NULL si no. El índice único es la garantía de
            // «una activa por persona» (CA-03, CA-08): varios NULL conviven en SQLite y en MySQL.
            $table->unsignedBigInteger('activa_usuario_hc_id')->nullable()->unique();

            // Hora de Colombia (cast HoraColombia): datetime, no timestamp.
            $table->dateTime('ingreso_en');
            $table->string('procedencia', 50);
            $table->string('procedencia_texto');
            $table->string('comuna', 10)->nullable();
            $table->string('comuna_texto')->nullable();
            $table->string('proyeccion', 50)->nullable();
            $table->string('proyeccion_texto')->nullable();
            $table->string('orientacion', 50)->nullable();
            $table->string('orientacion_texto')->nullable();
            $table->text('observacion_ingreso')->nullable();

            $table->dateTime('egreso_en')->nullable();
            $table->string('motivo_egreso', 50)->nullable();
            $table->string('motivo_egreso_texto')->nullable();
            $table->string('destino', 50)->nullable();
            $table->string('destino_texto')->nullable();
            $table->text('observacion_egreso')->nullable();

            $table->unsignedInteger('revision')->default(0);

            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('egreso_registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('egreso_registrado_en')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulada_en')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->index(['dependencia_id', 'componente_id', 'ingreso_en']);
            $table->index(['usuario_hc_id', 'estado']);
            $table->index(['sede_id', 'estado']);
        });

        // Listas de varias opciones de la estadía (condiciones), mismo formato que usuario_hc_respuestas.
        Schema::create('estadia_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estadia_id')->constrained('estadias')->restrictOnDelete();
            $table->string('campo', 50);
            $table->string('fuente', 30);
            $table->string('opcion', 50);
            $table->string('texto');
            $table->timestamps();

            $table->unique(['estadia_id', 'campo', 'opcion']);
            $table->index(['campo', 'opcion']);
        });

        // El historial de la estadía: el registro de verdad. Mismo formato que usuario_hc_cambios.
        Schema::create('estadia_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estadia_id')->constrained('estadias')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('componente_id')->nullable()->constrained('componentes')->nullOnDelete();
            $table->string('accion', 20);
            $table->string('campo', 50)->nullable();
            $table->string('codigo_antes', 50)->nullable();
            $table->string('codigo_despues', 50)->nullable();
            $table->text('antes')->nullable();
            $table->text('despues')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estadia_cambios');
        Schema::dropIfExists('estadia_respuestas');
        Schema::dropIfExists('estadias');
        Schema::dropIfExists('sede_capacidades');
        Schema::dropIfExists('sedes');
    }
};
