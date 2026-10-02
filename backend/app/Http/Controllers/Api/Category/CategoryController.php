<?php

namespace App\Http\Controllers\Api\Category;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // Load all root categories with:
        //  - count of active products directly under them
        //  - their children, each with its own active product count
        // Filter out categories (root and children) with 0 active products
        $categories = Category::whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->withCount(['products' => fn ($pq) => $pq->where('is_active', true)])
                  ->orderBy('sort_order');
            }])
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->values();

        return response()->json(['categories' => $categories]);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->with(['children' => function ($q) {
                $q->withCount(['products' => fn ($pq) => $pq->where('is_active', true)])
                  ->orderBy('sort_order');
            }, 'manualOrderFields'])
            ->firstOrFail();

        // Load active products directly on this category
        $category->load(['products' => function ($q) {
            $q->where('is_active', true)->latest();
        }]);

        return response()->json(['category' => $category]);
    }

    public function formSchema(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        // 1. Try to use the new form_schema column
        $fields = $category->form_schema;
        if (is_string($fields)) {
            $fields = json_decode($fields, true);
        }

        // 2. FALLBACK - Safely wrapped so it doesn't crash the API
        if (empty($fields)) {
            try {
                $fields = $category->manualOrderFields()
                    ->orderBy('sort_order')
                    ->get()
                    ->map(function ($f) {
                        return [
                            'key' => $f->key,
                            'label' => $f->label,
                            'label_ar' => $f->label_ar ?? $f->label,
                            'type' => $f->type,
                            'required' => $f->required,
                            'options' => is_array($f->options) ? $f->options : json_decode($f->options ?? '[]', true),
                        ];
                    })->toArray();
            } catch (\Exception $e) {
                $fields = [];
            }
        }

        return response()->json([
            'category' => $category,
            'fields' => $fields ?? [],
        ]);
    }
}
