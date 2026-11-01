<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\CatalogPricing;
use App\Services\PreciseMoney;
use App\Services\VipService;
use App\Enums\VipLevel;
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
            $markup = '10';

            Product::where('is_automation', true)
                ->where('is_active', true)
                ->select('id', 'price')
                ->chunk(500, function ($products) use ($store, $markup) {
                    $rows = [];
                    $now = now();
                    foreach ($products as $p) {
                        try {
                            $retail = CatalogPricing::exactForProduct($p, VipLevel::None);
                            $storePrice = PreciseMoney::providerWithMarkup($retail, $markup);
                        } catch (\DomainException $e) { continue; }
                        $rows[] = [
                            'store_id'     => $store->id,
                            'product_id'   => $p->id,
                            'custom_price' => $storePrice,
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

    public function show(Request $request, string $slug): JsonResponse
    {
        $store = Store::with(['products' => function ($q) {
            $q->where('products.is_active', true)
              ->select('products.id', 'products.name', 'products.name_ar', 'products.slug',
                       'products.image_url', 'products.admin_image_url', 'products.oranos_image_url', 'products.image_base64', 'products.price', 'products.base_price', 'products.oranos_product_id');
        }])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Replace product.price with the store's custom_price for display.
        $viewer = $request->user('sanctum');
        $level = $viewer ? app(VipService::class)->effectiveLevel($viewer) : VipLevel::None;
        $store->products->each(function ($p) use ($level) {
            $list = (string) ($p->pivot->custom_price ?? $p->getRawOriginal('price'));
            $platform = CatalogPricing::exactForProduct($p, VipLevel::None);
            if (PreciseMoney::compare($list, $platform) < 0) $list = $platform;
            $price = CatalogPricing::exactForProduct($p, $level, $list);
            $p->setAttribute('store_price', $price);
            $p->setAttribute('price', $price);
        });

        return response()->json(['store' => $store]);
    }

    public function updateProductPrice(Request $request, Store $store, Product $product): JsonResponse
    {
        if ($store->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'custom_price' => ['required', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,24})?$/'],
        ]);

        $attach = $store->products()->where('product_id', $product->id)->exists();
        if (! $attach) {
            return response()->json(['message' => 'Product is not in this store.'], 404);
        }

        // Refuse prices below our own retail (would lose the platform money).
        $platform = CatalogPricing::exactForProduct($product, VipLevel::None);
        if (PreciseMoney::compare((string) $data['custom_price'], $platform) < 0) {
            return response()->json([
                'message' => 'Custom price cannot be below the platform price of ' . $product->price,
            ], 422);
        }

        $store->products()->updateExistingPivot($product->id, [
            'custom_price' => PreciseMoney::decimal((string) $data['custom_price']),
        ]);

        return response()->json([
            'message' => 'Price updated.',
            'product_id' => $product->id,
            'custom_price' => PreciseMoney::decimal((string) $data['custom_price']),
        ]);
    }
}
