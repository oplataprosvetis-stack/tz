<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    /**
     * GET /api/products
     *
     * Примеры запросов:
     *   /api/products?category_id=5&in_stock=1&price_min=100&price_max=5000&per_page=30
     *   /api/products?sort_by=price&sort_dir=desc
     */
    public function index(ProductIndexRequest $request): JsonResponse
    {
        try {
            $products = $this->productService->list($request->validated());

            return response()->json([
                'success' => true,
                'data'    => $products->items(),
                'meta'    => [
                    'current_page' => $products->currentPage(),
                    'per_page'     => $products->perPage(),
                    'has_more'     => $products->hasMorePages(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера.',
            ], 500);
        }
    }
}
