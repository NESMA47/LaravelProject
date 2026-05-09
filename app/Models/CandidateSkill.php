<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateSkill extends Model
{
    use HasFactory, HasUuids;
    protected $fillable = [
        'candidate_id',
        'skill_id',
        'proficiency_level',
        'years_experience',
    ];

    protected $table = 'candidate_skills';

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'years_experience' => 'integer',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
