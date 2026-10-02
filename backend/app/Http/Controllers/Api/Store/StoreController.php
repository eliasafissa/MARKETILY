<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        $store = DB::transaction(function () use ($user, $data) {
            $store = Store::create([
                'user_id'           => $user->id,
                'name'              => $data['name'],
                'description'       => $data['description'] ?? null,
                'is_active'         => true,
                'is_linked_to_main' => true,
            ]);

            // Attach all active automation products with 10% markup on our price.
            $markup = 1.10;

            Product::where('is_automation', true)
                ->where('is_active', true)
                ->select('id', 'price')
                ->chunk(500, function ($products) use ($store, $markup) {
                    $rows = [];
                    $now = now();
                    foreach ($products as $p) {
                        $rows[] = [
                            'store_id'     => $store->id,
                            'product_id'   => $p->id,
                            'custom_price' => round((float) $p->price * $markup, 2),
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ];
                    }
                    DB::table('store_product')->insert($rows);
                });

            return $store;
        });

        return response()->json([
            'store'         => $store->fresh(),
            'product_count' => $store->products()->count(),
        ], 201);
    }

    public function my(Request $request): JsonResponse
    {
        $stores = $request->user()
            ->stores()
            ->withCount('products')
            ->get();

        return response()->json(['stores' => $stores]);
    }

    public function show(string $slug): JsonResponse
    {
        $store = Store::with(['products' => function ($q) {
            $q->where('products.is_active', true)
              ->select('products.id', 'products.name', 'products.name_ar', 'products.slug',
                       'products.image_url', 'products.image_base64', 'products.price');
        }])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Replace product.price with the store's custom_price for display.
        $store->products->each(function ($p) {
            $p->store_price = $p->pivot->custom_price ?? $p->price;
        });

        return response()->json(['store' => $store]);
    }

    public function updateProductPrice(Request $request, Store $store, Product $product): JsonResponse
    {
        if ($store->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'custom_price' => 'required|numeric|min:0.01',
        ]);

        $attach = $store->products()->where('product_id', $product->id)->exists();
        if (! $attach) {
            return response()->json(['message' => 'Product is not in this store.'], 404);
        }

        // Refuse prices below our own retail (would lose the platform money).
        if ((float) $data['custom_price'] < (float) $product->price) {
            return response()->json([
                'message' => 'Custom price cannot be below the platform price of ' . $product->price,
            ], 422);
        }

        $store->products()->updateExistingPivot($product->id, [
            'custom_price' => round((float) $data['custom_price'], 2),
        ]);

        return response()->json([
            'message' => 'Price updated.',
            'product_id' => $product->id,
            'custom_price' => (float) $data['custom_price'],
        ]);
    }
}
