<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinary) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::with(['category', 'externalStore']);

            if ($request->filled('category')) {
                $query->where('category_id', $request->integer('category'));
            }

            if ($request->filled('type')) {
                $query->where('type', $request->string('type'));
            }

            if ($request->filled('q')) {
                $term = $request->string('q');
                $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$term}%"));
            }

            $products = $query->latest()->paginate(25);

            return response()->json($products);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to load products', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'oranos' => Product::whereNotNull('oranos_product_id')->count(),
            'manual' => Product::whereNull('oranos_product_id')->count(),
            'total'  => Product::count(),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (! empty($data['image_base64'])) {
                $data['admin_image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/products');
            }
            unset($data['remove_admin_image'], $data['image_base64']);

            $data['type'] = 'manual';
            $data['is_automation'] = false;
            $data['oranos_product_id'] = null;
            $product = Product::create($data);

            return response()->json(['product' => $product->load(['category', 'externalStore'])], 201);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to create product', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        if ($product->oranos_product_id !== null) {
            // Provider price is derived exclusively from base_price + global markup.
            // Manual price and pricing_mode parameters are never accepted for providers.
            if (array_key_exists('price', $data) || ($data['pricing_mode'] ?? 'auto') !== 'auto') {
                throw ValidationException::withMessages([
                    'price' => ['Oranos products use the global markup. Individual prices are disabled.'],
                ]);
            }
            $data = array_intersect_key($data, array_flip([
                'is_active', 'image_base64', 'remove_admin_image', 'icon',
            ]));
            if (! empty($data['is_active']) && ((float) $product->base_price <= 0 ||
                round((float) $product->price, 4) < round((float) $product->base_price, 4))) {
                throw ValidationException::withMessages(['price' => ['Invalid Oranos cost or selling price.']]);
            }
            if (array_key_exists('image_base64', $data) && $data['image_base64']) {
                try {
                    $data['admin_image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/products');
                } catch (\Throwable $e) {
                    report($e);
                    return response()->json(['message' => 'Image upload failed'], 500);
                }
            }
            if (! empty($data['remove_admin_image']) && ! isset($data['admin_image_url'])) {
                $data['admin_image_url'] = null;
            }
            unset($data['image_base64'], $data['remove_admin_image']);
        } else {
            unset($data['pricing_mode']);
            $data['type'] = 'manual';
            unset($data['is_automation'], $data['oranos_product_id']);
            if (! empty($data['image_base64'])) {
                try {
                    $data['admin_image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/products');
                } catch (\Throwable $e) {
                    report($e);
                    return response()->json(['message' => 'Image upload failed'], 500);
                }
            }
            if (! empty($data['remove_admin_image']) && ! isset($data['admin_image_url'])) {
                $data['admin_image_url'] = null;
            }
            unset($data['image_base64'], $data['remove_admin_image']);
        }

        $product->update($data);
        return response()->json(['product' => $product->fresh(['category', 'externalStore'])]);
    }

    public function destroy(Product $product): JsonResponse
    {
        try {
            if ($product->oranos_product_id !== null) {
                return response()->json(['message' => 'Disable Oranos products instead of deleting synced data.'], 422);
            }
            $product->delete();

            return response()->json(['message' => 'Product deleted.']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to delete product', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }
}
