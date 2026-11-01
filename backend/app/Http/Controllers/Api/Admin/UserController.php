<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VipLevel;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\OrderStatus;
use App\Services\VipService;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles');

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn($q) => $q->where('name', 'LIKE', "%{$term}%")->orWhere('email', 'LIKE', "%{$term}%"));
        }

        if ($request->filled('vip_level')) {
            $query->where('vip_level', $request->string('vip_level'));
        }

        if ($request->boolean('banned')) {
            $query->whereNotNull('banned_at');
        }

        $users = $query->withSum(['orders as completed_spend' => fn ($q) => $q->where('status', OrderStatus::Completed->value)], 'total')
            ->latest()->paginate(25);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles', 'orders', 'transactions');
        return response()->json(['user' => $user]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'vip_override' => ['sometimes', 'string', 'in:auto,none,vip1,vip2,vip3,vip4'],
            'banned' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('vip_override', $data)) {
            if (! $request->user()->hasRole('admin')) {
                return response()->json(['message' => 'Only admin may manage VIP levels.'], 403);
            }
            $selected = $data['vip_override'];
            $user->vip_override = $selected === 'auto' ? null : VipLevel::from($selected);
            unset($data['vip_override']);
        }

        if (array_key_exists('banned', $data)) {
            $user->banned_at = $data['banned'] ? now() : null;
            unset($data['banned']);
        }

        $user->fill($data);
        $user->save();
        if ($request->has('vip_override')) {
            app(VipService::class)->syncLevel($user);
        }

        return response()->json(['user' => $user->fresh()->load('roles')]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->hasRole('admin')) {
            return response()->json(['message' => 'Cannot delete an admin.'], 422);
        }
        // DB foreign keys cascade user deletion into transactions/orders: protect historical records.
        if ($user->transactions()->exists() || $user->orders()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف حساب يحتوي على إيداعات أو طلبات أو سجل مالي؛ استخدم تعطيل الحساب للحفاظ على الأرشيف.',
            ], 409);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted.']);
    }

    public function adjustBalance(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (float) $data['amount'];
        $note = $data['note'] ?? null;

        return DB::transaction(function () use ($user, $amount, $note) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            $user->balance += $amount;
            $user->save();

            Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::AdminAdjustment,
                'amount' => $amount,
                'fee' => 0,
                'status' => TransactionStatus::Approved,
                'method' => 'admin',
                'gateway_ref' => 'admin-' . auth()->id() . '-' . now()->timestamp,
                'meta' => $note ? ['note' => $note, 'admin_id' => auth()->id()] : ['admin_id' => auth()->id()],
            ]);

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'balance' => (float) $user->balance,
                ],
                'message' => $amount >= 0 ? 'Balance added successfully' : 'Balance deducted successfully',
            ]);
        });
    }
}
