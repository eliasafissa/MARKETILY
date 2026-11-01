<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ParentFallback extends Command
{
    protected $signature = 'oranos:parent-fallback';
    protected $description = 'Use parent category images as fallback for empty products';

    public function handle(): int
    {
        $base  = rtrim(config('services.oranos.url'), '/');
        $token = config('services.oranos.token');

        // 1) Pull all products from /client/api/products to get parent_id mapping
        $this->info('Fetching /client/api/products...');
        $r = Http::withHeader('api-token', $token)->timeout(120)->get("$base/client/api/products");
        $apiProds = $r->json();
        $this->info('Got '.count($apiProds).' products');

        $parentOf = [];
        foreach ($apiProds as $p) {
            if (!empty($p['id']) && !empty($p['parent_id'])) {
                $parentOf[$p['id']] = (int)$p['parent_id'];
            }
        }

        // 2) Build oranos_cat_id -> image_url map from DB
        $catImg = DB::table('categories')
            ->whereNotNull('oranos_category_id')
            ->whereNotNull('image_url')
            ->where('image_url', 'like', '%/images/%')
            ->pluck('image_url', 'oranos_category_id')
            ->all();

        // 3) Walk up parent chain — check oranos_parent_id too
        $catParent = DB::table('categories')
            ->whereNotNull('oranos_category_id')
            ->whereNotNull('oranos_parent_id')
            ->pluck('oranos_parent_id', 'oranos_category_id')  // child => parent_local
            ->all();

        // local_id -> oranos_id lookup
        $localToOranos = DB::table('categories')
            ->whereNotNull('oranos_category_id')
            ->pluck('oranos_category_id', 'id')
            ->all();

        // 4) For each empty product, try to find a category image
        $empty = Product::where(function($q){
            $q->whereNull('image_url')->orWhere('image_url','');
        })->whereNotNull('oranos_product_id')->get();

        $filled = 0; $missed = 0;
        foreach ($empty as $prod) {
            $oid = $prod->oranos_product_id;
            $parentOranos = $parentOf[$oid] ?? null;
            if (!$parentOranos) { $missed++; continue; }

            // Try direct
            $img = $catImg[$parentOranos] ?? null;

            // Try walking up oranos_parent_id chain
            $guard = 0;
            $cur = $parentOranos;
            while (!$img && $guard++ < 5) {
                $localId = array_search($cur, $localToOranos);
                if (!$localId) break;
                $parentLocal = $catParent[$cur] ?? null;
                if (!$parentLocal) break;
                $parentOranos = $localToOranos[$parentLocal] ?? null;
                if (!$parentOranos) break;
                $img = $catImg[$parentOranos] ?? null;
                $cur = $parentOranos;
            }

            if ($img) {
                $prod->update(['image_url' => $img]);
                $filled++;
            } else {
                $missed++;
            }
        }

        $this->info("Filled: $filled  Still empty: $missed");

        return 0;
    }
}
