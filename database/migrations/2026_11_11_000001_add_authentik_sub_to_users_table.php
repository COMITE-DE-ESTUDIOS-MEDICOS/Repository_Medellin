<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOC-22: el `sub` del OIDC de Authentik. El primer ingreso liga a la persona
 * por su documento y guarda aquí el `sub`; desde ahí entra solo por él.
 * Único: un `sub` es una sola cuenta de Authentik, y una sola de Documenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('authentik_sub')->nullable()->unique()->after('authentik_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['authentik_sub']);
            $table->dropColumn('authentik_sub');
        });
    }
};
