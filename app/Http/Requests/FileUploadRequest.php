<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:15360'],
            'file_type' => ['required', 'string', 'in:resume,avatar,company_logo,company_cover,document,attachment,verification_document'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'in:candidate,employer,application'],
            'entity_id' => ['sometimes', 'nullable', 'string', 'uuid'],
        ];
    }
}
