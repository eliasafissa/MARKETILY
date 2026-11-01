<?php

namespace Tests\Unit;

use App\Support\OranosImageUrl;
use PHPUnit\Framework\TestCase;

class OranosImageUrlTest extends TestCase
{
    public function test_documented_relative_image_path(): void
    {
        $this->assertSame('https://api.oranosmarket.com/images/category/1710948113.webp',
            OranosImageUrl::normalize('images/category/1710948113.webp'));
    }

    public function test_double_slash_in_real_image_path_is_normalized(): void
    {
        $this->assertSame('https://api.oranosmarket.com/images/category/photo.webp',
            OranosImageUrl::normalize('https://api.oranosmarket.com//images/category/photo.webp'));
    }

    public function test_api_homepage_is_not_a_photo(): void
    {
        $this->assertNull(OranosImageUrl::normalize('https://api.oranosmarket.com/'));
        $this->assertTrue(OranosImageUrl::isEmptyApiUrl('https://api.oranosmarket.com/'));
    }

    public function test_external_or_unsafe_urls_are_rejected(): void
    {
        foreach ([
            'http://api.oranosmarket.com/images/category/a.webp',
            'https://bad.example/images/category/a.webp',
            'https://api.oranosmarket.com.evil.example/images/category/a.webp',
            'https://api.oranosmarket.com/images/category/../secret.webp',
            'https://api.oranosmarket.com/images/category/a.webp?redirect=evil',
            '//evil.example/images/category/a.webp',
            'javascript:alert(1)',
            'https://api.oranosmarket.com/images/category/empty.png',
        ] as $bad) {
            $this->assertNull(OranosImageUrl::normalize($bad), $bad);
        }
    }
}
