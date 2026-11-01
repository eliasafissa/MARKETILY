<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OranosMarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only provider health and order audit. Does not place orders or spend money. */
class AuditOranosPurchases extends Command
{
    protected $signature = 'marketly:audit-oranos {--live-readonly : GET provider profile, no purchases}';
    protected $description = 'Read-only Oranos order pipeline health audit; never creates a paid upstream order.';

    public function handle(OranosMarketService $provider): int
    {
        $this->line('ORANOS ORDER PIPELINE AUDIT (NO PAID API CALLS)');
        $this->line('TOKEN: '.(config('services.oranos.token') ? 'CONFIGURED' : 'MISSING'));
        $this->line('BASE URL: '.(str_starts_with((string) config('services.oranos.url'), 'https://') ? 'HTTPS' : 'INVALID'));
        $this->line('ASYNC QUEUE MODE: '.(config('services.oranos.async_orders') ? 'ENABLED (worker required)' : 'SYNC (bounded by HTTPS timeout)'));
        $this->line('QUEUE CONNECTION: '.config('queue.default'));
        $ca = config('services.oranos.ca_bundle') ?: storage_path('app/certs/cacert.pem');
        $this->line('CA BUNDLE: '.(is_file($ca) ? 'PRESENT' : 'system default'));
        if (!Schema::hasTable('orders') || !Schema::hasTable('order_items')) return self::FAILURE;
        $stats = DB::table('order_items')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN oranos_order_uuid IS NOT NULL AND oranos_order_id IS NULL THEN 1 ELSE 0 END) as unsynced')
            ->first();
        $this->line('ORDER ITEMS: '.$stats->total);
        $this->line('ITEMS AWAITING ORANOS ID: '.($stats->unsynced ?? 0));
        $this->line('ORDERS PROCESSING: '.Order::where('status', OrderStatus::Processing)->count());
        if ($this->option('live-readonly')) {
            try {
                $profile = $provider->getProfile();
                $this->info('LIVE READ-ONLY ORANOS PROFILE: OK');
                $this->line('BALANCE RECEIVED: '.(isset($profile['balance']) ? 'YES' : 'NO'));
            } catch (\Throwable $e) {
                $this->warn('LIVE READ-ONLY ORANOS PROFILE: FAILED ('.$e::class.')');
                return self::FAILURE;
            }
        }
        $this->warn('A successful real purchase requires a separate authorized paid end-to-end test. This audit never creates one.');
        return self::SUCCESS;
    }
}
