<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobSearchRequest;
use App\Http\Resources\JobDetailResource;
use App\Http\Resources\JobListResource;
use App\Models\Category;
use App\Models\Employer;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function index(JobSearchRequest $request): JsonResponse
    {
        $query = Job::with(['employer', 'category', 'jobSkills.skill'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });

        // Search (MySQL FULLTEXT with LIKE fallback for short terms)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                // For SQLite compatibility in tests, use LIKE instead of FULLTEXT
                if (DB::getDriverName() === 'sqlite') {
                    $q->where('title', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%')
                      ->orWhere('requirements', 'like', '%' . $search . '%');
                } else {
                    $q->whereFullText(['title', 'description', 'requirements'], $search)
                      ->orWhere('title', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%')
                      ->orWhere('requirements', 'like', '%' . $search . '%');
                }
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->input('category'))->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Workplace filter
        if ($request->filled('workplace')) {
            $query->where('workplace_type', $request->input('workplace'));
        }

        // Experience level
        if ($request->filled('experience')) {
            $query->where('experience_level', $request->input('experience'));
        }

        // Location (city LIKE)
        if ($request->filled('location')) {
            $location = $request->input('location');
            $query->where(function ($q) use ($location) {
                $q->where('city', 'like', '%' . $location . '%')
                  ->orWhere('location', 'like', '%' . $location . '%');
            });
        }

        // Salary range
        if ($request->filled('salary_min')) {
            $salaryMin = $request->input('salary_min');
            $query->where(function ($q) use ($salaryMin) {
                $q->where('salary_max', '>=', $salaryMin)
                  ->orWhereNull('salary_max');
            });
        }
        if ($request->filled('salary_max')) {
            $salaryMax = $request->input('salary_max');
            $query->where(function ($q) use ($salaryMax) {
                $q->where('salary_min', '<=', $salaryMax)
                  ->orWhereNull('salary_min');
            });
        }

        // Skills filter (jobs having ANY of the specified skills)
        if ($request->filled('skills')) {
            $skillIds = $request->input('skills');
            $query->whereHas('jobSkills', function ($q) use ($skillIds) {
                $q->whereIn('skill_id', $skillIds);
            });
        }

        // Sorting
        $sort = $request->input('sort', 'created_at:desc');
        [$column, $direction] = explode(':', $sort);
        $allowedSorts = ['created_at', 'salary_max', 'applications_count'];
        $allowedDirs = ['asc', 'desc'];
        if (in_array($column, $allowedSorts, true) && in_array($direction, $allowedDirs, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->latest();
        }

        $perPage = $request->input('per_page', 20);
        $perPage = min((int) $perPage, 50);

        $jobs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => JobListResource::collection($jobs),
                'meta' => [
                    'current_page' => $jobs->currentPage(),
                    'last_page' => $jobs->lastPage(),
                    'per_page' => $jobs->perPage(),
                    'total' => $jobs->total(),
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $job = Job::with(['employer', 'category', 'jobSkills.skill'])->find($id);

        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.',
            ], 404);
        }

        $isPubliclyVisible = $job->status === 'active'
            && is_null($job->deleted_at)
            && (is_null($job->expires_at) || $job->expires_at > now());

        if (! $isPubliclyVisible) {
            $user = auth('sanctum')->user();
            $canView = false;

            if ($user) {
                if ($user->role === 'admin') {
                    $canView = true;
                } elseif ($user->role === 'employer') {
                    $employer = Employer::where('user_id', $user->id)->first();
                    if ($employer && $job->employer_id === $employer->id) {
                        $canView = true;
                    }
                }
            }

            if (! $canView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found.',
                ], 404);
            }
        }

        // Increment views count only for public views
        if ($isPubliclyVisible) {
            $job->increment('views_count');
        }

        return response()->json([
            'success' => true,
            'data' => new JobDetailResource($job),
        ]);
    }
}
