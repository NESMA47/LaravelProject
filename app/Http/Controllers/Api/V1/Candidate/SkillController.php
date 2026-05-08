<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\SyncSkillsRequest;
use App\Http\Resources\CandidateSkillResource;
use App\Models\Candidate;
use App\Models\CandidateSkill;
use App\Services\ProfileCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SkillController extends Controller
{
    public function sync(SyncSkillsRequest $request): JsonResponse
    {
        $candidate = Candidate::where('user_id', Auth::id())->firstOrFail();
        $skills = $request->validated('skills');

        DB::transaction(function () use ($candidate, $skills) {
            $candidate->candidateSkills()->delete();

            foreach ($skills as $skill) {
                CandidateSkill::create([
                    'candidate_id' => $candidate->id,
                    'skill_id' => $skill['skill_id'],
                    'proficiency_level' => $skill['proficiency_level'] ?? 'intermediate',
                    'years_experience' => $skill['years_experience'] ?? null,
                ]);
            }
        });

        $candidate->profile_completion_score = ProfileCompletionService::calculate($candidate);
        $candidate->save();

        return response()->json([
            'success' => true,
            'data' => CandidateSkillResource::collection(
                $candidate->fresh()->candidateSkills()->with('skill')->get()
            ),
        ]);
    }
}
