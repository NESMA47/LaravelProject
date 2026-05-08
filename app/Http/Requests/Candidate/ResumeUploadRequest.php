<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class ResumeUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
