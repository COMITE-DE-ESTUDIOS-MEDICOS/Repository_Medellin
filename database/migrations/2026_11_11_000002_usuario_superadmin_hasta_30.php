<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DOC-22: el usuario del superadministrador (p. ej. «adminDocumenta») lleva
     * letras y llega a 30 caracteres; el de una persona es su cédula (20).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('documento', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('documento', 20)->nullable()->change();
        });
    }
};
