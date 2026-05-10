<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CompanyReview;
use App\Models\Employer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_candidates' => User::where('role', 'candidate')->count(),
            'total_employers' => User::where('role', 'employer')->count(),
            'total_admins' => User::where('role', 'admin')->count(),
            'total_jobs' => Job::count(),
            'pending_jobs' => Job::where('status', 'pending_review')->count(),
            'active_jobs' => Job::where('status', 'active')->count(),
            'closed_jobs' => Job::where('status', 'closed')->count(),
            'total_applications' => Application::count(),
            'total_reviews_pending' => CompanyReview::where('is_approved', false)->whereNull('approved_at')->count(),
        ];

        $recentJobs = Job::with(['employer', 'category'])
            ->where('status', 'pending_review')
            ->latest()
            ->take(5)
            ->get();

        $recentUsers = User::latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_jobs' => $recentJobs,
                'recent_users' => $recentUsers,
            ],
        ]);
    }
}
