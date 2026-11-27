<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DOC-33: la persona asignada es opcional al registrar un caso (CCTV-13). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos_cctv', function (Blueprint $table) {
            $table->unsignedBigInteger('colaborador_asignado_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sin vuelta atrás real: con casos sin asignar la columna no puede volver a ser obligatoria.
    }
};
