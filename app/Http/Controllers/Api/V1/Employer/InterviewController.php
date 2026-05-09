<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CancelInterviewRequest;
use App\Http\Requests\Employer\RescheduleInterviewRequest;
use App\Http\Requests\Employer\ScheduleInterviewRequest;
use App\Http\Requests\Employer\SetInterviewOutcomeRequest;
use App\Http\Resources\InterviewResource;
use App\Models\Application;
use App\Models\Employer;
use App\Models\Interview;
use App\Models\Job;
use App\Services\InterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InterviewController extends Controller
{
    private function getEmployer(): Employer
    {
        return Employer::where('user_id', Auth::id())->firstOrFail();
    }

    private function getApplication(string $id): ?Application
    {
        $employer = $this->getEmployer();

        return Application::where('id', $id)
            ->whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })
            ->first();
    }

    private function getInterview(string $applicationId, string $interviewId): ?Interview
    {
        $application = $this->getApplication($applicationId);
        if (! $application) {
            return null;
        }

        return Interview::withTrashed()
            ->where('id', $interviewId)
            ->where('application_id', $application->id)
            ->first();
    }

    // E-5: Schedule interview
    public function store(string $applicationId, ScheduleInterviewRequest $request): JsonResponse
    {
        $application = $this->getApplication($applicationId);

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        if (! in_array($application->current_status, ['shortlisted', 'interviewed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot schedule interview: application must be shortlisted or interviewed.',
            ], 403);
        }

        $data = $request->validated();
        $interview = InterviewService::schedule($application, $data, Auth::id());

        return response()->json([
            'success' => true,
            'data' => [
                'interview' => new InterviewResource($interview),
                'application_current_status' => $application->fresh()->current_status,
            ],
        ], 201);
    }

    // E-6: Reschedule interview
    public function reschedule(string $applicationId, string $interviewId, RescheduleInterviewRequest $request): JsonResponse
    {
        $interview = $this->getInterview($applicationId, $interviewId);

        if (! $interview) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found.',
            ], 404);
        }

        if ($interview->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Only scheduled interviews can be rescheduled.',
            ], 403);
        }

        if ($interview->deleted_at) {
            return response()->json([
                'success' => false,
                'message' => 'Interview has been deleted.',
            ], 403);
        }

        $interview = InterviewService::reschedule($interview, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new InterviewResource($interview),
        ]);
    }

    // E-7: Cancel interview
    public function cancel(string $applicationId, string $interviewId, CancelInterviewRequest $request): JsonResponse
    {
        $interview = $this->getInterview($applicationId, $interviewId);

        if (! $interview) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found.',
            ], 404);
        }

        if ($interview->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Only scheduled interviews can be cancelled.',
            ], 403);
        }

        $interview = InterviewService::cancel($interview, $request->input('cancellation_note'));

        return response()->json([
            'success' => true,
            'data' => new InterviewResource($interview),
        ]);
    }

    // E-8: Set interview outcome
    public function outcome(string $applicationId, string $interviewId, SetInterviewOutcomeRequest $request): JsonResponse
    {
        $interview = $this->getInterview($applicationId, $interviewId);

        if (! $interview) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found.',
            ], 404);
        }

        if ($interview->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Interview outcome can only be set for scheduled interviews.',
            ], 403);
        }

        if ($interview->deleted_at) {
            return response()->json([
                'success' => false,
                'message' => 'Interview has been deleted.',
            ], 403);
        }

        $data = $request->validated();
        $interview = InterviewService::setOutcome($interview, $data['status'], $data['notes'] ?? null);

        return response()->json([
            'success' => true,
            'data' => new InterviewResource($interview),
        ]);
    }
}
