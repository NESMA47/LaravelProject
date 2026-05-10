<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'employer_id',
        'posted_by_user_id',
        'category_id',
        'title',
        'slug',
        'description',
        'requirements',
        'responsibilities',
        'benefits',
        'type',
        'workplace_type',
        'experience_level',
        'career_level',
        'education_level',
        'salary_min',
        'salary_max',
        'currency',
        'is_salary_visible',
        'location',
        'city',
        'country',
        'vacancies',
        'status',
        'expires_at',
        'views_count',
        'applications_count',
        'is_confirmed',
        'rejection_reason',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_salary_visible' => 'boolean',
            'is_confirmed' => 'boolean',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'vacancies' => 'integer',
            'views_count' => 'integer',
            'applications_count' => 'integer',
        ];
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function jobSkills(): HasMany
    {
        return $this->hasMany(JobSkill::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function skills(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Skill::class, 'job_skills', 'job_id', 'skill_id')
                ->withPivot('id', 'is_required') ;
}
}
