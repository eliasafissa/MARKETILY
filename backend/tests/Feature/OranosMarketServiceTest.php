<?php

namespace Tests\Feature;

use App\Services\OranosMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OranosMarketServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_products_throws_on_error_response(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/products' => Http::response([
                'status' => 'ERROR',
                'msg' => 'rate limited',
                'code' => 100,
            ], 200),
        ]);

        $service = app(OranosMarketService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oranos getProducts returned associative array');

        $service->getProducts();
    }

    public function test_get_products_throws_on_empty_response(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/products' => Http::response([], 200),
        ]);

        $service = app(OranosMarketService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oranos getProducts returned empty response');

        $service->getProducts();
    }

    public function test_get_products_throws_on_non_array_response(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/products' => Http::response('{"status": "OK"}', 200), // object, not array
        ]);

        $service = app(OranosMarketService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oranos getProducts returned associative array');

        $service->getProducts();
    }

    public function test_get_products_throws_on_malformed_item(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/products' => Http::response([
                ['name' => 'test'], // missing 'id' key
            ], 200),
        ]);

        $service = app(OranosMarketService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oranos getProducts returned malformed item');

        $service->getProducts();
    }

    public function test_get_products_returns_list_on_success(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/products' => Http::response([
                ['id' => 1, 'name' => 'Product 1', 'price' => 10],
                ['id' => 2, 'name' => 'Product 2', 'price' => 20],
            ], 200),
        ]);

        $service = app(OranosMarketService::class);

        $products = $service->getProducts();

        $this->assertIsArray($products);
        $this->assertCount(2, $products);
        $this->assertEquals(1, $products[0]['id']);
        $this->assertEquals('Product 1', $products[0]['name']);
    }

    public function test_get_categories_throws_on_error_response(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/categories' => Http::response([
                'status' => 'ERROR',
                'msg' => 'unauthorized',
                'code' => 401,
            ], 200),
        ]);

        $service = app(OranosMarketService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oranos getCategories returned associative array');

        $service->getCategories();
    }

    public function test_get_categories_throws_on_empty_response(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/categories' => Http::response([], 200),
        ]);

        $service = app(OranosMarketService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oranos getCategories returned empty response');

        $service->getCategories();
    }

    public function test_get_categories_throws_on_non_array_response(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/categories' => Http::response('{"status": "OK"}', 200), // object, not array
        ]);

        $service = app(OranosMarketService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oranos getCategories returned associative array');

        $service->getCategories();
    }

    public function test_get_categories_throws_on_malformed_item(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/categories' => Http::response([
                ['name' => 'category'], // missing 'id' key
            ], 200),
        ]);

        $service = app(OranosMarketService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oranos getCategories returned malformed item');

        $service->getCategories();
    }

    public function test_get_categories_returns_list_on_success(): void
    {
        Http::fake([
            'api.oranosmarket.com/client/api/categories' => Http::response([
                ['id' => 1, 'name' => 'Category 1'],
                ['id' => 2, 'name' => 'Category 2'],
            ], 200),
        ]);

        $service = app(OranosMarketService::class);

        $categories = $service->getCategories();

        $this->assertIsArray($categories);
        $this->assertCount(2, $categories);
        $this->assertEquals(1, $categories[0]['id']);
        $this->assertEquals('Category 1', $categories[0]['name']);
    }
}