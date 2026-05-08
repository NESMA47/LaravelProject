<?php

namespace App\Http\Requests\Employer;

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
            'company_name' => ['sometimes', 'string', 'max:150'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_size' => ['sometimes', 'nullable', 'string', 'max:20'],
            'founded_year' => ['sometimes', 'nullable', 'integer', 'min:1800', 'max:' . date('Y')],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'headquarters' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }
}
