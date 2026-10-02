<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CloudinaryService
{
    private readonly string $cloudName;

    private readonly string $apiKey;

    private readonly string $apiSecret;

    public function __construct()
    {
        $url = config('services.cloudinary.url');

        if (empty($url)) {
            throw new InvalidArgumentException('CLOUDINARY_URL is not configured.');
        }

        // Format: cloudinary://API_KEY:API_SECRET@CLOUD_NAME
        $parsed = parse_url($url);
        $this->apiKey = $parsed['user'] ?? '';
        $this->apiSecret = $parsed['pass'] ?? '';
        $this->cloudName = ltrim($parsed['host'] ?? '', '@');
    }

    public function upload(string $base64Data, string $folder = 'marketly'): string
    {
        $mime = 'image/png';
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $mime = 'image/'.$matches[1];
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        }

        $timestamp = time();
        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        $signature = $this->sign($params);

        try {
            $response = Http::asMultipart()
                ->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload", [
                    ['name' => 'file', 'contents' => base64_decode($base64Data), 'filename' => 'upload.'.($mime === 'image/jpeg' ? 'jpg' : 'png')],
                    ['name' => 'api_key', 'contents' => $this->apiKey],
                    ['name' => 'timestamp', 'contents' => (string) $timestamp],
                    ['name' => 'folder', 'contents' => $folder],
                    ['name' => 'signature', 'contents' => $signature],
                ]);
        } catch (ConnectionException $e) {
            Log::error('Cloudinary upload connection error', ['error' => $e->getMessage()]);
            throw $e;
        }

        if (! $response->successful()) {
            Log::error('Cloudinary upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Cloudinary upload failed: '.$response->status());
        }

        $data = $response->json();

        Log::info('Cloudinary upload successful', [
            'public_id' => $data['public_id'] ?? null,
            'url' => $data['secure_url'] ?? null,
        ]);

        return $data['secure_url'];
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
