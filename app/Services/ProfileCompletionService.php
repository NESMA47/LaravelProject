<?php

namespace App\Services;

use App\Models\Candidate;

class ProfileCompletionService
{
    public static function calculate(Candidate $candidate): int
    {
        $score = 0;

        $score += filled($candidate->headline) ? 10 : 0;
        $score += filled($candidate->bio) ? 10 : 0;
        $score += filled($candidate->location) ? 5 : 0;
        $score += filled($candidate->linkedin_url) || filled($candidate->github_url) || filled($candidate->portfolio_url) ? 10 : 0;
        $score += $candidate->experiences()->count() > 0 ? 20 : 0;
        $score += $candidate->educations()->count() > 0 ? 15 : 0;
        $score += $candidate->candidateSkills()->count() > 0 ? 15 : 0;
        $score += $candidate->resumes()->count() > 0 ? 10 : 0;
        $score += filled($candidate->expected_salary_min) ? 5 : 0;

        return min($score, 100);
    }
}
