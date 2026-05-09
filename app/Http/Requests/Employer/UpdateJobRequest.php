<?php

namespace App\Http\Requests\Employer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'category_id' => ['sometimes', 'nullable', 'string', 'uuid', 'exists:categories,id'],
            'description' => ['sometimes', 'string'],
            'requirements' => ['sometimes', 'string'],
            'responsibilities' => ['sometimes', 'nullable', 'string'],
            'benefits' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'string', 'in:full_time,part_time,contract,freelance,internship'],
            'workplace_type' => ['sometimes', 'string', 'in:remote,on_site,hybrid'],
            'experience_level' => ['sometimes', 'string', 'in:junior,mid,senior,lead,executive'],
            'career_level' => ['sometimes', 'nullable', 'string', 'max:50'],
            'education_level' => ['sometimes', 'nullable', 'string', 'in:high_school,bachelor,master,phd,diploma,any'],
            'salary_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_salary_visible' => ['sometimes', 'boolean'],
            'location' => ['sometimes', 'string', 'max:200'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'vacancies' => ['sometimes', 'integer', 'min:1'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'skills' => ['sometimes', 'array'],
            'skills.*.skill_id' => ['required_with:skills', 'string', 'uuid', 'exists:skills,id'],
            'skills.*.is_required' => ['sometimes', 'boolean'],
            'skills.*.min_proficiency' => ['sometimes', 'nullable', 'string', 'in:beginner,intermediate,advanced,expert'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $min = $this->input('salary_min');
            $max = $this->input('salary_max');
            if ($min !== null && $max !== null && $max < $min) {
                $validator->errors()->add('salary_max', 'Max salary must be greater than or equal to min salary.');
            }
        });
    }
}
