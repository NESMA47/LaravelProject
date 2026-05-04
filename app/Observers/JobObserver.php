<?php

namespace App\Observers;

use App\Models\Job;

class JobObserver
{
    public function created(Job $job): void
    {
        if ($job->category_id) {
            $job->category()->increment('jobs_count');
        }
    }

    public function deleted(Job $job): void
    {
        if ($job->category_id) {
            $job->category()->decrement('jobs_count');
        }
    }

    public function restored(Job $job): void
    {
        if ($job->category_id) {
            $job->category()->increment('jobs_count');
        }
    }

    public function updated(Job $job): void
    {
        $originalCategoryId = $job->getOriginal('category_id');
        $newCategoryId = $job->category_id;

        if ($originalCategoryId !== $newCategoryId) {
            if ($originalCategoryId) {
                \App\Models\Category::where('id', $originalCategoryId)->decrement('jobs_count');
            }
            if ($newCategoryId) {
                \App\Models\Category::where('id', $newCategoryId)->increment('jobs_count');
            }
        }
    }
}
