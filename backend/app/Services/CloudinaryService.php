<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloudinaryService
{
    private readonly string $cloudName;

    private readonly string $apiKey;

    private readonly string $apiSecret;

    public function __construct()
    {
        $url = config('services.cloudinary.url');

        // Local public storage is the safe default when Cloudinary is not configured.
        $url = $url ?: '';

        // Format: cloudinary://API_KEY:API_SECRET@CLOUD_NAME
        $parsed = parse_url($url);
        $this->apiKey = $parsed['user'] ?? '';
        $this->apiSecret = $parsed['pass'] ?? '';
        $this->cloudName = ltrim($parsed['host'] ?? '', '@');
    }

    public function upload(string $base64Data, string $folder = 'marketly'): string
    {
        if (strlen($base64Data) > 7_200_000 || ! preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D', $base64Data, $matches)) {
            throw new InvalidArgumentException('Invalid image format or size; upload PNG, JPEG or WebP up to 5 MB.');
        }
        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024 || strlen($bytes) === 0) {
            throw new InvalidArgumentException('Image exceeds the 5 MB limit.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $dimensions = @getimagesizefromstring($bytes);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (! isset($allowed[$mime]) || ! $dimensions || $dimensions[0] > 6000 || $dimensions[1] > 6000) {
            throw new InvalidArgumentException('Unsupported image content or dimensions.');
        }
        $safeFolder = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $folder);
        if ($this->cloudName === '' || $this->apiKey === '' || $this->apiSecret === '') {
            $relativePath = trim($safeFolder, '/') . '/' . Str::random(36) . '.' . $allowed[$mime];
            if (! Storage::disk('public')->put($relativePath, $bytes)) {
                throw new \RuntimeException('Could not save image to public storage.');
            }
            return '/storage/' . $relativePath;
        }

        $timestamp = time();
        $params = ['folder' => $safeFolder, 'timestamp' => $timestamp];
        $signature = $this->sign($params);
        try {
            $response = Http::asMultipart()->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload", [
                ['name' => 'file', 'contents' => $bytes, 'filename' => 'upload.' . $allowed[$mime]],
                ['name' => 'api_key', 'contents' => $this->apiKey],
                ['name' => 'timestamp', 'contents' => (string) $timestamp],
                ['name' => 'folder', 'contents' => $safeFolder],
                ['name' => 'signature', 'contents' => $signature],
            ]);
        } catch (ConnectionException $e) {
            Log::error('Cloudinary upload connection error', ['error' => $e->getMessage()]);
            throw $e;
        }
        if (! $response->successful()) {
            Log::error('Cloudinary upload failed', ['status' => $response->status()]);
            throw new \RuntimeException('Cloudinary upload failed: ' . $response->status());
        }
        return (string) $response->json('secure_url');
    }

    public function delete(string $url): void
    {
        $publicId = $this->extractPublicId($url);

        if (empty($publicId)) {
            Log::warning('Could not extract public_id from Cloudinary URL: '.$url);

            return;
        }

        $timestamp = time();
        $signature = $this->sign([
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ]);

        $response = Http::asForm()->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy", [
            'public_id' => $publicId,
            'api_key' => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        if (! $response->successful()) {
            Log::warning('Cloudinary delete failed', [
                'public_id' => $publicId,
                'status' => $response->status(),
            ]);
        }
    }

    private function sign(array $params): string
    {
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k.'='.$v.'&';
        }
        $str = rtrim($str, '&');
        $str .= $this->apiSecret;

        return sha1($str);
    }

    private function extractPublicId(string $url): ?string
    {
        // Cloudinary URLs contain /upload/ before the public_id
        if (preg_match('/\/upload\/(?:v\d+\/)?(.+)\.[a-z]+$/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
