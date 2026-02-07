<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PolcarCar>
 */
class PolcarCarFactory extends Factory
{

    public function definition(): array
    {
        return [
            'brand' => $this->faker->randomElement(['Toyota', 'Honda', 'Ford', 'BMW', 'Mercedes', 'Audi', 'Volkswagen', 'Nissan']),
            'model' => $this->faker->randomElement(['Corolla', 'Civic', 'Focus', 'X5', 'C-Class', 'A4', 'Golf', 'Qashqai']),
            'production_year' => $this->faker->numberBetween(1991, 2025),
        ];
    }
}
