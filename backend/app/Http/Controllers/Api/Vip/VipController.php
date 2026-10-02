<?php

namespace App\Http\Controllers\Api\Vip;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VipLevel;
use App\Events\VipLevelChanged;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\VipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VipController extends Controller
{
    public function __construct(private readonly VipService $vip)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'level' => $user->vip_level->value,
            'label' => $user->vip_level->label(),
            'withdrawal_limit' => $this->vip->withdrawalLimit($user),
            'fee_percent' => $this->vip->feePercent($user),
            'balance' => (float) $user->balance,
            'upgrade_prices' => [
                'vip1' => $this->vip->upgradePrice(VipLevel::Vip1),
                'vip2' => $this->vip->upgradePrice(VipLevel::Vip2),
                'vip3' => $this->vip->upgradePrice(VipLevel::Vip3),
            ],
        ]);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target' => ['required', 'string', 'in:vip1,vip2,vip3'],
        ]);

        $user = $request->user();
        $target = VipLevel::from($data['target']);
        $price = $this->vip->upgradePrice($target);

        if ($user->vip_level === $target) {
            return response()->json(['message' => 'You already hold this VIP level.'], 422);
        }

        if ($target === VipLevel::Vip2 && $user->vip_level === VipLevel::None) {
            return response()->json(['message' => 'You must upgrade to VIP1 first.'], 422);
        }

        if ($target === VipLevel::Vip3 && $user->vip_level !== VipLevel::Vip2) {
            return response()->json(['message' => 'يجب الوصول إلى VIP2 أولاً.'], 422);
        }

        if ((float) $user->balance < $price) {
            return response()->json(['message' => 'Insufficient balance.'], 422);
        }

        $oldLevel = $user->vip_level->value;
        $user->decrement('balance', $price);
        $user->update(['vip_level' => $target]);

        Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::VipUpgrade,
            'amount' => $price,
            'fee' => 0,
            'status' => TransactionStatus::Approved,
            'method' => 'cash_wallet',
            'meta' => ['from' => $oldLevel, 'to' => $target->value],
        ]);

        event(new VipLevelChanged($user, $oldLevel, $target->value));

        return response()->json([
            'user' => $user->fresh(),
            'message' => "Upgraded to {$target->label()}.",
        ]);
    }
}
