<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $salaryVisible = $this->is_salary_visible;

        return [
            'id' => $this->id,
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
            'salary_min' => $salaryVisible ? $this->salary_min : null,
            'salary_max' => $salaryVisible ? $this->salary_max : null,
            'currency' => $this->currency,
            'is_salary_visible' => $this->is_salary_visible,
            'location' => $this->location,
            'city' => $this->city,
            'country' => $this->country,
            'vacancies' => $this->vacancies,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'views_count' => $this->views_count,
            'applications_count' => $this->applications_count,
            'created_at' => $this->created_at,
            'employer' => $this->whenLoaded('employer', fn () => [
                'id' => $this->employer->id,
                'company_name' => $this->employer->company_name,
                'slug' => $this->employer->slug,
                'logo_url' => $this->employer->logo_url,
                'cover_image_url' => $this->employer->cover_image_url,
                'industry' => $this->employer->industry,
                'company_size' => $this->employer->company_size,
                'founded_year' => $this->employer->founded_year,
                'website' => $this->employer->website,
                'description' => $this->employer->description,
                'headquarters' => $this->employer->headquarters,
                'is_verified' => $this->employer->is_verified,
                'average_rating' => $this->employer->average_rating,
                'total_reviews' => $this->employer->total_reviews,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'icon' => $this->category->icon,
            ]),
            'skills' => JobSkillResource::collection($this->whenLoaded('jobSkills')),
        ];
    }
}
