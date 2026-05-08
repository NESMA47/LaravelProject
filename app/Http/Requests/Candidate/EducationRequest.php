<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class EducationRequest extends FormRequest
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
            'degree' => [$required, 'string', 'max:150'],
            'institution' => [$required, 'string', 'max:200'],
            'field_of_study' => [$required, 'string', 'max:150'],
            'start_year' => [$required, 'integer', 'min:1900', 'max:2100'],
            'end_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'grade' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_current' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
