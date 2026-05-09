<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_name',
        'slug',
        'logo_url',
        'logo_file_id',
        'cover_image_url',
        'cover_image_file_id',
        'industry',
        'company_size',
        'founded_year',
        'website',
        'description',
        'headquarters',
        'address',
        'city',
        'country',
        'is_verified',
        'verification_document_id',
        'average_rating',
        'total_reviews',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'average_rating' => 'decimal:1',
            'total_reviews' => 'integer',
            'founded_year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CompanyReview::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(EmployerTeamMember::class);
    }

    public function logoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'logo_file_id');
    }

    public function coverImageFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_image_file_id');
    }

    public function verificationDocument(): BelongsTo
    {
        return $this->belongsTo(File::class, 'verification_document_id');
    }
}
