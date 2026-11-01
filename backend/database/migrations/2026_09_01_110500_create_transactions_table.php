<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['deposit', 'withdrawal', 'purchase', 'refund', 'vip_upgrade'])->default('deposit');
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 18, 2)->default('0.00');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('method')->nullable();  // cash_wallet, binance_pay, usdt
            $table->string('gateway_ref')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};