<?php

namespace Database\Factories;

use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\UsuarioHc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fichas ficticias, nunca personas reales.
 *
 * @extends Factory<UsuarioHc>
 */
class UsuarioHcFactory extends Factory
{
    protected $model = UsuarioHc::class;

    public function definition(): array
    {
        return [
            'dependencia_id' => Dependencia::factory(),
            // Un componente de la misma dependencia (la fábrica no pasa por $fillable).
            'componente_registro_id' => fn (array $atributos) => Componente::factory()->create(['dependencia_id' => $atributos['dependencia_id']])->id,
            'tipo_documento' => 'CC',
            'tipo_documento_texto' => 'Cédula de ciudadanía',
            'numero_documento' => (string) fake()->unique()->numberBetween(10000000, 1999999999),
            'numero_ficha' => null,
            'primer_nombre' => fake()->firstName(),
            'primer_apellido' => fake()->lastName(),
            'estado_vital' => UsuarioHc::VIVA,
        ];
    }

    /** Registrada en ese componente, y en su dependencia. */
    public function en(Componente $componente): static
    {
        return $this->state(fn () => [
            'dependencia_id' => $componente->dependencia_id,
            'componente_registro_id' => $componente->id,
        ]);
    }

    /** «Sin documento»: el número queda vacío, no se inventa (CA-05). */
    public function sinDocumento(): static
    {
        return $this->state(fn () => [
            'tipo_documento' => 'SD',
            'tipo_documento_texto' => 'Sin documento',
            'numero_documento' => null,
        ]);
    }
}
