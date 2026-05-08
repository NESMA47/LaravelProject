<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:skills,name'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:100', 'unique:skills,slug'],
            'category_id' => ['sometimes', 'nullable', 'string', 'uuid', 'exists:categories,id'],
        ];
    }
}
