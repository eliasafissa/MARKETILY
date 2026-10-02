<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\PartnerRequestController;
use App\Models\PartnerApiRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PartnerRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user already has a pending or approved request
        $existing = PartnerApiRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json([
                'ok' => false,
                'error' => 'already_requested',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'store_name' => ['required', 'string', 'max:255'],
            'store_url' => ['required', 'url', 'max:500'],
            'phone' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $partnerRequest = PartnerApiRequest::create([
            'user_id' => $user->id,
            'store_name' => $request->store_name,
            'store_url' => $request->store_url,
            'phone' => $request->phone,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        return response()->json([
            'ok' => true,
            'request' => $partnerRequest->load('user'),
        ]);
    }

    public function myRequest(Request $request): JsonResponse
    {
        $user = $request->user();

        $partnerRequest = PartnerApiRequest::where('user_id', $user->id)
            ->latest()
            ->first();

        $apiKey = null;
        if ($partnerRequest && $partnerRequest->status === 'approved') {
            $apiKey = $user->api_key;
        }

        return response()->json([
            'ok' => true,
            'request' => $partnerRequest,
            'api_key' => $apiKey,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = PartnerApiRequest::with(['user', 'approver'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(25);

        return response()->json([
            'ok' => true,
            'data' => $requests,
        ]);
    }

    public function approve(PartnerApiRequest $partnerApiRequest): JsonResponse
    {
        if ($partnerApiRequest->status === 'approved') {
            return response()->json([
                'ok' => true,
                'api_key' => $partnerApiRequest->user->api_key,
            ]);
        }

        $key = \Illuminate\Support\Str::random(64);

        $partnerApiRequest->user->update([
            'api_key' => $key,
            'api_key_generated_at' => now(),
        ]);

        $partnerApiRequest->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return response()->json([
            'ok' => true,
            'api_key' => $key,
        ]);
    }

    public function reject(Request $request, PartnerApiRequest $partnerApiRequest): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $partnerApiRequest->update([
            'status' => 'rejected',
            'rejected_reason' => $request->reason,
        ]);

        return response()->json(['ok' => true]);
    }
}