<?php

namespace Database\Factories;

use App\Models\Componente;
use App\Models\Sede;
use App\Models\SedeCapacidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sedes ficticias, con su primera capacidad (20, vigente desde el 1/1/2026).
 *
 * @extends Factory<Sede>
 */
class SedeFactory extends Factory
{
    protected $model = Sede::class;

    public function definition(): array
    {
        return [
            'componente_id' => Componente::factory(),
            'dependencia_id' => fn (array $atributos) => Componente::withoutGlobalScopes()->find($atributos['componente_id'])->dependencia_id,
            'nombre' => 'Sede '.fake()->unique()->city(),
            'activa' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Sede $sede) => SedeCapacidad::create([
            'sede_id' => $sede->id,
            'capacidad' => 20,
            'vigente_desde' => '2026-01-01',
        ]));
    }

    public function en(Componente $componente): static
    {
        return $this->state(fn () => ['componente_id' => $componente->id, 'dependencia_id' => $componente->dependencia_id]);
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activa' => false]);
    }
}
