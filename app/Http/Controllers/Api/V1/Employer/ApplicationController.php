<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\UpdateApplicationStatusRequest;
use App\Http\Resources\ApplicationDetailResource;
use App\Http\Resources\ApplicationListResource;
use App\Http\Resources\HistoryStageResource;
use App\Models\Application;
use App\Models\Employer;
use App\Models\Job;
use App\Services\ApplicationStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    private function getEmployer(): Employer
    {
        return Employer::where('user_id', Auth::id())->firstOrFail();
    }

    private function getEmployerApplication(string $id): ?Application
    {
        $employer = $this->getEmployer();

        return Application::where('id', $id)
            ->whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })
            ->first();
    }

    // E-1: Global inbox
    public function index(): JsonResponse
    {
        $employer = $this->getEmployer();

        // Validate job_id filter if provided
        $jobId = request('job_id');
        if ($jobId) {
            $job = Job::where('id', $jobId)->where('employer_id', $employer->id)->first();
            if (! $job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or does not belong to you.',
                ], 403);
            }
        }

        $query = Application::with(['stages'])
            ->whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })
            ->when(request('status'), function ($q, $status) {
                $q->where('current_status', $status);
            })
            ->when($jobId, function ($q) use ($jobId) {
                $q->where('job_id', $jobId);
            });

        $sort = request('sort', 'applied_at_desc');
        match ($sort) {
            'applied_at_asc' => $query->orderBy('applied_at', 'asc'),
            'updated_at_desc' => $query->orderBy('updated_at', 'desc'),
            default => $query->orderBy('applied_at', 'desc'),
        };

        $applications = $query->paginate(request('per_page', 20));

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

    // E-2: Per-job applications with pipeline summary
    public function jobApplications(string $jobId): JsonResponse
    {
        $employer = $this->getEmployer();

        $job = Job::where('id', $jobId)
            ->where('employer_id', $employer->id)
            ->first();

        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.',
            ], 404);
        }

        $query = Application::where('job_id', $jobId)
            ->when(request('status'), function ($q, $status) {
                $q->where('current_status', $status);
            });

        $sort = request('sort', 'applied_at_desc');
        match ($sort) {
            'applied_at_asc' => $query->orderBy('applied_at', 'asc'),
            'updated_at_desc' => $query->orderBy('updated_at', 'desc'),
            default => $query->orderBy('applied_at', 'desc'),
        };

        $applications = $query->paginate(request('per_page', 20));

        // Pipeline summary with single GROUP BY query
        $pipelineSummary = DB::table('applications')
            ->selectRaw('current_status, COUNT(*) as count')
            ->where('job_id', $jobId)
            ->groupBy('current_status')
            ->pluck('count', 'current_status')
            ->toArray();

        $allStages = ['applied', 'reviewed', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected', 'withdrawn', 'job_removed'];
        $summary = array_merge(
            array_fill_keys($allStages, 0),
            $pipelineSummary
        );

        return response()->json([
            'success' => true,
            'data' => [
                'pipeline_summary' => $summary,
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

    // E-3: Single application detail
    public function show(string $id): JsonResponse
    {
        $application = $this->getEmployerApplication($id);

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

    // E-4: Update application status
    public function updateStatus(string $id, UpdateApplicationStatusRequest $request): JsonResponse
    {
        $application = $this->getEmployerApplication($id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        $newStatus = $request->input('status');
        $currentStatus = $application->current_status;

        // Cannot update terminal stages
        if (ApplicationStageService::isTerminal($currentStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'Application is ' . $currentStatus . ' and cannot be modified.',
            ], 403);
        }

        $error = ApplicationStageService::getErrorMessage($currentStatus, $newStatus);
        if ($error) {
            return response()->json([
                'success' => false,
                'message' => $error,
            ], 409);
        }

        DB::transaction(function () use ($application, $newStatus, $request) {
            ApplicationStageService::insertStage(
                $application,
                $newStatus,
                $request->input('notes'),
                Auth::id(),
                false
            );
        });

        $application->load(['stages.changedBy']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $application->id,
                'current_status' => $application->current_status,
                'history' => HistoryStageResource::collection($application->stages),
            ],
        ]);
    }
}
