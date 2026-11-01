<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogPricing;
use App\Services\OrderService;
use App\Services\VipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerApiController extends Controller
{
    private ?array $partnerVisibleCategoryIdsCache = null;

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
                'balance' => (string) $user->balance,
                'currency' => 'USD',
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::whereIn('id', $this->partnerVisibleCategoryIds())
            ->whereHas('products', fn (Builder $query) => $this->applyPartnerProductScope($query))
            ->select('id', 'name', 'name_ar', 'slug', 'parent_id', 'image_url', 'admin_image_url', 'oranos_image_url', 'oranos_category_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $categories,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $query = Product::with('category:id,slug');
        $this->applyPartnerProductScope($query);

        if ($request->filled('category_slug')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category_slug));
        }

        if ($request->filled('source')) {
            $source = strtolower((string) $request->string('source'));
            if (in_array($source, ['oranos', 'local'], true)) {
                $query->when($source === 'oranos', fn (Builder $q) => $q->whereNotNull('oranos_product_id'))
                    ->when($source === 'local', fn (Builder $q) => $q->whereNull('oranos_product_id'));
            }
        }

        if ($request->filled('q')) {
            $term = (string) $request->string('q');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('name_ar', 'LIKE', "%{$term}%")
                    ->orWhere('description', 'LIKE', "%{$term}%")
                    ->orWhere('description_ar', 'LIKE', "%{$term}%");
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
            'base_price',
            'stock',
            'image_url',
            'admin_image_url',
            'oranos_image_url',
            'category_id',
            'type',
            'params',
            'is_automation'
        )->latest()->paginate($perPage);

        $level = app(VipService::class)->effectiveLevel(auth()->user());
        $products->getCollection()->transform(function (Product $product) use ($level) {
            CatalogPricing::display($product, $level);
            $product->setAttribute('source', $product->oranos_product_id ? 'oranos' : 'local');
            $product->setAttribute('category_slug', $product->category?->slug);
            $product->setAttribute('product_type', $product->is_automation && $product->oranos_product_id ? 'automation' : 'manual');
            return $product;
        });

        return response()->json([
            'ok' => true,
            'data' => $products,
        ]);
    }

    public function product(string $slug): JsonResponse
    {
        $query = Product::with('category:id,slug')->where('slug', $slug);
        $this->applyPartnerProductScope($query);
        $product = $query->select(
            'id',
            'oranos_product_id',
            'name',
            'name_ar',
            'slug',
            'price',
            'base_price',
            'stock',
            'image_url',
            'admin_image_url',
            'oranos_image_url',
            'category_id',
            'type',
            'params',
            'is_automation'
        )->first();

        if (! $product) {
            return response()->json([
                'ok' => false,
                'error' => 'not_found',
            ], 404);
        }

        CatalogPricing::display($product, app(VipService::class)->effectiveLevel(auth()->user()));
        $product->setAttribute('source', $product->oranos_product_id ? 'oranos' : 'local');
        $product->setAttribute('category_slug', $product->category?->slug);
        $product->setAttribute('product_type', $product->is_automation && $product->oranos_product_id ? 'automation' : 'manual');

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

        $query = Product::query()->where('slug', $request->product_slug);
        $this->applyPartnerProductScope($query);
        $product = $query->first();

        if (! $product) {
            return response()->json([
                'ok' => false,
                'error' => 'product_not_found',
            ], 404);
        }

        $quantity = (int) $request->quantity;
        /** @var User $user */
        $user = auth()->user();
        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        } elseif (mb_strlen($idempotencyKey) > 80) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_idempotency_key',
            ], 422);
        }

        try {
            $order = app(OrderService::class)->createOrder($user, [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'payload' => $request->params ?? [],
            ]], 'partner_api', [], null, null, $idempotencyKey);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $order->load('items.product');

        return response()->json([
            'ok' => true,
            'order_id' => $order->id,
            'status' => $order->status->value,
            'total_charged' => (string) $order->total,
            'new_balance' => (string) $user->fresh()->balance,
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

    /**
     * Honor visibility of each category and every ancestor without altering
     * Oranos imports or the admin's ability to manage hidden products.
     * A broken parent reference or a category cycle is treated as hidden.
     * The IDs are computed once per controller instance/request.
     *
     * @return array<int, int>
     */
    private function partnerVisibleCategoryIds(): array
    {
        if (isset($this->partnerVisibleCategoryIdsCache)) {
            return $this->partnerVisibleCategoryIdsCache;
        }

        $categories = Category::query()
            ->get(['id', 'parent_id', 'is_visible'])
            ->keyBy('id');
        $memo = [];
        $visiting = [];
        $isVisible = function (int $id) use (&$isVisible, &$memo, &$visiting, $categories): bool {
            if (array_key_exists($id, $memo)) {
                return $memo[$id];
            }
            if (isset($visiting[$id])) {
                return false;
            }
            $category = $categories->get($id);
            if (! $category || ! (bool) $category->is_visible) {
                return $memo[$id] = false;
            }
            $visiting[$id] = true;
            $parentId = $category->parent_id;
            $visible = $parentId === null || $isVisible((int) $parentId);
            unset($visiting[$id]);
            return $memo[$id] = $visible;
        };

        $ids = [];
        foreach ($categories as $category) {
            if ($isVisible((int) $category->id)) {
                $ids[] = (int) $category->id;
            }
        }

        return $this->partnerVisibleCategoryIdsCache = $ids;
    }

    private function applyPartnerProductScope(Builder $query): Builder
    {
        return $query
            ->whereIn('category_id', $this->partnerVisibleCategoryIds())
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->where(function (Builder $inner) {
                $inner
                    ->where(function (Builder $oranos) {
                        $oranos->whereNotNull('oranos_product_id')
                            ->where('oranos_available', true)
                            ->whereRaw('base_price > 0 AND price >= base_price');
                    })
                    ->orWhere(function (Builder $local) {
                        $local->whereNull('oranos_product_id')
                            ->whereRaw('price > 0');
                    });
            });
    }
}
