<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CompanyReview;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_users'           => User::count(),
            'total_candidates'      => User::where('role', 'candidate')->count(),
            'total_employers'       => User::where('role', 'employer')->count(),
            'total_admins'          => User::where('role', 'admin')->count(),
            'total_jobs'            => Job::count(),
            'pending_jobs'          => Job::where('status', 'pending')->count(),
            'active_jobs'           => Job::where('status', 'active')->count(),
            'closed_jobs'           => Job::where('status', 'closed')->count(),
            'total_applications'    => Application::count(),
            'total_reviews_pending' => CompanyReview::where('is_approved', false)->count(),
        ];

        $recentJobs = Job::with('employer:id,company_name,slug')
            ->latest()->limit(5)
            ->get(['id', 'title', 'status', 'employer_id', 'created_at']);

        $recentUsers = User::latest()->limit(5)
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'created_at']);

        return response()->json([
            'success' => true,
            'data'    => compact('stats', 'recentJobs', 'recentUsers'),
        ]);
    }

    public function listUsers(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->role,   fn($q, $r)  => $q->where('role', $r))
            ->when($request->status, fn($q, $s)  => $q->where('is_active', $s === 'active'))
            ->when($request->search, fn($q, $kw) => $q->where(function ($q) use ($kw) {
                $q->where('first_name', 'like', "%{$kw}%")
                  ->orWhere('last_name',  'like', "%{$kw}%")
                  ->orWhere('email',      'like', "%{$kw}%");
            }))
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function showUser(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $activity = match ($user->role) {
            'candidate' => [
                'applications_count' => Application::where('candidate_id',
                    optional($user->candidate)->id)->count(),
            ],
            'employer' => [
                'jobs_count'  => Job::where('employer_id',
                    optional($user->employer)->id)->count(),
                'recent_jobs' => Job::where('employer_id',
                    optional($user->employer)->id)
                    ->latest()->limit(5)
                    ->get(['id', 'title', 'status', 'created_at']),
            ],
            default => [],
        };

        return response()->json([
            'success' => true,
            'data'    => array_merge($user->toArray(), ['activity' => $activity]),
        ]);
    }

    public function updateUserStatus(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change the status of another admin account.',
            ], 403);
        }

        $validated = $request->validate(['is_active' => 'required|boolean']);
        $user->update(['is_active' => $validated['is_active']]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated.',
            'data'    => $user->only('id', 'first_name', 'last_name', 'email', 'is_active'),
        ]);
    }

    public function listJobs(Request $request): JsonResponse
    {
        $jobs = Job::with(['employer:id,company_name,slug', 'category:id,name'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->withCount('applications')
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $jobs]);
    }

    public function showJob(string $id): JsonResponse
    {
        $job = Job::with([
            'employer:id,company_name,slug',
            'category:id,name',
            'skills:id,name',
        ])->withCount('applications')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $job]);
    }

    public function deleteJob(string $id): JsonResponse
    {
        $job = Job::findOrFail($id);
        $job->forceDelete();

        return response()->json(['success' => true, 'message' => 'Job permanently deleted.']);
    }
}