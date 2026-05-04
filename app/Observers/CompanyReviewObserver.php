<?php

namespace App\Observers;

use App\Jobs\RecalculateEmployerRating;
use App\Models\CompanyReview;

class CompanyReviewObserver
{
    public function saved(CompanyReview $review): void
    {
        if ($review->is_approved) {
            RecalculateEmployerRating::dispatch($review->employer_id);
        }
    }

    public function deleted(CompanyReview $review): void
    {
        RecalculateEmployerRating::dispatch($review->employer_id);
    }
}
