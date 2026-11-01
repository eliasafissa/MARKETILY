<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::all()->groupBy('group')->map(function ($groupSettings) {
            return $groupSettings->keyBy('key');
        });

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['required', 'string'],
            'type' => ['sometimes', 'string', 'in:string,float,int,boolean,json'],
        ]);

        $this->saveSettings([ $data ]);
        return response()->json(['message' => 'Setting updated.']);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.key' => ['required', 'string'],
            'items.*.value' => ['required', 'string'],
            'items.*.type' => ['sometimes', 'string'],
        ]);

        $this->saveSettings($items['items']);
        return response()->json(['message' => 'Settings updated.']);
    }

    private function saveSettings(array $items): void
    {
        $restricted = ['oranos_markup_percent', 'vip1_discount_percent', 'vip2_discount_percent', 'vip3_discount_percent', 'vip4_discount_percent'];
        foreach ($items as $item) {
            if (in_array($item['key'], $restricted, true)) {
                $v = $item['value'];
                if (! is_numeric($v) || ! is_finite((float) $v) || (float) $v < 0 ||
                    (float) $v > ($item['key'] === 'oranos_markup_percent' ? 1000 : 100)) {
                    throw ValidationException::withMessages(['value' => ['Markup must be 0–1000%; VIP discounts 0–100%.']]);
                }
            }
        }
        DB::transaction(function () use ($items, $restricted) {
            $markupChanged = false;
            foreach ($items as $item) {
                $key = $item['key'];
                $existing = Setting::where('key', $key)->first();
                if ($key === 'oranos_markup_percent' && (string) ($existing?->value ?? '20') !== (string) $item['value']) {
                    $markupChanged = true;
                }
                $group = str_contains($key, '_discount_percent') ? Setting::GROUP_VIP
                    : ($key === 'oranos_markup_percent' ? Setting::GROUP_PAYMENT : ($existing?->group ?? Setting::GROUP_GENERAL));
                Setting::updateOrCreate(['key' => $key], [
                    'value' => $item['value'],
                    'type' => in_array($key, $restricted, true) ? 'float' : ($item['type'] ?? $existing?->type ?? 'string'),
                    'group' => $group,
                ]);
            }
            if ($markupChanged && Artisan::call('oranos:apply-markup') !== 0) {
                throw new \RuntimeException('Oranos price recalculation failed. Settings were rolled back.');
            }
        });
    }

    /** Update company info (admin only) */
    public function updateCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Company details can be saved independently (e.g. WhatsApp only).
            'company_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'string', 'max:255'],
            'twitter_url' => ['nullable', 'string', 'max:255'],
            'telegram_url' => ['nullable', 'string', 'max:255'],
            // MARKETLLY-WHATSAPP-VALIDATION-V1
            'whatsapp_url' => ['nullable', 'string', 'max:2048'],
        ]);

        // Save every validated field, including empty strings (clearing must work).
        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '', Setting::GROUP_COMPANY);
        }

        return response()->json(['message' => 'Company info updated.', 'settings' => $data]);
    }

    /** Update legal page content (admin only) */
    public function updateLegal(Request $request, string $page): JsonResponse
    {
        $keyPrefix = match ($page) {
            'terms'   => 'legal_terms',
            'privacy' => 'legal_privacy',
            'refund'  => 'legal_refund',
            default   => null,
        };

        if (! $keyPrefix) {
            return response()->json(['message' => 'Unknown legal page.'], 404);
        }

        $data = $request->validate([
            'content_en' => ['sometimes', 'nullable', 'string'],
            'content_ar' => ['sometimes', 'nullable', 'string'],
        ]);

        if (array_key_exists('content_en', $data)) {
            Setting::set("{$keyPrefix}_en", $data['content_en'] ?? '', Setting::GROUP_LEGAL);
        }
        if (array_key_exists('content_ar', $data)) {
            Setting::set("{$keyPrefix}_ar", $data['content_ar'] ?? '', Setting::GROUP_LEGAL);
        }

        return response()->json(['message' => 'Legal page updated.', 'page' => $page]);
    }
}
