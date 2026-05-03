# User Story 6 — Application Pipeline & Interviews

> **Day:** 3 (Afternoon)  
> **Priority:** 🔴 Critical — Core business logic connecting candidates and employers  
> **Prerequisite:** US3 (Candidate Profile), US4 (Jobs), US5 (Public Discovery)

---

## Goal

Implement the full application lifecycle: candidate applies to a job with a cover letter and optional resume selection, employer reviews applications, updates status through a pipeline, and schedules interviews. Triple snapshots ensure data integrity even if original records change.

---

## Tables Needed

### New Migrations (4 tables)

1. **`applications`**
2. **`application_stages`**
3. **`interviews`**

### Migration Details

#### `applications`
```php
Schema::create('applications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
    $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
    $table->text('cover_letter')->nullable();
    $table->json('job_snapshot'); // immutable at time of application
    $table->json('employer_snapshot'); // immutable at time of application
    $table->json('candidate_snapshot'); // immutable at time of application
    $table->enum('status', ['applied', 'reviewed', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected', 'withdrawn'])->default('applied');
    $table->string('current_stage', 50)->nullable();
    $table->timestamp('withdrawn_at')->nullable();
    $table->string('withdrawn_reason', 255)->nullable();
    $table->timestamp('applied_at')->useCurrent();
    $table->timestamps();

    $table->unique(['job_id', 'candidate_id']); // prevent duplicate applications
    $table->index(['candidate_id', 'applied_at']);
    $table->index(['job_id', 'status']);
});
```

#### `application_stages`
```php
Schema::create('application_stages', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
    $table->enum('stage', ['applied', 'reviewed', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected', 'withdrawn']);
    $table->text('notes')->nullable();
    $table->foreignUuid('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['application_id', 'created_at']);
});
```

#### `interviews`
```php
Schema::create('interviews', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
    $table->timestamp('scheduled_at');
    $table->unsignedSmallInteger('duration_minutes')->default(60);
    $table->enum('location_type', ['video_call', 'phone', 'in_person']);
    $table->string('location_details', 255)->nullable(); // Zoom link, address, room number
    $table->text('notes')->nullable();
    $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
    $table->foreignUuid('created_by_user_id')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
});
```

---

## Snapshot Service

Create a dedicated service class for building immutable snapshots:

```php
class ApplicationSnapshotService
{
    public static function createSnapshots(Application $application, Job $job, Candidate $candidate): void
    {
        $application->job_snapshot = self::buildJobSnapshot($job);
        $application->employer_snapshot = self::buildEmployerSnapshot($job->employer);
        $application->candidate_snapshot = self::buildCandidateSnapshot($candidate);
        $application->save();
    }

    private static function buildJobSnapshot(Job $job): array
    {
        return [
            'title' => $job->title,
            'description' => $job->description,
            'requirements' => $job->requirements,
            'benefits' => $job->benefits,
            'type' => $job->type,
            'workplace_type' => $job->workplace_type,
            'experience_level' => $job->experience_level,
            'salary_min' => $job->salary_min,
            'salary_max' => $job->salary_max,
            'currency' => $job->currency,
            'location' => $job->location,
            'skills' => $job->skills->map(fn($js) => [
                'name' => $js->skill->name,
                'is_required' => $js->is_required,
            ])->toArray(),
        ];
    }

    private static function buildEmployerSnapshot(Employer $employer): array
    {
        return [
            'company_name' => $employer->company_name,
            'slug' => $employer->slug,
            'logo_url' => $employer->logo_url,
            'industry' => $employer->industry,
            'website' => $employer->website,
            'headquarters' => $employer->headquarters,
            'is_verified' => $employer->is_verified,
        ];
    }

    private static function buildCandidateSnapshot(Candidate $candidate): array
    {
        $user = $candidate->user;
        $defaultResume = $candidate->resumes()->where('is_default', true)->with('file')->first();

        return [
            'name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'headline' => $candidate->headline,
            'location' => $candidate->location,
            'bio' => $candidate->bio,
            'skills' => $candidate->skills->map(fn($cs) => $cs->skill->name)->toArray(),
            'experience_summary' => $candidate->experience->first()?->title . ' at ' . $candidate->experience->first()?->company_name,
            'education_summary' => $candidate->education->first()?->degree . ', ' . $candidate->education->first()?->institution,
            'linkedin_url' => $candidate->linkedin_url,
            'github_url' => $candidate->github_url,
            'portfolio_url' => $candidate->portfolio_url,
            'expected_salary_min' => $candidate->expected_salary_min,
            'expected_salary_max' => $candidate->expected_salary_max,
            'resume_url' => $defaultResume?->file?->url,
            'resume_title' => $defaultResume?->title,
        ];
    }
}
```

