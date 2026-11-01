<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'items.product']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // HAIDAR-V5-SEARCH-ORDERS: includes product names, customers and date range.
        if ($request->filled('search')) {
            $needle = mb_substr(trim((string) $request->input('search')), 0, 120);
            $query->where(function ($q) use ($needle) {
                $q->whereHas('user', function ($uq) use ($needle) {
                      $uq->where('name', 'like', '%'.$needle.'%')->orWhere('email', 'like', '%'.$needle.'%');
                  })->orWhereHas('items.product', function ($pq) use ($needle) {
                      $pq->where('name', 'like', '%'.$needle.'%')->orWhere('name_ar', 'like', '%'.$needle.'%');
                  });
                if (ctype_digit($needle)) $q->orWhere('id', (int) $needle);
            });
        }
        if ($request->filled('date_from')) {
            $request->validate(['date_from' => 'date_format:Y-m-d']);
            $query->whereDate('created_at', '>=', (string) $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $request->validate(['date_to' => 'date_format:Y-m-d']);
            $query->whereDate('created_at', '<=', (string) $request->input('date_to'));
        }

        if ($request->boolean('manual')) {
            $query->where(function ($q) {
                $q->whereHas('items.product', fn ($p) => $p->where('type', 'manual'))
                    ->orWhereNotNull('failure_reason')
                    ->orWhere('payment_method', '!=', 'cash_wallet');
            });
        }

        $orders = $query->latest()->paginate(25);

        return response()->json($orders);
    }

    public function pendingManual(): JsonResponse
    {
        $orders = Order::with(['user', 'items.product'])
            ->whereIn('status', ['pending', 'processing'])
            ->where(function ($q) {
                $q->whereHas('items.product', fn ($p) => $p->where('type', 'manual'))
                    ->orWhereNotNull('failure_reason')
                    ->orWhere('payment_method', '!=', 'cash_wallet');
            })
            ->latest()
            ->paginate(25);

        return response()->json($orders);
    }

    public function pendingManualCount(): JsonResponse
    {
        $count = Order::whereIn('status', ['pending', 'processing'])
            ->where(function ($q) {
                $q->whereHas('items.product', fn ($p) => $p->where('type', 'manual'))
                    ->orWhereNotNull('failure_reason')
                    ->orWhere('payment_method', '!=', 'cash_wallet');
            })
            ->count();

        return response()->json(['count' => $count]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,processing,completed,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            match ($data['status']) {
                'completed' => $this->orders->markCompleted($order),
                'rejected' => $this->orders->markRejected($order, $request->string('notes')->toString()),
                'processing' => $this->orders->markProcessing($order),
                default => $order->update(['status' => $data['status']]),
            };
        } catch (\Throwable $e) {
            Log::error('Order status update failed', [
                'order_id' => $order->id,
                'status' => $data['status'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e instanceof \DomainException ? $e->getMessage() : 'Failed to update order status'], $e instanceof \DomainException ? 422 : 500);
        }

        return response()->json(['order' => $order->fresh(['items.product'])]);
    }
}
