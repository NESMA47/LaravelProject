<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $salaryVisible = $this->is_salary_visible;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'employer' => $this->whenLoaded('employer', fn () => [
                'id' => $this->employer->id,
                'company_name' => $this->employer->company_name,
                'slug' => $this->employer->slug,
                'logo_url' => $this->employer->logo_url,
                'is_verified' => $this->employer->is_verified,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'type' => $this->type,
            'workplace_type' => $this->workplace_type,
            'experience_level' => $this->experience_level,
            'salary_min' => $salaryVisible ? $this->salary_min : null,
            'salary_max' => $salaryVisible ? $this->salary_max : null,
            'currency' => $this->currency,
            'is_salary_visible' => $this->is_salary_visible,
            'location' => $this->location,
            'city' => $this->city,
            'vacancies' => $this->vacancies,
            'skills' => JobSkillResource::collection($this->whenLoaded('jobSkills')),
            'applications_count' => $this->applications_count,
            'created_at' => $this->created_at,
        ];
    }
}
