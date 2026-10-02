<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing ENUM order (MySQL stores ENUM as int — reordering corrupts data).
        // Only append 'admin_adjustment' at the end.
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
            'deposit','withdrawal','purchase','refund','vip_upgrade','admin_adjustment'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
            'deposit','withdrawal','purchase','refund','vip_upgrade'
        ) NOT NULL");
    }
};
