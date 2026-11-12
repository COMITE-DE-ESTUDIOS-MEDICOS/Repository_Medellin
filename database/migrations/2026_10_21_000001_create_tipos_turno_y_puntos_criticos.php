<?php

use Database\Seeders\CatalogoTurnosCalleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Los códigos de la malla de turnos (N1, T3, N7, C13…), por
         * componente. Son los mismos que usa nómina: cada código vale unas
         * horas. Los que no son turno (D descanso, I incapacidad, VAC…)
         * también van aquí, con es_turno = false, porque salen en la malla
         * y en el reporte.
         */
        Schema::create('tipos_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('componente_id')->constrained('componentes')->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('nombre');
            $table->boolean('es_turno')->default(true);
            // Días en que aplica, ISO: 1 = lunes … 7 = domingo. Vacío = todos.
            $table->json('dias')->nullable();
            $table->boolean('festivos')->default(false);
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            // Las que cuenta nómina para ese código (su hoja de convenciones).
            $table->decimal('horas_nomina', 5, 2)->default(0);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['componente_id', 'codigo']);
        });

        // Las zonas a las que se asigna a cada persona en la malla.
        Schema::create('puntos_criticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('componente_id')->constrained('componentes')->cascadeOnDelete();
            $table->foreignId('nodo_id')->nullable()->constrained('nodos')->nullOnDelete();
            $table->string('nombre', 300);
            // Dónde queda, para verlo en el mapa (y, más adelante, comparar
            // con la ubicación de quien marca ahí).
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['componente_id', 'nombre']);
        });

        // En producción el despliegue no corre seeders: el catálogo de Calle
        // se carga aquí (idempotente; no hace nada si Calle no existe).
        (new CatalogoTurnosCalleSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('puntos_criticos');
        Schema::dropIfExists('tipos_turno');
    }
};
