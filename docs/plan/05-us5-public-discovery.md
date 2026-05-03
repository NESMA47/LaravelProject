# User Story 5 — Public Job Discovery & Search

> **Day:** 3 (Morning)  
> **Priority:** 🟡 Medium — Connects job supply (employers) with job seekers (candidates)  
> **Prerequisite:** US2 (Categories, Skills), US4 (Jobs exist in DB)  
> **Note:** All endpoints are **public** (no auth required) except saved jobs.

---

## Goal

Enable anyone (authenticated or not) to browse, search, filter, and view job listings and company profiles. This is the public-facing surface of the platform.

---

## Tables Needed

**No new tables.** This US only reads from existing tables:
- `jobs` (with `job_skills` join)
- `employers`
- `categories`
- `skills`
- `company_reviews`

---

## Backend Endpoints

### Public Endpoints (no auth)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 5.1 | `GET` | `/api/v1/jobs` | List active jobs with pagination, filtering, sorting |
| 5.2 | `GET` | `/api/v1/jobs/:slug` | Job detail (single job by slug) |
| 5.3 | `GET` | `/api/v1/employers` | List verified employers (paginated) |
| 5.4 | `GET` | `/api/v1/employers/:slug` | Employer public profile + active jobs[] + approved reviews[] |
| 5.5 | `GET` | `/api/v1/employers/:slug/reviews` | Paginated approved reviews for a company |

### Candidate Endpoints (auth required)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 5.6 | `GET` | `/api/v1/candidate/saved-jobs` | My saved jobs (with job snapshot) |
| 5.7 | `POST` | `/api/v1/candidate/saved-jobs` | Save a job |
| 5.8 | `DELETE` | `/api/v1/candidate/saved-jobs/:job_id` | Unsave a job |

### Endpoint Details

#### 5.1 List Jobs
```
GET /api/v1/jobs?
  search=vue+frontend
  &category=software-development
  &type=full_time
  &workplace=remote
  &experience=senior
  &location=Cairo
  &salary_min=20000&salary_max=50000
  &skills[]=uuid1&skills[]=uuid2
  &page=1&per_page=20
  &sort=created_at:desc
```

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Full-text search on `title`, `description`, `requirements` (MySQL MATCH AGAINST) |
| `category` | string | Category `slug` (exact match) |
| `type` | string | `full_time`, `part_time`, etc. |
| `workplace` | string | `remote`, `on_site`, `hybrid` |
| `experience` | string | `junior`, `mid`, `senior`, `lead`, `executive` |
| `location` | string | City name (LIKE match on `city` or `location`) |
| `salary_min` | int | Minimum salary range |
| `salary_max` | int | Maximum salary range |
| `skills[]` | array | UUIDs of required skills (jobs that have ANY of these skills) |
| `sort` | string | `created_at:desc`, `salary_max:desc`, `applications_count:asc` |
| `page` | int | Pagination page |
| `per_page` | int | Items per page (max 50) |

**Default scope:** Only jobs with `status = 'active'` AND `deleted_at IS NULL` AND (`expires_at IS NULL OR expires_at > NOW()`).

```json
// Response
{
  "success": true,
  "data": {
    "data": [
      {
        "id": "uuid",
        "title": "Senior Frontend Developer",
        "slug": "senior-frontend-developer-vodafone-egypt-2026-05",
        "employer": {
          "id": "uuid",
          "company_name": "Vodafone Egypt",
          "slug": "vodafone-egypt",
          "logo_url": "...",
          "is_verified": true
        },
        "category": { "id": "uuid", "name": "Software Development", "slug": "software-development" },
        "type": "full_time",
        "workplace_type": "hybrid",
        "experience_level": "senior",
        "salary_min": 25000,
        "salary_max": 40000,
        "currency": "EGP",
        "is_salary_visible": true,
        "location": "Smart Village, Giza",
        "city": "Giza",
        "vacancies": 2,
        "skills": [
          { "skill_id": "uuid", "name": "Vue.js", "is_required": true }
        ],
        "applications_count": 5,
        "created_at": "2026-05-03T10:00:00Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 98
    }
  }
}
```

