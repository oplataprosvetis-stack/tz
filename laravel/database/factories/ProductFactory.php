<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name'        => $this->faker->words(3, true),
            'sku'         => 'SKU-' . $this->faker->unique()->numerify('#######'),
            'description' => $this->faker->sentence(),
            'price'       => $this->faker->randomFloat(2, 1, 99999),
            'in_stock'    => $this->faker->boolean(70),
        ];
    }
}
