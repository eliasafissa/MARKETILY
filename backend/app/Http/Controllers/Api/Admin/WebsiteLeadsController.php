<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteLeadsController extends Controller
{
    public function update(Request $request, int $id)
    {
        $input = $request->validate(['status'=>['required','in:new,contacted,in_progress,completed,rejected']]);
        $updated = DB::table('website_leads')->where('id',$id)->update(['status'=>$input['status'],'updated_at'=>now()]);
        return $updated ? response()->json(['status'=>$input['status']]) : response()->json(['message'=>'الطلب غير موجود'],404);
    }
    public function index(Request $request)
    {
        $request->validate(['search'=>'nullable|string|max:120','date_from'=>'nullable|date_format:Y-m-d','date_to'=>'nullable|date_format:Y-m-d']);
        $q = DB::table('website_leads');
        if ($request->filled('search')) {
            $s = mb_substr((string)$request->input('search'),0,120);
            $q->where(function($sub) use($s) { $sub->where('name','like','%'.$s.'%')->orWhere('email','like','%'.$s.'%')->orWhere('phone','like','%'.$s.'%')->orWhere('details','like','%'.$s.'%'); });
        }
        if ($request->filled('date_from')) $q->whereDate('created_at','>=',$request->input('date_from'));
        if ($request->filled('date_to')) $q->whereDate('created_at','<=',$request->input('date_to'));
        return response()->json($q->orderByDesc('id')->paginate(25));
    }
}
