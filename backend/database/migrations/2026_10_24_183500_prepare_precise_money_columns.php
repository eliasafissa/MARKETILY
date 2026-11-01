<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Increase money column precision without changing existing values or numeric defaults.
 * Keep the list explicit and validate the entire schema before any ALTER TABLE.
 */
return new class extends Migration {
    private const TARGET = 'decimal(28,12)';
    private const COLUMNS = [
        'users' => ['balance' => [2, false]],
        'products' => ['price' => [4, false], 'base_price' => [4, true]],
        'order_items' => ['unit_price' => [4, false]],
        'orders' => ['subtotal' => [2, false], 'fee' => [2, false], 'total' => [2, false]],
        'transactions' => ['amount' => [2, false], 'fee' => [2, false]],
        'store_product' => ['custom_price' => [2, true]],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Expected MySQL/MariaDB database.');
        }

        // First inspect ALL money columns; do not begin ALTER if any differs.
        $changes = [];
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => [$scale, $nullable]) {
                $info = DB::selectOne(
                    'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA '
                    . 'FROM information_schema.COLUMNS '
                    . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
                    [$table, $column]
                );
                if (!$info) {
                    throw new RuntimeException("Missing money column: {$table}.{$column}");
                }
                $type = strtolower((string) $info->COLUMN_TYPE);
                $oldType = "decimal(18,{$scale})";
                if ($type !== $oldType && $type !== self::TARGET) {
                    throw new RuntimeException("Unexpected money type: {$table}.{$column}={$type}");
                }
                if (($info->IS_NULLABLE === 'YES') !== $nullable) {
                    throw new RuntimeException("Unexpected nullable state: {$table}.{$column}");
                }
                if ((string) $info->COLUMN_COMMENT !== '' || trim((string) $info->EXTRA) !== '') {
                    throw new RuntimeException("Unexpected column attributes: {$table}.{$column}");
                }

                $raw = $info->COLUMN_DEFAULT;
                if ($raw === null || (is_string($raw) && strtoupper(trim($raw)) === 'NULL')) {
                    // MariaDB may report nullable DEFAULT NULL as the string 'NULL'.
                    if (!$nullable && $raw !== null) {
                        throw new RuntimeException("Invalid NULL default: {$table}.{$column}");
                    }
                    $defaultSql = $nullable ? ' DEFAULT NULL' : '';
                } else {
                    $number = trim((string) $raw);
                    // Only simple decimal numeric literals are accepted, never SQL expressions.
                    if (!preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/D', $number)) {
                        throw new RuntimeException("Unexpected default literal: {$table}.{$column}");
                    }
                    $magnitude = ltrim($number, '-');
                    $digits = explode('.', $magnitude, 2);
                    if (strlen(ltrim($digits[0], '0')) > 16 || strlen($digits[1] ?? '') > 12) {
                        throw new RuntimeException("Default outside target decimal precision: {$table}.{$column}");
                    }
                    $defaultSql = ' DEFAULT ' . $number;
                }

                if ($type !== self::TARGET) {
                    $changes[] = [$table, $column, $nullable, $defaultSql];
                }
            }
        }

        foreach ($changes as [$table, $column, $nullable, $defaultSql]) {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(28,12) {$nullSql}{$defaultSql}");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Refusing to narrow money precision because fractional values could be lost.');
    }
};
