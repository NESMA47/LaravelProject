<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ExperienceRequest;
use App\Http\Resources\CandidateExperienceResource;
use App\Models\Candidate;
use App\Models\CandidateExperience;
use App\Services\ProfileCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ExperienceController extends Controller
{
    private function getCandidate(): Candidate
    {
        return Candidate::where('user_id', Auth::id())->firstOrFail();
    }

    public function store(ExperienceRequest $request): JsonResponse
    {
        $candidate = $this->getCandidate();
        $experience = $candidate->experiences()->create($request->validated());

        $candidate->profile_completion_score = ProfileCompletionService::calculate($candidate);
        $candidate->save();

        return response()->json([
            'success' => true,
            'data' => new CandidateExperienceResource($experience),
        ], 201);
    }

    public function update(ExperienceRequest $request, string $id): JsonResponse
    {
        $candidate = $this->getCandidate();
        $experience = $candidate->experiences()->findOrFail($id);
        $experience->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CandidateExperienceResource($experience),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $candidate = $this->getCandidate();
        $experience = $candidate->experiences()->findOrFail($id);
        $experience->delete();

        $candidate->profile_completion_score = ProfileCompletionService::calculate($candidate);
        $candidate->save();

        return response()->json([
            'success' => true,
            'message' => 'Experience deleted successfully.',
        ]);
    }
}
