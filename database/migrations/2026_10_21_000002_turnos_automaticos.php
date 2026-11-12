<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos automáticos: cada persona tiene su turno, su punto crítico y sus
 * días de descanso habituales; al marcar entrada el sistema reconoce el turno
 * por la hora; y quien se esperaba y no marcó queda como novedad.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lo habitual de cada persona: con esto se sabe a quién esperar cada día.
        Schema::table('colaboradores', function (Blueprint $table) {
            $table->foreignId('tipo_turno_id')->nullable()->after('nodo_id')->constrained('tipos_turno')->nullOnDelete();
            $table->foreignId('punto_critico_id')->nullable()->after('tipo_turno_id')->constrained('puntos_criticos')->nullOnDelete();
            // ISO: 1 = lunes … 7 = domingo.
            $table->json('dias_descanso')->nullable()->after('punto_critico_id');
        });

        // Lo que el sistema reconoció al marcar la entrada.
        Schema::table('marcaciones', function (Blueprint $table) {
            $table->foreignId('tipo_turno_id')->nullable()->after('enlace_turno_id')->constrained('tipos_turno')->nullOnDelete();
            $table->foreignId('punto_critico_id')->nullable()->after('tipo_turno_id')->constrained('puntos_criticos')->nullOnDelete();
            $table->unsignedSmallInteger('minutos_tarde')->nullable()->after('punto_critico_id');
        });

        // El QR pegado en un punto crítico: quien marca con él, queda en ese punto.
        Schema::table('enlaces_turno', function (Blueprint $table) {
            $table->foreignId('punto_critico_id')->nullable()->after('nodo_id')->constrained('puntos_criticos')->nullOnDelete();
        });

        // «No registró entrada» no nace de una marca: nace de un día.
        Schema::table('novedades_turno', function (Blueprint $table) {
            $table->foreignId('marcacion_id')->nullable()->change();
            $table->date('fecha')->nullable()->after('entrada_id');
            $table->index(['colaborador_id', 'fecha', 'tipo']);
        });

        // D, VAC, I, P… por fechas: quien está ausente no se espera ese día.
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('tipo_turno_id')->constrained('tipos_turno')->restrictOnDelete();
            $table->date('desde');
            $table->date('hasta');
            $table->string('motivo', 500)->nullable();
            $table->foreignId('registrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['colaborador_id', 'desde', 'hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias');

        Schema::table('novedades_turno', function (Blueprint $table) {
            // MySQL usa el índice compuesto para la llave de colaborador_id:
            // primero uno simple que lo sustituya.
            $table->index('colaborador_id');
            $table->dropIndex(['colaborador_id', 'fecha', 'tipo']);
            $table->dropColumn('fecha');
        });

        Schema::table('enlaces_turno', fn (Blueprint $t) => $t->dropConstrainedForeignId('punto_critico_id'));

        Schema::table('marcaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('punto_critico_id');
            $table->dropConstrainedForeignId('tipo_turno_id');
            $table->dropColumn('minutos_tarde');
        });

        Schema::table('colaboradores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('punto_critico_id');
            $table->dropConstrainedForeignId('tipo_turno_id');
            $table->dropColumn('dias_descanso');
        });
    }
};
