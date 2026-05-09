<?php

namespace App\Http\Requests\Employer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:reviewed,shortlisted,interviewed,offered,hired,rejected'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
