<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OranosMarketService
{
    protected string $base;
    protected ?string $token;

    public function __construct()
    {
        $this->base  = rtrim(config('services.oranos.url'), '/');
        $this->token = config('services.oranos.token');
    }

    protected function http()
    {
        $headers = [
            'api-token'       => $this->token,
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Language' => 'ar,en;q=0.9',
            'Referer'         => 'https://oranosmarket.com/',
            'Origin'          => 'https://oranosmarket.com',
            'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        ];
        return Http::withHeaders($headers)->timeout(120)->connectTimeout(30);
    }

    public function getCategories(): array
    {
        $response = $this->http()->get("{$this->base}/client/api/categories");
        $this->logRawResponse('getCategories', $response);

        $data = $response->json() ?? [];

        return $this->validateListResponse($data, 'getCategories');
    }

    public function getProducts(): array
    {
        $response = $this->http()->get("{$this->base}/client/api/products");
        $this->logRawResponse('getProducts', $response);

        $data = $response->json() ?? [];

        return $this->validateListResponse($data, 'getProducts');
    }

    /**
     * Log raw HTTP response for debugging.
     */
    protected function logRawResponse(string $method, $response): void
    {
        $status = $response->status();
        $body = $response->body();
        $preview = substr($body, 0, 500);
        Log::debug("Oranos {$method} response", [
            'status' => $status,
            'body_preview' => $preview,
        ]);
    }

    /**
     * Validate that the response is a non-empty list of items with 'id' key.
     *
     * @param  array|mixed  $data
     * @return array
     * @throws RuntimeException if response is not a valid list
     */
    protected function validateListResponse(mixed $data, string $method): array
    {
        if (! is_array($data)) {
            throw new RuntimeException("Oranos {$method} returned non-array response: " . json_encode($data));
        }

        if (empty($data)) {
            throw new RuntimeException("Oranos {$method} returned empty response");
        }

        // Check if it's an associative array (error object) vs a list
        $keys = array_keys($data);
        if ($keys !== range(0, count($data) - 1)) {
            throw new RuntimeException("Oranos {$method} returned associative array (likely error object): " . json_encode($data));
        }

        // Check first item has 'id' key
        $first = $data[0] ?? null;
        if (! is_array($first) || ! array_key_exists('id', $first)) {
            throw new RuntimeException("Oranos {$method} returned malformed item (missing 'id'): " . json_encode($first));
        }

        return $data;
    }

    public function getCategoryProducts(int $oranosCategoryId, string $lang = 'ar'): array
    {
        return $this->http()->get("{$this->base}/api/category/products/{$oranosCategoryId}?lang={$lang}")->json() ?? [];
    }

    public function getConfigs(string $lang = 'ar', string $currency = 'USD'): array
    {
        return $this->http()->get("{$this->base}/api/configs?lang={$lang}&currency={$currency}")->json() ?? [];
    }

    public function getProfile(): array
    {
        return $this->http()->get("{$this->base}/client/api/profile")->json() ?? [];
    }

    public function createOrder(int $productId, int $quantity, string $playerId, array $extraParams = []): array
    {
        $query = http_build_query(array_merge([
            'qty' => $quantity,
            'playerId' => $playerId,
            'order_uuid' => Str::random(32),
        ], $extraParams));

        try {
            $response = $this->http()->get("{$this->base}/client/api/newOrder/{$productId}/params?{$query}");
        } catch (\Exception $e) {
            Log::error('Oranos API createOrder failed', ['error' => $e->getMessage(), 'product_id' => $productId]);
            throw new RuntimeException('Oranos API request failed: '.$e->getMessage());
        }

        if (! $response->successful()) {
            Log::error('Oranos API createOrder error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'product_id' => $productId,
            ]);
            throw new RuntimeException('Oranos API error: '.$response->status());
        }

        return $response->json();
    }

    public function checkOrders(array $orderIds): array
    {
        $orders = rawurlencode(json_encode($orderIds));

        try {
            $response = $this->http()->get("{$this->base}/client/api/check?orders={$orders}");
        } catch (\Exception $e) {
            Log::error('Oranos API checkOrders failed', ['error' => $e->getMessage(), 'order_ids' => $orderIds]);
            throw new RuntimeException('Oranos API request failed: '.$e->getMessage());
        }

        if (! $response->successful()) {
            Log::error('Oranos API checkOrders error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Oranos API error: '.$response->status());
        }

        return $response->json();
    }
}
