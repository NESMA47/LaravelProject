<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'display_order',
        'is_active',
        'jobs_count',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'jobs_count' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }
}
