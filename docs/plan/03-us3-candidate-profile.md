# User Story 3 — Candidate Profile Builder

> **Day:** 2 (Morning)  
> **Priority:** 🟡 Medium — Required before applications can be submitted  
> **Prerequisite:** US1 (Authentication), US2 (Core Data — for skills)  
> **Parallel:** Can be developed alongside US4 (Employer Profile & Jobs)

---

## Goal

Enable candidates to build and manage their professional profile: basic info (headline, bio, location, social links), work experience, education, skills with proficiency, and multiple resumes with a default flag.

---

## Tables Needed

### New Migrations (6 tables)

1. **`candidates`**
2. **`candidate_education`**
3. **`candidate_experience`**
4. **`candidate_skills`** (join table)
5. **`resumes`**

> **Note:** `candidates` table should reference `users` with a unique FK. `resumes` references both `candidates` and `files`.

### Migration Details

#### `candidates`
```php
Schema::create('candidates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
    $table->string('headline', 150)->nullable();
    $table->text('bio')->nullable();
    $table->string('location', 150)->nullable();
    $table->string('city', 100)->nullable();
    $table->char('country', 2)->default('EG');
    $table->unsignedSmallInteger('experience_years')->nullable();
    $table->enum('education_level', ['high_school', 'bachelor', 'master', 'phd', 'diploma'])->nullable();
    $table->string('linkedin_url', 500)->nullable();
    $table->string('github_url', 500)->nullable();
    $table->string('portfolio_url', 500)->nullable();
    $table->string('website_url', 500)->nullable();
    $table->boolean('is_open_to_work')->default(true);
    $table->enum('preferred_job_type', ['full_time', 'part_time', 'contract', 'freelance', 'internship'])->nullable();
    $table->json('preferred_locations')->nullable(); // MySQL JSON type
    $table->unsignedInteger('expected_salary_min')->nullable();
    $table->unsignedInteger('expected_salary_max')->nullable();
    $table->char('currency', 3)->default('EGP');
    $table->unsignedTinyInteger('profile_completion_score')->default(0);
    $table->timestamps();

    $table->index(['is_open_to_work', 'city']);
});
```

#### `candidate_education`
```php
Schema::create('candidate_education', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
    $table->string('degree', 150);
    $table->string('institution', 200);
    $table->string('field_of_study', 150);
    $table->unsignedSmallInteger('start_year');
    $table->unsignedSmallInteger('end_year')->nullable();
    $table->string('grade', 50)->nullable();
    $table->boolean('is_current')->default(false);
    $table->text('description')->nullable();
    $table->timestamps();
});
```

#### `candidate_experience`
```php
Schema::create('candidate_experience', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
    $table->string('title', 150);
    $table->string('company_name', 150);
    $table->string('location', 150)->nullable();
    $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'freelance', 'internship']);
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->boolean('is_current')->default(false);
    $table->text('description')->nullable();
    $table->timestamps();
});
```

#### `candidate_skills` (join table)
```php
Schema::create('candidate_skills', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
    $table->foreignUuid('skill_id')->constrained('skills')->cascadeOnDelete();
    $table->enum('proficiency_level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('intermediate');
    $table->unsignedTinyInteger('years_experience')->nullable();
    $table->timestamps();

    $table->unique(['candidate_id', 'skill_id']);
});
```

#### `resumes`
```php
Schema::create('resumes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
    $table->string('title', 150); // e.g. "General CV", "Frontend Specialist"
    $table->foreignUuid('file_id')->constrained('files')->cascadeOnDelete();
    $table->boolean('is_default')->default(false);
    $table->timestamps();

    $table->index(['candidate_id', 'is_default']);
});
```

> **MySQL Note:** Cannot create a partial unique index for "one default per candidate". Enforce in application code (transaction that unsets other defaults before setting new one).

---

## Backend Endpoints

