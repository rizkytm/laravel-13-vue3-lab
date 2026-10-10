<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            // Seeder memakai WithoutModelEvents → slug dibuat eksplisit di sini.
            // Model event (creating) tetap diuji terpisah tanpa slug.
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'price' => fake()->numberBetween(10_000, 500_000),
            'stock' => fake()->numberBetween(1, 50),
            'options' => [
                'color' => fake()->randomElement(['red', 'blue', 'green']),
                'weight_gram' => fake()->numberBetween(100, 2000),
            ],
            'is_active' => true,
        ];
    }
}