---

## Backend Endpoints

### Candidate Endpoints (Bearer + role=candidate)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 6.1 | `GET` | `/api/v1/candidate/applications` | My applications with job summary (from snapshot) |
| 6.2 | `POST` | `/api/v1/candidate/applications` | Apply to a job |
| 6.3 | `PATCH` | `/api/v1/candidate/applications/:id/withdraw` | Withdraw my application |

### Employer Endpoints (Bearer + role=employer)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 6.4 | `GET` | `/api/v1/employer/applications` | All applications for employer's jobs (paginated, filterable by status) |
| 6.5 | `GET` | `/api/v1/employer/applications/:id` | Full application with candidate_snapshot, stages[], interviews[] |
| 6.6 | `PATCH` | `/api/v1/employer/applications/:id/status` | Update status (reviewed, shortlisted, interviewed, offered, rejected, hired) |
| 6.7 | `POST` | `/api/v1/employer/applications/:id/interviews` | Schedule interview |
| 6.8 | `PUT` | `/api/v1/employer/applications/:id/interviews/:interview_id` | Reschedule/cancel interview |

### Endpoint Details

#### 6.1 Candidate Applications
```json
// GET /api/v1/candidate/applications
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "status": "shortlisted",
      "current_stage": "Invited for technical screen",
      "applied_at": "2026-05-01T10:00:00Z",
      "updated_at": "2026-05-03T14:00:00Z",
      "cover_letter": "I am very excited to apply...",
      "job_snapshot": {
        "title": "Senior Frontend Developer",
        "employer_name": "Vodafone Egypt",
        "location": "Smart Village, Giza",
        "salary_min": 25000,
        "salary_max": 40000,
        "currency": "EGP",
        "type": "full_time"
      },
      "employer_snapshot": {
        "company_name": "Vodafone Egypt",
        "slug": "vodafone-egypt",
        "logo_url": "..."
      }
    }
  ]
}
```

#### 6.2 Apply for Job
```json
// POST /api/v1/candidate/applications
{
  "job_id": "uuid",
  "cover_letter": "I am very excited to apply for this role...",
  "resume_id": "uuid" // optional; if omitted, uses default resume
}

// Response 201
{
  "success": true,
  "data": {
    "id": "uuid",
    "status": "applied",
    "applied_at": "2026-05-03T10:30:00Z",
    "job_snapshot": { ... },
    "employer_snapshot": { ... },
    "candidate_snapshot": { ... }
  }
}
```

**Business Rules:**
- Re-apply to same job → 409 "You have already applied for this job"
- Job must be `active` and not expired
- If `resume_id` provided, it must belong to the candidate
- If `resume_id` omitted, candidate must have a default resume (or at least one resume)
- Increment `jobs.applications_count` (simple `++` for MVP, or atomic `UPDATE`)
- Create `application_stages` row: `stage: 'applied'`, `notes: 'Application received'`
- Fire `ApplicationSubmitted` event
- Transaction: create application → create snapshot → create initial stage → increment counter → fire event

#### 6.3 Withdraw Application
```json
// PATCH /api/v1/candidate/applications/{id}/withdraw
{
  "reason": "Accepted another offer" // optional
}

// Response 200
{
  "success": true,
  "data": {
    "id": "uuid",
    "status": "withdrawn",
    "withdrawn_at": "2026-05-03T15:00:00Z"
  }
}
```

**Business Rules:**
- Only the applicant can withdraw
- Cannot withdraw if status is `hired` or `rejected`
- Set `status = 'withdrawn'`, `withdrawn_at = now()`
- Create stage: `stage: 'withdrawn'`, `notes: reason or 'Candidate withdrew'`
- Decrement `jobs.applications_count`
- Fire `ApplicationWithdrawn` event

#### 6.4 Employer Applications Inbox
```
GET /api/v1/employer/applications?
  status=pending
  &job_id=uuid
  &page=1&per_page=20
```

