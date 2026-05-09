<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JobSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:full_time,part_time,contract,freelance,internship,temporary'],
            'workplace' => ['nullable', 'string', 'in:remote,on_site,hybrid'],
            'experience' => ['nullable', 'string', 'in:junior,mid,senior,lead,executive'],
            'location' => ['nullable', 'string', 'max:255'],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'min:0'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'uuid'],
            'sort' => ['nullable', 'string', 'regex:/^(created_at|salary_max|applications_count):(asc|desc)$/'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
