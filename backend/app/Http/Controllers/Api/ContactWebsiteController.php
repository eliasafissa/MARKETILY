<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactWebsiteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'needs' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            Mail::raw(
                "New website inquiry:\n\n" .
                "Name: {$data['name']}\n" .
                "Email: {$data['email']}\n" .
                "Phone: {$data['phone']}\n\n" .
                "Needs:\n{$data['needs']}",
                function ($message) use ($data) {
                    $message->to(config('mail.from.address'))
                        ->subject('Marketly - New Website Inquiry from ' . $data['name']);
                }
            );

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Contact website email failed', ['error' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'error' => 'Failed to send inquiry',
            ], 500);
        }
    }
}