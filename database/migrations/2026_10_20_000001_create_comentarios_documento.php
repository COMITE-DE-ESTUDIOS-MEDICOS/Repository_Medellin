<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comentarios sobre un archivo: «la foto está movida», «no se ve el
        // documento de identidad»… Quien lo subió se entera por la campana.
        Schema::create('comentarios_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->timestamps();

            $table->index(['documento_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comentarios_documento');
    }
};
