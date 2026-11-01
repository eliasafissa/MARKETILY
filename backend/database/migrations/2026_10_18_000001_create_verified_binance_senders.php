<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('verified_binance_senders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('binance_uid', 24)->unique();
            $table->foreignId('verified_by_admin_id')->constrained('users');
            $table->string('verification_note', 1000);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('verified_binance_senders'); }
};
