<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
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
        $key = $request->header('Idempotency-Key');
        if ($key !== null && ! preg_match('/^[a-zA-Z0-9_-]{12,80}$/', $key)) {
            return response()->json(['message' => 'Invalid Idempotency-Key.'], 422);
        }
        $items = $request->input('items', []);

        // Validate quantities against product qty_values
        foreach ($items as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if ($productId && $quantity !== null) {
                $product = Product::find($productId);
                if ($product && $product->oranos_product_id && $product->qty_values === null && (int) $quantity !== 1) {
                    return response()->json(['message' => 'Package quantity must equal 1.', 'item_index' => $index], 422);
                }
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
                $request->filled('expected_total') ? (string) $request->input('expected_total') : null,
                $key,
            );

            return response()->json(['order' => $order->load('items.product')], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.payload' => ['nullable', 'array'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ]);
        try {
            return response()->json($this->orders->quoteOrder(
                $request->user(), $data['items'], $data['store_id'] ?? null,
            ));
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
