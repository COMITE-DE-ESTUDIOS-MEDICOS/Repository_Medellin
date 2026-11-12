<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DOC-11: el registro de supervisión territorial (antes, el Forms
     * «REGISTROS DE SUPERVISIÓN V6»). Lo llena el Apoyo al control territorial
     * por un enlace por cédula; los dinamizadores quedan ligados a su ficha.
     */
    public function up(): void
    {
        Schema::create('supervisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('componente_id')->constrained('componentes')->restrictOnDelete();
            $table->foreignId('enlace_turno_id')->nullable()->constrained('enlaces_turno')->nullOnDelete();

            // Quién supervisa, también como texto: el reporte dice lo que era ese día.
            $table->foreignId('supervisor_id')->constrained('colaboradores')->restrictOnDelete();
            $table->string('supervisor_nombre');
            $table->string('supervisor_cedula', 20);

            $table->foreignId('dinamizador1_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('dinamizador2_id')->nullable()->constrained('colaboradores')->restrictOnDelete();

            $table->foreignId('punto_critico_id')->nullable()->constrained('puntos_criticos')->nullOnDelete();
            $table->string('punto_critico_otro')->nullable();
            $table->foreignId('nodo_id')->nullable()->constrained('nodos')->nullOnDelete();
            $table->foreignId('tipo_turno_id')->nullable()->constrained('tipos_turno')->nullOnDelete();
            $table->dateTime('visita_at');
            $table->text('descripcion_punto');

            // Los nueve criterios del Forms, de 1 a 5 (Supervision::CRITERIOS).
            foreach (['horario', 'presencia', 'territorio', 'aplicativos', 'protocolos', 'epp', 'comunidad', 'mediacion', 'presentacion'] as $criterio) {
                $table->unsignedTinyInteger('c_'.$criterio);
            }
            $table->unsignedTinyInteger('calificacion_global');

            $table->string('incidencia', 20);
            $table->boolean('casos_escalados');
            $table->text('casos_escalados_cual')->nullable();
            $table->text('casos_prioritarios')->nullable();
            $table->text('recomendaciones');
            $table->text('observaciones')->nullable();

            $table->foreignId('evidencia1_documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('evidencia2_documento_id')->nullable()->constrained('documentos')->nullOnDelete();

            // Ubicación del celular al enviar (null: sin señal) y cuándo se abrió el formulario.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('precision_m')->nullable();
            $table->string('municipio')->nullable();
            $table->dateTime('inicio_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('agente')->nullable();
            $table->timestamps();

            $table->index(['dependencia_id', 'visita_at']);
        });

        // Quien ya tiene el cargo en su lista lo estrena como cargo que supervisa (Calle en producción).
        foreach (DB::table('componentes')->get(['id', 'config']) as $componente) {
            $config = json_decode((string) $componente->config, true) ?: [];
            $cargo = collect($config['cargos'] ?? [])->first(fn ($c) => mb_strtolower(trim((string) $c)) === 'apoyo al control territorial');

            if ($cargo !== null && ! isset($config['cargos_supervision'])) {
                $config['cargos_supervision'] = [$cargo];
                DB::table('componentes')->where('id', $componente->id)->update(['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisiones');
    }
};
