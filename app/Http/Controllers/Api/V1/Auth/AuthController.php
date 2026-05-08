<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private FileUploadService $fileService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $result['access_token'],
                'token_type' => $result['token_type'],
                'user' => new UserResource($result['user']),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->input('email'),
            $request->input('password')
        );

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (isset($result['deactivated'])) {
            return response()->json([
                'success' => false,
                'message' => 'Account deactivated. Contact support.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $result['access_token'],
                'token_type' => $result['token_type'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['candidate', 'employer']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully.',
        ]);
    }

    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $file = $this->fileService->upload(
                $request->file('avatar'),
                $user,
                'avatar',
                null,
                null,
            );

            if ($user->avatar_file_id) {
                $oldFile = $user->avatarFile;
                $user->avatar_url = null;
                $user->avatar_file_id = null;
                $user->save();
                if ($oldFile) {
                    $this->fileService->delete($oldFile);
                }
            }

            $data['avatar_file_id'] = $file->id;
            $data['avatar_url'] = $file->url;
        }

        $user = $this->authService->updateProfile($user, $data);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load(['candidate', 'employer'])),
        ]);
    }
}
