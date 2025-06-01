<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['income', 'outcome']),
            'amount' => $this->faker->randomNumber(4),   
            'category' => $this->faker->word(),
            'owner_id' => $this->faker->randomNumber(5, true), // Simula un ID de usuario
            'owner_name' => $this->faker->userName(),
        ];
    }
}
