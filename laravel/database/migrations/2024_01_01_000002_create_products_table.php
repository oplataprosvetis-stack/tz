<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name');
            $table->string('sku', 64)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('in_stock')->default(false);
            $table->timestamps();

            $table->foreign('category_id')
                  ->references('id')
                  ->on('categories')
                  ->restrictOnDelete();

            // Составной индекс под основные сценарии фильтрации:
            // WHERE category_id = ? AND in_stock = ? AND price BETWEEN ? AND ?
            // Порядок колонок: equality-фильтры первыми, range-фильтр последним.
            $table->index(['category_id', 'in_stock', 'price'], 'idx_products_filter');

            // Индекс для фильтрации только по цене (без категории)
            $table->index(['price'], 'idx_products_price');

            // Индекс для фильтрации только по наличию
            $table->index(['in_stock'], 'idx_products_in_stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
