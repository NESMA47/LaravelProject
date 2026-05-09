<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use App\Models\Employer;
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
            'file' => [
                'required',
                'file',
                'max:15360',
                function ($attribute, $value, $fail) {
                    $fileType = $this->input('file_type');
                    $mime = $value->getMimeType();

                    $imageMimes = [
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                        'image/svg+xml',
                        'image/jpg',
                        'image/avif',
                    ];

                    $documentMimes = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.oasis.opendocument.text',
                        'text/plain',
                    ];

                    if (in_array($fileType, ['avatar', 'company_logo', 'company_cover']) && ! in_array($mime, $imageMimes)) {
                        $fail('The file must be an image (jpeg, png, gif, webp, svg, avif).');
                    }

                    if ($fileType === 'resume' && ! in_array($mime, $documentMimes)) {
                        $fail('The file must be a PDF, Word document, ODT, or plain text.');
                    }
                },
            ],
            'file_type' => [
                'required',
                'string',
                'in:resume,avatar,company_logo,company_cover,document,attachment,verification_document',
                function ($attribute, $value, $fail) {
                    $user = $this->user();

                    $candidateTypes = ['resume', 'avatar'];
                    $employerTypes = ['company_logo', 'company_cover'];

                    if ($user->role === 'candidate' && ! in_array($value, $candidateTypes)) {
                        $fail('Candidates can only upload resume or avatar files.');
                    }

                    if ($user->role === 'employer' && ! in_array($value, $employerTypes)) {
                        $fail('Employers can only upload company logo or cover image files.');
                    }
                },
            ],
            'entity_type' => [
                'sometimes',
                'nullable',
                'string',
                'in:candidate,employer,application',
                function ($attribute, $value, $fail) {
                    $fileType = $this->input('file_type');

                    $requiredEntity = match ($fileType) {
                        'resume' => 'candidate',
                        'company_logo', 'company_cover' => 'employer',
                        default => null,
                    };

                    if ($requiredEntity && $value !== $requiredEntity) {
                        $fail("Files of type {$fileType} must be linked to a {$requiredEntity}.");
                    }

                    if ($fileType === 'avatar' && $value !== null) {
                        $fail('Avatar uploads do not require an entity type.');
                    }
                },
            ],
            'entity_id' => [
                'sometimes',
                'nullable',
                'string',
                'uuid',
                function ($attribute, $value, $fail) {
                    $fileType = $this->input('file_type');
                    $entityType = $this->input('entity_type');
                    $user = $this->user();

                    if ($fileType === 'avatar' && $value !== null) {
                        $fail('Avatar uploads do not require an entity ID.');
                    }

                    if ($entityType === 'candidate') {
                        $candidate = Candidate::find($value);
                        if (! $candidate || $candidate->user_id !== $user->id) {
                            $fail('Unauthorized candidate entity.');
                        }
                        if ($user->role !== 'candidate') {
                            $fail('Only candidates can upload candidate files.');
                        }
                    }

                    if ($entityType === 'employer') {
                        $employer = Employer::find($value);
                        if (! $employer || $employer->user_id !== $user->id) {
                            $fail('Unauthorized employer entity.');
                        }
                        if ($user->role !== 'employer') {
                            $fail('Only employers can upload employer files.');
                        }
                    }
                },
            ],
        ];
    }
}
