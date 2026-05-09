<?php

namespace App\Observers;

use App\Models\Job;
use App\Services\ApplicationStageService;
use Illuminate\Support\Facades\DB;

class JobObserver
{
    public function deleted(Job $job): void
    {
        if (! $job->isForceDeleting()) {
            $this->handleSoftDelete($job);
        }
    }

    private function handleSoftDelete(Job $job): void
    {
        $applications = $job->applications()
            ->whereNotIn('current_status', ['hired', 'rejected', 'withdrawn'])
            ->get();

        foreach ($applications as $application) {
            DB::transaction(function () use ($application) {
                $application->update([
                    'job_removed_at' => now(),
                    'current_status' => 'job_removed',
                ]);

                ApplicationStageService::insertStage(
                    $application,
                    'job_removed',
                    'This job listing was removed by the employer.',
                    null,
                    true
                );

                // Cancel and soft-delete all scheduled interviews
                $application->interviews()
                    ->where('status', 'scheduled')
                    ->whereNull('deleted_at')
                    ->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => 'job_removed',
                        'cancellation_note' => 'This interview was cancelled because the employer removed the job listing.',
                    ]);

                $application->interviews()
                    ->where('status', 'cancelled')
                    ->whereNull('deleted_at')
                    ->get()
                    ->each->delete();
            });
        }
    }
}
