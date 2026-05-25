<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Создаёт тестовые данные: 10 категорий, 10 000 товаров, остатки.
     * Для полноценного нагрузочного теста количество можно увеличить.
     */
    public function run(): void
    {
        // Категории
        $categories = [];
        for ($i = 1; $i <= 10; $i++) {
            $categories[] = [
                'id'         => $i,
                'name'       => "Категория {$i}",
                'slug'       => "category-{$i}",
                'parent_id'  => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('categories')->insert($categories);

        // Товары — вставляем чанками по 1000
        $chunkSize = 1000;
        $total     = 10_000;

        for ($offset = 0; $offset < $total; $offset += $chunkSize) {
            $products = [];
            $stocks   = [];

            for ($i = $offset; $i < $offset + $chunkSize; $i++) {
                $inStock = rand(0, 1);
                $qty     = $inStock ? rand(1, 500) : 0;

                $products[] = [
                    'category_id' => rand(1, 10),
                    'name'        => "Товар #{$i}",
                    'sku'         => 'SKU-' . str_pad($i, 7, '0', STR_PAD_LEFT),
                    'description' => "Описание товара #{$i}",
                    'price'       => rand(100, 100_000) / 100,
                    'in_stock'    => $inStock,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            DB::table('products')->insert($products);

            // Остатки — берём ID только что вставленных товаров
            $insertedIds = DB::table('products')
                ->orderBy('id', 'desc')
                ->limit($chunkSize)
                ->pluck('id');

            foreach ($insertedIds as $productId) {
                $stocks[] = [
                    'product_id' => $productId,
                    'warehouse'  => 'main',
                    'quantity'   => rand(0, 500),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('stocks')->insert($stocks);
        }
    }
}
