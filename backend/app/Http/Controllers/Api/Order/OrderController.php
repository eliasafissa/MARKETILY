<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items.product'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $items = $request->input('items', []);

        // Validate quantities against product qty_values
        foreach ($items as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if ($productId && $quantity !== null) {
                $product = Product::find($productId);
                if ($product && $product->qty_values) {
                    $qtyValues = $product->qty_values;
                    $isFormatA = is_array($qtyValues) && isset($qtyValues['min']) && isset($qtyValues['max']);
                    $isFormatB = is_array($qtyValues) && array_is_list($qtyValues);

                    if ($isFormatA) {
                        $min = (int) $qtyValues['min'];
                        $max = (int) $qtyValues['max'];
                        if ($quantity < $min || $quantity > $max) {
                            return response()->json([
                                'error' => 'invalid_quantity',
                                'message' => "Quantity must be between {$min} and {$max}",
                                'allowed' => ['min' => $min, 'max' => $max],
                                'item_index' => $index,
                            ], 422);
                        }
                    } elseif ($isFormatB) {
                        $allowed = array_map('intval', $qtyValues);
                        if (!in_array((int) $quantity, $allowed, true)) {
                            return response()->json([
                                'error' => 'invalid_quantity',
                                'message' => 'Selected quantity is not in the allowed tiers',
                                'allowed' => $allowed,
                                'item_index' => $index,
                            ], 422);
                        }
                    }
                }
            }
        }

        try {
            $order = $this->orders->createOrder(
                $request->user(),
                $items,
                $request->string('payment_method')->toString(),
                (array) $request->input('meta', []),
                $request->filled('store_id') ? (int) $request->input('store_id') : null,
            );

            return response()->json(['order' => $order->load('items.product')], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['order' => $order->load('items.product')]);
    }
}
