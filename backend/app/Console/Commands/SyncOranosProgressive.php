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

/**
 * Non-destructive progressive Oranos import.
 * Never deletes products, sends orders, or disables records absent from partial provider responses.
 */
class SyncOranosProgressive extends Command
{
    protected $signature = 'oranos:sync-progressive {--max-minutes=45 : Time limit for this run} {--dry-run : Preview without writes}';
    protected $description = 'Import verified Oranos content incrementally with persistent checkpoints and safe price markup';

    public function handle(OranosMarketService $api): int
    {
        $folder = storage_path('app/oranos-progressive');
        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            $this->error('Cannot create checkpoint directory');
            return self::FAILURE;
        }
        $lock = fopen($folder.'/sync.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('SYNC_ALREADY_RUNNING');
            if ($lock) fclose($lock);
            return self::SUCCESS;
        }
        $deadline = microtime(true) + max(2, min(360, (int) $this->option('max-minutes'))) * 60;
        $dry = (bool) $this->option('dry-run');
        $stats = ['fetched' => 0, 'cached' => 0, 'categories_created' => 0, 'products_created' => 0, 'products_updated' => 0, 'invalid_prices' => 0, 'failed' => 0];
        try {
            $markup = (float) Setting::get('oranos_markup_percent', 20);
            if (!is_finite($markup) || $markup < 0 || $markup > 10000) throw new \RuntimeException('Invalid markup percent');
            $queue = [[0, null]];
            $seen = [];
            $state = $folder.'/last-success.json';
            while ($queue && microtime(true) < $deadline) {
                [$providerId, $parentId] = array_shift($queue);
                if (isset($seen[$providerId])) continue;
                $seen[$providerId] = true;
                $cacheFile = $folder.'/category-'.$providerId.'.json';
                $content = null;
                $age = is_file($cacheFile) ? time() - filemtime($cacheFile) : PHP_INT_MAX;
                if ($age >= 0 && $age < 12 * 3600) {
                    $candidate = json_decode((string) file_get_contents($cacheFile), true);
                    if ($this->validContent($candidate)) {
                        $content = $candidate;
                        $stats['cached']++;
                    }
                }
                for ($attempt = 1; $content === null && $attempt <= 3 && microtime(true) < $deadline; $attempt++) {
                    try {
                        $candidate = $api->getContent((int)$providerId);
                        if (!$this->validContent($candidate)) throw new \RuntimeException('Malformed response');
                        $content = $candidate;
                        $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                        if ($encoded === false) throw new \RuntimeException('Cannot serialize provider response');
                        $temp = $cacheFile.'.'.getmypid().'.tmp';
                        if (file_put_contents($temp, $encoded, LOCK_EX) === false || !rename($temp, $cacheFile)) {
                            @unlink($temp);
                            throw new \RuntimeException('Cannot save checkpoint');
                        }
                        $stats['fetched']++;
                    } catch (\Throwable $e) {
                        $this->warn('FETCH_RETRY category='.$providerId.' attempt='.$attempt.' type='.get_class($e));
                        if ($attempt < 3) sleep(min(20, 4 * $attempt));
                    }
                }
                if ($content === null) {
                    $stats['failed']++;
                    // Do not discard a failed node. The next run retries it from the live API.
                    continue;
                }
                $children = [];
                foreach ($content['categories'] as $child) {
                    if (!is_array($child) || !filter_var($child['id'] ?? null, FILTER_VALIDATE_INT)) continue;
                    $cid = (int) $child['id'];
                    if ($cid <= 0 || $cid === $providerId) continue;
                    $name = trim((string)($child['name'] ?? ''));
                    if ($name === '' || strtolower($name) === 'null') continue;
                    $children[$cid] = ['name' => $name, 'parent' => $providerId === 0 ? null : $providerId];
                    $queue[] = [$cid, $providerId];
                }
                if ($dry) {
                    $this->line('DRY_NODE '.$providerId.' children='.count($children).' products='.count($content['products']));
                    continue;
                }
                // Commit each fully validated node atomically. Never write an incomplete node.
                DB::transaction(function () use ($providerId, $parentId, $children, $content, $markup, &$stats) {
                    $parent = $providerId === 0 ? null : Category::where('oranos_category_id', $providerId)->first();
                    if ($providerId !== 0 && !$parent) throw new \RuntimeException('Missing parent #'.$providerId);
                    foreach ($children as $cid => $info) {
                        $cat = Category::where('oranos_category_id', $cid)->first();
                        if (!$cat) {
                            $cat = Category::create([
                                'oranos_category_id' => $cid,
                                'parent_id' => $parent?->id,
                                'name' => $info['name'], 'name_ar' => $info['name'],
                                'slug' => 'oranos-cat-'.$cid,
                                'type' => 'auto', 'icon' => 'package', 'sort_order' => 0,
                            ]);
                            $stats['categories_created']++;
                        } elseif ($cat->parent_id !== $parent?->id) {
                            $cat->update(['parent_id' => $parent?->id]);
                        }
                    }
                    foreach ($content['products'] as $data) {
                        if (!is_array($data) || !filter_var($data['id'] ?? null, FILTER_VALIDATE_INT)) continue;
                        $pid = (int)$data['id'];
                        if ($pid <= 0) continue;
                        $name = trim((string)($data['name'] ?? ''));
                        if ($name === '') continue;
                        $assignedProviderId = filter_var($data['parent_id'] ?? null, FILTER_VALIDATE_INT) ?: $providerId;
                        $localCat = Category::where('oranos_category_id', $assignedProviderId)->first();
                        if (!$localCat && $providerId !== 0) $localCat = $parent;
                        if (!$localCat) {
                            $this->warn('UNMAPPED_PRODUCT '.$pid.' category='.$assignedProviderId);
                            continue;
                        }
                        $validPrice = is_numeric($data['price'] ?? null) && is_finite((float)$data['price']) && (float)$data['price'] > 0;
                        $price = $validPrice ? (float)$data['price'] : 0;
                        if (!$validPrice) $stats['invalid_prices']++;
                        $availability = $data['available'] ?? true;
                        if (is_string($availability)) $availability = in_array(strtolower($availability), ['active','available','in_stock','true','1'], true);
                        $availability = (bool)$availability;
                        $existing = Product::where('oranos_product_id', $pid)->first();
                        $payload = [
                            'category_id' => $localCat->id,
                            'base_price' => $price,
                            'oranos_available' => $availability,
                            'qty_values' => $data['qty_values'] ?? null,
                            'params' => is_array($data['params'] ?? null) ? $data['params'] : [],
                        ];
                        if ($existing) {
                            if (($existing->pricing_mode ?? 'auto') === 'auto' && $validPrice) {
                                $payload['price'] = round($price * (1 + $markup / 100), 4);
                            }
                            // Never automatically re-enable a manually disabled product.
                            if ($existing->is_active && (!$availability || !$validPrice)) $payload['is_active'] = false;
                            $existing->update($payload);
                            $stats['products_updated']++;
                        } else {
                            Product::create(array_merge($payload, [
                                'oranos_product_id' => $pid, 'name' => $name, 'name_ar' => $name,
                                'description' => $name, 'description_ar' => $name,
                                'slug' => 'oranos-product-'.$pid, 'type' => 'auto',
                                'is_automation' => true, 'pricing_mode' => 'auto',
                                'price' => $validPrice ? round($price * (1 + $markup / 100), 4) : 0,
                                'stock' => $availability ? 999999999 : 0,
                                'is_active' => $availability && $validPrice,
                            ]));
                            $stats['products_created']++;
                        }
                    }
                }, 1);
                file_put_contents($state, json_encode(['last_category' => $providerId, 'at' => date(DATE_ATOM), 'stats' => $stats]));
                if (($stats['fetched'] + $stats['cached']) % 20 === 0) {
                    $this->line('PROGRESS '.json_encode($stats));
                }
            }
            $complete = empty($queue) && $stats['failed'] === 0;
            $this->info('RESULT: '.($complete ? 'TREE_TRAVERSED' : 'PARTIAL_SYNC_WILL_RETRY'));
            $this->info('COUNTERS: '.json_encode($stats));
            $this->info('GLOBAL_MARKUP_PERCENT: '.$markup);
            $this->info('NO_PURCHASE_ATTEMPTED');
            return $complete ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            Log::error('Oranos progressive sync failed', ['type'=>get_class($e), 'message'=>$e->getMessage()]);
            $this->error('SYNC_ERROR: '.get_class($e).' '.$e->getMessage());
            $this->info('COUNTERS: '.json_encode($stats));
            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function validContent($data): bool
    {
        return is_array($data) && is_array($data['categories'] ?? null) && is_array($data['products'] ?? null)
            && array_is_list($data['categories']) && array_is_list($data['products']);
    }
}
