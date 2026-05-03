# User Story 4 — Employer Profile & Job Management

> **Day:** 2 (Afternoon)  
> **Priority:** 🟡 Medium — Required before jobs exist for discovery & applications  
> **Prerequisite:** US1 (Authentication), US2 (Core Data)  
> **Parallel:** Can be developed alongside US3 (Candidate Profile)

---

## Goal

Enable employers to manage their company profile and post/edit/manage jobs with required skills. Jobs are created in `draft` or `pending_review` status and go through an approval workflow.

---

## Tables Needed

### New Migrations (3 tables)

1. **`employers`**
2. **`employer_team_members`**
3. **`jobs`**
4. **`job_skills`** (join table)

> **Note:** `employers` and `jobs` must be created. `job_skills` is the join between `jobs` and `skills`.

### Migration Details

#### `employers`
```php
Schema::create('employers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
    $table->string('company_name');
    $table->string('slug')->unique();
    $table->string('logo_url', 500)->nullable();
    $table->foreignUuid('logo_file_id')->nullable()->constrained('files')->nullOnDelete();
    $table->string('cover_image_url', 500)->nullable();
    $table->foreignUuid('cover_image_file_id')->nullable()->constrained('files')->nullOnDelete();
    $table->string('industry', 100)->nullable();
    $table->string('company_size', 20)->nullable();
    $table->unsignedSmallInteger('founded_year')->nullable();
    $table->string('website', 255)->nullable();
    $table->text('description')->nullable();
    $table->string('headquarters', 255)->nullable();
    $table->string('address', 255)->nullable();
    $table->string('city', 100)->nullable();
    $table->char('country', 2)->default('EG');
    $table->boolean('is_verified')->default(false);
    $table->foreignUuid('verification_document_id')->nullable()->constrained('files')->nullOnDelete();
    $table->decimal('average_rating', 2, 1)->default(0.0);
    $table->unsignedInteger('total_reviews')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['slug', 'is_verified']);
});
```

> **Circular FK note:** `logo_file_id` and `cover_image_file_id` reference `files`. Ensure `files` migration runs before `employers`, or add these columns in a later migration.

#### `employer_team_members`
```php
Schema::create('employer_team_members', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('employer_id')->constrained('employers')->cascadeOnDelete();
    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('role_in_company', 50); // e.g. "Recruiter", "HR Manager"
    $table->boolean('is_primary')->default(false);
    $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->unique(['employer_id', 'user_id']);
});
```

#### `jobs`
```php
Schema::create('jobs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('employer_id')->constrained('employers')->cascadeOnDelete();
    $table->foreignUuid('posted_by_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('description');
    $table->text('requirements');
    $table->text('responsibilities')->nullable();
    $table->text('benefits')->nullable();
    $table->enum('type', ['full_time', 'part_time', 'contract', 'freelance', 'internship']);
    $table->enum('workplace_type', ['remote', 'on_site', 'hybrid']);
    $table->enum('experience_level', ['junior', 'mid', 'senior', 'lead', 'executive']);
    $table->string('career_level', 50)->nullable();
    $table->enum('education_level', ['high_school', 'bachelor', 'master', 'phd', 'diploma', 'any'])->nullable();
    $table->unsignedInteger('salary_min')->nullable();
    $table->unsignedInteger('salary_max')->nullable();
    $table->char('currency', 3)->default('EGP');
    $table->boolean('is_salary_visible')->default(true);
    $table->string('location');
    $table->string('city', 100)->nullable();
    $table->char('country', 2)->default('EG');
    $table->unsignedSmallInteger('vacancies')->default(1);
    $table->enum('status', ['draft', 'pending_review', 'active', 'paused', 'closed', 'rejected', 'expired'])->default('draft');
    $table->timestamp('expires_at')->nullable();
    $table->unsignedInteger('views_count')->default(0);
    $table->unsignedInteger('applications_count')->default(0);
    $table->boolean('is_featured')->default(false);
    $table->timestamp('featured_until')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['status', 'is_featured', 'created_at']);
    $table->index(['employer_id', 'status']);
    $table->index(['category_id', 'status']);
    $table->index(['city', 'country', 'status']);
    $table->fullText(['title', 'description', 'requirements']); // MySQL FULLTEXT
});
```

#### `job_skills` (join table)
```php
Schema::create('job_skills', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
    $table->foreignUuid('skill_id')->constrained('skills')->cascadeOnDelete();
    $table->boolean('is_required')->default(true);
    $table->enum('min_proficiency', ['beginner', 'intermediate', 'advanced', 'expert'])->nullable();
    $table->timestamps();

    $table->unique(['job_id', 'skill_id']);
});
```

---

## Backend Endpoints

