<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['categories', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->text('admin_image_url')->nullable();
                $table->text('oranos_image_url')->nullable();
            });

            // Keep existing owner uploads; treat legacy provider images as fallback.
            DB::table($tableName)->select('id', 'image_url')->whereNotNull('image_url')
                ->orderBy('id')->chunkById(250, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        $url = trim((string) $row->image_url);
                        if ($url === '' || str_contains($url, 'empty.png')) continue;
                        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                        $ownerImage = str_contains($host, 'cloudinary.com') || str_contains($url, '/storage/');
                        DB::table($tableName)->where('id', $row->id)->update([
                            ($ownerImage ? 'admin_image_url' : 'oranos_image_url') => $url,
                            'image_url' => null,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (['categories', 'products'] as $tableName) {
            DB::table($tableName)->whereNull('image_url')->update([
                'image_url' => DB::raw('COALESCE(admin_image_url, oranos_image_url)'),
            ]);
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['admin_image_url', 'oranos_image_url']);
            });
        }
    }
};
