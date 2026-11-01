<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 18, 4)->change();
            $table->decimal('base_price', 18, 4)->nullable()->change();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 4)->change();
            $table->uuid('oranos_order_uuid')->nullable()->unique();
            $table->string('oranos_order_id')->nullable()->index();
            $table->string('oranos_status', 24)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['oranos_order_uuid', 'oranos_order_id', 'oranos_status']));
        // Price precision is not reduced on rollback (would lose data).
    }
};
