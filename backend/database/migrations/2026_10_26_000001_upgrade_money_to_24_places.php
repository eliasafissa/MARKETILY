<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const COLUMNS = [
        'users' => ['balance'],
        'products' => ['price', 'base_price'],
        'order_items' => ['unit_price'],
        'orders' => ['subtotal', 'fee', 'total'],
        'transactions' => ['amount', 'fee'],
        'store_product' => ['custom_price'],
    ];
    public function up(): void
    {
        // SQLite lacks fixed-point DECIMAL semantics; use it only for nonfinancial test smoke.
        if (DB::getDriverName() === 'sqlite' && app()->environment('testing')) return;
        if (DB::getDriverName() !== 'mysql') throw new RuntimeException('MySQL/MariaDB required');
        $changes = [];
        foreach (self::COLUMNS as $table => $names) foreach ($names as $name) {
            $c = DB::selectOne('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table, $name]);
            if (! $c || ! in_array(strtolower((string)$c->COLUMN_TYPE), ['decimal(28,12)', 'decimal(52,24)'], true) || (string)$c->COLUMN_COMMENT !== '' || trim((string)$c->EXTRA) !== '') {
                throw new RuntimeException("Unsafe existing SQL column: {$table}.{$name}");
            }
            if (strtolower($c->COLUMN_TYPE) === 'decimal(52,24)') continue;
            $default = $c->COLUMN_DEFAULT;
            if ($default !== null && strtoupper((string)$default) !== 'NULL' && ! preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/D', (string)$default)) throw new RuntimeException('Unexpected money default');
            $sqlDefault = $default === null || strtoupper((string)$default) === 'NULL' ? '' : ' DEFAULT '.(string)$default;
            $changes[] = [$table, $name, $c->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL', $sqlDefault];
        }
        // MySQL alters are not transactional: ensure a verified backup exists before running.
        foreach ($changes as [$t, $n, $nullable, $default]) DB::statement("ALTER TABLE `{$t}` MODIFY `{$n}` DECIMAL(52,24) {$nullable}{$default}");
    }
    public function down(): void { throw new RuntimeException('Refusing precision loss on rollback'); }
};
