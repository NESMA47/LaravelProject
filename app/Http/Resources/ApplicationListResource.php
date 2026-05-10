<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $jobSnapshot = $this->job_snapshot ?? [];
        $employerSnapshot = $this->employer_snapshot ?? [];
        $candidateSnapshot = $this->candidate_snapshot ?? [];

        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'current_status' => $this->current_status,
            'job_removed_at' => $this->job_removed_at,
            'applied_at' => $this->applied_at,
            'updated_at' => $this->updated_at,
            'withdrawn_at' => $this->withdrawn_at,
            'cover_letter' => $request->route()->getPrefix() === 'api/v1/employer'
                ? (str($this->cover_letter)->limit(200)->value())
                : $this->cover_letter,
            'resume_url' => $this->resume_url,
            'interviews_count' => $this->whenCounted('interviews'),
            'job_snapshot' => [
                'title' => $jobSnapshot['title'] ?? null,
                'location' => $jobSnapshot['location'] ?? null,
                'type' => $jobSnapshot['type'] ?? null,
            ],
            'employer_snapshot' => [
                'company_name' => $employerSnapshot['company_name'] ?? null,
                'slug' => $employerSnapshot['slug'] ?? null,
                'logo_url' => $employerSnapshot['logo_url'] ?? null,
            ],
            'candidate_snapshot' => [
                'name' => $candidateSnapshot['name'] ?? null,
                'email' => $candidateSnapshot['email'] ?? null,
                'headline' => $candidateSnapshot['headline'] ?? null,
                'location' => $candidateSnapshot['location'] ?? null,
                'skills' => $candidateSnapshot['skills'] ?? [],
                'resume_url' => $candidateSnapshot['resume_url'] ?? null,
            ],
        ];
    }
}
