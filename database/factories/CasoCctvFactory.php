<?php

namespace Database\Factories;

use App\Models\CasoCctv;
use App\Models\Colaborador;
use App\Models\Componente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CasoCctv>
 */
class CasoCctvFactory extends Factory
{
    protected $model = CasoCctv::class;

    public function definition(): array
    {
        return [
            'componente_id' => Componente::factory(),
            'dependencia_id' => fn (array $a): int => Componente::withoutGlobalScopes()->find($a['componente_id'])->dependencia_id,
            'numero' => fake()->unique()->numberBetween(1, 99999),
            'clave_formulario' => (string) Str::uuid(),
            'estado' => CasoCctv::ABIERTO,
            'entidad' => 'camaras',
            'requerido_at' => now()->subHour(),
            'descripcion' => fake()->sentence(),
            'ubicacion' => fake()->streetAddress(),
            'comuna' => 10,
        ];
    }

    public function de(Componente $componente): static
    {
        return $this->state(fn (): array => ['componente_id' => $componente->id, 'dependencia_id' => $componente->dependencia_id]);
    }

    public function asignadoA(Colaborador $persona): static
    {
        return $this->state(fn (): array => ['colaborador_asignado_id' => $persona->id]);
    }

    public function atendido(int $minutos = 85): static
    {
        return $this->state(fn (array $a): array => [
            'estado' => CasoCctv::ATENDIDO,
            'atendido_at' => $a['requerido_at']->copy()->addMinutes($minutos),
            'tiempo_respuesta_min' => $minutos,
        ]);
    }
}
