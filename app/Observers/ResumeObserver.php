<?php

namespace App\Observers;

use App\Models\Resume;

class ResumeObserver
{
    /**
     * Ensure only one default resume per candidate.
     */
    public function saving(Resume $resume): void
    {
        if ($resume->is_default) {
            Resume::query()
                ->where('candidate_id', $resume->candidate_id)
                ->where('id', '!=', $resume->id)
                ->update(['is_default' => false]);
        }
    }

    /**
     * If the deleted resume was the default, promote the most recent one.
     */
    public function deleted(Resume $resume): void
    {
        if ($resume->is_default) {
            $next = Resume::query()
                ->where('candidate_id', $resume->candidate_id)
                ->latest('updated_at')
                ->first();

            if ($next) {
                $next->update(['is_default' => true]);
            }
        }
    }
}
