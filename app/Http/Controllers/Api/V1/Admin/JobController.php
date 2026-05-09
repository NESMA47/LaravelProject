<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{

    public function index(): JsonResponse
    {
        $jobs = Job::with('employer')->latest()->get();
    
         return response()->json([
        'success' => true,
        'data' => $jobs
    ]);
    }
    public function confirm(string $id): JsonResponse
    {
        $job = Job::findOrFail($id);

        DB::transaction(function () use ($job) {
            $job->update([
                'is_confirmed' => true,
                'status' => 'active',
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

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,pending_review,active,paused,closed,rejected,expired'],
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
        }

        $job->update($update);

        return response()->json([
            'success' => true,
            'data' => new JobResource($job->fresh()->load(['employer', 'category', 'jobSkills.skill'])),
        ]);
    }
}
