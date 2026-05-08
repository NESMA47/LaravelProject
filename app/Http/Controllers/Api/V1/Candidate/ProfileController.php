<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Resources\CandidateResource;
use App\Models\Candidate;
use App\Services\ProfileCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $candidate = Candidate::with(['educations', 'experiences', 'candidateSkills.skill', 'resumes.file'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new CandidateResource($candidate),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $candidate = Candidate::where('user_id', Auth::id())->firstOrFail();
        $data = $request->validated();

        $candidate->fill($data);
        $candidate->profile_completion_score = ProfileCompletionService::calculate($candidate);
        $candidate->save();

        return response()->json([
            'success' => true,
            'data' => new CandidateResource($candidate->fresh()->load(['educations', 'experiences', 'candidateSkills.skill', 'resumes.file'])),
        ]);
    }
}
