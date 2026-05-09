<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'current_status' => $this->current_status,
            'job_removed_at' => $this->job_removed_at,
            'applied_at' => $this->applied_at,
            'updated_at' => $this->updated_at,
            'withdrawn_at' => $this->withdrawn_at,
            'withdrawn_reason' => $this->withdrawn_reason,
            'cover_letter' => $this->cover_letter,
            'resume_url' => $this->resume_url,
            'job_snapshot' => $this->job_snapshot,
            'employer_snapshot' => $this->employer_snapshot,
            'candidate_snapshot' => $this->candidate_snapshot,
            'history' => HistoryStageResource::collection($this->whenLoaded('stages')),
            'interviews' => InterviewResource::collection($this->whenLoaded('interviews')),
        ];
    }
}
