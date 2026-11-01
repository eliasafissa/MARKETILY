<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            if (!Schema::hasColumn('categories', 'oranos_parent_id')) {
                $t->unsignedBigInteger('oranos_parent_id')->nullable()->after('oranos_category_id');
                $t->index('oranos_parent_id');
            }
        });
    }
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            if (Schema::hasColumn('categories', 'oranos_parent_id')) {
                $t->dropIndex(['oranos_parent_id']);
                $t->dropColumn('oranos_parent_id');
            }
        });
    }
};
