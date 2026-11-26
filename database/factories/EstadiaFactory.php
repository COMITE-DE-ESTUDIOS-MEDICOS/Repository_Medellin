<?php

namespace Database\Factories;

use App\Models\Componente;
use App\Models\Estadia;
use App\Models\Sede;
use App\Models\User;
use App\Models\UsuarioHc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Estadías ficticias: por defecto activa, con ingreso hace dos días. La persona
 * y la sede salen del mismo componente y dependencia.
 *
 * @extends Factory<Estadia>
 */
class EstadiaFactory extends Factory
{
    protected $model = Estadia::class;

    public function definition(): array
    {
        return [
            'componente_id' => Componente::factory(),
            'dependencia_id' => fn (array $a) => Componente::withoutGlobalScopes()->find($a['componente_id'])->dependencia_id,
            'usuario_hc_id' => fn (array $a) => UsuarioHc::factory()->create(['dependencia_id' => $a['dependencia_id'], 'componente_registro_id' => $a['componente_id']])->id,
            'sede_id' => fn (array $a) => Sede::factory()->create(['dependencia_id' => $a['dependencia_id'], 'componente_id' => $a['componente_id']])->id,
            'sede_nombre' => fn (array $a) => Sede::withoutGlobalScopes()->find($a['sede_id'])->nombre,
            'estado' => Estadia::ACTIVA,
            'activa_usuario_hc_id' => fn (array $a) => $a['usuario_hc_id'],
            'ingreso_en' => now(config('repositorio.horario.zona'))->subDays(2),
            'procedencia' => Estadia::PROCEDENCIA_CALLE,
            'procedencia_texto' => 'Intervención calle',
            'registrado_por' => User::factory(),
        ];
    }

    public function en(Componente $componente): static
    {
        return $this->state(fn () => ['componente_id' => $componente->id, 'dependencia_id' => $componente->dependencia_id]);
    }

    public function activa(): static
    {
        return $this->state(fn (array $a) => [
            'estado' => Estadia::ACTIVA,
            'activa_usuario_hc_id' => $a['usuario_hc_id'],
            'egreso_en' => null,
        ]);
    }

    public function egresada(): static
    {
        return $this->state(fn (array $a) => [
            'estado' => Estadia::EGRESADA,
            'activa_usuario_hc_id' => null,
            'egreso_en' => now(config('repositorio.horario.zona'))->subDay(),
            'motivo_egreso' => 'egreso_voluntario',
            'motivo_egreso_texto' => 'Egreso voluntario',
            'egreso_registrado_por' => $a['registrado_por'],
            'egreso_registrado_en' => now(),
        ]);
    }

    public function anulada(): static
    {
        return $this->state(fn (array $a) => [
            'estado' => Estadia::ANULADA,
            'activa_usuario_hc_id' => null,
            'anulada_por' => $a['registrado_por'],
            'anulada_en' => now(),
            'motivo_anulacion' => 'Registrada por error',
        ]);
    }
}