#### 5.2 Job Detail
```json
// GET /api/v1/jobs/:slug
{
  "success": true,
  "data": {
    "id": "uuid",
    "title": "Senior Frontend Developer",
    "slug": "...",
    "description": "...",
    "requirements": "...",
    "responsibilities": "...",
    "benefits": "...",
    "type": "full_time",
    "workplace_type": "hybrid",
    "experience_level": "senior",
    "career_level": "Senior",
    "education_level": "bachelor",
    "salary_min": 25000,
    "salary_max": 40000,
    "currency": "EGP",
    "is_salary_visible": true,
    "location": "Smart Village, Giza",
    "city": "Giza",
    "country": "EG",
    "vacancies": 2,
    "status": "active",
    "expires_at": "2026-06-03T00:00:00Z",
    "views_count": 512,
    "applications_count": 5,
    "created_at": "2026-05-03T10:00:00Z",
    "employer": {
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
      "total_reviews": 128
    },
    "category": { "id": "uuid", "name": "Software Development", "slug": "software-development", "icon": "Code" },
    "skills": [
      { "skill_id": "uuid", "name": "Vue.js", "is_required": true, "min_proficiency": "expert" },
      { "skill_id": "uuid", "name": "React", "is_required": true, "min_proficiency": "advanced" },
      { "skill_id": "uuid", "name": "TypeScript", "is_required": false }
    ]
  }
}
```

**Business Rules:**
- Increment `views_count` on each view. For now, simple `++`. (Future: `job_views` table for dedup).
- If `is_salary_visible === false`, hide `salary_min` and `salary_max` from public response (return null).
- If job is not `active` (e.g., `closed`, `draft`, `pending_review`, `expired`), return 404 for public endpoint. Only the owning employer and admin can see non-active jobs.

#### 5.4 Employer Public Profile
```json
// GET /api/v1/employers/:slug
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
    "website": "...",
    "description": "...",
    "headquarters": "Smart Village, Giza, Egypt",
    "city": "Giza",
    "country": "EG",
    "is_verified": true,
    "average_rating": 4.1,
    "total_reviews": 128,
    "active_jobs": [
      // 3-6 most recent active jobs (summary only)
      { "id": "uuid", "title": "...", "slug": "...", "type": "full_time", "location": "..." }
    ],
    "recent_reviews": [
      // 3 most recent approved reviews (summary)
      { "id": "uuid", "rating_overall": 5, "title": "...", "pros": "...", "cons": "...", "is_anonymous": false }
    ]
  }
}
```

#### 5.6 Saved Jobs (Auth Required)
```json
// GET /api/v1/candidate/saved-jobs
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "job": {
        "id": "uuid",
        "title": "Senior Frontend Developer",
        "slug": "...",
        "employer": { "company_name": "Vodafone Egypt", "logo_url": "..." },
        "location": "...",
        "type": "full_time",
        "salary_min": 25000,
        "salary_max": 40000,
        "currency": "EGP",
        "is_salary_visible": true
      },
      "saved_at": "2026-05-03T08:00:00Z",
      "notes": "Apply before Friday"
    }
  ]
}
```

---

## Laravel Implementation Guide

### Controllers
```
app/Http/Controllers/Api/V1/
  ├── JobController.php          (5.1, 5.2)
  ├── EmployerController.php   (5.3, 5.4)
  └── SavedJobController.php   (5.6, 5.7, 5.8) in Candidate/ subfolder
```

### Form Requests
```
app/Http/Requests/
  └── JobSearchRequest.php     // validation for query params
```

### Resources
```
app/Http/Resources/
  ├── JobListResource.php       // lightweight: title, employer, salary, location, skills[]
  ├── JobDetailResource.php     // full: description, requirements, benefits, employer full, skills full
  ├── EmployerPublicResource.php // company profile + active_jobs[] + recent_reviews[]
  └── SavedJobResource.php
```

### Query Builder (Job Listing)

Use Laravel's query builder with conditional filters:

```php
class JobController extends Controller
{
    public function index(JobSearchRequest $request)
    {
        $query = Job::with(['employer', 'category', 'skills.skill'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });

        // Search (MySQL FULLTEXT)
        if ($request->filled('search')) {
            $query->whereFullText(['title', 'description', 'requirements'], $request->search);
        }

        // Category filter
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->firstOrFail();
            $query->where('category_id', $category->id);
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Workplace filter
        if ($request->filled('workplace')) {
            $query->where('workplace_type', $request->workplace);
        }

        // Experience level
        if ($request->filled('experience')) {
            $query->where('experience_level', $request->experience);
        }

        // Location (city LIKE)
        if ($request->filled('location')) {
            $query->where(function ($q) use ($request) {
                $q->where('city', 'like', '%' . $request->location . '%')
                  ->orWhere('location', 'like', '%' . $request->location . '%');
            });
        }

        // Salary range
        if ($request->filled('salary_min')) {
            $query->where('salary_max', '>=', $request->salary_min)
                  ->orWhereNull('salary_max');
        }
        if ($request->filled('salary_max')) {
            $query->where('salary_min', '<=', $request->salary_max)
                  ->orWhereNull('salary_min');
        }

        // Skills filter (jobs having ANY of the specified skills)
        if ($request->filled('skills')) {
            $skillIds = $request->skills;
            $query->whereHas('skills', function ($q) use ($skillIds) {
                $q->whereIn('skill_id', $skillIds);
            });
        }

        // Sorting
        $sort = $request->input('sort', 'created_at:desc');
        [$column, $direction] = explode(':', $sort);
        $allowedSorts = ['created_at', 'salary_max', 'applications_count'];
        $allowedDirs = ['asc', 'desc'];
        if (in_array($column, $allowedSorts) && in_array($direction, $allowedDirs)) {
            $query->orderBy($column, $direction);
        } else {
            $query->latest();
        }

        return JobListResource::collection(
            $query->paginate($request->input('per_page', 20))
        );
    }
}
```

