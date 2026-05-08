<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\EducationRequest;
use App\Http\Resources\CandidateEducationResource;
use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Services\ProfileCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EducationController extends Controller
{
    private function getCandidate(): Candidate
    {
        return Candidate::where('user_id', Auth::id())->firstOrFail();
    }

    public function store(EducationRequest $request): JsonResponse
    {
        $candidate = $this->getCandidate();
        $education = $candidate->educations()->create($request->validated());

        $candidate->profile_completion_score = ProfileCompletionService::calculate($candidate);
        $candidate->save();

        return response()->json([
            'success' => true,
            'data' => new CandidateEducationResource($education),
        ], 201);
    }

    public function update(EducationRequest $request, string $id): JsonResponse
    {
        $candidate = $this->getCandidate();
        $education = $candidate->educations()->findOrFail($id);
        $education->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CandidateEducationResource($education),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $candidate = $this->getCandidate();
        $education = $candidate->educations()->findOrFail($id);
        $education->delete();

        $candidate->profile_completion_score = ProfileCompletionService::calculate($candidate);
        $candidate->save();

        return response()->json([
            'success' => true,
            'message' => 'Education deleted successfully.',
        ]);
    }
}
