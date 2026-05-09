<?php

namespace App\Http\Requests\Employer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RescheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'location_type' => ['nullable', 'string', 'in:video_call,phone,in_person'],
            'location_details' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hasAny = collect($validator->getData())->except([])->filter()->isNotEmpty();
                if (! $hasAny) {
                    $validator->errors()->add('general', 'At least one field must be provided to reschedule.');
                }
            },
        ];
    }
}
