<?php

namespace App\Services;

use App\Models\Employer;
use App\Models\Job;
use Illuminate\Support\Str;

class JobService
{
    public static function generateSlug(string $title, Employer $employer): string
    {
        $base = Str::slug($title . ' ' . $employer->company_name . ' ' . now()->format('Y-m'));
        $slug = $base;
        $counter = 2;

        while (Job::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Employer-allowed status transitions.
     * Returns null if allowed, or error message if forbidden.
     */
    public static function canEmployerTransition(Job $job, string $newStatus): ?string
    {
        $current = $job->status;

        // Employer can only transition draft -> pending_review
        if ($current === 'draft' && $newStatus === 'pending_review') {
            return null;
        }

        // For active/paused/closed toggles, job must be confirmed
        if (! $job->is_confirmed) {
            return 'Job must be approved by admin before status can be changed.';
        }

        $allowed = [
            'active' => ['paused', 'closed'],
            'paused' => ['active', 'closed'],
            'closed' => ['active', 'paused'],
        ];

        if (isset($allowed[$current]) && in_array($newStatus, $allowed[$current])) {
            return null;
        }

        return "Cannot transition from {$current} to {$newStatus}.";
    }

    /**
     * Admin-allowed status transitions.
     */
    public static function canAdminTransition(Job $job, string $newStatus): ?string
    {
        $current = $job->status;

        // Admin can do almost anything
        $forbidden = [
            'expired' => ['active', 'paused', 'closed', 'draft'],
        ];

        if (isset($forbidden[$current]) && in_array($newStatus, $forbidden[$current])) {
            return 'Expired jobs cannot be reactivated. Create a new job posting.';
        }

        return null;
    }

    public static function syncSkills(Job $job, array $skills): void
    {
        $job->jobSkills()->delete();

        foreach ($skills as $skill) {
            $job->jobSkills()->create([
                'skill_id' => $skill['skill_id'],
                'is_required' => $skill['is_required'] ?? true,
                'min_proficiency' => $skill['min_proficiency'] ?? null,
            ]);
        }
    }
}
