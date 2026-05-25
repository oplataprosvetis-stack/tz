<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Электроника',
            'slug' => 'electronics',
        ]);
    }

    public function test_returns_paginated_products(): void
    {
        Product::factory()->count(25)->create([
            'category_id' => $this->category->id,
            'in_stock'    => true,
        ]);

        $response = $this->getJson('/api/products?per_page=10');

        $response->assertOk()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => ['id', 'name', 'sku', 'price', 'in_stock', 'category'],
                     ],
                     'meta' => ['current_page', 'per_page', 'has_more'],
                 ])
                 ->assertJsonCount(10, 'data')
                 ->assertJsonPath('meta.has_more', true);
    }

    public function test_filters_by_category(): void
    {
        $other = Category::create(['name' => 'Одежда', 'slug' => 'clothes']);

        Product::factory()->count(3)->create(['category_id' => $this->category->id]);
        Product::factory()->count(5)->create(['category_id' => $other->id]);

        $response = $this->getJson("/api/products?category_id={$this->category->id}");

        $response->assertOk()
                 ->assertJsonCount(3, 'data');
    }

    public function test_filters_by_price_range(): void
    {
        Product::factory()->create(['category_id' => $this->category->id, 'price' => 50]);
        Product::factory()->create(['category_id' => $this->category->id, 'price' => 150]);
        Product::factory()->create(['category_id' => $this->category->id, 'price' => 300]);

        $response = $this->getJson('/api/products?price_min=100&price_max=200');

        $response->assertOk()
                 ->assertJsonCount(1, 'data');
    }

    public function test_filters_by_in_stock(): void
    {
        Product::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'in_stock'    => true,
        ]);
        Product::factory()->count(2)->create([
            'category_id' => $this->category->id,
            'in_stock'    => false,
        ]);

        $response = $this->getJson('/api/products?in_stock=1');

        $response->assertOk()
                 ->assertJsonCount(3, 'data');
    }

    public function test_eager_loads_category_and_stocks(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);
        Stock::create([
            'product_id' => $product->id,
            'warehouse'  => 'main',
            'quantity'    => 42,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk();

        $item = $response->json('data.0');
        $this->assertArrayHasKey('category', $item);
        $this->assertArrayHasKey('stocks', $item);
        $this->assertEquals('Электроника', $item['category']['name']);
        $this->assertEquals(42, $item['stocks'][0]['quantity']);
    }

    public function test_validation_rejects_bad_params(): void
    {
        $response = $this->getJson('/api/products?per_page=999');

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }

    public function test_validation_rejects_negative_price(): void
    {
        $response = $this->getJson('/api/products?price_min=-10');

        $response->assertStatus(422);
    }
}
