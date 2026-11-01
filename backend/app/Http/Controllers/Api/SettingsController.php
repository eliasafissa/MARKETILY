<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /** Public company info (name, contact, social links) */
    public function company(): JsonResponse
    {
        $settings = Setting::where('group', Setting::GROUP_COMPANY)->get();

        $data = [];
        foreach ($settings as $s) {
            $data[$s->key] = $s->castValue();
        }

        return response()->json(['settings' => $data]);
    }

    /** Legal page content */
    public function legal(string $page, \Illuminate\Http\Request $request): JsonResponse
    {
        $locale = $request->query('locale', 'en');

        $key = match ($page) {
            'terms'   => "legal_terms_{$locale}",
            'privacy' => "legal_privacy_{$locale}",
            'refund'  => "legal_refund_{$locale}",
            default   => null,
        };

        if (! $key) {
            return response()->json(['message' => 'Unknown legal page.'], 404);
        }

        $setting = Setting::where('key', $key)->first();
        $content = $setting?->castValue() ?? '';

        return response()->json([
            'page' => $page,
            'content' => $content,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ]);
    }
}
