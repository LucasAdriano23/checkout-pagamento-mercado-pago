<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Feature;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $colorFeature = Feature::factory()->create(['name' => 'Cor', 'unit' => null]);
        $sizeFeature = Feature::factory()->create(['name' => 'Tamanho', 'unit' => null]);

        $colors = ['Preto', 'Branco', 'Azul', 'Vermelho', 'Verde'];
        $sizes = ['P', 'M', 'G', 'GG'];

        Product::factory()
        ->has(Sku::factory()
            ->count(3)
            ->afterCreating(function (Sku $sku) use ($colorFeature, $sizeFeature, $colors, $sizes) {
                $sku->features()->attach([
                    $colorFeature->id => ['value' => fake()->randomElement($colors)],
                    $sizeFeature->id => ['value' => fake()->randomElement($sizes)],
                ]);
            })
        )
        ->count(5)
        ->create([
            'brand_id' => Brand::first()->id,
            'category_id' => Category::first()->id
        ]);
    }
}
