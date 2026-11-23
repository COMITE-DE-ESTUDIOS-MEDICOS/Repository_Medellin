<?php

namespace Database\Factories;

use App\Models\Componente;
use App\Models\Dependencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Componente>
 */
class ComponenteFactory extends Factory
{
    protected $model = Componente::class;

    public function definition(): array
    {
        $nombre = 'Componente '.fake()->unique()->numberBetween(1, 99999);

        return [
            'dependencia_id' => Dependencia::factory(),
            'nombre' => $nombre,
            'slug' => str($nombre)->slug()->toString(),
            'activo' => true,
            'config' => null,
            'modulos' => array_keys(Componente::MODULOS),
        ];
    }

    /** DOC-25: como Básica y Estabilización hoy, sin ningún módulo. */
    public function sinModulos(): static
    {
        return $this->state(fn () => ['modulos' => []]);
    }

    /** Reglas propias de este componente, encima de Componente::REGLAS. */
    public function conReglas(array $reglas): static
    {
        return $this->state(fn () => ['config' => $reglas]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
