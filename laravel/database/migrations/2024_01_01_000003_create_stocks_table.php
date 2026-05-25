<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('warehouse', 64)->default('main');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->cascadeOnDelete();

            // Уникальность: один товар — одна запись на склад
            $table->unique(['product_id', 'warehouse'], 'uq_stock_product_warehouse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
