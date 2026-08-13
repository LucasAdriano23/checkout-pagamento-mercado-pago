<?php

namespace Database\Factories;

use App\Models\OrderSku;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Sku;

/**
 * @extends Factory<OrderSku>
 */
class OrderSkuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sku = Sku::with('product')->inRandomOrder()->first();

        return [
            'sku_id' => $sku->id,
            'product' => $sku->toJson(),
            'quantity' => $this->faker->randomDigitNot(except: 0),
            'unitary_price' => $sku->price
        ];
    }
}
