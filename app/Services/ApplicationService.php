<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

class ApplicationService
{
    public static function apply(Candidate $candidate, Job $job, ?string $coverLetter = null, ?string $resumeId = null): Application
    {
        // Determine resume URL
        $resume = null;
        if ($resumeId) {
            $resume = $candidate->resumes()->where('id', $resumeId)->with('file')->first();
        } else {
            $resume = $candidate->resumes()->where('is_default', true)->with('file')->first();
            if (! $resume) {
                $resume = $candidate->resumes()->with('file')->first();
            }
        }

        $resumeUrl = $resume?->file?->url;

        $application = DB::transaction(function () use ($candidate, $job, $coverLetter, $resumeUrl) {
            $application = Application::create([
                'job_id' => $job->id,
                'original_job_id' => $job->id,
                'candidate_id' => $candidate->id,
                'cover_letter' => $coverLetter,
                'current_status' => 'applied',
                'resume_url' => $resumeUrl,
                'applied_at' => now(),
            ]);

            // Create snapshots
            ApplicationSnapshotService::createSnapshots($application, $job, $candidate);

            // Insert initial stage
            ApplicationStageService::insertStage(
                $application,
                'applied',
                'Candidate applied to this job',
                null,
                true
            );

            return $application;
        });

        return $application;
    }

    public static function withdraw(Application $application, ?string $reason = null, string $candidateUserId): void
    {
        $current = $application->current_status;

        if ($current === 'withdrawn') {
            throw new \RuntimeException('You have already withdrawn this application.');
        }

        if ($current === 'hired') {
            throw new \RuntimeException('You cannot withdraw from an application that has been accepted.');
        }

        if ($current === 'rejected') {
            throw new \RuntimeException('This application has already been closed by the employer.');
        }

        if ($current === 'job_removed') {
            throw new \RuntimeException('This application is no longer active because the job was removed.');
        }

        DB::transaction(function () use ($application, $reason, $candidateUserId) {
            $application->update([
                'withdrawn_at' => now(),
                'withdrawn_reason' => $reason,
                'current_status' => 'withdrawn',
            ]);

            ApplicationStageService::insertStage(
                $application,
                'withdrawn',
                $reason ?? 'Candidate withdrew their application.',
                $candidateUserId,
                false
            );

            // Cancel all scheduled interviews
            $application->interviews()
                ->where('status', 'scheduled')
                ->whereNull('deleted_at')
                ->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'candidate_cancelled',
                    'cancellation_note' => 'Candidate withdrew their application.',
                ]);

            // Soft-delete cancelled interviews
            $application->interviews()
                ->where('status', 'cancelled')
                ->whereNull('deleted_at')
                ->get()
                ->each->delete();

            // Decrement counter (not below zero)
            DB::table('jobs')
                ->where('id', $application->job_id)
                ->where('applications_count', '>', 0)
                ->decrement('applications_count');
        });
    }
}
