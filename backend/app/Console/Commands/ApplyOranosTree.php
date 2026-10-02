<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyOranosTree extends Command
{
    protected $signature = 'oranos:apply-tree {--export : Also write readable tree files}';
    protected $description = 'Apply harvested Oranos category tree to DB and export it';

    public function handle(): int
    {
        $path = storage_path('app/oranos-harvest.json');
        if (!file_exists($path)) {
            $this->error("Run `php artisan oranos:harvest` first — $path missing");
            return 1;
        }

        $j    = json_decode(file_get_contents($path), true);
        $tree = $j['tree'] ?? [];
        $this->info("Tree nodes in harvest: " . count($tree));

        $localByOranos = Category::whereNotNull('oranos_category_id')
            ->pluck('id', 'oranos_category_id')
            ->all();

        $updated = 0;
        $skipped = 0;
        $orphan  = 0;

        foreach ($tree as $oid => $node) {
            $localId = $localByOranos[$oid] ?? null;
            if (!$localId) { $orphan++; continue; }

            $parentOranos = $node['parent_id'] ?? null;
            $parentLocal  = $parentOranos ? ($localByOranos[$parentOranos] ?? null) : null;

            // Bypass $fillable with query builder
            DB::table('categories')->where('id', $localId)
                ->update(['oranos_parent_id' => $parentLocal]);

            $updated++;
        }

        $this->info("Applied: $updated  Orphans: $orphan");

        if ($this->option('export')) {
            $this->export($tree, $localByOranos);
        }

        return 0;
    }

    protected function export(array $tree, array $localByOranos): void
    {
        $csvPath = storage_path('app/oranos-category-tree.csv');
        $fh = fopen($csvPath, 'w');
        fputcsv($fh, ['oranos_id','local_id','name','parent_oranos_id','parent_name','photo','products_count']);
        foreach ($tree as $oid => $n) {
            fputcsv($fh, [
                $oid, $localByOranos[$oid] ?? '', $n['name'] ?? '',
                $n['parent_id'] ?? '', $n['parent_name'] ?? '',
                $n['photo'] ?? '', $n['products_count'] ?? 0,
            ]);
        }
        fclose($fh);

        $byParent = [];
        foreach ($tree as $oid => $n) {
            $byParent[$n['parent_id'] ?? 0][] = $oid;
        }
        $build = function ($parentId) use (&$build, $tree, $byParent, $localByOranos) {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $oid) {
                $n = $tree[$oid];
                $out[] = [
                    'oranos_id'      => (int)$oid,
                    'local_id'       => $localByOranos[$oid] ?? null,
                    'name'           => $n['name'] ?? '',
                    'photo'          => $n['photo'] ?? null,
                    'products_count' => $n['products_count'] ?? 0,
                    'children'       => $build($oid),
                ];
            }
            return $out;
        };
        file_put_contents(
            storage_path('app/oranos-category-tree.json'),
            json_encode([
                'generated_at' => now()->toIso8601String(),
                'total_nodes'  => count($tree),
                'roots'        => $build(0),
                'orphans'      => $build(-1),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        $this->info("CSV:    $csvPath");
        $this->info("Nested: storage/app/oranos-category-tree.json");
    }
}
