<?php

namespace App\Http\Controllers\Api\Vip;

use App\Enums\VipLevel;
use App\Http\Controllers\Controller;
use App\Services\CatalogPricing;
use App\Services\VipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VipController extends Controller
{
    public function __construct(private readonly VipService $vip) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $level = $this->vip->effectiveLevel($user);
        return response()->json([
            'level' => $level->value,
            'label' => $level->label(),
            'discount_percent' => CatalogPricing::percentageFor($level),
            'thresholds' => ['vip1' => 1000, 'vip2' => 2000, 'vip3' => 5000],
            'vip4_admin_only' => true,
            'discounts' => [
                'vip1' => CatalogPricing::percentageFor(VipLevel::Vip1),
                'vip2' => CatalogPricing::percentageFor(VipLevel::Vip2),
                'vip3' => CatalogPricing::percentageFor(VipLevel::Vip3),
                'vip4' => CatalogPricing::percentageFor(VipLevel::Vip4),
            ],
            'withdrawal_limit' => $this->vip->withdrawalLimit($user),
            'fee_percent' => $this->vip->feePercent($user),
            'balance' => (float) $user->balance,
            ...$this->vip->progress($user),
        ]);
    }
}
