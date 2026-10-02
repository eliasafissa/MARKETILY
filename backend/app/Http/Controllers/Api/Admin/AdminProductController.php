<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
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

            return response()->json(['message' => 'Failed to load products', 'error' => $e->getMessage()], 500);
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
                $data['image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/products');
                unset($data['image_base64']);
            }

            $product = Product::create($data);

            return response()->json(['product' => $product->load(['category', 'externalStore'])], 201);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to create product', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            $data = $request->validated();

            if (! empty($data['image_base64'])) {
                $data['image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/products');
                unset($data['image_base64']);
            }

            $product->update($data);

            return response()->json(['product' => $product->fresh(['category', 'externalStore'])]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to update product', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Product $product): JsonResponse
    {
        try {
            $product->delete();

            return response()->json(['message' => 'Product deleted.']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to delete product', 'error' => $e->getMessage()], 500);
        }
    }
}