```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": "uuid",
        "status": "applied",
        "candidate_snapshot": {
          "name": "Ahmed Khaled",
          "email": "ahmed@example.com",
          "headline": "Senior Frontend Developer | Vue.js",
          "location": "Cairo, Egypt",
          "skills": ["Vue.js", "React", "TypeScript"],
          "resume_url": "..."
        },
        "job_snapshot": {
          "title": "Senior Frontend Developer"
        },
        "applied_at": "2026-05-03T10:30:00Z",
        "cover_letter": "...",
        "current_stage": null
      }
    ],
    "meta": { "current_page": 1, "last_page": 3, "total": 42 }
  }
}
```

**Business Rules:**
- Employer only sees applications for THEIR jobs (verify via `job.employer_id`)
- Filterable by `status` and `job_id`
- Sorted by `applied_at desc` (newest first)

#### 6.5 Application Detail (Employer)
```json
// GET /api/v1/employer/applications/{id}
{
  "success": true,
  "data": {
    "id": "uuid",
    "status": "shortlisted",
    "cover_letter": "...",
    "candidate_snapshot": { /* full snapshot */ },
    "job_snapshot": { /* full snapshot */ },
    "employer_snapshot": { /* full snapshot */ },
    "stages": [
      { "stage": "applied", "notes": "Application received", "created_at": "..." },
      { "stage": "reviewed", "notes": "Strong Vue.js background", "created_at": "...", "changed_by": "Mostafa Ali" },
      { "stage": "shortlisted", "notes": "Invited for technical screen", "created_at": "...", "changed_by": "Mostafa Ali" }
    ],
    "interviews": [
      {
        "id": "uuid",
        "scheduled_at": "2026-05-10T14:00:00Z",
        "duration_minutes": 60,
        "location_type": "video_call",
        "location_details": "https://zoom.us/j/123456",
        "notes": "Technical discussion with senior dev team",
        "status": "scheduled",
        "created_by": "Mostafa Ali"
      }
    ],
    "applied_at": "...",
    "updated_at": "..."
  }
}
```

#### 6.6 Update Application Status
```json
// PATCH /api/v1/employer/applications/{id}/status
{
  "status": "shortlisted",
  "notes": "Strong Vue.js background. Moving to technical screen."
}

// Response 200
{
  "success": true,
  "data": {
    "id": "uuid",
    "status": "shortlisted",
    "current_stage": "Strong Vue.js background. Moving to technical screen."
  }
}
```

**Business Rules:**
- Allowed transitions:
  - `applied` → `reviewed`, `shortlisted`, `rejected`
  - `reviewed` → `shortlisted`, `interviewed`, `rejected`
  - `shortlisted` → `interviewed`, `rejected`
  - `interviewed` → `offered`, `rejected`
  - `offered` → `hired`, `rejected`
  - `rejected` → (no change allowed)
  - `hired` → (no change allowed)
  - `withdrawn` → (no change allowed)
- Invalid transition → 409 "Invalid status transition from X to Y"
- Create `application_stages` row with the new stage and notes
- Update `applications.status` and `applications.current_stage` (human-readable note)
- Fire `ApplicationStatusChanged` event

#### 6.7 Schedule Interview
```json
// POST /api/v1/employer/applications/{id}/interviews
{
  "scheduled_at": "2026-05-10T14:00:00Z",
  "duration_minutes": 60,
  "location_type": "video_call",
  "location_details": "https://zoom.us/j/123456",
  "notes": "Technical discussion with senior dev team"
}
```

**Business Rules:**
- Can only schedule if application status is `shortlisted` or `interviewed`
- `scheduled_at` must be in the future
- Auto-update application status to `interviewed` if not already

---

## Laravel Implementation Guide

### Controllers
```
app/Http/Controllers/Api/V1/Candidate/
  └── ApplicationController.php   (6.1, 6.2, 6.3)

app/Http/Controllers/Api/V1/Employer/
  ├── ApplicationController.php   (6.4, 6.5, 6.6)
  └── InterviewController.php     (6.7, 6.8)
```

### Form Requests
```
app/Http/Requests/Candidate/
  ├── ApplyRequest.php
  └── WithdrawRequest.php

app/Http/Requests/Employer/
  ├── UpdateApplicationStatusRequest.php
  └── ScheduleInterviewRequest.php
```

### Services
```
app/Services/
  ├── ApplicationService.php      (create application with snapshots, counters)
  ├── ApplicationStageService.php (status transitions, validation)
  └── InterviewService.php
```