All endpoints require `Bearer` token + `role === employer` (or `employer_team_members` match).

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 4.1 | `GET` | `/api/v1/employer/profile` | Company profile + team_members[] |
| 4.2 | `PUT` | `/api/v1/employer/profile` | Update company details, logo, cover image |
| 4.3 | `GET` | `/api/v1/employer/jobs` | All jobs posted by this employer (paginated) |
| 4.4 | `POST` | `/api/v1/employer/jobs` | Create new job (status defaults to `pending_review`) |
| 4.5 | `GET` | `/api/v1/employer/jobs/:id` | Job detail with applicant_count + skills[] |
| 4.6 | `PUT` | `/api/v1/employer/jobs/:id` | Edit job (allowed if status is draft or pending_review; for active, only certain fields editable) |
| 4.7 | `PATCH` | `/api/v1/employer/jobs/:id/status` | Toggle status: `active` ↔ `closed` or `paused`. Cannot re-activate if expired or rejected. |
| 4.8 | `DELETE` | `/api/v1/employer/jobs/:id` | Soft delete job (only if no pending applications, or admin-only hard delete) |

### Endpoint Details

#### 4.1 Get Employer Profile
```json
// GET /api/v1/employer/profile
{
  "success": true,
  "data": {
    "id": "uuid",
    "company_name": "Vodafone Egypt",
    "slug": "vodafone-egypt",
    "logo_url": "...",
    "cover_image_url": "...",
    "industry": "Telecommunications",
    "company_size": "10001+",
    "founded_year": 1998,
    "website": "https://vodafone.com.eg",
    "description": "...",
    "headquarters": "Smart Village, Giza, Egypt",
    "is_verified": true,
    "average_rating": 4.1,
    "total_reviews": 128,
    "team_members": [
      {
        "id": "uuid",
        "user": { "id": "uuid", "first_name": "Mostafa", "last_name": "Ali", "email": "hr@vodafone-egypt.com", "avatar_url": "..." },
        "role_in_company": "HR Manager",
        "is_primary": true
      }
    ]
  }
}
```

#### 4.4 Create Job
```json
// POST /api/v1/employer/jobs
{
  "title": "Senior Frontend Developer",
  "category_id": "uuid",
  "description": "Join our team to build customer-facing portals...",
  "requirements": "- 5+ years experience\n- Vue.js expertise",
  "responsibilities": "Lead frontend architecture decisions...",
  "benefits": "Health insurance, remote days...",
  "type": "full_time",
  "workplace_type": "hybrid",
  "experience_level": "senior",
  "career_level": "Senior",
  "education_level": "bachelor",
  "salary_min": 25000,
  "salary_max": 40000,
  "is_salary_visible": true,
  "location": "Smart Village, Giza, Egypt",
  "city": "Giza",
  "vacancies": 2,
  "skills": [
    { "skill_id": "uuid", "is_required": true, "min_proficiency": "expert" },
    { "skill_id": "uuid", "is_required": false }
  ]
}

// Response 201
{
  "success": true,
  "data": {
    "id": "uuid",
    "title": "Senior Frontend Developer",
    "slug": "senior-frontend-developer-vodafone-egypt-2026-05",
    "status": "pending_review",
    "employer_id": "uuid",
    "skills": [...]
  }
}
```

**Business Rules:**
- `slug` auto-generated from `title + company_name + year-month`. If collision, append `-2`, `-3`.
- `status` defaults to `pending_review` (for admin approval). Employer can also set `draft` explicitly.
- `posted_by_user_id` = authenticated user's ID (for audit).
- `applications_count` initialized to 0.
- Validate `salary_max >= salary_min` if both provided.
- All `skill_id`s must exist in `skills` table.

#### 4.6 Edit Job
```json
// PUT /api/v1/employer/jobs/{id}
// Same payload as create, minus auto-generated fields
```

**Business Rules:**
- Can fully edit if status is `draft` or `pending_review`.
- If status is `active`, only editable fields: `description`, `requirements`, `benefits`, `responsibilities`, `salary_min`, `salary_max`, `is_salary_visible`, `vacancies`, `expires_at`.
- Changing skills on an active job is allowed but triggers re-indexing.
- Cannot edit if status is `closed`, `rejected`, or `expired` → 409.

#### 4.7 Toggle Status
```json
// PATCH /api/v1/employer/jobs/{id}/status
{
  "status": "closed"
}
```

**Allowed transitions:**
- `active` → `closed` (employer closes hiring)
- `active` → `paused` (employer pauses temporarily)
- `paused` → `active` (employer resumes)
- `closed` → `active` (employer re-opens)
- `draft` → `pending_review` (employer submits for approval)

**Forbidden:**
- Any → `rejected` (only admin can reject)
- `expired` → anything (employer must create new job or admin re-activates)

---

## Laravel Implementation Guide

### Controllers
```
app/Http/Controllers/Api/V1/Employer/
  ├── ProfileController.php   (4.1, 4.2)
  └── JobController.php       (4.3, 4.4, 4.5, 4.6, 4.7, 4.8)
```

### Form Requests
```
app/Http/Requests/Employer/
  ├── UpdateProfileRequest.php
  ├── CreateJobRequest.php
  ├── UpdateJobRequest.php
  └── UpdateJobStatusRequest.php
```

### Services
```
app/Services/
  ├── JobService.php          (slug generation, skill sync, status transitions)
  └── EmployerProfileService.php
```

