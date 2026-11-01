<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityNotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $deposits = Transaction::where('user_id', $uid)->where('type', 'deposit')
            ->where(function ($q) {
                $q->where('meta->stage', '!=', 'awaiting_transfer')->orWhereNull('meta->stage');
            })->orderByDesc('id')->limit(50)->get();
        $orders = Order::where('user_id', $uid)->orderByDesc('id')->limit(50)->get();
        $events = collect();
        foreach ($deposits as $d) {
            $state = $d->status instanceof \BackedEnum ? $d->status->value : (string) $d->status;
            $label = match ($state) {
                'approved', 'completed', 'success' => 'تم اعتماد الإيداع وإضافة الرصيد',
                'rejected', 'failed' => 'تم رفض الإيداع',
                default => (($d->meta['stage'] ?? '') === 'awaiting_admin' ? 'الإيداع بانتظار المراجعة' : 'حالة الإيداع قيد الانتظار'),
            };
            $events->push(['id'=>'deposit-'.$d->id.'-'.$state, 'type'=>'deposit', 'status'=>$state,
                'title'=>$label, 'detail'=> $state === 'rejected' ? ($d->rejection_reason ?? '') : '',
                'created_at'=>optional($d->updated_at)->toIso8601String(), 'link'=>'/dashboard/deposits']);
        }
        foreach ($orders as $o) {
            $state = $o->status instanceof \BackedEnum ? $o->status->value : (string) $o->status;
            $label = match ($state) {
                'completed', 'delivered', 'success' => 'اكتمل طلب الشراء بنجاح',
                'rejected', 'failed', 'cancelled', 'canceled' => 'تعذر تنفيذ طلب الشراء',
                default => 'تم تسجيل طلب الشراء أو تحديث حالته',
            };
            $events->push(['id'=>'order-'.$o->id.'-'.$state,'type'=>'order','status'=>$state,
                'title'=>$label, 'detail'=>'طلب #'.$o->id,
                'created_at'=>optional($o->updated_at)->toIso8601String(), 'link'=>'/dashboard/orders']);
        }
        return response()->json(['notifications'=>$events->sortByDesc('created_at')->take(50)->values()]);
    }
}
