<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Enums\VipLevel;
use App\Services\CatalogPricing;
use App\Services\VipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $visibleCategoryIds = Category::storefrontVisibleIds();
        $query = Product::with(['category:id,slug,name,name_ar,image_url,admin_image_url,oranos_image_url'])
            ->where('is_active', true)
            ->whereIn('category_id', $visibleCategoryIds)
            ->where(fn ($q) => $q->whereNull('oranos_product_id')->orWhereRaw('base_price > 0 AND price >= base_price'));

        if ($request->filled('category')) {
            $category = Category::where('slug', (string) $request->query('category'))->first();
            if (! $category || ! in_array((int) $category->id, $visibleCategoryIds, true)) {
                return response()->json(['message' => 'Category not found.'], 404);
            }

            $ids = [(int) $category->id];
            if ($request->query('scope') === 'descendants') {
                // Single lookup over parent IDs: fast for hundreds of categories.
                $byParent = Category::select('id', 'parent_id')->whereIn('id', $visibleCategoryIds)->get()->groupBy('parent_id');
                $queue = [(int) $category->id];
                $visited = [(int) $category->id => true];
                while ($queue) {
                    $id = array_shift($queue);
                    foreach ($byParent->get($id, collect()) as $child) {
                        $childId = (int) $child->id;
                        if (isset($visited[$childId])) continue;
                        $visited[$childId] = true;
                        $ids[] = $childId;
                        $queue[] = $childId;
                    }
                }
            }
            $query->whereIn('category_id', $ids);
        }

        if ($request->filled('q')) {
            $term = trim(mb_substr((string) $request->query('q'), 0, 100));
            if ($term !== '') {
                $term = '%' . addcslashes($term, '%_\\') . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'LIKE', $term)
                        ->orWhere('name_ar', 'LIKE', $term)
                        ->orWhere('description', 'LIKE', $term)
                        ->orWhere('description_ar', 'LIKE', $term);
                });
            }
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        // Strict allowlist: never use user input as raw ORDER BY columns.
        switch ($request->query('sort')) {
            case 'name_asc':
                $query->orderBy('name_ar')->orderBy('name')->orderBy('id');
                break;
            case 'name_desc':
                $query->orderByDesc('name_ar')->orderByDesc('name')->orderBy('id');
                break;
            default:
                $query->latest('id');
        }

        $perPage = max(1, min((int) $request->integer('per_page', 24), 48));
        $page = $query->paginate($perPage);
        $level = $this->viewerLevel($request);
        $page->getCollection()->each(fn (Product $p) => CatalogPricing::display($p, $level));
        return response()->json($page);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $visibleCategoryIds = Category::storefrontVisibleIds();
        $product = Product::with(['category:id,slug,name,name_ar,image_url,admin_image_url,oranos_image_url', 'externalStore'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereIn('category_id', $visibleCategoryIds)
            ->where(fn ($q) => $q->whereNull('oranos_product_id')->orWhereRaw('base_price > 0 AND price >= base_price'))
            ->firstOrFail();

        return response()->json(['product' => CatalogPricing::display($product, $this->viewerLevel($request))]);
    }
    private function viewerLevel(Request $request): VipLevel
    {
        $user = $request->user('sanctum');
        return $user ? app(VipService::class)->effectiveLevel($user) : VipLevel::None;
    }
}
