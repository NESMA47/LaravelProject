<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class ExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('id') !== null;
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:150'],
            'company_name' => [$required, 'string', 'max:150'],
            'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'employment_type' => [$required, 'string', 'in:full_time,part_time,contract,freelance,internship'],
            'start_date' => [$required, 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'is_current' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
