<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function __construct(
        private PasswordResetService $passwordService,
    ) {
    }

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordService->createToken($request->input('email'));

        return response()->json([
            'success' => true,
            'message' => 'If the email exists, a reset link has been sent.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $success = $this->passwordService->reset(
            $request->input('email'),
            $request->input('token'),
            $request->input('password')
        );

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }
}
