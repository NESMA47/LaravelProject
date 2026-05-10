<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\Employer;
use App\Models\Job;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    // Existing: confirm, reject, updateStatus (from earlier US)

    // 8.5: List all jobs with moderation queue
    public function index(Request $request): JsonResponse
    {
        $query = Job::with(['employer', 'category', 'jobSkills.skill']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('employer_id')) {
            $query->where('employer_id', $request->input('employer_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $jobs = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => JobResource::collection($jobs),
                'meta' => [
                    'current_page' => $jobs->currentPage(),
                    'last_page' => $jobs->lastPage(),
                    'per_page' => $jobs->perPage(),
                    'total' => $jobs->total(),
                ],
            ],
        ]);
    }

    // 8.6: Job detail + application list
    public function show(string $id): JsonResponse
    {
        $job = Job::with(['employer', 'category', 'jobSkills.skill'])->find($id);

        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.',
            ], 404);
        }

        $applications = $job->applications()
            ->with(['candidate.user'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'job' => new JobResource($job),
                'applications' => $applications,
            ],
        ]);
    }

    // 8.7: Admin update job status (override existing)
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,pending_review,active,paused,closed,rejected,expired'],
            'rejection_reason' => ['nullable', 'string', 'required_if:status,rejected'],
        ]);

        $job = Job::findOrFail($id);
        $newStatus = $validated['status'];

        $error = JobService::canAdminTransition($job, $newStatus);
        if ($error) {
            return response()->json([
                'success' => false,
                'message' => $error,
            ], 409);
        }

        $update = ['status' => $newStatus];

        if ($newStatus === 'active') {
            $update['is_confirmed'] = true;
            $update['expires_at'] = now()->addDays(30);
        }

        if ($newStatus === 'rejected') {
            $update['rejection_reason'] = $validated['rejection_reason'];
        }

        $job->update($update);

        return response()->json([
            'success' => true,
            'data' => new JobResource($job->fresh()->load(['employer', 'category', 'jobSkills.skill'])),
        ]);
    }

public function destroy(string $id): JsonResponse
{
    $job = Job::find($id);

    if (! $job) {
        return response()->json([
            'success' => false,
            'message' => 'Job not found.',
        ], 404);
    }

    try {
        $job->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Job has been permanently removed.',
        ]);

    } catch (\Illuminate\Database\QueryException $e) {
        if ($e->getCode() == '23000') {
            return response()->json([
                'success' => false,
                'message' => 'This job cannot be deleted because it is linked to other data (like skills or applications). You can reject it instead.',
            ], 422);
        }

        return response()->json([
            'success' => false,
            'message' => 'An error occurred while deleting the job.',
        ], 500);
    }
}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employer_id' => ['required', 'string', 'uuid', 'exists:employers,id'],
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['sometimes', 'nullable', 'string', 'uuid', 'exists:categories,id'],
            'description' => ['required', 'string'],
            'requirements' => ['required', 'string'],
            'responsibilities' => ['sometimes', 'nullable', 'string'],
            'benefits' => ['sometimes', 'nullable', 'string'],
            'type' => ['required', 'string', 'in:full_time,part_time,contract,freelance,internship'],
            'workplace_type' => ['required', 'string', 'in:remote,on_site,hybrid'],
            'experience_level' => ['required', 'string', 'in:junior,mid,senior,lead,executive'],
            'career_level' => ['sometimes', 'nullable', 'string', 'max:50'],
            'education_level' => ['sometimes', 'nullable', 'string', 'in:high_school,bachelor,master,phd,diploma,any'],
            'salary_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_salary_visible' => ['sometimes', 'boolean'],
            'location' => ['required', 'string', 'max:200'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'vacancies' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:draft,pending_review,active,paused,closed,rejected,expired'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'skills' => ['sometimes', 'array'],
            'skills.*.skill_id' => ['required_with:skills', 'string', 'uuid', 'exists:skills,id'],
            'skills.*.is_required' => ['sometimes', 'boolean'],
            'skills.*.min_proficiency' => ['sometimes', 'nullable', 'string', 'in:beginner,intermediate,advanced,expert'],
        ]);

        $employer = Employer::findOrFail($data['employer_id']);

        $jobData = array_merge($data, [
            'employer_id' => $employer->id,
            'posted_by_user_id' => auth()->id(),
            'slug' => JobService::generateSlug($data['title'], $employer),
            'status' => $data['status'] ?? 'active',
            'is_confirmed' => true,
        ]);

        if ($jobData['status'] === 'active') {
            $jobData['expires_at'] = now()->addDays(30);
        }

        $job = DB::transaction(function () use ($jobData, $data) {
            $job = Job::create($jobData);

            if (! empty($data['skills'])) {
                JobService::syncSkills($job, $data['skills']);
            }

            return $job;
        });

        return response()->json([
            'success' => true,
            'data' => new JobResource($job->load(['employer', 'category', 'jobSkills.skill'])),
        ], 201);
    }

    public function confirm(string $id): JsonResponse
    {
        $job = Job::findOrFail($id);

        DB::transaction(function () use ($job) {
            $job->update([
                'is_confirmed' => true,
                'status' => 'active',
                'expires_at' => now()->addDays(30),
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => new JobResource($job->fresh()->load(['employer', 'category', 'jobSkills.skill'])),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        $job = Job::findOrFail($id);
        $job->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'success' => true,
            'data' => new JobResource($job->fresh()->load(['employer', 'category', 'jobSkills.skill'])),
        ]);
    }
}