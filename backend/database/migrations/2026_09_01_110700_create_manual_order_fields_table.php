<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_order_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->string('label');
            $table->string('key');
            $table->enum('type', ['text', 'textarea', 'select', 'checkbox', 'number'])->default('text');
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();  // for select type
            $table->string('placeholder')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('manual_order_fields', function (Blueprint $table) {
            $table->index(['category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_order_fields');
    }
};