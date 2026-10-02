<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerApiController extends Controller
{
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'balance' => (float) $user->balance,
                'currency' => 'USD',
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::whereNotNull('oranos_category_id')
            ->select('id', 'name', 'name_ar', 'slug', 'parent_id', 'image_url')
            ->orderBy('name')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $categories,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $query = Product::with('category:id,slug')
            ->whereNotNull('oranos_product_id')
            ->where('is_active', true)
            ->where('oranos_available', true)
            ->where('stock', '>', 0);

        if ($request->filled('category_slug')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category_slug));
        }

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('description', 'LIKE', "%{$term}%");
            });
        }

        $perPage = min((int) $request->integer('per_page', 50), 100);
        $products = $query->select(
            'id',
            'oranos_product_id',
            'name',
            'name_ar',
            'slug',
            'price',
            'stock',
            'image_url',
            'category_id',
            'product_type',
            'params'
        )->latest()->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $products,
        ]);
    }

    public function product(string $slug): JsonResponse
    {
        $product = Product::with('category:id,slug')
            ->where('slug', $slug)
            ->whereNotNull('oranos_product_id')
            ->where('is_active', true)
            ->where('oranos_available', true)
            ->where('stock', '>', 0)
            ->select(
                'id',
                'oranos_product_id',
                'name',
                'name_ar',
                'slug',
                'price',
                'stock',
                'image_url',
                'category_id',
                'product_type',
                'params'
            )
            ->first();

        if (! $product) {
            return response()->json([
                'ok' => false,
                'error' => 'not_found',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $product,
        ]);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'product_slug' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'params' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $product = Product::where('slug', $request->product_slug)
            ->whereNotNull('oranos_product_id')
            ->where('is_active', true)
            ->where('oranos_available', true)
            ->where('stock', '>', 0)
            ->first();

        if (! $product) {
            return response()->json([
                'ok' => false,
                'error' => 'product_not_found',
            ], 404);
        }

        $quantity = (int) $request->quantity;
        $total = (float) $product->price * $quantity;

        /** @var User $user */
        $user = auth()->user();

        // Use DB transaction with row lock
        $order = DB::transaction(function () use ($user, $product, $quantity, $total, $request) {
            // Reload user with lock
            $lockedUser = User::lockForUpdate()->findOrFail($user->id);

            if ((float) $lockedUser->balance < $total) {
                return response()->json([
                    'ok' => false,
                    'error' => 'insufficient_balance',
                    'required' => $total,
                    'current' => (float) $lockedUser->balance,
                ], 402);
            }

            // Deduct balance
            $lockedUser->decrement('balance', $total);

            // Create order via OrderService
            $orderService = app(OrderService::class);
            $order = $orderService->createOrder($lockedUser, [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'payload' => $request->params ?? [],
                ]
            ], 'partner_api');

            return $order;
        });

        // If the transaction returned a JSON response (insufficient balance), return it
        if ($order instanceof JsonResponse) {
            return $order;
        }

        // Reload order with items
        $order->load('items.product');

        return response()->json([
            'ok' => true,
            'order_id' => $order->id,
            'status' => $order->status->value,
            'total_charged' => (float) $order->total,
            'new_balance' => (float) $user->fresh()->balance,
        ]);
    }

    public function orderStatus(int $id): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->select('id', 'status', 'created_at', 'updated_at')
            ->first();

        if (! $order) {
            return response()->json([
                'ok' => false,
                'error' => 'not_found',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $order->id,
                'status' => $order->status->value,
                'created_at' => $order->created_at?->toIso8601String(),
                'fulfilled_at' => $order->updated_at?->toIso8601String(),
            ],
        ]);
    }
}