All endpoints require `Bearer` token + `role === candidate`. Return 403 otherwise.

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 3.1 | `GET` | `/api/v1/candidate/profile` | Full candidate profile + education[] + experience[] + skills[] + resumes[] |
| 3.2 | `PUT` | `/api/v1/candidate/profile` | Upsert core profile fields (headline, bio, location, socials, preferences, salary) |
| 3.3 | `POST` | `/api/v1/candidate/education` | Add education entry |
| 3.4 | `PUT` | `/api/v1/candidate/education/:id` | Update education entry (ownership verified) |
| 3.5 | `DELETE` | `/api/v1/candidate/education/:id` | Delete education entry |
| 3.6 | `POST` | `/api/v1/candidate/experience` | Add experience entry |
| 3.7 | `PUT` | `/api/v1/candidate/experience/:id` | Update experience entry |
| 3.8 | `DELETE` | `/api/v1/candidate/experience/:id` | Delete experience entry |
| 3.9 | `POST` | `/api/v1/candidate/skills` | **Batch sync** — replaces entire skill set. Payload: `[{skill_id, proficiency_level, years_experience}]` |
| 3.10 | `GET` | `/api/v1/candidate/resumes` | List all resumes for this candidate |
| 3.11 | `POST` | `/api/v1/candidate/resumes` | Upload new resume (multipart: title + file) |
| 3.12 | `PUT` | `/api/v1/candidate/resumes/:id` | Update resume title |
| 3.13 | `DELETE` | `/api/v1/candidate/resumes/:id` | Delete resume (and its file). Cannot delete last resume if candidate has no others. |
| 3.14 | `PATCH` | `/api/v1/candidate/resumes/:id/default` | Set this resume as default (unsets others) |

### Endpoint Details

#### 3.1 Get Profile
```json
// GET /api/v1/candidate/profile
{
  "success": true,
  "data": {
    "id": "uuid",
    "headline": "Senior Frontend Developer | Vue.js",
    "bio": "Passionate frontend developer...",
    "location": "Cairo, Egypt",
    "city": "Cairo",
    "country": "EG",
    "experience_years": 5,
    "education_level": "bachelor",
    "linkedin_url": "...",
    "github_url": "...",
    "portfolio_url": "...",
    "is_open_to_work": true,
    "preferred_job_type": "full_time",
    "preferred_locations": ["Cairo", "Remote"],
    "expected_salary_min": 25000,
    "expected_salary_max": 40000,
    "currency": "EGP",
    "profile_completion_score": 75,
    "education": [ /* array of CandidateEducationResource */ ],
    "experience": [ /* array of CandidateExperienceResource */ ],
    "skills": [
      { "skill_id": "uuid", "name": "Vue.js", "proficiency_level": "expert", "years_experience": 5 }
    ],
    "resumes": [
      { "id": "uuid", "title": "General CV", "is_default": true, "file": { "url": "...", "size_bytes": 124000 } }
    ]
  }
}
```

**Implementation:** Eager load all relationships in a single query:
```php
$candidate = Candidate::with(['education', 'experience', 'skills.skill', 'resumes.file'])
    ->where('user_id', auth()->id())
    ->firstOrFail();
```

#### 3.2 Update Profile
```json
// PUT /api/v1/candidate/profile
{
  "headline": "Senior Frontend Developer | Vue.js & React",
  "bio": "Updated bio...",
  "location": "Cairo, Egypt",
  "city": "Cairo",
  "experience_years": 5,
  "education_level": "bachelor",
  "linkedin_url": "https://linkedin.com/in/ahmedkhaled",
  "github_url": "https://github.com/ahmedkhaled",
  "portfolio_url": "https://ahmedkhaled.dev",
  "is_open_to_work": true,
  "preferred_job_type": "full_time",
  "preferred_locations": ["Cairo", "Remote"],
  "expected_salary_min": 25000,
  "expected_salary_max": 40000
}
```

**Business Rules:**
- If `expected_salary_max` is provided, it must be >= `expected_salary_min`.
- `preferred_locations` is stored as JSON array.
- After update, recalculate `profile_completion_score` (see formula below).

