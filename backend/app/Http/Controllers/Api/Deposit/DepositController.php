<?php

namespace App\Http\Controllers\Api\Deposit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\DepositStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepositRequest;
use App\Models\Transaction;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepositController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $deposits = Transaction::where('user_id', $request->user()->id)
            ->where('type', TransactionType::Deposit)
            ->latest()
            ->paginate(20);

        return response()->json($deposits);
    }

    public function store(StoreDepositRequest $request): JsonResponse
    {
        $method = $request->string('method')->toString();
        $amount = (float) $request->float('amount');
        $user   = $request->user();

        $gateway = $this->gateways->driver($method);
        $depositData = $gateway->createDeposit($amount, 'USD', ['user_id' => $user->id]);

        $transaction = DB::transaction(function () use ($user, $amount, $method, $depositData) {
            $autoApprove = $method === 'cash_wallet';

            $txn = Transaction::create([
                'user_id'     => $user->id,
                'type'        => TransactionType::Deposit,
                'amount'      => $amount,
                'fee'         => 0,
                'status'      => $autoApprove ? TransactionStatus::Approved : TransactionStatus::Pending,
                'method'      => $method,
                'gateway_ref' => $depositData['reference'] ?? null,
                'meta'        => $depositData,
            ]);

            if ($autoApprove) {
                $user->increment('balance', $amount);
                $this->safeBroadcast(new DepositStatusChanged($txn));
            }

            return $txn;
        });

        return response()->json([
            'transaction' => $transaction,
            'deposit'     => $depositData,
            'balance'     => (float) $user->fresh()->balance,
        ], 201);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['transaction' => $transaction]);
    }

    private function safeBroadcast(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed (non-fatal)', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
