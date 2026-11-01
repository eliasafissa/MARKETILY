<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) return;
        if (!Schema::hasColumn('transactions', 'payment_reference')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('payment_reference', 190)->nullable();
            });
        }
        // Existing rows have NULL payment_reference; MySQL permits multiple NULLs.
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['method', 'payment_reference'], 'transactions_method_receipt_unique');
        });
    }
    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'payment_reference')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropUnique('transactions_method_receipt_unique');
                $table->dropColumn('payment_reference');
            });
        }
    }
};