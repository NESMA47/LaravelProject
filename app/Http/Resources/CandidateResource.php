<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'location' => $this->location,
            'city' => $this->city,
            'country' => $this->country,
            'experience_years' => $this->experience_years,
            'education_level' => $this->education_level,
            'linkedin_url' => $this->linkedin_url,
            'github_url' => $this->github_url,
            'portfolio_url' => $this->portfolio_url,
            'website_url' => $this->website_url,
            'is_open_to_work' => $this->is_open_to_work,
            'preferred_job_type' => $this->preferred_job_type,
            'preferred_locations' => $this->preferred_locations,
            'expected_salary_min' => $this->expected_salary_min,
            'expected_salary_max' => $this->expected_salary_max,
            'currency' => $this->currency,
            'profile_completion_score' => $this->profile_completion_score,
            'education' => CandidateEducationResource::collection($this->whenLoaded('educations')),
            'experience' => CandidateExperienceResource::collection($this->whenLoaded('experiences')),
            'skills' => CandidateSkillResource::collection($this->whenLoaded('candidateSkills')),
            'resumes' => ResumeResource::collection($this->whenLoaded('resumes')),
        ];
    }
}
