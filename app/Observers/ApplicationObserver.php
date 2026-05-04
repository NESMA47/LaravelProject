<?php

namespace App\Observers;

use App\Models\Application;

class ApplicationObserver
{
    public function created(Application $application): void
    {
        $application->job()->increment('applications_count');
    }

    public function deleted(Application $application): void
    {
        $application->job()->decrement('applications_count');
    }
}
