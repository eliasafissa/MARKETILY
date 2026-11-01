<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY vip_level ENUM('none','vip1','vip2','vip3','vip4') NOT NULL DEFAULT 'none'");
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('vip_override', 8)->nullable()->after('vip_level');
        });
        // Preserve existing VIP grants. Admin can return users to automatic qualification.
        DB::table('users')->where('vip_level', '!=', 'none')->update(['vip_override' => DB::raw('vip_level')]);
    }

    public function down(): void
    {
        // Do not silently discard VIP4 grants when rolling back.
        if (DB::table('users')->where('vip_level', 'vip4')->orWhere('vip_override', 'vip4')->exists()) {
            throw new RuntimeException('VIP4 users exist. Reassign them before rolling back.');
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('vip_override'));
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY vip_level ENUM('none','vip1','vip2','vip3') NOT NULL DEFAULT 'none'");
        }
    }
};
