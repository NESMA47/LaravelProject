<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'headline' => ['sometimes', 'nullable', 'string', 'max:150'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'experience_years' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'education_level' => ['sometimes', 'nullable', 'string', 'in:high_school,bachelor,master,phd,diploma'],
            'linkedin_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'github_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'portfolio_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_open_to_work' => ['sometimes', 'boolean'],
            'preferred_job_type' => ['sometimes', 'nullable', 'string', 'in:full_time,part_time,contract,freelance,internship'],
            'preferred_locations' => ['sometimes', 'nullable', 'array'],
            'expected_salary_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'expected_salary_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'gt:expected_salary_min'],
        ];
    }

}