### Events
```
app/Events/
  ├── ApplicationSubmitted.php
  ├── ApplicationStatusChanged.php
  └── ApplicationWithdrawn.php
```

### Policies
```php
class ApplicationPolicy
{
    public function viewAsCandidate(User $user, Application $application) {
        return $user->role === 'candidate' && $application->candidate->user_id === $user->id;
    }

    public function viewAsEmployer(User $user, Application $application) {
        if ($user->role !== 'employer') return false;
        $employer = $user->employer;
        return $application->job->employer_id === $employer->id;
    }

    public function updateStatus(User $user, Application $application) {
        return $this->viewAsEmployer($user, $application);
    }
}
```

---

## Frontend Refactoring

### Files to Modify

| File | Change |
|------|--------|
| `src/api/services/api.js` | **Add application endpoints:** `getApplications`, `applyForJob`, `withdrawApplication` (candidate); `getEmployerApplications`, `getApplicationDetails`, `updateApplicationStatus`, `scheduleInterview`, `updateInterview` (employer). |
| `src/stores/candidate.store.js` | `applyForJob()` now calls `POST /candidate/applications`. Payload simplified: `{ job_id, cover_letter, resume_id }`. Response includes snapshots (no need to fetch job separately). `withdrawApplication()` calls `PATCH /withdraw`. |
| `src/stores/employer.store.js` | `fetchMyApplications()` now calls `GET /employer/applications`. `updateApplicationStatus()` calls `PATCH /status`. `fetchApplicationDetails()` calls `GET /employer/applications/:id` and receives full snapshots (no need for complex multi-API merging). |
| `src/features/jobs/views/ApplyJob.vue` | Add optional resume selection dropdown (candidate's resumes). Send `resume_id` in payload. |
| `src/features/employer/views/EmployerApplicationsView.vue` | Applications now come with `candidate_snapshot` pre-populated. No need to fetch candidate user/profile separately. |
| `src/features/employer/views/ApplicationDetailsView.vue` | Candidate data comes from `candidate_snapshot`. Add interview scheduling form. Display stage history timeline. |
| `src/features/candidate/views/MyApplicationsView.vue` | Job data comes from `job_snapshot`. No need to link back to live job. Withdraw action updates status. |

### Data Shape Changes

Old json-server application:
```json
{
  "id": "1",
  "job_id": 1,
  "candidate_id": 1,
  "cover_letter": "...",
  "status": "shortlisted",
  "applied_at": "..."
}
```

New Laravel:
```json
{
  "id": "uuid",
  "job_id": "uuid",
  "candidate_id": "uuid",
  "cover_letter": "...",
  "status": "shortlisted",
  "applied_at": "...",
  "job_snapshot": { "title": "...", "employer_name": "...", "location": "...", "salary_min": 25000 },
  "employer_snapshot": { "company_name": "...", "logo_url": "..." },
  "candidate_snapshot": { "name": "...", "email": "...", "headline": "...", "skills": [], "resume_url": "..." },
  "stages": [...]
}
```

**Action:**
- Remove all frontend code that fetches candidate user/profile separately when viewing applications. Data is now self-contained in the application response.
- `candidate_id` in old data was sometimes `user.id`, sometimes `candidate.id` — now always `candidates.id`.

---

## Testing Checklist

- [ ] Apply to active job → 201, snapshots created, counter incremented
- [ ] Re-apply to same job → 409
- [ ] Apply to closed job → 404
- [ ] Apply without any resume → 422 "Upload a resume first"
- [ ] Apply with specific `resume_id` → snapshot contains that resume URL
- [ ] Withdraw application → status=withdrawn, counter decremented, stage created
- [ ] Employer sees only applications for their jobs
- [ ] Employer updates status `applied` → `shortlisted` → stage created
- [ ] Invalid transition `rejected` → `hired` → 409
- [ ] Schedule interview → interview created, status auto-updated to `interviewed`
- [ ] Employer application detail includes full candidate snapshot (even if candidate later deletes resume)
- [ ] Candidate application list shows job snapshot (even if employer later deletes/closes job)
- [ ] Soft-deleted job → applications still visible via snapshots (job snapshot preserved)

---

*Next: Read `07-us7-saved-jobs-reviews.md` (can run in parallel with this US, but after US5).*
