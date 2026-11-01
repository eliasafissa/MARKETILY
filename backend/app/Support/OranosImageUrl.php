<?php

namespace App\Support;

/**
 * Only use real image files from the documented Oranos image path.
 * Never treat the API homepage as an image and never load a third-party host.
 */
final class OranosImageUrl
{
    public static function normalize(mixed $source): ?string
    {
        if (! is_string($source)) {
            return null;
        }

        $source = trim($source);
        if ($source === '' || strlen($source) > 2048 || str_contains($source, '\\') || preg_match('/[\x00-\x20\x7F]/', $source)) {
            return null;
        }

        if (str_starts_with($source, '//')) {
            return null;
        }

        if (preg_match('~^https?://~i', $source)) {
            $parts = parse_url($source);
            if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https'
                || strtolower($parts['host'] ?? '') !== 'api.oranosmarket.com'
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
                || isset($parts['query']) || isset($parts['fragment'])) {
                return null;
            }
            $path = $parts['path'] ?? '';
        } else {
            if (str_contains($source, ':') || str_contains($source, '?') || str_contains($source, '#')) {
                return null;
            }
            $path = $source;
        }

        $path = '/'.ltrim($path, '/');
        $path = preg_replace('~/+~', '/', $path);
        if (! is_string($path)
            || ! preg_match('~^/images/(?:category|product)/[A-Za-z0-9_./-]+\.(?:png|jpe?g|webp|gif|avif)$~i', $path)
            || in_array('..', explode('/', $path), true)
            || str_contains(strtolower($path), 'empty.png')) {
            return null;
        }

        return 'https://api.oranosmarket.com'.$path;
    }

    /** Recognize bad base-URL placeholders without touching other uploaded art. */
    public static function isEmptyApiUrl(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }
        $parts = parse_url(trim($url));
        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === 'api.oranosmarket.com'
            && trim($parts['path'] ?? '', '/') === ''
            && ! isset($parts['query']) && ! isset($parts['fragment']);
    }
}
