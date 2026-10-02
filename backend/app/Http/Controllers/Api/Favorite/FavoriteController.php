<?php

namespace App\Http\Controllers\Api\Favorite;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = Favorite::with('product.category')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($favorites);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $favorite = Favorite::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $validated['product_id'],
        ]);

        $favorite->load('product');

        return response()->json(['favorite' => $favorite], $favorite->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $deleted = Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json([
            'deleted' => (bool) $deleted,
            'message' => $deleted ? 'Removed from favorites' : 'Not in favorites',
        ]);
    }

    public function check(Request $request, int $productId): JsonResponse
    {
        $exists = Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->exists();

        return response()->json(['favorited' => $exists]);
    }
}
