<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos a los usuarios por la campana: los de cada versión (automáticos,
 * desde CHANGELOG.md) y los que escribe Administración. Queda el historial
 * de qué se dijo, a quién y quién lo mandó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            // Null: para todas las dependencias (los avisos de versión).
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('mensaje');
            // Única: cada versión se anuncia una sola vez aunque se despliegue dos.
            $table->string('version', 40)->nullable()->unique();
            $table->json('roles')->nullable();
            $table->unsignedInteger('destinatarios')->default(0);
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
