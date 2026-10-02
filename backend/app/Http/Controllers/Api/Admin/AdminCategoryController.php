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

class AdminCategoryController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinary) {}

    public function index(): JsonResponse
    {
        try {
            $categories = Category::with(['children', 'manualOrderFields'])
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get();

            return response()->json(['categories' => $categories]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to load categories', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (! empty($data['image_base64'])) {
                $data['image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/categories');
                unset($data['image_base64']);
            }

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
                $data['image_url'] = $this->cloudinary->upload($data['image_base64'], 'marketly/categories');
                unset($data['image_base64']);
            }

            $category->update($data);

            return response()->json(['category' => $category->fresh(['manualOrderFields'])]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to update category', 'error' => $e->getMessage()], 500);
        }
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
