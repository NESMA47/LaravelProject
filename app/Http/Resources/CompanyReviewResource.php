<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'employer_id' => $this->employer_id,
            'candidate_id' => $this->candidate_id,
            'job_title_at_time' => $this->job_title_at_time,
            'employment_type' => $this->employment_type,
            'is_current_employee' => $this->is_current_employee,
            'is_anonymous' => $this->is_anonymous,
            'rating_overall' => $this->rating_overall,
            'rating_work_life_balance' => $this->rating_work_life_balance,
            'rating_salary' => $this->rating_salary,
            'rating_culture' => $this->rating_culture,
            'rating_management' => $this->rating_management,
            'rating_career_growth' => $this->rating_career_growth,
            'title' => $this->title,
            'pros' => $this->pros,
            'cons' => $this->cons,
            'advice' => $this->advice,
            'is_approved' => $this->is_approved,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
        ];

        if (! $this->is_anonymous && $this->relationLoaded('candidate') && $this->candidate) {
            $data['candidate'] = [
                'id' => $this->candidate->id,
                'name' => $this->candidate->user?->first_name . ' ' . $this->candidate->user?->last_name,
            ];
        }

        if ($this->relationLoaded('employer') && $this->employer) {
            $data['employer'] = [
                'id' => $this->employer->id,
                'company_name' => $this->employer->company_name,
                'slug' => $this->employer->slug,
            ];
        }

        if ($this->relationLoaded('reply') && $this->reply) {
            $data['reply'] = [
                'id' => $this->reply->id,
                'reply' => $this->reply->reply,
                'created_at' => $this->reply->created_at,
                'updated_at' => $this->reply->updated_at,
            ];
        }

        return $data;
    }
}
