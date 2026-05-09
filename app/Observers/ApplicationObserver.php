<?php

namespace App\Observers;

use App\Models\Application;

class ApplicationObserver
{
    public function created(Application $application): void
    {
        if ($application->job_id) {
            $application->job()->increment('applications_count');
        }
    }

    public function deleted(Application $application): void
    {
        if ($application->job_id) {
            $application->job()->decrement('applications_count');
        }
    }
}
