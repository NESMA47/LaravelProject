<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ApplyRequest;
use App\Http\Requests\Candidate\WithdrawRequest;
use App\Http\Resources\ApplicationDetailResource;
use App\Http\Resources\ApplicationListResource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;
use App\Services\ApplicationService;
use App\Services\ApplicationStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    private function getCandidate(): Candidate
    {
        return Candidate::where('user_id', Auth::id())->firstOrFail();
    }

    private function getCandidateApplication(string $id): ?Application
    {
        $candidate = $this->getCandidate();

        return Application::where('id', $id)
            ->where('candidate_id', $candidate->id)
            ->first();
    }

    // C-1: List my applications
    public function index(): JsonResponse
    {
        $candidate = $this->getCandidate();

        $applications = Application::withCount('interviews')
            ->where('candidate_id', $candidate->id)
            ->when(request('status'), function ($q, $status) {
                $q->where('current_status', $status);
            })
            ->orderBy('applied_at', 'desc')
            ->paginate(request('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ApplicationListResource::collection($applications),
                'meta' => [
                    'current_page' => $applications->currentPage(),
                    'last_page' => $applications->lastPage(),
                    'per_page' => $applications->perPage(),
                    'total' => $applications->total(),
                ],
            ],
        ]);
    }

    // C-2: Get single application detail
    public function show(string $id): JsonResponse
    {
        $application = $this->getCandidateApplication($id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        $application->load(['stages.changedBy', 'interviews' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'success' => true,
            'data' => new ApplicationDetailResource($application),
        ]);
    }

    // C-3: Apply to a job
    public function store(ApplyRequest $request): JsonResponse
    {
        $candidate = $this->getCandidate();
        $data = $request->validated();

        $job = Job::where('id', $data['job_id'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or no longer active.',
            ], 404);
        }

        // Check duplicate application (original_job_id + candidate_id)
        $existing = Application::where('original_job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied for this job.',
            ], 409);
        }

        // Validate resume
        $resumeId = $data['resume_id'] ?? null;
        if ($resumeId) {
            $resume = $candidate->resumes()->where('id', $resumeId)->first();
            if (! $resume) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected resume does not belong to you.',
                ], 422);
            }
        } else {
            if ($candidate->resumes()->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload a resume first.',
                ], 422);
            }
        }

        $application = ApplicationService::apply(
            $candidate,
            $job,
            $data['cover_letter'] ?? null,
            $resumeId
        );

        $application->load(['stages.changedBy', 'interviews' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'success' => true,
            'data' => new ApplicationDetailResource($application),
        ], 201);
    }

    // C-4: Withdraw application
    public function withdraw(string $id, WithdrawRequest $request): JsonResponse
    {
        $application = $this->getCandidateApplication($id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        try {
            ApplicationService::withdraw($application, $request->input('reason'), Auth::id());
        } catch (\RuntimeException $e) {
            $code = match (true) {
                str_contains($e->getMessage(), 'already withdrawn') => 409,
                str_contains($e->getMessage(), 'accepted') => 403,
                str_contains($e->getMessage(), 'closed by the employer') => 403,
                str_contains($e->getMessage(), 'job was removed') => 403,
                default => 422,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $application->id,
                'current_status' => $application->current_status,
                'withdrawn_at' => $application->withdrawn_at,
                'withdrawn_reason' => $application->withdrawn_reason,
            ],
        ]);
    }
}
