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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'oranos_order_id')) {
                $table->string('oranos_order_id')->nullable()->after('payment_ref');
            }
            if (!Schema::hasColumn('orders', 'oranos_status')) {
                $table->string('oranos_status')->nullable()->after('oranos_order_id');
            }
            if (!Schema::hasColumn('orders', 'commission')) {
                $table->decimal('commission', 18, 2)->default(0)->after('oranos_status');
            }
            if (!Schema::hasColumn('orders', 'failure_reason')) {
                $table->string('failure_reason')->nullable()->after('commission');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['oranos_order_id', 'oranos_status', 'commission', 'failure_reason']);
        });
    }
};