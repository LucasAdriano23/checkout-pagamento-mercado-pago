<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Brand;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::inRandomOrder()->first()->id,
            'name' => $this->faker->word(),
            'description' => $this->faker->paragraph(nbSentences: 1),
            'slug' => $this->faker->slug(),
            'technical_description' => $this->faker->paragraph(nbSentences: 1)
        ];
    }
}
