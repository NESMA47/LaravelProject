<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'job_id',
        'candidate_id',
        'cover_letter',
        'job_snapshot',
        'employer_snapshot',
        'candidate_snapshot',
        'status',
        'current_stage',
        'withdrawn_at',
        'withdrawn_reason',
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
        return $this->hasMany(ApplicationStage::class);
    }

    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }
}
