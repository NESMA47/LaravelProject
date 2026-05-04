<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'headline',
    'bio',
    'location',
    'city',
    'country',
    'experience_years',
    'education_level',
    'linkedin_url',
    'github_url',
    'portfolio_url',
    'website_url',
    'is_open_to_work',
    'preferred_job_type',
    'preferred_locations',
    'expected_salary_min',
    'expected_salary_max',
    'currency',
    'profile_completion_score',
])]
class Candidate extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $attributes = [
        'preferred_locations' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'is_open_to_work' => 'boolean',
            'preferred_locations' => 'array',
            'profile_completion_score' => 'integer',
            'experience_years' => 'integer',
            'expected_salary_min' => 'integer',
            'expected_salary_max' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class);
    }

    public function candidateSkills(): HasMany
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function savedJobs(): HasMany
    {
        return $this->hasMany(SavedJob::class);
    }

    public function companyReviews(): HasMany
    {
        return $this->hasMany(CompanyReview::class);
    }
}
