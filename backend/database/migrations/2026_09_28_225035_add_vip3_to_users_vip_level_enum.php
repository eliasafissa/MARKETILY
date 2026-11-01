<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support MySQL MODIFY/ENUM; its schema stores enum() as text.
        // MySQL/MariaDB statement is intentionally unchanged.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Unsupported database driver for VIP level migration.');
        }

        DB::statement("ALTER TABLE users MODIFY vip_level ENUM('none','vip1','vip2','vip3') NOT NULL DEFAULT 'none'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Unsupported database driver for VIP level migration.');
        }

        DB::statement("ALTER TABLE users MODIFY vip_level ENUM('none','vip1','vip2') NOT NULL DEFAULT 'none'");
    }
};