> **MySQL FULLTEXT Note:** MySQL's default FULLTEXT has a minimum word length (ft_min_word_len = 4 by default). Short searches like "php" or "go" may not match. For MVP, this is acceptable. Consider adding a `LIKE '%term%'` fallback for short terms.

---

## Frontend Refactoring

### Files to Modify

| File | Change |
|------|--------|
| `src/api/services/api.js` | **Update jobs API:** `getAll` → `GET /jobs` (with query params), `getActive` removed (default scope handles it), `getById` → `getBySlug`, `getByCategory` removed (use query param). Add `getEmployers`, `getEmployerBySlug`, `saveJob`, `unsaveJob`, `getSavedJobs`. |
| `src/stores/jobs.js` | `fetchActiveJobs()` now calls `/api/v1/jobs` with no params. `fetchAllCategories` already updated in US2. |
| `src/features/jobs/views/JobListView.vue` | Search/filter form now sends query params to Laravel instead of client-side filtering. Pagination uses Laravel's `meta` response. |
| `src/features/jobs/views/JobDetailsView.vue` | Job fetched by `slug` not `id`. URL param changes from `:id` to `:slug` OR keep `:id` in frontend route and resolve via API. **Recommendation:** Keep frontend route as `/jobs/:id` but the API call uses the slug (which is also unique). The `id` in frontend route can be the slug string. |
| `src/features/candidate/views/DashboardView.vue` | Saved jobs count may come from backend now (or keep local count). |
| `src/stores/candidate.store.js` | Add `fetchSavedJobs()`, `saveJob(jobId)`, `unsaveJob(jobId)` actions. |

### Router Consideration

Current router uses numeric IDs:
```javascript
{ path: '/jobs/:id', name: 'job.detail', ... }
{ path: '/candidate/jobs/:id', name: 'candidate.job-details', ... }
```

The param `:id` is used as the job identifier. Since slugs are strings and unique, we can either:
1. **Keep route as-is:** The `:id` param becomes the slug string. Works fine since slug is unique.
2. **Rename param to `:slug`:** More explicit but requires updating all `route.params.id` references to `route.params.slug`.

**Recommendation:** Keep route paths as `/jobs/:id` but pass the slug as the `id` param. The backend `GET /api/v1/jobs/:slug` will accept it. Zero router changes needed.

---

## Testing Checklist

- [ ] `GET /jobs` returns only active, non-deleted, non-expired jobs
- [ ] `GET /jobs?search=vue` returns jobs with "vue" in title/description
- [ ] `GET /jobs?category=software-development` filters by category slug
- [ ] `GET /jobs?type=full_time&workplace=remote` applies both filters
- [ ] `GET /jobs?skills[]=uuid1&skills[]=uuid2` returns jobs with either skill
- [ ] `GET /jobs?sort=salary_max:desc` orders correctly
- [ ] `GET /jobs/invalid-slug` → 404
- [ ] `GET /jobs/slug-of-closed-job` → 404 (public cannot see closed jobs)
- [ ] `GET /employers` returns only verified employers (or all? decide: **all with `is_verified` flag visible**)
- [ ] `GET /employers/vodafone-egypt` returns company + active jobs + reviews
- [ ] `POST /candidate/saved-jobs` with `job_id` → creates saved_job record
- [ ] Duplicate save → 409 "Already saved" (or idempotent 200)
- [ ] `GET /candidate/saved-jobs` returns saved jobs with job data eager-loaded
- [ ] `DELETE /candidate/saved-jobs/:job_id` → removes saved job

---

*Next: Read `06-us6-application-pipeline.md` after US5 complete.*
