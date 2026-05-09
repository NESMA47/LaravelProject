<?php

namespace App\Jobs;

use App\Models\Employer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateEmployerRating implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $employerId)
    {
    }

    public function handle(): void
    {
        $stats = \App\Models\CompanyReview::query()
            ->where('employer_id', $this->employerId)
            ->where('is_approved', true)
            ->selectRaw('COUNT(*) as total, AVG(rating_overall) as average')
            ->first();

        Employer::where('id', $this->employerId)->update([
            'total_reviews' => (int) $stats->total,
            'average_rating' => $stats->average ? round((float) $stats->average, 1) : 0.0,
        ]);
    }
}
