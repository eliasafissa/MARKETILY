<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class WebsiteLeadCaptureController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:100'],
            'email' => ['required','email','max:255'],
            'phone' => ['required','string','max:50'],
            'site_type' => ['required','string','in:store,business,portfolio,other'],
            'needs' => ['required','string','max:2000'],
        ]);
        // Persist to our own database first. SMTP is OPTIONAL and never determines whether a lead is recorded.
        $id = DB::table('website_leads')->insertGetId([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
            'details' => json_encode(['site_type'=>$data['site_type'],'needs'=>$data['needs']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'status' => 'new', 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            if (\App\Http\Controllers\Api\Auth\EmailVerificationController::mailReady()) {
                Mail::raw("طلب إنشاء موقع رقم {$id}\nالاسم: {$data['name']}\nالبريد: {$data['email']}\nالهاتف: {$data['phone']}\nالنوع: {$data['site_type']}\nالوصف: {$data['needs']}",
                    fn ($mail) => $mail->to(config('mail.from.address'))->subject('Marketlly - طلب إنشاء موقع'));
            }
        } catch (\Throwable $e) { report($e); /* DB record is already safely committed */ }
        return response()->json(['ok'=>true, 'request_id'=>$id, 'message'=>'تم استلام الطلب وحفظه'], 201);
    }
}
