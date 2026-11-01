<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\ManualOrderField;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCategoryController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinary) {}

    public function index(): JsonResponse
    {
        $categories = Category::with('manualOrderFields')->orderBy('sort_order')->orderBy('id')->get();
        $counts = DB::table('products')->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')->pluck('total', 'category_id');
        $byParent = $categories->groupBy(fn ($c) => $c->parent_id === null ? 'root' : (string) $c->parent_id);
        $visited = [];
        $walk = function (?int $parent, int $depth = 0) use (&$walk, &$visited, $byParent, $counts) {
            if ($depth > 60) return collect();
            $result = collect();
            foreach ($byParent->get($parent === null ? 'root' : (string) $parent, collect()) as $cat) {
                if (isset($visited[$cat->id])) continue;
                $visited[$cat->id] = true;
                $children = $walk((int) $cat->id, $depth + 1);
                $cat->setRelation('children', $children);
                $cat->setAttribute('children_count', $children->count());
                $cat->setAttribute('subtree_categories_count', $children->count() + $children->sum('subtree_categories_count'));
                $cat->setAttribute('products_count', (int) ($counts[$cat->id] ?? 0));
                $cat->setAttribute('subtree_products_count', $cat->products_count + $children->sum('subtree_products_count'));
                $result->push($cat);
            }
            return $result;
        };
        $roots = $walk(null);
        foreach ($categories as $cat) {
            if (isset($visited[$cat->id])) continue;
            $children = $walk((int) $cat->id);
            $cat->setRelation('children', $children);
            $cat->setAttribute('children_count', $children->count());
            $cat->setAttribute('subtree_categories_count', $children->count() + $children->sum('subtree_categories_count'));
            $cat->setAttribute('products_count', (int) ($counts[$cat->id] ?? 0));
            $cat->setAttribute('subtree_products_count', $cat->products_count + $children->sum('subtree_products_count'));
            $visited[$cat->id] = true;
            $roots->push($cat);
        }
        return response()->json(['categories' => $roots]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (! empty($data['image_base64'])) {
                $data['admin_image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/categories');
                unset($data['image_base64']);
            }

            unset($data['remove_admin_image'], $data['image_base64']);
            $category = Category::create($data);

            return response()->json(['category' => $category], 201);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Failed to create category', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            $data = $request->validated();

            if (! empty($data['image_base64'])) {
                $data['admin_image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/categories');
                unset($data['image_base64']);
            }

            if (! empty($data['remove_admin_image']) && ! isset($data['admin_image_url'])) {
                $data['admin_image_url'] = null;
            }
            unset($data['remove_admin_image'], $data['image_base64']);
            $category->update($data);

            return response()->json(['category' => $category->fresh(['manualOrderFields'])]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Failed to update category', 'error' => $e->getMessage()], 500);
        }
    }

    /** Explicit admin visibility, separate from provider synchronization. */
    public function visibility(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate(['is_visible' => ['required', 'boolean']]);
        $category->update(['is_visible' => $data['is_visible']]);
        return response()->json(['category' => $category->fresh(), 'message' => 'تم تحديث ظهور القسم.']);
    }

    public function destroy(Category $category): JsonResponse
    {
        try {
            if ($category->products()->exists()) {
                return response()->json(['message' => 'Cannot delete a category with products.'], 422);
            }
            $category->delete();

            return response()->json(['message' => 'Category deleted.']);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Failed to delete category', 'error' => $e->getMessage()], 500);
        }
    }

    public function storeField(Request $request, Category $category): JsonResponse
    {
        try {
            $data = $request->validate([
                'label' => ['required', 'string'],
                'key' => ['required', 'string'],
                'type' => ['required', 'string', 'in:text,textarea,select,checkbox,number'],
                'required' => ['nullable', 'boolean'],
                'options' => ['nullable', 'array'],
                'placeholder' => ['nullable', 'string'],
                'sort_order' => ['nullable', 'integer'],
            ]);

            $field = ManualOrderField::create(array_merge($data, ['category_id' => $category->id]));

            return response()->json(['field' => $field], 201);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Failed to create field', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroyField(ManualOrderField $field): JsonResponse
    {
        try {
            $field->delete();
            return response()->json(['message' => 'Field deleted.']);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Failed to delete field', 'error' => $e->getMessage()], 500);
        }
    }
}
