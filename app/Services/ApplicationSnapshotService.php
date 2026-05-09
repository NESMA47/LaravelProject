<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;

class ApplicationSnapshotService
{
    public static function createSnapshots(Application $application, Job $job, Candidate $candidate): void
    {
        $application->job_snapshot = self::buildJobSnapshot($job);
        $application->employer_snapshot = self::buildEmployerSnapshot($job->employer);
        $application->candidate_snapshot = self::buildCandidateSnapshot($candidate);
        $application->save();
    }

    private static function buildJobSnapshot(Job $job): array
    {
        return [
            'title' => $job->title,
            'description' => $job->description,
            'requirements' => $job->requirements,
            'benefits' => $job->benefits,
            'type' => $job->type,
            'workplace_type' => $job->workplace_type,
            'experience_level' => $job->experience_level,
            'salary_min' => $job->salary_min,
            'salary_max' => $job->salary_max,
            'currency' => $job->currency,
            'location' => $job->location,
            'skills' => $job->jobSkills->map(fn ($js) => [
                'name' => $js->skill?->name,
                'is_required' => $js->is_required,
            ])->toArray(),
        ];
    }

    private static function buildEmployerSnapshot(\App\Models\Employer $employer): array
    {
        return [
            'company_name' => $employer->company_name,
            'slug' => $employer->slug,
            'logo_url' => $employer->logo_url,
            'industry' => $employer->industry,
            'website' => $employer->website,
            'headquarters' => $employer->headquarters,
            'is_verified' => $employer->is_verified,
        ];
    }

    private static function buildCandidateSnapshot(Candidate $candidate): array
    {
        $user = $candidate->user;
        $defaultResume = $candidate->resumes()->where('is_default', true)->with('file')->first();

        return [
            'name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'headline' => $candidate->headline,
            'location' => $candidate->location,
            'bio' => $candidate->bio,
            'skills' => $candidate->candidateSkills->map(fn ($cs) => $cs->skill?->name)->filter()->values()->toArray(),
            'experience_summary' => $candidate->experiences->first()?->title . ' at ' . $candidate->experiences->first()?->company_name,
            'education_summary' => $candidate->educations->first()?->degree . ', ' . $candidate->educations->first()?->institution,
            'linkedin_url' => $candidate->linkedin_url,
            'github_url' => $candidate->github_url,
            'portfolio_url' => $candidate->portfolio_url,
            'website_url' => $candidate->website_url,
            'expected_salary_min' => $candidate->expected_salary_min,
            'expected_salary_max' => $candidate->expected_salary_max,
            'resume_url' => $defaultResume?->file?->url,
            'resume_title' => $defaultResume?->title,
        ];
    }
}
