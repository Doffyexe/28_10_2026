<?php

namespace Database\Factories;

use App\Models\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    protected $model = Curso::class;

    public function definition(): array
    {
        return [
            'sigla' => fake()->unique()->regexify('IF[0-9]{4}'),
            'nombreCurso' => fake()->sentence(3),
            'creditos' => fake()->numberBetween(1, 5),
        ];
    }
}
