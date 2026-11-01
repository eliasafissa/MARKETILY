<?php

namespace App\Http\Controllers\Api\Category;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CategoryController extends Controller
{
    /**
     * Build the complete category tree in memory. No N+1 queries and no
     * hiding parents whose products live in deeper descendant categories.
     */
    private function tree(): array
    {
        $allCategories = Category::orderBy('sort_order')->orderBy('id')->get();
        $lookup = $allCategories->keyBy('id');
        $categories = $allCategories->filter(function (Category $candidate) use ($lookup) {
            $seen = [];
            $node = $candidate;
            while ($node) {
                if (! $node->is_visible || isset($seen[$node->id])) return false;
                $seen[$node->id] = true;
                $node = $node->parent_id ? $lookup->get($node->parent_id) : null;
            }
            return true;
        });
        $directCounts = DB::table('products')
            ->where('is_active', true)
            ->selectRaw('category_id, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id');

        $byParent = $categories->groupBy(fn (Category $cat) => $cat->parent_id === null ? 'root' : (string) $cat->parent_id);
        $visited = [];
        $make = function (?int $parent, int $depth = 0) use (&$make, &$visited, $byParent, $directCounts): Collection {
            if ($depth > 60) {
                return collect();
            }
            $siblings = $byParent->get($parent === null ? 'root' : (string) $parent, collect());
            $result = collect();
            foreach ($siblings as $cat) {
                if (isset($visited[$cat->id])) {
                    continue; // Bad parent cycles must not crash public pages.
                }
                $visited[$cat->id] = true;
                $children = $make((int) $cat->id, $depth + 1);
                $cat->setRelation('children', $children);
                $cat->setAttribute('children_count', $children->count());
                $cat->setAttribute('subtree_categories_count', $children->count() + $children->sum('subtree_categories_count'));
                $direct = (int) ($directCounts[$cat->id] ?? 0);
                $cat->setAttribute('products_count', $direct);
                $cat->setAttribute('subtree_products_count', $direct + $children->sum('subtree_products_count'));
                $result->push($cat);
            }
            return $result;
        };

        $roots = $make(null);
        // Do not silently lose categories if an imported parent is invalid.
        foreach ($categories as $cat) {
            if ($cat->parent_id !== null && Category::whereKey($cat->parent_id)->where('is_visible', false)->exists()) {
                continue;
            }
            if (! isset($visited[$cat->id])) {
                $cat->setRelation('children', $make((int) $cat->id));
                $cat->setAttribute('children_count', $cat->children->count());
                $cat->setAttribute('subtree_categories_count', $cat->children->count() + $cat->children->sum('subtree_categories_count'));
                $direct = (int) ($directCounts[$cat->id] ?? 0);
                $cat->setAttribute('products_count', $direct);
                $cat->setAttribute('subtree_products_count', $direct + $cat->children->sum('subtree_products_count'));
                $visited[$cat->id] = true;
                $roots->push($cat);
            }
        }
        return [$roots, $categories];
    }

    /** Hidden ancestors and empty branches are unavailable on public endpoints. */
    private function publiclyReachable(Category $category): bool
    {
        $seen = [];
        $node = $category;
        while ($node) {
            if (isset($seen[$node->id]) || ! $node->is_visible) return false;
            $seen[$node->id] = true;
            $node = $node->parent_id ? Category::find($node->parent_id) : null;
        }
        [$roots] = $this->tree();
        $find = function (Collection $nodes) use (&$find, $category): bool {
            foreach ($nodes as $item) {
                if ((int) $item->id === (int) $category->id) return (int) $item->subtree_products_count > 0;
                if ($find($item->children)) return true;
            }
            return false;
        };
        return $find($roots);
    }

    public function index(): JsonResponse
    {
        [$roots] = $this->tree();
        // Only public tree is filtered. No category or product is deleted.
        $visible = function (Collection $nodes) use (&$visible): Collection {
            return $nodes->filter(fn (Category $cat) => (int) $cat->subtree_products_count > 0)
                ->map(function (Category $cat) use (&$visible) {
                    $children = $visible($cat->children);
                    $cat->setRelation('children', $children);
                    $cat->setAttribute('children_count', $children->count());
                    return $cat;
                })->values();
        };
        return response()->json(['categories' => $visible($roots)]);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->where('is_visible', true)->with('manualOrderFields')->firstOrFail();
        abort_unless($this->publiclyReachable($category), 404);
        [$roots] = $this->tree();
        $find = function (Collection $nodes) use (&$find, $category): ?Category {
            foreach ($nodes as $node) {
                if ($node->id === $category->id) return $node;
                $found = $find($node->children);
                if ($found) return $found;
            }
            return null;
        };
        $treeNode = $find($roots);
        if ($treeNode) {
            $category->setRelation('children', $treeNode->children);
            $category->setAttribute('children_count', $treeNode->children_count);
            $category->setAttribute('subtree_categories_count', $treeNode->subtree_categories_count);
            $category->setAttribute('products_count', $treeNode->products_count);
            $category->setAttribute('subtree_products_count', $treeNode->subtree_products_count);
        } else {
            $category->setRelation('children', collect());
            $category->setAttribute('children_count', 0);
            $category->setAttribute('subtree_categories_count', 0);
            $category->setAttribute('products_count', 0);
            $category->setAttribute('subtree_products_count', 0);
        }

        // Backwards compatibility: the public category page uses paginated
        // /products?category=...&scope=descendants for the complete product list.
        $category->setRelation('products', Product::where('category_id', $category->id)
            ->where('is_active', true)->latest()->limit(200)->get());

        return response()->json(['category' => $category]);
    }

    public function formSchema(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->where('is_visible', true)->firstOrFail();
        abort_unless($this->publiclyReachable($category), 404);

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
