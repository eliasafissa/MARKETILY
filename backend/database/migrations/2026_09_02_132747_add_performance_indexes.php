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
            $table->index('category_id');
            $table->index('is_active');
            $table->index('type');
            $table->index(['is_active', 'category_id']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->index('slug');
            $table->index('type');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index('email');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('status');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['type']);
            $table->dropIndex(['is_active', 'category_id']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['type']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
        });
    }
};
