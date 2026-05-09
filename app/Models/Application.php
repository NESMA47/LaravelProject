<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'original_job_id',
        'candidate_id',
        'cover_letter',
        'job_snapshot',
        'employer_snapshot',
        'candidate_snapshot',
        'current_status',
        'current_stage',
        'withdrawn_at',
        'withdrawn_reason',
        'job_removed_at',
        'resume_url',
        'applied_at',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'job_snapshot' => 'array',
            'employer_snapshot' => 'array',
            'candidate_snapshot' => 'array',
            'withdrawn_at' => 'datetime',
            'job_removed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ApplicationStage::class)->orderBy('created_at');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }
}
