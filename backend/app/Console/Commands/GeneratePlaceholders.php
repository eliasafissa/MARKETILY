<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;

class GeneratePlaceholders extends Command
{
    protected $signature = 'oranos:generate-placeholders {--force : Regenerate existing files}';
    protected $description = 'Generate branded placeholder images for items with no image';

    protected array $fontCandidates = [
        '/System/Library/Fonts/Supplemental/Arial Unicode.ttf',
        '/System/Library/Fonts/GeezaPro.ttc',
        '/Library/Fonts/Arial Unicode.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
    ];

    public function handle(): int
    {
        if (!extension_loaded('gd')) {
            $this->error('PHP GD extension is not loaded.');
            return 1;
        }

        $dir = storage_path('app/public/placeholders');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $font = null;
        foreach ($this->fontCandidates as $f) {
            if (file_exists($f)) { $font = $f; break; }
        }
        $this->info('Font: ' . ($font ?: 'none — solid colors only'));
        $this->info('Output: ' . $dir);

        $force = (bool) $this->option('force');

        $cats = Category::where(function($q){
            $q->whereNull('image_url')->orWhere('image_url','');
        })->get();
        $this->info("Categories to fill: {$cats->count()}");
        $catDone = 0;
        foreach ($cats as $cat) {
            $file = "$dir/cat-{$cat->id}.png";
            if ($force || !file_exists($file)) {
                $this->render($file, $cat->name ?? '?', $font);
            }
            $cat->update(['image_url' => url('storage/placeholders/cat-'.$cat->id.'.png')]);
            $catDone++;
        }

        $prods = Product::where(function($q){
            $q->whereNull('image_url')->orWhere('image_url','');
        })->get();
        $this->info("Products to fill: {$prods->count()}");
        $prodDone = 0;
        foreach ($prods as $prod) {
            $file = "$dir/prod-{$prod->id}.png";
            if ($force || !file_exists($file)) {
                $this->render($file, $prod->name ?? '?', $font);
            }
            $prod->update(['image_url' => url('storage/placeholders/prod-'.$prod->id.'.png')]);
            $prodDone++;
        }

        $this->newLine();
        $this->info("Done. Categories: $catDone  Products: $prodDone");
        return 0;
    }

    protected function render(string $path, string $name, ?string $font): void
    {
        $size = 400;
        $im = imagecreatetruecolor($size, $size);
        imageantialias($im, true);

        $h = abs(crc32($name));
        $r = 50 + ($h % 90);
        $g = 50 + (($h >> 8) % 90);
        $b = 80 + (($h >> 16) % 90);

        $bg = imagecolorallocate($im, $r, $g, $b);
        imagefilledrectangle($im, 0, 0, $size, $size, $bg);

        $overlay = imagecolorallocatealpha($im, 255, 255, 255, 105);
        for ($i = -$size; $i < $size * 2; $i += 50) {
            imageline($im, $i, 0, $i + $size, $size, $overlay);
        }

        $text = $this->initial($name);
        if ($font && $text !== '') {
            $white = imagecolorallocate($im, 255, 255, 255);
            $fontSize = mb_strlen($text) > 1 ? 140 : 180;
            $bbox = imagettfbbox($fontSize, 0, $font, $text);
            $tw = $bbox[2] - $bbox[0];
            $th = $bbox[1] - $bbox[7];
            $x = ($size - $tw) / 2 - $bbox[0];
            $y = ($size - $th) / 2 - $bbox[7];
            imagettftext($im, $fontSize, 0, (int)$x, (int)$y, $white, $font, $text);
        }

        imagepng($im, $path, 8);
        imagedestroy($im);
    }

    protected function initial(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '?';
        if (preg_match('/^[\p{Arabic}]/u', $name, $m)) {
            return $m[0];
        }
        return strtoupper(mb_substr($name, 0, 1));
    }
}
