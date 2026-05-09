<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_id' => ['required', 'string', 'uuid', 'exists:jobs,id'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume_id' => ['nullable', 'string', 'uuid', 'exists:resumes,id'],
        ];
    }
}
