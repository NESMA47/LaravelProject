<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\SavedJobResource;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\SavedJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SavedJobController extends Controller
{
    private function getCandidate(): Candidate
    {
        return Candidate::where('user_id', Auth::id())->firstOrFail();
    }

    public function index(): JsonResponse
    {
        $candidate = $this->getCandidate();

        $savedJobs = SavedJob::with(['job.employer'])
            ->where('candidate_id', $candidate->id)
            ->orderBy('saved_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => SavedJobResource::collection($savedJobs),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $candidate = $this->getCandidate();

        $request->validate([
            'job_id' => ['required', 'string', 'uuid', 'exists:jobs,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = SavedJob::where('candidate_id', $candidate->id)
            ->where('job_id', $request->input('job_id'))
            ->first();

        if ($existing) {
            // Idempotent: update notes if provided
            if ($request->filled('notes')) {
                $existing->update(['notes' => $request->input('notes')]);
            }

            return response()->json([
                'success' => true,
                'data' => new SavedJobResource($existing->load(['job.employer'])),
            ]);
        }

        $savedJob = SavedJob::create([
            'candidate_id' => $candidate->id,
            'job_id' => $request->input('job_id'),
            'notes' => $request->input('notes'),
            'saved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => new SavedJobResource($savedJob->load(['job.employer'])),
        ], 201);
    }

    public function destroy(string $jobId): JsonResponse
    {
        $candidate = $this->getCandidate();

        $savedJob = SavedJob::where('candidate_id', $candidate->id)
            ->where('job_id', $jobId)
            ->first();

        if (! $savedJob) {
            return response()->json([
                'success' => false,
                'message' => 'Saved job not found.',
            ], 404);
        }

        $savedJob->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job removed from saved list.',
        ]);
    }
}
