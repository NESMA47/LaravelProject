<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // 8.2: List all users
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => UserResource::collection($users),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ],
        ]);
    }

    // 8.3: User detail + role-specific activity
    public function show(string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $activity = [];

        if ($user->role === 'candidate') {
            $user->load('candidate');
            $activity['applications_count'] = $user->candidate?->applications()->count() ?? 0;
        }

        if ($user->role === 'employer') {
            $user->load('employer');
            $activity['jobs_count'] = $user->employer?->jobs()->count() ?? 0;
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(
                (new UserResource($user))->resolve(),
                ['activity' => $activity]
            ),
        ]);
    }

    // 8.4: Activate/deactivate user
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Prevent deactivating another admin
        if ($user->role === 'admin' && $user->id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate another admin account.',
            ], 403);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update(['is_active' => $validated['is_active']]);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }
}
