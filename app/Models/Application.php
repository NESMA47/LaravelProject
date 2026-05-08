<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    //
    use SoftDeletes;
   protected $fillable = [
        'candidate_id', 'job_id',
        'resume_path', 'contact_email', 'contact_phone',
        'status', 'notes', 'contact_unlocked',
    ];

    protected $casts = [
        'contact_unlocked' => 'boolean',
    ];
    // Relationships
    public function candidate()
    {    return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }
    public function job()
    {
        return $this->belongsTo(JobListing::class, 'job_id');
    }
    public function payment()   
    {
        return $this->hasOne(Payment::class, 'application_id');
    }
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }
}
