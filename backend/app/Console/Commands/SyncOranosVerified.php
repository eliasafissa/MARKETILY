<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** A complete provider snapshot must be fetched before any catalog row is changed. */
class SyncOranosVerified extends Command
{
    protected $signature = 'oranos:sync-verified {--dry-run : Fetch and audit without DB writes} {--min-products=2395 : Abort if provider returns fewer products than this established baseline} {--max-seconds=21600 : Maximum time allowed for fetching}';
    protected $description = 'Stage the full Oranos category tree and products before an atomic, non-destructive catalog sync';

    public function handle(OranosMarketService $api): int
    {
        $begin = microtime(true);
        $deadline = $begin + max(60, (int) $this->option('max-seconds'));
        // Persist successfully fetched category responses across invocations.
        // Provider data is never committed to the database unless the entire tree passes validation.
        $cacheDir = storage_path('app/oranos-sync-checkpoints');
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            $this->error('Cannot create Oranos checkpoint directory');
            return self::FAILURE;
        }
        $lockHandle = fopen($cacheDir.'/sync.lock', 'c');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $this->warn('RESULT: SYNC_ALREADY_RUNNING; not starting a second instance');
            return self::SUCCESS;
        }
        $cacheMaxAge = 24 * 3600; // bounded freshness window; never treat arbitrarily old content as current
        $nodes = [];
        $products = [];
        $queue = [[0, null]];
        $seen = [];
        $requests = 0;
        try {
            while ($queue) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Fetch deadline reached; nothing written.');
                }
                [$id, $parent] = array_shift($queue);
                if (isset($seen[$id])) continue;
                $seen[$id] = true;
                $content = null;
                $cachePath = $cacheDir.'/category-'.(int)$id.'.json';
                if (is_file($cachePath) && time() - filemtime($cachePath) <= $cacheMaxAge) {
                    $saved = json_decode((string) file_get_contents($cachePath), true);
                    if (is_array($saved) && is_array($saved['categories'] ?? null) && is_array($saved['products'] ?? null)) {
                        $content = $saved;
                    }
                }
                $attempt = 0;
                while ($content === null) {
                    if (microtime(true) >= $deadline) {
                        throw new \RuntimeException('Fetch time budget expired at category '.$id.'; checkpoint preserved; no database writes.');
                    }
                    $attempt++;
                    try {
                        $content = $api->getContent((int) $id);
                        if (!is_array($content['categories'] ?? null) || !is_array($content['products'] ?? null)) {
                            throw new \RuntimeException('Incomplete response at category '.$id.'; no database writes.');
                        }
                        // Atomic checkpoint write. Never cache transport errors or invalid shapes.
                        $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                        if ($encoded === false) throw new \RuntimeException('Cannot serialize checkpoint for category '.$id);
                        $tmpPath = $cachePath.'.'.getmypid().'.tmp';
                        if (file_put_contents($tmpPath, $encoded, LOCK_EX) === false || !rename($tmpPath, $cachePath)) {
                            @unlink($tmpPath);
                            throw new \RuntimeException('Cannot save checkpoint for category '.$id);
                        }
                    } catch (\Illuminate\Http\Client\ConnectionException $e) {
                        $this->warn('NETWORK_RETRY category='.$id.' attempt='.$attempt.' elapsed='.round(microtime(true)-$begin).'s: '.substr($e->getMessage(), 0, 130));
                        $remaining = $deadline - microtime(true);
                        if ($remaining <= 0) break;
                        sleep((int) min($remaining, min(90, 10 + 5 * min($attempt, 16))));
                    }
                }
                if ($content === null) {
                    throw new \RuntimeException('Time budget reached at category '.$id.'; saved category checkpoints retained; no database writes.');
                }
                $requests++;
                if (!is_array($content['categories'] ?? null) || !is_array($content['products'] ?? null)) {
                    throw new \RuntimeException('Incomplete content response for Oranos category '.$id.'; no writes.');
                }
                foreach ($content['categories'] as $child) {
                    if (!is_array($child) || !is_numeric($child['id'] ?? null)) continue;
                    $childId = (int) $child['id'];
                    if ($childId <= 0 || $childId === (int) $id) continue;
                    $name = trim((string) ($child['name'] ?? ''));
                    if ($name === '' || strtolower($name) === 'null') {
                        // Cannot safely construct a public category from an unnamed node.
                        continue;
                    }
                    if (!isset($nodes[$childId])) {
                        $nodes[$childId] = ['name' => $name, 'parent' => $id === 0 ? null : (int) $id];
                        $queue[] = [$childId, $id];
                    }
                }
                foreach ($content['products'] as $product) {
                    if (!is_array($product) || !is_numeric($product['id'] ?? null)) continue;
                    $pid = (int) $product['id'];
                    if ($pid <= 0) continue;
                    // The source category is the content node, not a guessed name.
                    $sourceCat = is_numeric($product['parent_id'] ?? null) ? (int) $product['parent_id'] : (int) $id;
                    if ($sourceCat === 0 || !isset($nodes[$sourceCat])) $sourceCat = (int) $id;
                    if (isset($products[$pid]) && $products[$pid]['category'] !== $sourceCat) {
                        throw new \RuntimeException('Conflicting provider categories for product #'.$pid.'; no writes.');
                    }
                    $products[$pid] = ['category' => $sourceCat, 'data' => $product];
                }
                if ($requests % 30 === 0) {
                    $this->line("FETCHED nodes={$requests} products=".count($products));
                }
            }
            $minProducts = max(1, (int) $this->option('min-products'));
            $this->info('PROVIDER_ROOT_AND_CATEGORY_REQUESTS: '.$requests);
            $this->info('CHECKPOINT_WINDOW_HOURS: 24');
            $this->info('PROVIDER_NAMED_CATEGORIES: '.count($nodes));
            $this->info('PROVIDER_UNIQUE_PRODUCTS: '.count($products));
            if (count($products) < $minProducts) {
                throw new \RuntimeException('Incomplete provider snapshot: '.count($products).' products, required '.$minProducts.'. No writes.');
            }
            $missingRefs = 0;
            $badPrices = 0;
            foreach ($products as $entry) {
                if (!isset($nodes[$entry['category']])) $missingRefs++;
                if (!is_numeric($entry['data']['price'] ?? null) || (float) $entry['data']['price'] <= 0) $badPrices++;
            }
            $this->info('MISSING_PROVIDER_CATEGORY_REFERENCES: '.$missingRefs);
            $this->info('PROVIDER_INVALID_PRICES: '.$badPrices);
            if ($missingRefs) throw new \RuntimeException('Unresolved product category references. No writes.');
            if ($this->option('dry-run')) {
                $this->info('RESULT: VERIFIED_SNAPSHOT_READ_ONLY');
                return self::SUCCESS;
            }

            $markupPercent = (float) Setting::get('oranos_markup_percent', 20);
            if (!is_finite($markupPercent) || $markupPercent < 0 || $markupPercent > 10000) {
                throw new \RuntimeException('Invalid global Oranos markup setting; no writes.');
            }
            $counts = ['new_categories' => 0, 'new_products' => 0, 'updated_products' => 0];
            DB::transaction(function () use ($nodes, $products, $markupPercent, &$counts) {
                $categoryIds = [];
                // Resolve parents via rounds; provider tree must be acyclic and rooted.
                $remaining = $nodes;
                while ($remaining) {
                    $progress = false;
                    foreach ($remaining as $providerId => $info) {
                        $parentProvider = $info['parent'];
                        if ($parentProvider !== null && !isset($categoryIds[$parentProvider])) continue;
                        $parentLocal = $parentProvider === null ? null : $categoryIds[$parentProvider];
                        $category = Category::where('oranos_category_id', $providerId)->first();
                        if (!$category) {
                            $slug = Str::slug($info['name']).'-oranos-'.$providerId;
                            if (Str::slug($info['name']) === '') $slug = 'oranos-cat-'.$providerId;
                            $category = Category::create([
                                'oranos_category_id' => $providerId, 'parent_id' => $parentLocal,
                                'name' => $info['name'], 'name_ar' => $info['name'],
                                'slug' => $slug, 'type' => 'auto', 'icon' => 'package', 'sort_order' => 0,
                            ]);
                            $counts['new_categories']++;
                        } else {
                            // The provider owns structural placement, but not merchant labels or images.
                            if ($category->parent_id !== $parentLocal) $category->update(['parent_id' => $parentLocal]);
                        }
                        $categoryIds[$providerId] = $category->id;
                        unset($remaining[$providerId]);
                        $progress = true;
                    }
                    if (!$progress) throw new \RuntimeException('Category tree cycle/unresolvable parent. Rolled back.');
                }
                foreach ($products as $providerId => $entry) {
                    $data = $entry['data'];
                    $name = trim((string) ($data['name'] ?? ''));
                    if ($name === '') continue;
                    $price = is_numeric($data['price'] ?? null) ? (float) $data['price'] : 0.0;
                    $available = $data['available'] ?? true;
                    if (is_string($available)) $available = in_array(strtolower($available), ['active', 'available', 'in_stock', 'true', '1'], true);
                    $available = (bool) $available;
                    $retail = $price > 0 ? round($price * (1 + $markupPercent / 100), 4) : 0.0;
                    $existing = Product::where('oranos_product_id', $providerId)->first();
                    $payload = [
                        'category_id' => $categoryIds[$entry['category']],
                        'base_price' => $price,
                        'oranos_available' => $available,
                        'qty_values' => $data['qty_values'] ?? null,
                        'params' => is_array($data['params'] ?? null) ? $data['params'] : [],
                        'metadata' => array_merge(is_array($existing?->metadata) ? $existing->metadata : [], ['oranos_product_type' => $data['product_type'] ?? null]),
                    ];
                    if ($existing) {
                        if (($existing->pricing_mode ?? 'auto') === 'auto') $payload['price'] = $retail;
                        // Do not reactivate explicitly disabled merchant products.
                        if ($existing->is_active) $payload['is_active'] = $available && $price > 0;
                        $existing->update($payload);
                        $counts['updated_products']++;
                    } else {
                        $slug = Str::slug($name).'-oranos-'.$providerId;
                        if (Str::slug($name) === '') $slug = 'oranos-product-'.$providerId;
                        Product::create(array_merge($payload, [
                            'oranos_product_id' => $providerId,
                            'name' => $name, 'name_ar' => $name, 'description' => $name,
                            'description_ar' => $name, 'slug' => $slug, 'type' => 'auto',
                            'is_automation' => true, 'pricing_mode' => 'auto',
                            'price' => $retail, 'stock' => $available ? 999999999 : 0,
                            'is_active' => $available && $price > 0,
                        ]));
                        $counts['new_products']++;
                    }
                }
            }, 1);
            foreach ($counts as $key => $value) $this->info(strtoupper($key).': '.$value);
            $this->info('GLOBAL_MARKUP_PERCENT: '.$markupPercent);
            $this->info('RESULT: ATOMIC_SYNC_SUCCESS');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Verified Oranos sync aborted', ['reason' => $e->getMessage()]);
            $this->error('RESULT: SYNC_ABORTED_OR_ROLLED_BACK');
            $this->error('REASON: '.$e->getMessage());
            return self::FAILURE;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}
