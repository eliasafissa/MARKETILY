<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('oranos_precise_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->primary();
            $table->unsignedBigInteger('oranos_product_id')->index();
            $table->decimal('unit_cost', 52, 24);
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('platform_unit_price', 52, 24)->nullable()->after('unit_price');
        });
    }
    public function down(): void
    {
        throw new RuntimeException('Refusing to remove financial audit price history');
    }
};
