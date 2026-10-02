<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('image_base64');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('image_base64');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
