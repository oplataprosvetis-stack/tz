<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * POST /api/orders
     *
     * Принимает данные заказа из Bitrix.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'items'    => ['required', 'array', 'min:1'],
            'total'    => ['required', 'numeric', 'min:0'],
        ]);

        try {
            DB::table('orders')->updateOrInsert(
                ['bitrix_order_id' => $validated['order_id']],
                [
                    'payload'    => json_encode($validated, JSON_THROW_ON_ERROR),
                    'status'     => 'new',
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );

            return response()->json(['success' => true], 201);
        } catch (\Throwable $e) {
            Log::error('Order sync failed', [
                'order_id' => $validated['order_id'] ?? null,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось сохранить заказ.',
            ], 500);
        }
    }
}
