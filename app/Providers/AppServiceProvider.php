<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\CompanyReview;
use App\Models\Job;
use App\Models\Resume;
use App\Observers\ApplicationObserver;
use App\Observers\CompanyReviewObserver;
use App\Observers\JobObserver;
use App\Observers\ResumeObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Job::observe(JobObserver::class);
        Application::observe(ApplicationObserver::class);
        CompanyReview::observe(CompanyReviewObserver::class);
        Resume::observe(ResumeObserver::class);
    }
}
