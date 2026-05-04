<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'email',
    'password_hash',
    'first_name',
    'last_name',
    'avatar_url',
    'avatar_file_id',
    'phone',
    'role',
    'is_active',
    'email_verified_at',
    'last_login_at',
])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }

    public function employer(): HasOne
    {
        return $this->hasOne(Employer::class);
    }

    public function passwordResets(): HasMany
    {
        return $this->hasMany(PasswordReset::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function employerTeamMemberships(): HasMany
    {
        return $this->hasMany(EmployerTeamMember::class);
    }

    public function avatarFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'avatar_file_id');
    }
}
