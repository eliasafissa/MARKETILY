<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'oranos_product_id')) {
                $table->integer('oranos_product_id')->nullable()->unique()->after('external_store_id');
            }
            if (!Schema::hasColumn('products', 'base_price')) {
                $table->decimal('base_price', 18, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('products', 'is_automation')) {
                $table->boolean('is_automation')->default(false)->after('base_price');
            }
            if (!Schema::hasColumn('products', 'qty_values')) {
                $table->json('qty_values')->nullable()->after('is_automation');
            }
            if (!Schema::hasColumn('products', 'params')) {
                $table->json('params')->nullable()->after('qty_values');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['oranos_product_id', 'base_price', 'is_automation', 'qty_values', 'params']);
        });
    }
};