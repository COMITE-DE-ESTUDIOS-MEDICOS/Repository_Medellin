<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOC-26 (plan D1): la ficha del Usuario HC y todo lo que cuelga de ella, más
 * los catálogos CIE-10 y países (vacíos: los carga un comando, D9). Solo
 * usuarios_hc lleva dependencia_id; las demás pertenecen por la ficha.
 * Ninguna lleva SoftDeletes: nada se borra (constitución IV).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios_hc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            // Lo pone el servidor al crear (el componente del contexto) y no cambia (CA-20).
            $table->foreignId('componente_registro_id')->constrained('componentes')->restrictOnDelete();

            $table->string('tipo_documento', 20);
            $table->string('tipo_documento_texto');
            // Como se escribió (recortado) · solo [0-9A-Z] para comparar (D5).
            $table->string('numero_documento', 20)->nullable();
            $table->string('documento_normalizado', 20)->nullable();
            // En mayúsculas, recortada y con sus guiones · sin guion para comparar («F-120» = «F120»).
            $table->string('numero_ficha', 30)->nullable();
            $table->string('ficha_normalizada', 30)->nullable();

            $table->string('primer_nombre', 100);
            $table->string('segundo_nombre', 100)->nullable();
            $table->string('primer_apellido', 100)->nullable();
            $table->string('segundo_apellido', 100)->nullable();
            $table->string('alias', 100)->nullable();
            // Se arman en saving (UsuarioHc::booted).
            $table->string('nombre_completo', 410);
            $table->string('nombre_busqueda', 500)->nullable();
            $table->string('alias_busqueda', 200)->nullable();

            $table->string('estado_vital', 10)->default('viva');
            $table->date('fecha_fallecimiento')->nullable();

            // La foto vigente. Sin FK: usuario_hc_fotos apunta a esta tabla (circular).
            $table->unsignedBigInteger('foto_id')->nullable()->index();
            $table->unsignedInteger('revision')->default(0);

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Varios NULL no chocan, en SQLite y en MySQL: «Sin documento» y la ficha vacía (CA-05, CA-06).
            $table->unique(['dependencia_id', 'tipo_documento', 'documento_normalizado'], 'usuarios_hc_documento_unico');
            $table->unique(['dependencia_id', 'ficha_normalizada'], 'usuarios_hc_ficha_unica');
            $table->index(['dependencia_id', 'nombre_completo']);
        });

        // Lo que cuelga de la ficha impide borrarla (restrictOnDelete): el historial, las fotos y
        // las identidades anteriores no se pierden en un borrado en cascada (nada se borra).

        // Identidades anteriores (nombre, documento o ficha que tuvo): siguen siendo buscables (CA-04).
        Schema::create('usuario_hc_identidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_hc_id')->constrained('usuarios_hc')->restrictOnDelete();
            $table->string('campo', 20);
            $table->string('valor', 410);
            $table->string('valor_busqueda', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('componente_id')->nullable()->constrained('componentes')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['campo', 'valor_busqueda']);
        });

        // 1 a 1. Aparte a propósito: la lista y la búsqueda nunca tocan una columna sensible (CA-21).
        Schema::create('usuario_hc_caracterizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_hc_id')->unique()->constrained('usuarios_hc')->restrictOnDelete();
            $table->date('fecha_nacimiento')->nullable();
            $table->date('fecha_ingreso_sistema')->nullable();
            // Vacío es «no informado», no cero (CA-09, CA-11).
            $table->unsignedSmallInteger('calle_anios')->nullable();
            $table->unsignedSmallInteger('calle_meses')->nullable();
            $table->unsignedSmallInteger('calle_semanas')->nullable();
            $table->timestamps();
        });

        // La respuesta vigente de cada lista: una fila por opción elegida, con código y texto (§7).
        Schema::create('usuario_hc_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_hc_id')->constrained('usuarios_hc')->restrictOnDelete();
            $table->string('campo', 50);
            $table->string('fuente', 30);
            $table->string('opcion', 50);
            $table->string('texto');
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->unique(['usuario_hc_id', 'campo', 'opcion']);
            $table->index(['campo', 'opcion']);
        });

        // El historial de la ficha: el registro de verdad (PA-28).
        Schema::create('usuario_hc_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_hc_id')->constrained('usuarios_hc')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('componente_id')->nullable()->constrained('componentes')->nullOnDelete();
            $table->string('seccion', 20);
            $table->string('campo', 50);
            $table->string('codigo_antes', 50)->nullable();
            $table->string('codigo_despues', 50)->nullable();
            $table->text('antes')->nullable();
            $table->text('despues')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Siempre JPEG (D10). Cambiar la foto crea una fila; ninguna se borra (CA-22).
        Schema::create('usuario_hc_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_hc_id')->constrained('usuarios_hc')->restrictOnDelete();
            $table->string('ruta');
            $table->unsignedBigInteger('tamano')->default(0);
            $table->foreignId('subida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        // Catálogos de referencia externos (SISPRO), sin dependencia_id (PA-13). Vacíos hasta el comando (D9).
        Schema::create('catalogo_cie10', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 4)->unique();
            $table->string('nombre');
            $table->string('nombre_busqueda')->nullable();
            $table->boolean('habilitado')->default(true);
        });

        Schema::create('catalogo_paises', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 3)->unique();
            $table->string('nombre');
            $table->string('nombre_oficial');
            $table->string('nombre_busqueda')->nullable();
            $table->boolean('habilitado')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_paises');
        Schema::dropIfExists('catalogo_cie10');
        Schema::dropIfExists('usuario_hc_fotos');
        Schema::dropIfExists('usuario_hc_cambios');
        Schema::dropIfExists('usuario_hc_respuestas');
        Schema::dropIfExists('usuario_hc_caracterizaciones');
        Schema::dropIfExists('usuario_hc_identidades');
        Schema::dropIfExists('usuarios_hc');
    }
};