### Policies
```php
class JobPolicy
{
    public function view(User $user, Job $job) {
        return $this->belongsToEmployer($user, $job);
    }

    public function update(User $user, Job $job) {
        return $this->belongsToEmployer($user, $job) && in_array($job->status, ['draft', 'pending_review', 'active']);
    }

    public function delete(User $user, Job $job) {
        return $this->belongsToEmployer($user, $job);
    }

    private function belongsToEmployer(User $user, Job $job) {
        if ($user->role !== 'employer') return false;
        $employer = $user->employer;
        if (!$employer) return false;
        // Primary owner or team member
        if ($job->employer_id === $employer->id) return true;
        $isTeamMember = EmployerTeamMember::where('employer_id', $job->employer_id)
            ->where('user_id', $user->id)->exists();
        return $isTeamMember;
    }
}
```

### Models
```php
class Job extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [/* all columns except id, slug, views_count, applications_count */];
    protected $casts = ['expires_at' => 'datetime', 'is_salary_visible' => 'boolean', 'is_featured' => 'boolean'];

    public function employer() { return $this->belongsTo(Employer::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function skills() { return $this->hasMany(JobSkill::class); }
    public function applications() { return $this->hasMany(Application::class); }
}
```

### Slug Generation Service
```php
class JobService
{
    public static function generateSlug(Job $job): string
    {
        $base = Str::slug($job->title . ' ' . $job->employer->company_name . ' ' . now()->format('Y-m'));
        $slug = $base;
        $counter = 2;
        while (Job::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }
}
```

---

## Frontend Refactoring

### Files to Modify

| File | Change |
|------|--------|
| `src/api/services/api.js` | **Add employer endpoints:** `getProfile`, `updateProfile`, `getJobs`, `createJob`, `getJob`, `updateJob`, `updateJobStatus`, `deleteJob`. Update base URL. |
| `src/stores/employer.store.js` | **Major refactor:** `postJob()` now calls `POST /employer/jobs` with new payload shape (skills as `{skill_id, is_required, min_proficiency}` array). `updateJob()` uses `PUT`. `toggleJobStatus()` uses `PATCH /status`. |
| `src/features/employer/views/PostJobView.vue` | Update form payload. Skills selector now passes `skill_id` UUIDs (not strings). Remove client-side `status: 'pending'` injection — backend handles it. |
| `src/features/employer/views/EditJobView.vue` | Same changes as PostJobView. Pre-populate skills from `job.skills` array. |
| `src/features/employer/views/ManageJobsView.vue` | Job status now comes from backend enum (`draft`, `pending_review`, `active`, etc.). Update status badges and toggle logic. |

### Store Changes

**Current employer store `postJob()`:**
```javascript
// Old (json-server)
const jobData = {
  ...jobPayload,
  employer_id: String(user.id), // WRONG — was storing user.id!
  status: 'pending',
  created_at: new Date().toISOString(),
}
```

**New:**
```javascript
// New (Laravel)
const jobData = {
  title, category_id, description, requirements, responsibilities,
  benefits, type, workplace_type, experience_level, career_level,
  education_level, salary_min, salary_max, is_salary_visible,
  location, city, vacancies, skills
}
// employer_id is inferred from auth token by backend
// status defaults to 'pending_review' on backend
```

### Data Shape Changes

Old json-server `jobs` response:
```json
{
  "id": "3",
  "employer_id": 3,  // this was sometimes user.id, sometimes employer.id — inconsistent
  "category_id": 2,
  "technologies": ["AI", "Python"], // string array, not normalized
  "status": "active"
}
```

New Laravel:
```json
{
  "id": "uuid",
  "employer_id": "uuid", // ALWAYS the employers.id
  "posted_by_user_id": "uuid",
  "category_id": "uuid",
  "skills": [
    { "skill_id": "uuid", "name": "Vue.js", "is_required": true, "min_proficiency": "expert" }
  ],
  "status": "active",
  "applications_count": 5
}
```

**Action:**
- Remove all frontend logic that manually sets `employer_id` from `user.id`.
- Skills array is now objects, not strings.
- `status` values changed from `active/pending/closed/draft/rejected` to include `pending_review`, `paused`, `expired`.

---

## Testing Checklist

- [ ] Employer registration auto-creates empty employer record
- [ ] `GET /employer/profile` returns company + team members
- [ ] `PUT /employer/profile` updates company details and uploads logo
- [ ] Create job → 201, status = `pending_review`, slug auto-generated
- [ ] Create job with duplicate slug (same title next month) → slug gets `-2` suffix
- [ ] Create job with invalid `skill_id` → 422
- [ ] Create job with `salary_max < salary_min` → 422
- [ ] Edit active job's title → 409 "Cannot edit title of active job"
- [ ] Edit active job's salary → 200 (allowed field)
- [ ] Toggle active → closed → 200
- [ ] Toggle expired → active → 409
- [ ] Employer A cannot see Employer B's jobs → 403
- [ ] Soft-deleted job excluded from employer's job list

---

*Next: Read `05-us5-public-discovery.md` after US3 + US4 complete.*
