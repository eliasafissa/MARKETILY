<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VipLevel;
use App\Events\VipLevelChanged;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $users = $query->latest()->paginate(25);

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
            'balance' => ['sometimes', 'numeric', 'min:0'],
            'vip_level' => ['sometimes', 'string', 'in:none,vip1,vip2'],
            'banned' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('vip_level', $data)) {
            $old = $user->vip_level->value;
            $user->vip_level = VipLevel::from($data['vip_level']);
            if ($old !== $data['vip_level']) {
                event(new VipLevelChanged($user, $old, $data['vip_level']));
            }
            unset($data['vip_level']);
        }

        if (array_key_exists('banned', $data)) {
            $user->banned_at = $data['banned'] ? now() : null;
            unset($data['banned']);
        }

        $user->fill($data);
        $user->save();

        return response()->json(['user' => $user->load('roles')]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->hasRole('admin')) {
            return response()->json(['message' => 'Cannot delete an admin.'], 422);
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
