<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

class ApplyOranosCategoryImages extends Command
{
    protected $signature = 'oranos:apply-category-images';
    protected $description = 'Apply real Oranos category images to matching Marketly categories';

    public function handle(): int
    {
        $map = config('oranos_category_images', []);
        $applied = 0;
        $skipped = 0;
        $missing = [];

        foreach ($map as $name => $url) {
            $category = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if (! $category) {
                $category = Category::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%'])->first();
            }

            if (! $category) {
                $missing[] = $name;
                continue;
            }

            if ($category->image_url === $url) {
                $skipped++;
                continue;
            }

            if ($category->image_url && str_contains($category->image_url, 'cloudinary')) {
                $this->line("⏭️  Skipped (Cloudinary): {$category->name}");
                $skipped++;
                continue;
            }

            $category->update(['image_url' => $url]);
            $this->line("✅ {$name} => {$category->name}");
            $applied++;
        }

        $this->newLine();
        $this->info("Applied: {$applied}. Skipped: {$skipped}.");

        if ($missing) {
            $this->warn('Not found: ' . implode(', ', $missing));
        }

        return 0;
    }
}
