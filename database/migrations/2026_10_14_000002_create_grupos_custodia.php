<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carpetas bajo custodia: una carpeta (y todo lo que cuelga de ella) la ven
 * y la trabajan solo los miembros de su grupo de custodia, además de
 * Coordinación y Administración. Así el líder del Nodo 2 no puede tocar lo
 * del Nodo 6.
 *
 * Grupo y no personas sueltas: los miembros cambian (entra un líder nuevo),
 * y basta con cambiarlo en el grupo para que valga en todas sus carpetas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_custodia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['dependencia_id', 'nombre']);
        });

        Schema::create('grupo_custodia_usuario', function (Blueprint $table) {
            $table->foreignId('grupo_custodia_id')->constrained('grupos_custodia')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['grupo_custodia_id', 'user_id']);
        });

        Schema::table('carpetas', function (Blueprint $table) {
            // Sin grupo, la carpeta es de toda la dependencia (como siempre).
            // Si se borra el grupo, la carpeta vuelve a ser de todos.
            $table->foreignId('grupo_custodia_id')->nullable()->after('carpeta_id')
                ->constrained('grupos_custodia')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carpetas', fn (Blueprint $table) => $table->dropConstrainedForeignId('grupo_custodia_id'));
        Schema::dropIfExists('grupo_custodia_usuario');
        Schema::dropIfExists('grupos_custodia');
    }
};
