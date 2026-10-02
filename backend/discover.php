<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Http;
$base  = rtrim(config('services.oranos.url'), '/');
$token = config('services.oranos.token');
echo "Base: {$base}\n\n";
$paths = [
    '/client/api/products','/client/api/categories','/client/api/category',
    '/client/api/categories/list','/client/api/categories/tree',
    '/client/api/products/full','/client/api/site','/client/api/home',
    '/client/api/config','/client/api/images','/client/api/product/images',
];
foreach ($paths as $path) {
    try {
        $r = Http::withHeader('api-token', $token)->timeout(20)->get($base . $path);
        $body = substr($r->body(), 0, 200);
        $json = str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[');
        printf("%-38s HTTP %d  %s\n", $path, $r->status(), $json ? 'JSON' : 'html');
        if ($json) echo "   " . str_replace("\n", ' ', $body) . "\n";
    } catch (\Throwable $e) {
        printf("%-38s ERR %s\n", $path, substr($e->getMessage(), 0, 60));
    }
}
