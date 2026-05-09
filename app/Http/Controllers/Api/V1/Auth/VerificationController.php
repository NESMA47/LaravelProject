<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        // TODO: Implement email verification logic with signed URLs or tokens

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified.',
            ], 400);
        }

        // TODO: Queue verification email

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent.',
        ]);
    }
}
