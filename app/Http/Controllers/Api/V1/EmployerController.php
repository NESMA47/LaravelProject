<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyReviewResource;
use App\Http\Resources\EmployerPublicResource;
use App\Http\Resources\JobResource;
use App\Models\Employer;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employers = Employer::query()
            ->with(['jobs' => function ($q) {
                $q->where('status', 'active')
                  ->whereNull('deleted_at')
                  ->where(function ($q2) {
                      $q2->whereNull('expires_at')
                         ->orWhere('expires_at', '>', now());
                  })
                  ->latest()
                  ->take(3);
            }])
            ->orderBy('company_name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => EmployerPublicResource::collection($employers),
                'meta' => [
                    'current_page' => $employers->currentPage(),
                    'last_page' => $employers->lastPage(),
                    'per_page' => $employers->perPage(),
                    'total' => $employers->total(),
                ],
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $employer = Employer::where('slug', $slug)->first();

        if (! $employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer not found.',
            ], 404);
        }

        $activeJobs = Job::where('employer_id', $employer->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->take(6)
            ->get();

        $recentReviews = $employer->reviews()
            ->where('is_approved', true)
            ->latest()
            ->take(3)
            ->get();

        $employer->setRelation('jobs', $activeJobs);
        $employer->setRelation('reviews', $recentReviews);

        return response()->json([
            'success' => true,
            'data' => new EmployerPublicResource($employer),
        ]);
    }

    public function reviews(string $slug, Request $request): JsonResponse
    {
        $employer = Employer::where('slug', $slug)->first();

        if (! $employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer not found.',
            ], 404);
        }

        $reviews = $employer->reviews()
            ->where('is_approved', true)
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => CompanyReviewResource::collection($reviews),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ],
        ]);
    }

    public function jobs(string $slug, Request $request): JsonResponse
    {
        $employer = Employer::where('slug', $slug)->first();

        if (! $employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer not found.',
            ], 404);
        }

        $jobs = Job::with(['category', 'jobSkills.skill'])
            ->where('employer_id', $employer->id)
            ->whereIn('status', ['active', 'paused'])
            ->where('is_confirmed', true)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => JobResource::collection($jobs),
        ]);
    }
}
