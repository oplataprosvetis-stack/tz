<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\Paginator;

class ProductService
{
    /**
     * Возвращает отфильтрованный и пагинированный список товаров.
     *
     * Используем Eloquent, потому что:
     * — удобная eager-загрузка связей (with) для решения N+1;
     * — читаемый fluent-синтаксис фильтров;
     * — при миллионе строк узкое место — индексы в БД, а не overhead ORM.
     *
     * simplePaginate() вместо paginate():
     * — не выполняет COUNT(*), который на миллионе записей стоит дорого;
     * — отдаёт ссылки «назад / вперёд» без номера последней страницы.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): Paginator
    {
        $query = Product::query()
            ->with(['category:id,name,slug', 'stocks:id,product_id,warehouse,quantity']);

        // --- Фильтрация (conditional where) ---

        $query->when($filters['category_id'] ?? null, function ($q, $categoryId) {
            $q->where('category_id', $categoryId);
        });

        $query->when(isset($filters['in_stock']), function ($q) use ($filters) {
            $q->where('in_stock', (bool) $filters['in_stock']);
        });

        $query->when($filters['price_min'] ?? null, function ($q, $min) {
            $q->where('price', '>=', $min);
        });

        $query->when($filters['price_max'] ?? null, function ($q, $max) {
            $q->where('price', '<=', $max);
        });

        // --- Сортировка ---

        $sortBy  = $filters['sort_by']  ?? 'id';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        // --- Пагинация ---

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->simplePaginate($perPage);
    }
}
