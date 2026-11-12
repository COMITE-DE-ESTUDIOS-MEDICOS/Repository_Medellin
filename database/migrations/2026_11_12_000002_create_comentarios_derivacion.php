<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DOC-23: comentarios en la ficha de un caso de derivación. Coordinación
        // deja indicaciones y quien creó el caso responde en el mismo hilo.
        // La persona de campo no los ve (la vía pública no los muestra).
        Schema::create('comentarios_derivacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('derivacion_id')->constrained('derivaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->timestamps();

            $table->index(['derivacion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comentarios_derivacion');
    }
};
