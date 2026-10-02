<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_order_fields', function (Blueprint $table) {
            $table->string('label_ar')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('manual_order_fields', function (Blueprint $table) {
            $table->dropColumn('label_ar');
        });
    }
};
