# User Story 7 — Saved Jobs & Company Reviews

> **Day:** 3 (Afternoon) — Can run in parallel with US6  
> **Priority:** 🟢 Low — Nice-to-have features that improve candidate engagement  
> **Prerequisite:** US3 (Candidate), US5 (Public Discovery)

---

## Goal

Implement two engagement features:
1. **Saved Jobs:** Candidates can bookmark jobs with personal notes.
2. **Company Reviews:** Candidates who have worked at a company can leave anonymous or named reviews with multi-dimensional ratings.

---

## Tables Needed

### New Migrations (2 tables)

1. **`saved_jobs`**
2. **`company_reviews`**

> **Note:** Both tables were already referenced in previous USs but are created here.

### Migration Details

#### `saved_jobs`
```php
Schema::create('saved_jobs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
    $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
    $table->text('notes')->nullable();
    $table->timestamp('saved_at')->useCurrent();

    $table->unique(['candidate_id', 'job_id']);
});
```

#### `company_reviews`
```php
Schema::create('company_reviews', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('employer_id')->constrained('employers')->cascadeOnDelete();
    $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
    $table->string('job_title_at_time', 150)->nullable();
    $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'freelance', 'internship'])->nullable();
    $table->boolean('is_current_employee')->default(false);
    $table->boolean('is_anonymous')->default(false);
    $table->unsignedTinyInteger('rating_overall');
    $table->unsignedTinyInteger('rating_work_life_balance')->nullable();
    $table->unsignedTinyInteger('rating_salary')->nullable();
    $table->unsignedTinyInteger('rating_culture')->nullable();
    $table->unsignedTinyInteger('rating_management')->nullable();
    $table->unsignedTinyInteger('rating_career_growth')->nullable();
    $table->string('title', 200);
    $table->text('pros')->nullable();
    $table->text('cons')->nullable();
    $table->text('advice')->nullable();
    $table->boolean('is_approved')->default(false);
    $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->timestamps();

    $table->unique(['employer_id', 'candidate_id']); // one review per candidate per employer
    $table->index(['employer_id', 'is_approved', 'created_at']);
});
```

---

## Backend Endpoints

### Candidate Endpoints (Bearer + role=candidate)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 7.1 | `GET` | `/api/v1/candidate/saved-jobs` | List saved jobs (already in US5) |
| 7.2 | `POST` | `/api/v1/candidate/saved-jobs` | Save a job (already in US5) |
| 7.3 | `DELETE` | `/api/v1/candidate/saved-jobs/:job_id` | Unsave a job (already in US5) |
| 7.4 | `GET` | `/api/v1/candidate/reviews` | My submitted reviews |
| 7.5 | `POST` | `/api/v1/candidate/reviews` | Submit a company review |
| 7.6 | `PUT` | `/api/v1/candidate/reviews/:id` | Edit my review (only if not yet approved) |
| 7.7 | `DELETE` | `/api/v1/candidate/reviews/:id` | Delete my review (only if not yet approved) |

### Employer Endpoints (Bearer + role=employer)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 7.8 | `GET` | `/api/v1/employer/reviews` | Reviews about my company (with moderation status) |
| 7.9 | `POST` | `/api/v1/employer/reviews/:id/reply` | Official reply to a review |

### Public Endpoints (no auth)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 7.10 | `GET` | `/api/v1/employers/:slug/reviews` | Approved reviews for a company (already in US5) |

### Admin Endpoints (Bearer + role=admin)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 7.11 | `GET` | `/api/v1/admin/reviews` | Pending review moderation queue |
| 7.12 | `PATCH` | `/api/v1/admin/reviews/:id/approve` | Approve a review |
| 7.13 | `PATCH` | `/api/v1/admin/reviews/:id/reject` | Reject a review |

### Endpoint Details

#### 7.5 Submit Review
```json
// POST /api/v1/candidate/reviews
{
  "employer_id": "uuid",
  "job_title_at_time": "Senior Frontend Developer",
  "employment_type": "full_time",
  "is_current_employee": false,
  "is_anonymous": true,
  "rating_overall": 5,
  "rating_work_life_balance": 5,
  "rating_salary": 4,
  "rating_culture": 5,
  "rating_management": 5,
  "rating_career_growth": 4,
  "title": "Best place I've worked at in Egypt",
  "pros": "Fully remote, excellent engineering culture...",
  "cons": "Salary is good but can be better compared to international companies.",
  "advice": "Increase salary bands for senior engineers."
}
```

**Business Rules:**
- One review per candidate per employer (unique constraint)
- All ratings 1-5
- `rating_overall` is required; others optional
- Review starts with `is_approved = false` (pending moderation)
- Fire `ReviewSubmitted` event

#### 7.12 Approve Review (Admin)
```php
// PATCH /api/v1/admin/reviews/{id}/approve
// Transaction:
DB::transaction(function () {
    $review->update(['is_approved' => true, 'approved_by' => auth()->id(), 'approved_at' => now()]);

    // Recalculate employer aggregates
    $employer = $review->employer;
    $approvedReviews = $employer->reviews()->where('is_approved', true);
    $employer->update([
        'average_rating' => $approvedReviews->avg('rating_overall'),
        'total_reviews' => $approvedReviews->count(),
    ]);
});
```

---

## Frontend Refactoring

### Files to Modify

| File | Change |
|------|--------|
| `src/api/services/api.js` | **Add review endpoints:** `getMyReviews`, `submitReview`, `updateReview`, `deleteReview`, `getEmployerReviews`, `replyToReview` (employer), `getPendingReviews` (admin), `approveReview`, `rejectReview` (admin). |
| `src/features/candidate/views/DashboardView.vue` | Display saved jobs count from API if not already done. |
| `src/features/jobs/views/JobDetailsView.vue` | Add "Save Job" button (heart icon) that calls save/unsave API. Show saved state. |
| `src/features/employer/views/EmployerDashboardView.vue` | Display average rating and review count if available. |
| `src/features/admin/views/` | Add review moderation page (list pending reviews with approve/reject actions). |

### Data Shape

Old json-server reviews:
```json
{
  "id": "1",
  "candidate_id": 1,
  "employer_id": 3,
  "rating_overall": 5,
  "is_approved": true
}
```

New Laravel:
```json
{
  "id": "uuid",
  "candidate_id": "uuid",
  "employer_id": "uuid",
  "rating_overall": 5,
  "is_approved": false,
  "approved_by": null,
  "approved_at": null
}
```

---

## Testing Checklist

- [ ] Save job → 201, appears in saved jobs list
- [ ] Save same job again → 409 (or idempotent 200)
- [ ] Unsave job → 204, removed from list
- [ ] Submit review with missing `rating_overall` → 422
- [ ] Submit second review for same employer → 409
- [ ] Review appears in employer reviews with `is_approved: false`
- [ ] Admin approves review → `is_approved: true`, employer aggregates recalculated
- [ ] Public employer page shows only approved reviews
- [ ] Candidate can edit unapproved review → 200
- [ ] Candidate cannot edit approved review → 409
- [ ] Employer reply appears on review detail

---

*Next: Read `08-us8-admin-notifications.md` after all core features complete.*
