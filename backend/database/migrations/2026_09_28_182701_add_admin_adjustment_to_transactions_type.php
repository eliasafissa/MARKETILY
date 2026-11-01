<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite represents enum() columns as strings and does not support MODIFY COLUMN.
        // MySQL/MariaDB behavior remains exactly as before: append enum value at the end.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Unsupported database driver for transaction type migration.');
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
            'deposit','withdrawal','purchase','refund','vip_upgrade','admin_adjustment'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Unsupported database driver for transaction type migration.');
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
            'deposit','withdrawal','purchase','refund','vip_upgrade'
        ) NOT NULL");
    }
};
