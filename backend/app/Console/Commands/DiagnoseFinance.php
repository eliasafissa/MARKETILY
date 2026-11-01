<?php
namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseFinance extends Command
{
    protected $signature = 'marketly:diagnose-finance';
    protected $description = 'Read-only database diagnostics for API /deposits 500, without disclosing credentials.';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
            $this->info('DB CONNECTION: OK');
        } catch (\Throwable $e) {
            $this->error('DB CONNECTION: FAILED (' . $e::class . ')');
            return 1;
        }
        foreach (['users', 'transactions', 'sessions', 'cache'] as $table) {
            try {
                $found = Schema::hasTable($table);
                $this->line(strtoupper($table) . ': ' . ($found ? 'EXISTS' : 'MISSING'));
            } catch (\Throwable $e) {
                $this->error("SCHEMA ERROR: {$table} (" . $e::class . ")");
            }
        }
        if (! Schema::hasTable('transactions')) return 1;
        foreach (['user_id', 'type', 'amount', 'status', 'meta', 'payment_reference'] as $column) {
            $this->line('TRANSACTIONS COLUMN ' . $column . ': ' . (Schema::hasColumn('transactions', $column) ? 'OK' : 'MISSING'));
        }
        try {
            $count = DB::table('transactions')->where('type', 'deposit')->count();
            $this->info('DEPOSIT SQL COUNT: ' . $count);
            $transaction = Transaction::where('type', TransactionType::Deposit)->latest()->first();
            if ($transaction !== null) {
                // Exercise enum and JSON model casts, but never print personal data.
                $transaction->toArray();
            }
            $this->info('DEPOSIT MODEL CASTS: OK');
        } catch (\Throwable $e) {
            $this->error('DEPOSIT QUERY FAILED: ' . $e::class);
            $this->warn('Read backend/storage/logs/laravel.log for the detailed stack trace.');
            return 1;
        }
        $ca = config('services.oranos.ca_bundle') ?: storage_path('app/certs/cacert.pem');
        $this->line('ORANOS CA FILE: ' . (is_file($ca) ? 'FOUND' : 'MISSING'));
        return 0;
    }
}