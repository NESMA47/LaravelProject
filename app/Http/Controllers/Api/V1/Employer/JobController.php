<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CreateJobRequest;
use App\Http\Requests\Employer\UpdateJobRequest;
use App\Http\Requests\Employer\UpdateJobStatusRequest;
use App\Http\Resources\JobResource;
use App\Models\Employer;
use App\Models\Job;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function __construct(
        private JobService $jobService,
    ) {
    }

    private function getEmployer(): Employer
    {
        return Employer::where('user_id', Auth::id())->firstOrFail();
    }

    private function belongsToEmployer(Job $job): bool
    {
        $employer = $this->getEmployer();
        return $job->employer_id === $employer->id;
    }

    // Authenticated: list my jobs
    public function index(Request $request): JsonResponse
    {
        $employer = $this->getEmployer();
        $jobs = $job = Job::with(['category', 'jobSkills.skill'])
            ->where('employer_id', $employer->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => JobResource::collection($jobs),
        ]);
    }

    // Public: list jobs by employer slug
    public function indexByEmployer(string $slug, Request $request): JsonResponse
    {
        $employer = Employer::where('slug', $slug)->firstOrFail();
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

    // Public: show job by id
    public function show(string $id): JsonResponse
    {
        $job = Job::with(['employer', 'category', 'jobSkills.skill'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new JobResource($job),
        ]);
    }

    public function store(CreateJobRequest $request): JsonResponse
    {
        $employer = $this->getEmployer();
        $data = $request->validated();

        $slug = JobService::generateSlug($data['title'], $employer);

        $job = DB::transaction(function () use ($employer, $data, $slug) {
            $job = Job::create([
                'employer_id' => $employer->id,
                'posted_by_user_id' => Auth::id(),
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'],
                'requirements' => $data['requirements'],
                'responsibilities' => $data['responsibilities'] ?? null,
                'benefits' => $data['benefits'] ?? null,
                'type' => $data['type'],
                'workplace_type' => $data['workplace_type'],
                'experience_level' => $data['experience_level'],
                'career_level' => $data['career_level'] ?? null,
                'education_level' => $data['education_level'] ?? null,
                'salary_min' => $data['salary_min'] ?? null,
                'salary_max' => $data['salary_max'] ?? null,
                'is_salary_visible' => $data['is_salary_visible'] ?? true,
                'location' => $data['location'],
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? 'EG',
                'vacancies' => $data['vacancies'] ?? 1,
                'status' => $data['status'] ?? 'draft',
                'is_confirmed' => false,
                'expires_at' => isset($data['expires_at']) ? $data['expires_at'] : null,
            ]);

            if (! empty($data['skills'])) {
                JobService::syncSkills($job, $data['skills']);
            }

            return $job;
        });

        return response()->json([
            'success' => true,
            'data' => new JobResource($job->load(['category', 'jobSkills.skill'])),
        ], 201);
    }

    public function update(UpdateJobRequest $request, string $id): JsonResponse
    {
        $job = Job::findOrFail($id);

        if (! $this->belongsToEmployer($job)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        // Forbidden statuses for editing
        if (in_array($job->status, ['closed', 'rejected', 'expired'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit job with status ' . $job->status . '.',
            ], 409);
        }

        // Active jobs: restrict editable fields
        $data = $request->validated();
        if ($job->status === 'active') {
            $allowed = ['description', 'requirements', 'responsibilities', 'benefits', 'salary_min', 'salary_max', 'is_salary_visible', 'vacancies', 'expires_at', 'skills'];
            $data = array_intersect_key($data, array_flip($allowed));
        }

        DB::transaction(function () use ($job, $data) {
            $job->update($data);

            if (isset($data['skills'])) {
                JobService::syncSkills($job, $data['skills']);
            }
        });

        return response()->json([
            'success' => true,
            'data' => new JobResource($job->fresh()->load(['category', 'jobSkills.skill'])),
        ]);
    }

    public function updateStatus(UpdateJobStatusRequest $request, string $id): JsonResponse
    {
        $job = Job::findOrFail($id);

        if (! $this->belongsToEmployer($job)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $newStatus = $request->input('status');
        $error = JobService::canEmployerTransition($job, $newStatus);

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => $error,
            ], 409);
        }

        $job->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'data' => new JobResource($job->fresh()->load(['category', 'jobSkills.skill'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $job = Job::findOrFail($id);

        if (! $this->belongsToEmployer($job)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $job->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job deleted successfully.',
        ]);
    }
}
