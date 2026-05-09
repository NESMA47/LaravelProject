<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class SyncSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skills' => ['required', 'array'],
            'skills.*.skill_id' => ['required', 'string', 'uuid', 'exists:skills,id'],
            'skills.*.proficiency_level' => ['sometimes', 'string', 'in:beginner,intermediate,advanced,expert'],
            'skills.*.years_experience' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
