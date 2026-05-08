<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $skillId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('skills', 'name')->ignore($skillId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('skills', 'slug')->ignore($skillId)],
            'category_id' => ['sometimes', 'nullable', 'string', 'uuid', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