#### 3.9 Batch Sync Skills
```json
// POST /api/v1/candidate/skills
{
  "skills": [
    { "skill_id": "uuid", "proficiency_level": "expert", "years_experience": 5 },
    { "skill_id": "uuid", "proficiency_level": "advanced" }
  ]
}
```

**Implementation:**
1. Delete all existing `candidate_skills` for this candidate.
2. Validate all `skill_id`s exist in `skills` table.
3. Insert all new rows in a single transaction.
4. Recalculate `profile_completion_score`.

> **No auto-creation of skills.** If a `skill_id` doesn't exist, return 422 `errors.skills[0].skill_id: "Skill not found"`.

#### 3.11 Upload Resume
```json
// POST /api/v1/candidate/resumes (multipart/form-data)
{
  "title": "Frontend Specialist CV",
  "file": <PDF file>,
  "is_default": true
}
```

**Business Rules:**
- File must be PDF, DOC, or DOCX.
- Max size: 5MB.
- If `is_default === true`, transactionally unset all other defaults for this candidate first.
- If this is the first resume for the candidate, auto-set `is_default = true` regardless of request.
- Use `FileUploadService` (from US1) to store the file, then create `resumes` record linking to the `files.id`.

#### 3.14 Set Default Resume
```php
// PATCH /api/v1/candidate/resumes/{id}/default
// Transaction:
DB::transaction(function () {
    Resume::where('candidate_id', $candidateId)->update(['is_default' => false]);
    $resume->update(['is_default' => true]);
});
```

---

## Profile Completion Score Formula

Recalculate on every profile update (education/experience/skill change, or core profile update):

```php
$score = 0;
$score += filled($candidate->headline) ? 10 : 0;
$score += filled($candidate->bio) ? 10 : 0;
$score += filled($candidate->location) ? 5 : 0;
$score += filled($candidate->linkedin_url) || filled($candidate->github_url) || filled($candidate->portfolio_url) ? 10 : 0;
$score += $candidate->experience()->count() > 0 ? 20 : 0;
$score += $candidate->education()->count() > 0 ? 15 : 0;
$score += $candidate->skills()->count() > 0 ? 15 : 0;
$score += $candidate->resumes()->count() > 0 ? 10 : 0;
$score += filled($candidate->expected_salary_min) ? 5 : 0;
// Max: 100
```

---

## Laravel Implementation Guide

### Controllers
```
app/Http/Controllers/Api/V1/Candidate/
  ├── ProfileController.php    (3.1, 3.2)
  ├── EducationController.php    (3.3, 3.4, 3.5)
  ├── ExperienceController.php   (3.6, 3.7, 3.8)
  ├── SkillController.php        (3.9)
  └── ResumeController.php       (3.10, 3.11, 3.12, 3.13, 3.14)
```

### Form Requests
```
app/Http/Requests/Candidate/
  ├── UpdateProfileRequest.php
  ├── EducationRequest.php
  ├── ExperienceRequest.php
  ├── SyncSkillsRequest.php
  └── ResumeUploadRequest.php
```

### Resources
```
app/Http/Resources/
  ├── CandidateResource.php
  ├── CandidateEducationResource.php
  ├── CandidateExperienceResource.php
  ├── CandidateSkillResource.php
  └── ResumeResource.php
```

### Models
```php
class Candidate extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'headline', 'bio', 'location', 'city', 'country', 'experience_years', 'education_level', 'linkedin_url', 'github_url', 'portfolio_url', 'website_url', 'is_open_to_work', 'preferred_job_type', 'preferred_locations', 'expected_salary_min', 'expected_salary_max', 'currency', 'profile_completion_score'];

    protected $casts = [
        'preferred_locations' => 'array',
        'is_open_to_work' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function education() { return $this->hasMany(CandidateEducation::class)->orderBy('start_year', 'desc'); }
    public function experience() { return $this->hasMany(CandidateExperience::class)->orderBy('start_date', 'desc'); }
    public function skills() { return $this->hasMany(CandidateSkill::class); }
    public function resumes() { return $this->hasMany(Resume::class)->orderBy('created_at', 'desc'); }
}
```

