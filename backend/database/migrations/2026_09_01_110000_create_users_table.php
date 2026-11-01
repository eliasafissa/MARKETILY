<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // For fresh SQLite test databases, include the levels added by later migrations.
        // Those later migrations use MySQL-specific ENUM changes and are SQLite no-ops.
        // Keep the original MySQL/MariaDB creation schema unchanged.
        $vipLevels = Schema::getConnection()->getDriverName() === 'sqlite'
            ? ['none', 'vip1', 'vip2', 'vip3', 'vip4']
            : ['none', 'vip1', 'vip2'];

        Schema::create('users', function (Blueprint $table) use ($vipLevels) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('vip_level', $vipLevels)->default('none');
            $table->decimal('balance', 18, 2)->default('0.00');
            $table->timestamp('banned_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
