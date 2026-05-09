<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job' => $this->whenLoaded('job', fn () => [
                'id' => $this->job->id,
                'title' => $this->job->title,
                'slug' => $this->job->slug,
                'employer' => [
                    'company_name' => $this->job->employer->company_name ?? null,
                    'logo_url' => $this->job->employer->logo_url ?? null,
                ],
                'location' => $this->job->location,
                'type' => $this->job->type,
                'salary_min' => $this->job->is_salary_visible ? $this->job->salary_min : null,
                'salary_max' => $this->job->is_salary_visible ? $this->job->salary_max : null,
                'currency' => $this->job->currency,
                'is_salary_visible' => $this->job->is_salary_visible,
            ]),
            'saved_at' => $this->saved_at,
            'notes' => $this->notes,
        ];
    }
}
