<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employer_id',
    'candidate_id',
    'job_title_at_time',
    'employment_type',
    'is_current_employee',
    'is_anonymous',
    'rating_overall',
    'rating_work_life_balance',
    'rating_salary',
    'rating_culture',
    'rating_management',
    'rating_career_growth',
    'title',
    'pros',
    'cons',
    'advice',
    'is_approved',
    'approved_by',
    'approved_at',
])]
class CompanyReview extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'is_current_employee' => 'boolean',
            'is_anonymous' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'rating_overall' => 'integer',
            'rating_work_life_balance' => 'integer',
            'rating_salary' => 'integer',
            'rating_culture' => 'integer',
            'rating_management' => 'integer',
            'rating_career_growth' => 'integer',
        ];
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
