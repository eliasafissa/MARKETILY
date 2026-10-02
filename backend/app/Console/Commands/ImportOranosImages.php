<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

class ImportOranosImages extends Command
{
    protected $signature = 'oranos:import-images';
    protected $description = 'Import scraped images into categories by Oranos category ID';

    public function handle(): int
    {
        $path = storage_path('app/oranos-all-images.json');
        if (! file_exists($path)) {
            $this->error("Not found: {$path}");
            return 1;
        }

        $data = json_decode(file_get_contents($path), true);
        $images = $data['images'] ?? [];
        $this->info("Loaded " . count($images) . " images");

        $byId = [];
        foreach ($images as $img) {
            $page = $img['page'] ?? '';
            if (! preg_match('#/category/(\d+)#', $page, $m)) continue;
            if (! str_contains($img['url'] ?? '', '/images/category/')) continue;
            $oid = (int) $m[1];
            if (! isset($byId[$oid])) $byId[$oid] = $img['url'];
        }
        $this->info("Distinct Oranos category IDs with images: " . count($byId));

        $matched = 0; $skipped = 0; $noMatch = 0;
        foreach ($byId as $oid => $url) {
            $category = Category::where('oranos_category_id', $oid)->first();
            if (! $category) { $noMatch++; continue; }
            if (! empty($category->image_url) && str_contains($category->image_url, '/images/category/')) {
                $skipped++;
                continue;
            }
            $category->update(['image_url' => $url]);
            $matched++;
            $this->line("  ✓ #{$oid} {$category->name}");
        }

        $this->newLine();
        $this->info("Matched: {$matched}  Skipped: {$skipped}  No local category: {$noMatch}");

        return 0;
    }
}
