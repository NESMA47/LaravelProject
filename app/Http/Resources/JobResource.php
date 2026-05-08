<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employer_id' => $this->employer_id,
            'posted_by_user_id' => $this->posted_by_user_id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'responsibilities' => $this->responsibilities,
            'benefits' => $this->benefits,
            'type' => $this->type,
            'workplace_type' => $this->workplace_type,
            'experience_level' => $this->experience_level,
            'career_level' => $this->career_level,
            'education_level' => $this->education_level,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'currency' => $this->currency,
            'is_salary_visible' => $this->is_salary_visible,
            'location' => $this->location,
            'city' => $this->city,
            'country' => $this->country,
            'vacancies' => $this->vacancies,
            'status' => $this->status,
            'is_confirmed' => $this->is_confirmed,
            'expires_at' => $this->expires_at,
            'views_count' => $this->views_count,
            'applications_count' => $this->applications_count,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'employer' => $this->whenLoaded('employer', fn () => [
                'id' => $this->employer->id,
                'company_name' => $this->employer->company_name,
                'slug' => $this->employer->slug,
                'logo_url' => $this->employer->logo_url,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'skills' => JobSkillResource::collection($this->whenLoaded('jobSkills')),
        ];
    }
}