---

## Frontend Refactoring

### Files to Modify

| File | Change |
|------|--------|
| `src/api/services/api.js` | **Add candidate endpoints:** `getProfile`, `updateProfile`, `createEducation`, `updateEducation`, `deleteEducation`, `createExperience`, `updateExperience`, `deleteExperience`, `syncSkills`, `getResumes`, `uploadResume`, `updateResume`, `deleteResume`, `setDefaultResume`. Update base URL. |
| `src/stores/candidate.store.js` | **Major refactor:** Current store has complex batch-sync logic that does separate API calls for education/experience/skills. New backend handles most of this via `PUT /candidate/profile` and `POST /candidate/skills` (batch sync). Simplify store actions. |
| `src/features/candidate/views/ProfileView.vue` | Update form bindings to match new field names (snake_case from Laravel). Update resume upload flow: candidate can now upload multiple resumes with titles and set default. |
| `src/components/shared/SkillsSelector.vue` | Fetch skills from `/api/v1/skills` instead of json-server. No more client-side skill creation. Selected skills return `skill_id` UUIDs. |

### Store Refactoring Notes

**Current `candidate.store.js` complexity:**
- `updateProfile()` manually diffs education/experience/skills and makes many individual API calls.
- Skills are sometimes strings, sometimes IDs.

**New simplified flow:**
```javascript
// In store:
async updateProfile(profileData) {
  await candidatesApi.updateProfile(profileData)
  // Backend recalculates profile_completion_score
  await this.fetchProfile() // re-fetch everything
}

async syncSkills(skillsArray) {
  // skillsArray = [{ skill_id: 'uuid', proficiency_level: 'expert' }, ...]
  await candidatesApi.syncSkills({ skills: skillsArray })
  await this.fetchProfile()
}

async addEducation(eduData) {
  await candidatesApi.createEducation(eduData)
  await this.fetchProfile() // or append locally for speed
}

async addResume(title, file, isDefault) {
  const formData = new FormData()
  formData.append('title', title)
  formData.append('file', file)
  formData.append('is_default', isDefault)
  await candidatesApi.uploadResume(formData)
  await this.fetchProfile()
}
```

### Data Shape Changes

Old json-server had inconsistent `id` types and nested data:
```json
{ "id": "2", "user_id": 2, "skills": ["node js", "mysql"] } // skills as strings!
```

New Laravel:
```json
{
  "id": "uuid",
  "user_id": "uuid",
  "skills": [
    { "skill_id": "uuid", "name": "Node.js", "proficiency_level": "intermediate" }
  ]
}
```

**Action:** Update `ProfileView.vue` skill selector to emit `{ skill_id, name }` objects instead of raw strings. The batch sync endpoint only accepts `skill_id` + optional `proficiency_level`.

---

## Testing Checklist

- [ ] `GET /candidate/profile` returns 200 with all nested data for authenticated candidate
- [ ] `GET /candidate/profile` as employer → 403
- [ ] `PUT /candidate/profile` updates fields and recalculates profile_completion_score
- [ ] Add education → appears in profile, profile score increases
- [ ] Update education (change institution) → 200, only that record changes
- [ ] Delete education → 204, record gone, score decreases
- [ ] Add experience with `is_current=true` and `end_date=null` → stored correctly
- [ ] Sync skills with 5 items → replaces entire set (old ones deleted)
- [ ] Sync skills with invalid `skill_id` → 422 "Skill not found"
- [ ] Upload resume → file stored, `resumes` record created, linked to candidate
- [ ] Upload second resume with `is_default=true` → first resume `is_default` becomes false
- [ ] Delete last remaining resume → 409 "Cannot delete last resume" (or allow it? Decide: **allow** but application form will show "Upload resume first")
- [ ] Profile completion score = 100 when all fields filled

---

*Next: Read `04-us4-employer-jobs.md` (parallel with this US).*
