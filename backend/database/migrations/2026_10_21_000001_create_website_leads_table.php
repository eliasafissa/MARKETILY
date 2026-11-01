<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('website_leads')) return;
        Schema::create('website_leads', function(Blueprint $t) {
            $t->id(); $t->string('name',180)->default(''); $t->string('email',190)->default('');
            $t->string('phone',100)->default(''); $t->longText('details');
            $t->string('status',24)->default('new'); $t->timestamps();
            $t->index('created_at'); $t->index('status');
        });
    }
    public function down(): void { /* Financial and lead archives must never be deleted by rollback. */ }
};
