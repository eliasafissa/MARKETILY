<?php

namespace App\Http\Controllers\Api\Category;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // 1. Get all root categories
        $roots = Category::whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        // 2. Get all active product counts per category in ONE query
        $productCounts = DB::table('products')
            ->where('is_active', true)
            ->selectRaw('category_id, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id')
            ->all();

        // 3. Get all children grouped by parent_id in ONE query
        $allChildren = Category::whereNotNull('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id');

        // 4. Attach children + counts to roots
        foreach ($roots as $root) {
            $children = $allChildren->get($root->id, collect());
            $children->each(function ($child) use ($productCounts) {
                $child->products_count = $productCounts[$child->id] ?? 0;
            });
            $root->children = $children->values();
            $root->products_count = $productCounts[$root->id] ?? 0;
        }

        return response()->json(['categories' => $roots]);
    }

    public function show(string $slug): JsonResponse
    {
        // 1. Fetch the category with its manual order fields
        $category = Category::where('slug', $slug)
            ->with('manualOrderFields')
            ->firstOrFail();

        // 2. Get direct children with their product counts (single query)
        $childIds = Category::where('parent_id', $category->id)->pluck('id');

        $productCounts = DB::table('products')
            ->where('is_active', true)
            ->whereIn('category_id', $childIds->push($category->id))
            ->selectRaw('category_id, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id')
            ->all();

        $children = Category::where('parent_id', $category->id)
            ->orderBy('sort_order')
            ->get()
            ->each(function ($child) use ($productCounts) {
                $child->products_count = $productCounts[$child->id] ?? 0;
            });

        $category->children = $children->values();
        $category->products_count = $productCounts[$category->id] ?? 0;

        // 3. Load active products directly on this category (limit for safety)
        $products = \App\Models\Product::where('category_id', $category->id)
            ->where('is_active', true)
            ->latest()
            ->limit(200)
            ->get();

        $category->products = $products;

        return response()->json(['category' => $category]);
    }

    public function formSchema(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $fields = $category->form_schema;
        if (is_string($fields)) {
            $fields = json_decode($fields, true);
        }

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
