<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

class MarketlyImagePriorityTest extends TestCase
{
    public function test_category_prefers_admin_image_and_falls_back_to_oranos(): void
    {
        $category = new Category();
        $category->setRawAttributes([
            'image_url' => null,
            'admin_image_url' => 'https://example.com/admin.jpg',
            'oranos_image_url' => 'https://api.oranosmarket.com/images/provider.jpg',
        ]);
        $this->assertSame('https://example.com/admin.jpg', $category->image_url);
        $category->admin_image_url = null;
        $this->assertSame('https://api.oranosmarket.com/images/provider.jpg', $category->image_url);
    }

    public function test_product_prefers_admin_image_and_falls_back_to_oranos(): void
    {
        $product = new Product();
        $product->setRawAttributes([
            'image_url' => null,
            'admin_image_url' => 'https://example.com/owner.jpg',
            'oranos_image_url' => 'https://api.oranosmarket.com/images/provider.jpg',
        ]);
        $this->assertSame('https://example.com/owner.jpg', $product->image_url);
        $product->admin_image_url = null;
        $this->assertSame('https://api.oranosmarket.com/images/provider.jpg', $product->image_url);
    }
}
