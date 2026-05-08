<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\UpdateProfileRequest;
use App\Http\Resources\EmployerResource;
use App\Models\Employer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $employer = Employer::with('teamMembers.user')
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new EmployerResource($employer),
        ]);
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $employer = Employer::with('teamMembers.user')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new EmployerResource($employer),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $employer = Employer::where('user_id', Auth::id())->firstOrFail();
        $employer->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new EmployerResource($employer->fresh()->load('teamMembers.user')),
        ]);
    }
}
